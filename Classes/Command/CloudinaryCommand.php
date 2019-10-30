<?php

namespace Sinso\Cloudinary\Command;

/*
 * This file is part of the Sinso/Cloudinary project under GPLv2 or later.
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
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class CloudinaryCommand
 */
class CloudinaryCommand extends Command
{

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var array
     */
    protected $arguments = [];

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
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'Source storage identifier'
            )->addArgument(
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

        $this->options = $input->getOptions();
        $this->arguments = $input->getArguments();
    }

    /**
     * Move file
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $source = $input->getArgument('source');
        $target = $input->getArgument('target');


        $sourceStorage = ResourceFactory::getInstance()->getStorageObject($source);
        $targetStorage = ResourceFactory::getInstance()->getStorageObject($target);

        $files = $this->getFiles($sourceStorage->getUid());

        $this->log(
            'Copying %s files from storage "%s" (%s) to "%s" (%s)',
            [
                count($files),
                $sourceStorage->getName(),
                $sourceStorage->getUid(),
                $targetStorage->getName(),
                $targetStorage->getUid(),
            ],
            'info'
        );

        $counter = 0;
        foreach ($files as $file) {
            $fileObject = ResourceFactory::getInstance()->getFileObjectByStorageAndIdentifier(
                $sourceStorage->getUid(),
                $file['identifier']
            );

            if ($fileObject->exists()) {
                $this->log('Copying %s', [$fileObject->getIdentifier()]);
                $targetStorage->addFile(
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
     * @param int $storageIdentifier
     * @return array
     */
    protected function getFiles(int $storageIdentifier): array
    {
        $query = $this->getQueryBuilder();
        $query
            ->select('*')
            ->from($this->tableName)
            ->where(
                $query->expr()->eq('storage', $storageIdentifier),
                $query->expr()->eq('missing', 0),
                $query->expr()->eq('type', File::FILETYPE_IMAGE)
            );

        return $query->execute()->fetchAll();
    }

    /**
     * @param string $message
     * @param array $arguments
     * @param string $severity
     */
    protected function log(string $message, array $arguments = [], $severity = '')
    {
        if (!$this->isSilent()) {
            if ($severity) {
                $message = '<' . $severity . '>' . $message . '</' . $severity . '>';
            }
            $this->io->writeln(
                vsprintf($message, $arguments)
            );
        }
    }

    /**
     * @return bool
     */
    protected function isSilent(): bool
    {
        return $this->options['silent'] !== false;
    }
}
