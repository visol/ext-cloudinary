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
use TYPO3\CMS\Core\Resource\ResourceFactory;
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
     * @param int|ResourceStorage $targetStorage
     * @return bool
     */
    public function forceMove(File $fileObject, $targetStorage): bool
    {
        $isUpdated = $isDeletedFromSourceStorage = false;

        $fileNameAndAbsolutePath = $this->getAbsolutePath($fileObject);
        if (file_exists($fileNameAndAbsolutePath)) {

            // Convert numerical storage id to object
            if (is_numeric($targetStorage)) {
                $targetStorage = ResourceFactory::getInstance()->getStorageObject($targetStorage);
            }

            $this->initializeApi($targetStorage);

            // Upload the file storage
            $isUploaded = $this->cloudinaryUploadFile($fileObject);

            if ($isUploaded) {

                // Update the storage uid
                $isUpdated = $this->updateFile(
                    $fileObject,
                    [
                        'storage' => $targetStorage->getUid(),
                    ]
                );

                if ($isUpdated) {
                    // Delete the file form the local storage
                    $isDeletedFromSourceStorage = unlink($fileNameAndAbsolutePath);
                }
            }
        }

        return $isUpdated && $isDeletedFromSourceStorage;
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
     *
     * @return bool
     */
    protected function cloudinaryUploadFile(File $fileObject): bool
    {

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

        // Upload the file
        $resource = \Cloudinary\Uploader::upload(
            $this->getAbsolutePath($fileObject),
            $options
        );
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
