<?php

namespace Visol\Cloudinary\Services;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Doctrine\DBAL\Driver\Connection;
use Visol\Cloudinary\Utility\CloudinaryPathUtility;
use Symfony\Component\Console\Command\Command;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Class FileMoveService
 */
class FileMoveService extends Command
{

    /**
     * @var string
     */
    protected $tableName = 'sys_file';

    /**
     * @param File $fileObject
     * @param ResourceStorage $targetStorage
     *
     * @return bool
     */
    public function fileExists(File $fileObject, ResourceStorage $targetStorage): bool
    {
        $this->initializeApi($targetStorage);

        // Retrieve the Public Id based on the file identifier
        $publicId = CloudinaryPathUtility::computeCloudinaryPublicId($fileObject->getIdentifier());

        try {
            $api = new \Cloudinary\Api();
            $resource = $api->resource($publicId);
            $fileExists = !empty($resource);
        } catch (\Exception $exception) {
            $fileExists = false;
        }

        return $fileExists;
    }

    /**
     * @param File $fileObject
     * @param string $baseUrl
     *
     */
    public function download(File $fileObject, string $baseUrl)
    {
        $this->ensureDirectoryExistence($fileObject);

        // Get storage configuration
        $configuration = $fileObject->getStorage()->getConfiguration();

        $url = sprintf(
            '%s/%s/%s',
            $baseUrl,
            rtrim($configuration['basePath'], DIRECTORY_SEPARATOR),
            ltrim($fileObject->getIdentifier(), DIRECTORY_SEPARATOR)
        );

        $contents = file_get_contents($url);
        $isDownloaded = false;

        if ($contents) {
            $isDownloaded = (bool)file_put_contents(
                $this->getAbsolutePath($fileObject),
                $contents
            );
        }
        return $isDownloaded;
    }

    /**
     * @param File $fileObject
     * @param ResourceStorage $targetStorage
     * @param bool $removeFile
     *
     * @return bool
     */
    public function forceMove(File $fileObject, ResourceStorage $targetStorage, $removeFile = true): bool
    {
        $isUpdated = $isDeletedFromSourceStorage = false;

        $fileNameAndAbsolutePath = $this->getAbsolutePath($fileObject);

        if (file_exists($fileNameAndAbsolutePath)) {

            $isUploaded = $this->fileExists($fileObject, $targetStorage)
                ? true
                : $this->cloudinaryUploadFile($fileObject, $targetStorage);

            if ($isUploaded) {

                // Update the storage uid
                $isUpdated = $this->updateFile(
                    $fileObject,
                    [
                        'storage' => $targetStorage->getUid(),
                    ]
                );

                if ($removeFile && $isUpdated) {
                    // Delete the file form the local storage
                    $isDeletedFromSourceStorage = unlink($fileNameAndAbsolutePath);
                }
            }
        }
        return $isUpdated && $isDeletedFromSourceStorage;
    }

    /**
     * @param File $fileObject
     * @param ResourceStorage $targetStorage
     * @param bool $removeFile
     *
     * @return bool
     */
    public function changeStorage(File $fileObject, ResourceStorage $targetStorage, $removeFile = true): bool
    {
        // Update the storage uid
        $isMigrated = (bool)$this->updateFile(
            $fileObject,
            [
                'storage' => $targetStorage->getUid(),
            ]
        );

        if ($removeFile) {
            // Delete the file form the local storage
            $isMigrated = unlink($this->getAbsolutePath($fileObject));
        }

        return $isMigrated;
    }

    /**
     * @param File $fileObject
     */
    protected function ensureDirectoryExistence(File $fileObject)
    {

        // Make sure the directory exists
        $directory = dirname($this->getAbsolutePath($fileObject));
        if (!is_dir($directory)) {
            GeneralUtility::mkdir_deep($directory);
        }
    }

    /**
     * @param File $fileObject
     *
     * @return string
     */
    protected function getAbsolutePath(File $fileObject): string
    {
        // Compute the absolute file name of the file to move
        $configuration = $fileObject->getStorage()->getConfiguration();
        $fileRelativePath = rtrim($configuration['basePath'], '/') . $fileObject->getIdentifier();
        return GeneralUtility::getFileAbsFileName($fileRelativePath);
    }

    /**
     * @param File $fileObject
     * @param ResourceStorage $targetStorage
     * @param string $baseUrl
     *
     * @return bool
     */
    public function cloudinaryUploadFile(
        File $fileObject,
        ResourceStorage $targetStorage,
        string $baseUrl = ''
    ): bool {

        $this->ensureDirectoryExistence($fileObject);

        $this->initializeApi($targetStorage);

        $publicId = PathUtility::basename(
            CloudinaryPathUtility::computeCloudinaryPublicId($fileObject->getName())
        );

        $options = [
            'public_id' => $publicId,
            'folder' => CloudinaryPathUtility::computeCloudinaryPath(
                $fileObject->getParentFolder()->getIdentifier()
            ),
            'overwrite' => true,
        ];

        $fileNameAndPath = $baseUrl
            ? rtrim($baseUrl, DIRECTORY_SEPARATOR) . $fileObject->getIdentifier()
            : $this->getAbsolutePath($fileObject);

        // Upload the file
        try {
            $resource = \Cloudinary\Uploader::upload(
                $fileNameAndPath,
                $options
            );
        } catch (\Exception $e) {
            $resource = false;
        }

        return !empty($resource);
    }

    /**
     * @param ResourceStorage $targetStorage
     */
    protected function initializeApi(ResourceStorage $targetStorage)
    {
        // Compute the absolute file name of the file to move
        $configuration = $targetStorage->getConfiguration();
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

    /**
     * @param File $fileObject
     * @param array $values
     *
     * @return int
     */
    protected function updateFile(File $fileObject, array $values): int
    {
        $connection = $this->getConnection();
        return $connection->update(
            $this->tableName,
            $values,
            [
                'uid' => $fileObject->getUid(),
            ]
        );
    }
}
