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
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Command\Command;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Driver\CloudinaryDriver;

/**
 * Class AbstractCloudinaryCommand
 */
abstract class AbstractCloudinaryCommand extends Command
{

    const SUCCESS = 'success';
    const WARNING = 'warning';
    const ERROR = 'error';

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * @var bool
     */
    protected $isSilent = false;

    /**
     * @var string
     */
    protected $tableName = 'sys_file';

    /**
     * @param ResourceStorage $storage
     * @param InputInterface $input
     *
     * @return array
     */
    protected function getFiles(ResourceStorage $storage, InputInterface $input): array
    {
        $query = $this->getQueryBuilder();
        $query
            ->select('*')
            ->from($this->tableName)
            ->where(
                $query->expr()->eq('storage', $storage->getUid()),
                $query->expr()->eq('missing', 0)
            );

        // Possible custom exclude
        if ($input->getOption('exclude')) {
            $expressions = GeneralUtility::trimExplode(',', $input->getOption('exclude'));
            foreach ($expressions as $expression) {
                $query->andWhere(
                    $query->expr()->notLike(
                        'identifier',
                        $query->expr()->literal($expression)
                    )
                );
            }
        }

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
     * @param ResourceStorage $storage
     *
     * @return bool
     */
    protected function checkDriverType(ResourceStorage $storage): bool
    {
        return $storage->getDriverType() === CloudinaryDriver::DRIVER_TYPE;
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
     * @param string $message
     * @param array $arguments
     */
    protected function success(string $message = '', array $arguments = [])
    {
        $this->log($message, $arguments, self::SUCCESS);
    }

    /**
     * @param string $message
     * @param array $arguments
     */
    protected function warning(string $message = '', array $arguments = [])
    {
        $this->log($message, $arguments, self::WARNING);
    }

    /**
     * @param string $message
     * @param array $arguments
     */
    protected function error(string $message = '', array $arguments = [])
    {
        $this->log($message, $arguments, self::ERROR);
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
