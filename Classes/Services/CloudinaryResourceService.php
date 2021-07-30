<?php

namespace Visol\Cloudinary\Services;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Doctrine\DBAL\Driver\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class CloudinaryResourceService
 */
class CloudinaryResourceService
{

    /**
     * @var string
     */
    protected $tableName = 'tx_cloudinary_resource';

    /**
     * @var ResourceStorage
     */
    protected $storage;

    /**
     * CloudinaryResourceService constructor.
     *
     * @param ResourceStorage $storage
     */
    public function __construct(ResourceStorage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * @return int
     */
    public function markAsMissing(): int
    {
        $values = ['missing' => 1];
        $identifier['storage'] = $this->storage->getUid();
        return $this->getConnection()->update($this->tableName, $values, $identifier);
    }

    /**
     * @param string $publicId
     *
     * @return array
     */
    public function getResource(string $publicId): array
    {
        $query = $this->getQueryBuilder();
        $query
            ->select('*')
            ->from($this->tableName)
            ->where(
                $query->expr()->eq('storage', $this->storage->getUid()),
                $query->expr()->eq(
                    'public_id',
                    $query->expr()->literal($publicId)
                )
            );

        $resource = $query->execute()->fetch();
        return $resource
            ? $resource
            : [];
    }

    /**
     * @param string $folder
     * @param array $orderings
     * @param array $pagination
     * @param bool $recursive
     *
     * @return array
     */
    public function getResources(string $folder, array $orderings = [], array $pagination = [], bool $recursive = false): array
    {
        $query = $this->getQueryBuilder();
        $query
            ->select('*')
            ->from($this->tableName)
            ->where(
                $query->expr()->eq('storage', $this->storage->getUid())
            );

        // We should handle recursion
        $expresion = $recursive
            ? $query->expr()->like(
                'folder',
                $query->expr()->literal($folder . '%')
            )
            : $query->expr()->eq(
                'folder',
                $query->expr()->literal($folder)
            );
        $query->andWhere($expresion);

        if ($orderings) {
            $query->orderBy($orderings['fieldName'], $orderings['direction']);
        }


        if ($pagination && (int)$pagination['maxResult'] > 0) {
            $query->setMaxResults((int)$pagination['maxResult']);
            $query->setFirstResult((int)$pagination['firstResult']);
        }
        return $query->execute()->fetchAll();
    }

    /**
     * @param string $folder
     * @param bool $recursive
     *
     * @return int
     */
    public function count(string $folder, bool $recursive = false): int
    {
        $query = $this->getQueryBuilder();
        $query
            ->count('*')
            ->from($this->tableName)
            ->where(
                $query->expr()->eq('storage', $this->storage->getUid())
            );

        // We should handle recursion
        $expresion = $recursive
            ? $query->expr()->like(
                'folder',
                $query->expr()->literal($folder . '%')
            )
            : $query->expr()->eq(
                'folder',
                $query->expr()->literal($folder)
            );
        $query->andWhere($expresion);


        return (int)$query->execute()->fetchColumn(0);
    }

    /**
     * @param string $publicId
     *
     * @return int
     */
    public function delete(string $publicId): int
    {
        $identifier['public_id'] = $publicId;
        $identifier['storage'] = $this->storage->getUid();
        return $this->getConnection()->delete($this->tableName, $identifier);
    }

    /**
     * @param array $identifier
     *
     * @return int
     */
    public function deleteAll(array $identifier = []): int
    {
        $identifier['storage'] = $this->storage->getUid();
        return $this->getConnection()->delete($this->tableName, $identifier);
    }

    /**
     * @param array $cloudinaryResource
     *
     * @return array
     */
    public function save(array $cloudinaryResource): array
    {
        $publicIdHash = $this->getPublicIdHash($cloudinaryResource);

        // We must also save the folder here
        $folder = $this->getFolder($cloudinaryResource);
        if ($folder) {
            $this->getCloudinaryFolderService()->save($folder);
        }

        return $this->exists($publicIdHash)
            ? ['updated' => $this->update($cloudinaryResource, $publicIdHash)]
            : ['created' => $this->add($cloudinaryResource)];
    }

    /**
     * @param array $cloudinaryResource
     *
     * @return int
     */
    protected function add(array $cloudinaryResource): int
    {
        return $this->getConnection()->insert(
            $this->tableName,
            $this->getValues($cloudinaryResource)
        );
    }

    /**
     * @param array $cloudinaryResource
     * @param string $publicIdHash
     *
     * @return int
     */
    protected function update(array $cloudinaryResource, string $publicIdHash): int
    {
        return $this->getConnection()->update(
            $this->tableName,
            $this->getValues($cloudinaryResource),
            [
                'public_id_hash' => $publicIdHash,
                'storage' => $this->storage->getUid(),
            ]
        );
    }

    /**
     * @param string $publicIdHash
     *
     * @return int
     */
    protected function exists(string $publicIdHash): int
    {
        $query = $this->getQueryBuilder();
        $query
            ->count('*')
            ->from($this->tableName)
            ->where(
                $query->expr()->eq('storage', $this->storage->getUid()),
                $query->expr()->eq(
                    'public_id_hash',
                    $query->expr()->literal($publicIdHash)
                )
            );

        return (int)$query->execute()->fetchColumn(0);
    }

    /**
     * @param array $cloudinaryResource
     *
     * @return array
     */
    protected function getValues(array $cloudinaryResource): array
    {
        $publicIdHash = $this->getPublicIdHash($cloudinaryResource);

        return [
            'public_id' => $this->getValue('public_id', $cloudinaryResource),
            'public_id_hash' => $publicIdHash,
            'folder' => $this->getFolder($cloudinaryResource),
            'filename' => $this->getFileName($cloudinaryResource),
            'format' => $this->getValue('format', $cloudinaryResource),
            'version' => (int)$this->getValue('version', $cloudinaryResource),
            'resource_type' => $this->getValue('resource_type', $cloudinaryResource),
            'type' => $this->getValue('type', $cloudinaryResource),
            'created_at' => $this->getCreatedAt($cloudinaryResource),
            'uploaded_at' => $this->getUpdatedAt($cloudinaryResource),
            'bytes' => (int)$this->getValue('bytes', $cloudinaryResource),
            'width' => (int)$this->getValue('width', $cloudinaryResource),
            'height' => (int)$this->getValue('height', $cloudinaryResource),
            'aspect_ratio' => (double)$this->getValue('aspect_ratio', $cloudinaryResource),
            'pixels' => (int)$this->getValue('pixels', $cloudinaryResource),
            'url' => $this->getValue('url', $cloudinaryResource),
            'secure_url' => $this->getValue('secure_url', $cloudinaryResource),
            'status' => $this->getValue('status', $cloudinaryResource),
            'access_mode' => $this->getValue('access_mode', $cloudinaryResource),
            'access_control' => $this->getValue('access_control', $cloudinaryResource),
            'etag' => $this->getValue('etag', $cloudinaryResource),

            // typo3 info
            'missing' => 0,
            'storage' => $this->storage->getUid(),
        ];
    }

    /**
     * @param string $key
     * @param array $cloudinaryResource
     *
     * @return string
     */
    protected function getValue(string $key, array $cloudinaryResource): string
    {
        return isset($cloudinaryResource[$key])
            ? (string)$cloudinaryResource[$key]
            : '';
    }

    /**
     * @param array $cloudinaryResource
     *
     * @return string
     */
    protected function getFileName(array $cloudinaryResource): string
    {
        return basename($this->getValue('public_id', $cloudinaryResource));
    }

    /**
     * @param array $cloudinaryResource
     *
     * @return string
     */
    protected function getFolder(array $cloudinaryResource): string
    {
        $folder = dirname($this->getValue('public_id', $cloudinaryResource));
        return $folder === '.'
            ? ''
            : $folder;
    }

    /**
     * @param array $cloudinaryResource
     *
     * @return string
     */
    protected function getCreatedAt(array $cloudinaryResource): string
    {
        $createdAt = $this->getValue('created_at', $cloudinaryResource);
        return date(
            'Y-m-d h:i:s',
            strtotime($createdAt)
        );
    }

    /**
     * @param array $cloudinaryResource
     *
     * @return string
     */
    protected function getPublicIdHash(array $cloudinaryResource): string
    {
        $publicId = $this->getValue('public_id', $cloudinaryResource);
        return sha1($publicId);
    }

    /**
     * @param array $cloudinaryResource
     *
     * @return string
     */
    protected function getUpdatedAt(array $cloudinaryResource): string
    {
        $updatedAt = $this->getValue('updated_at', $cloudinaryResource)
            ? $this->getValue('updated_at', $cloudinaryResource)
            : $this->getValue('created_at', $cloudinaryResource);

        return date(
            'Y-m-d h:i:s',
            strtotime($updatedAt)
        );
    }

    /**
     * @return object|CloudinaryFolderService
     */
    protected function getCloudinaryFolderService(): CloudinaryFolderService
    {
        return GeneralUtility::makeInstance(CloudinaryFolderService::class, $this->storage->getUid());
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
