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
use Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Utility\CloudinaryApiUtility;

class FileMoveService
{

    protected string $tableName = 'sys_file';

    protected ?CloudinaryPathService $cloudinaryPathService = null;

    public function fileExists(ResourceStorage $storage, string $identifier): bool
    {
        $this->initializeCloudinaryService($storage);

        // Retrieve the Public Id based on the file identifier
        $publicId = $this->getCloudinaryPathService()->computeCloudinaryPublicId($identifier);

        try {
            $resource = (array)$this->getAdminApi($storage)->asset($publicId);

            // update resource index
            (new CloudinaryResourceService($storage))->save((array)$resource);
            return true;
        } catch (Exception $exception) {
            return false;
        }
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

    protected function getAbsolutePath(File $fileObject): string
    {
        // Compute the absolute file name of the file to move
        $configuration = $fileObject->getStorage()->getConfiguration();
        $fileRelativePath = rtrim($configuration['basePath'], '/') . $fileObject->getIdentifier();
        return GeneralUtility::getFileAbsFileName($fileRelativePath);
    }

    public function cloudinaryUploadFile(
        File $fileObject,
        Folder $targetFolder,
        string $newIdentifier,
        string $baseUrl = ''
    ): void {
        $targetStorage = $targetFolder->getStorage();
        $this->initializeCloudinaryService($targetStorage);
        $publicId = $this->getCloudinaryPathService()->computeCloudinaryPublicId($newIdentifier);

        $options = [
            'public_id' => basename($publicId),
            'folder' => $this->getCloudinaryPathService()
                ->computeCloudinaryFolderPath(
                    dirname($newIdentifier)
                ),
            'resource_type' => $this->getCloudinaryPathService()->getResourceType($newIdentifier),
            'overwrite' => true,
        ];
        $fileNameAndPath = $baseUrl
            ? rtrim($baseUrl, DIRECTORY_SEPARATOR) . $fileObject->getIdentifier()
            : ($fileObject->getPublicUrl() ?: $fileObject->getForLocalProcessing(false));

        // Upload the file
        $response = $this->getUploadApi($targetStorage)->upload(
            $fileNameAndPath,
            $options
        );
        $resource = (array)$response;
        (new CloudinaryResourceService($targetStorage))->save($resource);
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
