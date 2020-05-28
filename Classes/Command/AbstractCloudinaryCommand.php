<?php

namespace Visol\Cloudinary\Command;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Doctrine\DBAL\Driver\Connection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Command\Command;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class AbstractCloudinaryCommand
 */
abstract class AbstractCloudinaryCommand extends Command
{

    const WARNING = 'warning';
    const SUCCESS = 'success';

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * @var bool
     */
    protected $isSilent = false;

    /**
     * @var ResourceStorage
     */
    protected $sourceStorage;

    /**
     * @var ResourceStorage
     */
    protected $targetStorage;

    /**
     * @var string
     */
    protected $tableName = 'sys_file';

    /**
     * @var array
     */
    protected $missingFiles = [];

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
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
     * @param string $type
     * @param array $files
     */
    protected function writeLog(string $type, array $files)
    {
        $logFileName = sprintf(
            '/tmp/%s-files-%s-%s-log',
            $type,
            getmypid(),
            uniqid()
        );

        // Write log file
        file_put_contents($logFileName, var_export($files, true));

        // Display the message
        $this->log(
            'Pay attention, I have found %s %s files. A log file has been written at %s',
            [
                $type,
                count($files),
                $logFileName,
            ],
            self::WARNING
        );
    }

    /**
     * @param string $message
     * @param array $arguments
     * @param string $severity can be 'warning', 'error', 'success'
     */
    protected function log(string $message = '', array $arguments = [], $severity = '')
    {
        if (!$this->isSilent) {
            $formattedMessage = vsprintf($message, $arguments);
            if ($severity) {
                $this->io->$severity($formattedMessage);
            } else {
                $this->io->writeln($formattedMessage);
            }
        }
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
     * @return object|Connection
     */
    protected function getConnection(): Connection
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        return $connectionPool->getConnectionForTable($this->tableName);
    }

}
