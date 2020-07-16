<?php

namespace Visol\Cloudinary\Services;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Cloudinary\Search;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogManager;
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
     * @var CloudinaryService
     */
    protected $cloudinaryService;

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

        self::FOLDER_CREATED => 0,
        self::FOLDER_UPDATED => 0,
        self::FOLDER_DELETED => 0,
        self::FOLDER_TOTAL => 0,
    ];

    /**
     * CloudinaryScanService constructor.
     *
     * @param ResourceStorage $storage
     */
    public function __construct(ResourceStorage $storage)
    {
        if ($storage->getDriverType() !== CloudinaryDriver::DRIVER_TYPE) {
            throw new \Exception('Storage is not of type "cloudinary"', 1594714337);
        }
        $this->storage = $storage;
    }

    /**
     * @return void
     */
    public function empty(): void
    {
        $this->getCloudinaryResourceService()->deleteResources();
        $this->getCloudinaryResourceService()->deleteFolders();
    }

    /**
     * @return array
     */
    public function scan(): array
    {
        $this->preScan();

        // Before calling the Search API, make sure we are connected with the right cloudinary account
        $this->initializeApi();

        $cloudinaryFolder = $this->getCloudinaryService()->computeCloudinaryFolderPath(DIRECTORY_SEPARATOR);

        // Add a filter if the root directory contains a base path segment
        // + remove _processed_ folder from the search
        if ($cloudinaryFolder) {
            $expressions[] = sprintf('folder=%s/*',  $cloudinaryFolder);
            $expressions[] = sprintf('NOT folder=%s/%s/*', $cloudinaryFolder, $this->processedFolder);
        } else {
            $expressions[] = sprintf('NOT folder=%s/*', $this->processedFolder);
        }

        $folders = [];

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
                foreach ($response['resources'] as $resource)
                {

                    // Compute file identifier and add the info to the resource
                    $resource['file_identifier'] = $this->getCloudinaryService()->computeFileIdentifier($resource);

                    $result = $this->getCloudinaryResourceService()->save($resource);

                    // For the stats, we collect the number of files touched
                    $key = key($result);
                    $this->statistics[$key] += $result[$key];

                    // In any case we can add a file to the counter.
                    // Later we can verify the total corresponds to the "created" + "updated" + "deleted" files
                    $this->statistics[self::TOTAL]++;

                    // We collect valid folders here...
                    if ($resource['folder']) {
                        $folders[$resource['folder']] = '';
                    }
                }
            }
        } while (!empty($response) && array_key_exists('next_cursor', $response));

        // Persist previously collected folders
        foreach (array_keys($folders) as $folder) {
            $result = $this->getCloudinaryResourceService()->saveFolder($folder);

            // For the stats, we collect the number of files touched
            $key = key($result);
            $this->statistics[$key] += $result[$key];

            // In any case we can add a file to the counter.
            // Later we can verify the total corresponds to the "created" + "updated" + "deleted" files
            $this->statistics[self::FOLDER_TOTAL]++;
        }

        $this->postScan();

        return $this->statistics;
    }

    /**
     * @return void
     */
    protected function preScan(): void
    {
        $values = ['missing' => 1];
        $this->getCloudinaryResourceService()->updateResources($values);
        $this->getCloudinaryResourceService()->updateFolders($values);
    }

    /**
     * @return void
     */
    protected function postScan(): void
    {
        $identifier = ['missing' => 1];
        $this->statistics[self::DELETED] = $this->getCloudinaryResourceService()->deleteResources($identifier);
        $this->statistics[self::FOLDER_DELETED] = $this->getCloudinaryResourceService()->deleteFolders($identifier);
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
     * @return CloudinaryService
     */
    protected function getCloudinaryService(): CloudinaryService
    {
        if (!$this->cloudinaryService) {
            $this->cloudinaryService = GeneralUtility::makeInstance(
                CloudinaryService::class,
                $this->storage
            );
        }

        return $this->cloudinaryService;
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
