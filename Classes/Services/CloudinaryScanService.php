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
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Driver\CloudinaryDriver;

/**
 * Class CloudinaryScanService
 */
class CloudinaryScanService
{

    private const CREATED = 'created';
    private const UPDATED = 'updated';
    private const DELETED = 'deleted';
    private const TOTAL = 'total';
    private const FOLDER_CREATED = 'folder_created';
    private const FOLDER_UPDATED = 'folder_updated';
    private const FOLDER_DELETED = 'folder_deleted';
    private const FOLDER_TOTAL = 'folder_total';

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

//        self::FOLDER_CREATED => 0,
//        self::FOLDER_UPDATED => 0,
        self::FOLDER_DELETED => 0,
//        self::FOLDER_TOTAL => 0,
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

                    if ($this->io) {
                        $this->io->writeln($resource['public_id']);
                    }

                    // Save mirrored file
                    $result = $this->getCloudinaryResourceService()->save($resource);

                    // Find if the file exists in sys_file already
                    $fileIdentifier = $this->getCloudinaryPathService()->computeFileIdentifier($resource);
                    $file = ResourceFactory::getInstance()->getFileObjectByStorageAndIdentifier(
                        $this->storage->getUid(),
                        $fileIdentifier
                    );

                    if (!$file) {

//                        var_dump($fileIdentifier);
//                        var_dump($this->storage->hasFile($fileIdentifier));
//                        print_r($resource);
//                        exit();
//                        $this->storage->getFile($fileIdentifier);

                        if ($this->io) {
                            $this->io->writeln('Indexing new file: ' . $fileIdentifier);
                            $this->io->writeln('');
                        }
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
     * @return void
     */
    protected function initializeApi()
    {
        $configuration = $this->storage->getConfiguration();
        \Cloudinary::config(
            [
                'cloud_name' => $configuration['cloudName'],
                'api_key' => $configuration['apiKey'],
                'api_secret' => $configuration['apiSecret'],
                'timeout' => $configuration['timeout'],
                'secure' => true
            ]
        );
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
        return GeneralUtility::makeInstance(CloudinaryFolderService::class, $this->storage);
    }

    /**
     * @return CloudinaryPathService
     */
    protected function getCloudinaryPathService(): CloudinaryPathService
    {
        if (!$this->cloudinaryPathService) {
            $this->cloudinaryPathService = GeneralUtility::makeInstance(
                CloudinaryPathService::class,
                $this->storage
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
