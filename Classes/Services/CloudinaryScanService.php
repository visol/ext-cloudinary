<?php

namespace Visol\Cloudinary\Services;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Cloudinary\Api;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Log\Logger;
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

class CloudinaryScanService
{

    private const CREATED = 'created';
    private const UPDATED = 'updated';
    private const DELETED = 'deleted';
    private const TOTAL = 'total';
    private const FAILED = 'failed';
    private const FOLDER_DELETED = 'folder_deleted';

    protected ResourceStorage $storage;

    protected CloudinaryPathService $cloudinaryPathService;

    protected string $processedFolder = '_processed_';

    protected array $statistics = [
        self::CREATED => 0,
        self::UPDATED => 0,
        self::DELETED => 0,
        self::TOTAL => 0,
        self::FAILED => 0,

        self::FOLDER_DELETED => 0,
    ];

    protected ?SymfonyStyle $io = null;

    public function __construct(ResourceStorage $storage, SymfonyStyle $io = null)
    {
        if ($storage->getDriverType() !== CloudinaryDriver::DRIVER_TYPE) {
            throw new \Exception('Storage is not of type "cloudinary"', 1594714337);
        }
        $this->storage = $storage;
        $this->io = $io;
    }

    public function deleteAll(): void
    {
        $this->getCloudinaryResourceService()->deleteAll();
        $this->getCloudinaryFolderService()->deleteAll();
    }

    public function scanOne(string $publicId): array|null
    {
        try {
            $resource = (array)$this->getApi()->resource($publicId);
            $result = $this->getCloudinaryResourceService()->save($resource);
        } catch (Exception $exception) {
            $result = null;
        }
        return $result;
    }

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

        $this->console('Mirroring...', true);

        do {
            $nextCursor = isset($response)
                ? $response['next_cursor']
                : '';

            $this->info(
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
            $search = new Search();

            $response = $search
                ->expression(implode(' AND ', $expressions))
                ->sort_by('public_id', 'asc')
                ->max_results(500)
                ->next_cursor($nextCursor)
                ->execute();

            if (is_array($response['resources'])) {
                foreach ($response['resources'] as $resource) {
                    $fileIdentifier = $this->getCloudinaryPathService()->computeFileIdentifier($resource);
                    try {
                        $this->console($fileIdentifier);

                        // Save mirrored file
                        $result = $this->getCloudinaryResourceService()->save($resource);

                        // Find if the file exists in sys_file already
                        if (!$this->fileExistsInStorage($fileIdentifier)) {

                            $this->console('Indexing new file: ' . $fileIdentifier, true);

                            // This will trigger a file indexation
                            $this->storage->getFile($fileIdentifier);
                        }

                        // For the stats, we collect the number of files touched
                        $key = key($result);
                        $this->statistics[$key] += $result[$key];

                        // In any case we can add a file to the counter.
                        // Later we can verify the total corresponds to the "created" + "updated" + "deleted" files
                        $this->statistics[self::TOTAL]++;
                    } catch (\Exception $e) {
                        $this->statistics[self::FAILED]++;
                        $this->console(sprintf('Error could not process "%s"', $fileIdentifier));
                        // ignore
                    }
                }
            }
        } while (!empty($response) && isset($response['next_cursor']));

        $this->postScan();

        return $this->statistics;
    }

    protected function preScan(): void
    {
        $this->getCloudinaryResourceService()->markAsMissing();
        $this->getCloudinaryFolderService()->markAsMissing();
    }

    protected function postScan(): void
    {
        $identifiers = ['missing' => 1];
        $this->statistics[self::DELETED] = $this->getCloudinaryResourceService()->deleteAll($identifiers);
        $this->statistics[self::FOLDER_DELETED] = $this->getCloudinaryFolderService()->deleteAll($identifiers);
    }

    protected function fileExistsInStorage(string $fileIdentifier): bool
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

        return (bool)$query->execute()->fetchOne(0);
    }

    protected function getQueryBuilder(): QueryBuilder
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        return $connectionPool->getQueryBuilderForTable('sys_file');
    }

    protected function initializeApi(): void
    {
        CloudinaryApiUtility::initializeByConfiguration($this->storage->getConfiguration());
    }

    protected function getCloudinaryResourceService(): CloudinaryResourceService
    {
        return GeneralUtility::makeInstance(CloudinaryResourceService::class, $this->storage);
    }

    protected function getCloudinaryFolderService(): CloudinaryFolderService
    {
        return GeneralUtility::makeInstance(CloudinaryFolderService::class, $this->storage->getUid());
    }

    protected function getCloudinaryPathService(): CloudinaryPathService
    {
        if (!$this->cloudinaryPathService) {
            $this->cloudinaryPathService = GeneralUtility::makeInstance(
                CloudinaryPathService::class,
                $this->storage->getConfiguration()
            );
        }

        return $this->cloudinaryPathService;
    }

    protected function getApi()
    {
        // Initialize and configure the API for each call
        $this->initializeApi();

        // create a new instance upon each API call to avoid driver confusion
        return new Api();
    }

    protected function info(string $message, array $arguments = [], array $data = []): void
    {
        /** @var Logger $logger */
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $logger->log(
            LogLevel::INFO,
            vsprintf($message, $arguments),
            $data
        );
    }

    protected function console(string $message, $additionalBlankLine = false): void
    {
        if ($this->io) {
            $this->io->writeln($message);
            if ($additionalBlankLine) {
                $this->io->writeln('');
            }
        }
    }
}
