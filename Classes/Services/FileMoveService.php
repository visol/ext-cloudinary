<?php

namespace Visol\Cloudinary\Services;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Cloudinary\Api\Admin\AdminApi;
use Cloudinary\Api\Upload\UploadApi;
use Doctrine\DBAL\Driver\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Utility\CloudinaryApiUtility;

class FileMoveService
{

    protected string $tableName = 'sys_file';

    protected ?CloudinaryPathService $cloudinaryPathService = null;

    public function fileExists(File $fileObject, ResourceStorage $targetStorage): bool
    {
        $this->initializeCloudinaryService($targetStorage);

        // Retrieve the Public Id based on the file identifier
        $publicId = $this->getCloudinaryPathService()
            ->computeCloudinaryPublicId($fileObject->getIdentifier());

        try {
            $resource = $this->getAdminApi($targetStorage)->asset($publicId);
            $fileExists = !empty($resource);
        } catch (\Exception $exception) {
            $fileExists = false;
        }

        return $fileExists;
    }

    #public function forceMove(File $fileObject, ResourceStorage $targetStorage, $removeFile = true): bool
    #{
    #    $isUpdated = $isDeletedFromSourceStorage = false;
    #
    #    $fileNameAndAbsolutePath = $this->getAbsolutePath($fileObject);
    #
    #    if (file_exists($fileNameAndAbsolutePath)) {
    #        $isUploaded = $this->fileExists($fileObject, $targetStorage)
    #            ? true
    #            : $this->cloudinaryUploadFile($fileObject, $targetStorage);
    #
    #        if ($isUploaded) {
    #            // Update the storage uid
    #            $isUpdated = $this->updateFile(
    #                $fileObject,
    #                [
    #                    'storage' => $targetStorage->getUid(),
    #                ]
    #            );
    #
    #            if ($removeFile && $isUpdated) {
    #                // Delete the file form the local storage
    #                $isDeletedFromSourceStorage = unlink($fileNameAndAbsolutePath);
    #            }
    #        }
    #    }
    #    return $isUpdated && $isDeletedFromSourceStorage;
    #}

    public function changeStorage(File $fileObject, ResourceStorage $targetStorage, bool $removeFile = true): bool
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

    protected function ensureDirectoryExistence(File $fileObject)
    {

        // Make sure the directory exists
        $directory = dirname($this->getAbsolutePath($fileObject));
        if (!is_dir($directory)) {
            GeneralUtility::mkdir_deep($directory);
        }
    }

    protected function getAbsolutePath(File $fileObject): string
    {
        // Compute the absolute file name of the file to move
        $configuration = $fileObject->getStorage()->getConfiguration();
        $fileRelativePath = rtrim($configuration['basePath'], '/') . $fileObject->getIdentifier();
        return GeneralUtility::getFileAbsFileName($fileRelativePath);
    }

    public function cloudinaryUploadFile(
        File $fileObject,
        ResourceStorage $targetStorage,
        string $baseUrl = ''
    ): void {

        $this->initializeCloudinaryService($targetStorage);

        $this->ensureDirectoryExistence($fileObject);


        $fileIdentifier = $fileObject->getIdentifier();
        $publicId = $this->getCloudinaryPathService()
            ->computeCloudinaryPublicId($fileIdentifier);

        $options = [
            'public_id' => basename($publicId),
            'folder' => $this->getCloudinaryPathService()
                ->computeCloudinaryFolderPath(
                    $fileObject->getParentFolder()->getIdentifier()
                ),
            'resource_type' => $this->getCloudinaryPathService()->getResourceType($fileIdentifier),
            'overwrite' => true,
        ];
        $fileNameAndPath = $baseUrl
            ? rtrim($baseUrl, DIRECTORY_SEPARATOR) . $fileIdentifier
            : $this->getAbsolutePath($fileObject);

        // Upload the file
        $this->getUploadApi($targetStorage)->upload(
            $fileNameAndPath,
            $options
        );
    }

    protected function getQueryBuilder(): QueryBuilder
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        return $connectionPool->getQueryBuilderForTable($this->tableName);
    }

    protected function getConnection(): Connection
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        return $connectionPool->getConnectionForTable($this->tableName);
    }

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

    protected function getCloudinaryPathService(): CloudinaryPathService
    {
        return $this->cloudinaryPathService;
    }

    protected function initializeCloudinaryService(ResourceStorage $storage)
    {
        $this->cloudinaryPathService = GeneralUtility::makeInstance(
                CloudinaryPathService::class,
            $storage
            );
    }

    protected function getUploadApi(ResourceStorage $storage): UploadApi
    {
        return CloudinaryApiUtility::getCloudinary($storage)->uploadApi();
    }

    protected function getAdminApi(ResourceStorage $storage): AdminApi
    {
        return CloudinaryApiUtility::getCloudinary($storage)->adminApi();
    }

}
