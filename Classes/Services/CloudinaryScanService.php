<?php

namespace Visol\Cloudinary\Services;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Cloudinary\Search;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Driver\CloudinaryDriver;
use Visol\Cloudinary\Utility\CloudinaryApiUtility;

/**
 * Class CloudinaryScanService
 */
class CloudinaryScanService
{

    private const CREATED = 'created';
    private const UPDATED = 'updated';
    private const DELETED = 'deleted';
    private const TOTAL = 'total';
    private const FOLDER_DELETED = 'folder_deleted';

    /**
     * @var ResourceStorage
     */
    protected $storage;

    /**
     * @var CloudinaryPathService
     */
    protected $cloudinaryPathService;

    /**
     * @var string
     */
    protected $processedFolder = '_processed_';

    /**
     * @var array
     */
    protected $statistics = [
        self::CREATED => 0,
        self::UPDATED => 0,
        self::DELETED => 0,
        self::TOTAL => 0,

        self::FOLDER_DELETED => 0,
    ];

    /**
     * @var SymfonyStyle|null
     */
    protected $io;

    /**
     * CloudinaryScanService constructor.
     *
     * @param ResourceStorage $storage
     * @param SymfonyStyle|null $io
     *
     * @throws \Exception
     */
    public function __construct(ResourceStorage $storage, SymfonyStyle $io = null)
    {
        if ($storage->getDriverType() !== CloudinaryDriver::DRIVER_TYPE) {
            throw new \Exception('Storage is not of type "cloudinary"', 1594714337);
        }
        $this->storage = $storage;
        $this->io = $io;
    }

    /**
     * @return void
     */
    public function empty(): void
    {
        $this->getCloudinaryResourceService()->deleteAll();
        $this->getCloudinaryFolderService()->deleteAll();
    }

    /**
     * @return array
     */
    public function scan(): array
    {
        $this->preScan();

        // Before calling the Search API, make sure we are connected with the right cloudinary account
        $this->initializeApi();

        $cloudinaryFolder = $this->getCloudinaryPathService()->computeCloudinaryFolderPath(DIRECTORY_SEPARATOR);

        // Add a filter if the root directory contains a base path segment
        // + remove _processed_ folder from the search
        if ($cloudinaryFolder) {
            $expressions[] = sprintf('folder=%s/*', $cloudinaryFolder);
            $expressions[] = sprintf('NOT folder=%s/%s/*', $cloudinaryFolder, $this->processedFolder);
        } else {
            $expressions[] = sprintf('NOT folder=%s/*', $this->processedFolder);
        }

        if ($this->io) {
            $this->io->writeln('Mirroring...' . chr(10));
        }

        do {
            $nextCursor = isset($response)
                ? $response['next_cursor']
                : '';

            $this->log(
                '[API][SEARCH] Cloudinary\Search() - fetch resources from folder "%s" %s',
                [
                    $cloudinaryFolder,
                    $nextCursor ? 'and cursor ' . $nextCursor : '',
                ],
                [
                    'getCloudinaryResources()'
                ]
            );

            /** @var Search $search */
            $search = new \Cloudinary\Search();

            $response = $search
                ->expression(implode(' AND ', $expressions))
                ->sort_by('public_id', 'asc')
                ->max_results(500)
                ->next_cursor($nextCursor)
                ->execute();

            if (is_array($response['resources'])) {
                foreach ($response['resources'] as $resource) {

                    $fileIdentifier = $this->getCloudinaryPathService()->computeFileIdentifier($resource);
                    if ($this->io) {
                        $this->io->writeln($fileIdentifier);
                    }

                    // Save mirrored file
                    $result = $this->getCloudinaryResourceService()->save($resource);

                    // Find if the file exists in sys_file already
                    if (!$this->isFileIndexed($fileIdentifier)) {

                        if ($this->io) {
                            $this->io->writeln('Indexing new file: ' . $fileIdentifier);
                            $this->io->writeln('');
                        }

                        // This will trigger a file indexation
                        $this->storage->getFile($fileIdentifier);
                    }

                    // For the stats, we collect the number of files touched
                    $key = key($result);
                    $this->statistics[$key] += $result[$key];

                    // In any case we can add a file to the counter.
                    // Later we can verify the total corresponds to the "created" + "updated" + "deleted" files
                    $this->statistics[self::TOTAL]++;
                }
            }
        } while (!empty($response) && array_key_exists('next_cursor', $response));

        $this->postScan();

        return $this->statistics;
    }

    /**
     * @return void
     */
    protected function preScan(): void
    {
        $this->getCloudinaryResourceService()->markAsMissing();
        $this->getCloudinaryFolderService()->markAsMissing();
    }

    /**
     * @return void
     */
    protected function postScan(): void
    {
        $identifier = ['missing' => 1];
        $this->statistics[self::DELETED] = $this->getCloudinaryResourceService()->deleteAll($identifier);
        $this->statistics[self::FOLDER_DELETED] = $this->getCloudinaryFolderService()->deleteAll($identifier);
    }

    /**
     * @param string $fileIdentifier
     *
     * @return bool
     */
    protected function isFileIndexed(string $fileIdentifier): bool
    {
        $query = $this->getQueryBuilder();
        $query->count('*')
            ->from('sys_file')
            ->where(
                $this->getQueryBuilder()->expr()->eq(
                    'identifier',
                    $query->expr()->literal($fileIdentifier)
                ),
                $this->getQueryBuilder()->expr()->eq(
                    'storage',
                    $this->storage->getUid()
                )
            );

        return (bool)$query->execute()->fetchColumn(0);
    }

    /**
     * @return object|QueryBuilder
     */
    protected function getQueryBuilder(): QueryBuilder
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        return $connectionPool->getQueryBuilderForTable('sys_file');
    }

    /**
     * @return void
     */
    protected function initializeApi()
    {
        CloudinaryApiUtility::initializeByConfiguration($this->storage->getConfiguration());
    }

    /**
     * @return object|CloudinaryResourceService
     */
    protected function getCloudinaryResourceService(): CloudinaryResourceService
    {
        return GeneralUtility::makeInstance(CloudinaryResourceService::class, $this->storage);
    }

    /**
     * @return object|CloudinaryFolderService
     */
    protected function getCloudinaryFolderService(): CloudinaryFolderService
    {
        return GeneralUtility::makeInstance(CloudinaryFolderService::class, $this->storage->getUid());
    }

    /**
     * @return CloudinaryPathService
     */
    protected function getCloudinaryPathService(): CloudinaryPathService
    {
        if (!$this->cloudinaryPathService) {
            $this->cloudinaryPathService = GeneralUtility::makeInstance(
                CloudinaryPathService::class,
                $this->storage->getStorageRecord()
            );
        }

        return $this->cloudinaryPathService;
    }

    /**
     * @param string $message
     * @param array $arguments
     * @param array $data
     */
    protected function log(string $message, array $arguments = [], array $data = [])
    {
        /** @var \TYPO3\CMS\Core\Log\Logger $logger */
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $logger->log(
            LogLevel::INFO,
            vsprintf($message, $arguments),
            $data
        );
    }
}
