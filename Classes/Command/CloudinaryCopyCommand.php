<?php

namespace Visol\Cloudinary\Command;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Command\Command;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class CloudinaryCopyCommand
 */
class CloudinaryCopyCommand extends Command
{

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * @var ResourceStorage
     */
    protected $sourceStorage;

    /**
     * @var ResourceStorage
     */
    protected $targetStorage;

    /**
     * @var bool
     */
    protected $isSilent = false;

    /**
     * @var string
     */
    protected $tableName = 'sys_file';

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this
            ->setDescription(
                'Copy bunch of images from a local storage to a cloudinary storage'
            )
            ->addOption(
                'silent',
                's',
                InputOption::VALUE_OPTIONAL,
                'Mute output as much as possible',
                false
            )
            ->addOption(
                'yes',
                'y',
                InputOption::VALUE_OPTIONAL,
                'Accept everything by default',
                false
            )
            ->addOption(
                'base-url',
                '',
                InputArgument::OPTIONAL,
                'A base URL where to download missing files',
                ''
            )
            ->addOption(
                'filter',
                '',
                InputArgument::OPTIONAL,
                'A base URL where to download missing files',
                ''
            )
            ->addOption(
                'filter-file-type',
                '',
                InputArgument::OPTIONAL,
                'Add a possible filter for file type as defined by FAL (e.g 1,2,3,4,5)',
                ''
            )
            ->addOption(
                'limit',
                '',
                InputArgument::OPTIONAL,
                'Add a possible offset, limit to restrain the number of files. (eg. 0,100)',
                ''
            )
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'Source storage identifier'
            )
            ->addArgument(
                'target',
                InputArgument::REQUIRED,
                'Target storage identifier'
            )
            ->setHelp(
                'Usage: ./vendor/bin/typo3 cloudinary:copy 1 2'
            );
    }

    /**
     * Initializes the command after the input has been bound and before the input
     * is validated.
     *
     * @see InputInterface::bind()
     * @see InputInterface::validate()
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->isSilent = $input->getOption('silent');

        $this->sourceStorage = ResourceFactory::getInstance()->getStorageObject(
            $input->getArgument('source')
        );
        $this->targetStorage = ResourceFactory::getInstance()->getStorageObject(
            $input->getArgument('target')
        );
    }

    /**
     * Move file
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $files = $this->getFiles($input);

        if (count($files) === 0) {
            $this->log('No files found, no work for me!');
            return 0;
        }

        $this->log(
            'Copying %s files from storage "%s" (%s) to "%s" (%s)',
            [
                count($files),
                $this->sourceStorage->getName(),
                $this->sourceStorage->getUid(),
                $this->targetStorage->getName(),
                $this->targetStorage->getUid(),
            ],
            'info'
        );

        // A chance to the user to confirm the action
        if ($input->getOption('yes') === false) {

            $response = $this->io->confirm('Shall I continue?', true);

            if (!$response) {
                $this->log('Script aborted');
                return 0;
            }
        }

        $counter = 0;
        foreach ($files as $file) {
            $fileObject = ResourceFactory::getInstance()->getFileObjectByStorageAndIdentifier(
                $this->sourceStorage->getUid(),
                $file['identifier']
            );

            if ($fileObject->exists()) {
                $this->log('Copying %s', [$fileObject->getIdentifier()]);
                $this->targetStorage->addFile(
                    $fileObject->getForLocalProcessing(),
                    $fileObject->getParentFolder(),
                    $fileObject->getName(),
                    DuplicationBehavior::REPLACE
                );
                $counter++;
            }
        }
        $this->log(LF);
        $this->log('Number of files copied: %s', [$counter]);

        return 0;
    }

    /**
     * @return object|QueryBuilder
     */
    protected function getQueryBuilder(): QueryBuilder
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        return $connectionPool->getQueryBuilderForTable($this->tableName);
    }

    /**
     * @param InputInterface $input
     *
     * @return array
     */
    protected function getFiles(InputInterface $input): array
    {
        $query = $this->getQueryBuilder();
        $query
            ->select('*')
            ->from($this->tableName)
            ->where(
                $query->expr()->eq('storage', $this->sourceStorage->getUid()),
                $query->expr()->eq('missing', 0)
            );

        // Possible custom filter
        if ($input->getOption('filter')) {
            $query->andWhere(
                $query->expr()->like(
                    'identifier',
                    $query->expr()->literal($input->getOption('filter'))
                )
            );
        }

        // Possible filter by file type
        if ($input->getOption('filter-file-type')) {
            $query->andWhere(
                $query->expr()->eq(
                    'type',
                    $input->getOption('filter-file-type')
                )
            );
        }

        // Set a possible offset, limit
        if ($input->getOption('limit')) {
            [$offsetOrLimit, $limit] = GeneralUtility::trimExplode(
                ',',
                $input->getOption('limit'),
                true
            );

            if ($limit !== null) {

                $query->setFirstResult((int)$offsetOrLimit);
                $query->setMaxResults((int)$limit);
            } else {
                $query->setMaxResults((int)$offsetOrLimit);
            }
        }

        return $query->execute()->fetchAll();
    }

    /**
     * @param string $message
     * @param array $arguments
     * @param string $severity
     */
    protected function log(string $message, array $arguments = [], $severity = '')
    {
        if (!$this->isSilent) {
            if ($severity) {
                $message = '<' . $severity . '>' . $message . '</' . $severity . '>';
            }
            $this->io->writeln(
                vsprintf($message, $arguments)
            );
        }
    }
}
