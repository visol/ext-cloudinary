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
    protected string $tableName = 'tx_cloudinary_cache_resources';

    protected ResourceStorage $storage;

    public function __construct(ResourceStorage $storage)
    {
        $this->storage = $storage;
    }

    public function markAsMissing(): int
    {
        $values = ['missing' => 1];
        $identifier['storage'] = $this->storage->getUid();
        return $this->getConnection()->update($this->tableName, $values, $identifier);
    }

    public function getResource(string $publicId): array
    {
        $query = $this->getQueryBuilder();
        $query
            ->select('*')
            ->from($this->tableName)
            ->where(
                $query->expr()->eq('storage', $this->storage->getUid()),
                $query->expr()->eq('public_id_hash', $query->expr()->literal(sha1($publicId))),
            )
            ->setMaxResults(1);

        $resource = $query->execute()->fetchAssociative();
        return $resource ?: [];
    }

    public function getResources(
        string $folder,
        array  $orderings = [],
        array  $pagination = [],
        bool   $recursive = false
    ): array
    {
        $query = $this->getQueryBuilder();
        $query
            ->select('*')
            ->from($this->tableName)
            ->where($query->expr()->eq('storage', $this->storage->getUid()));

        // We should handle recursion
        $expression = $recursive
            ? $query->expr()->like('folder', $query->expr()->literal($folder . '%'))
            : $query->expr()->eq('folder', $query->expr()->literal($folder));
        $query->andWhere($expression);

        if ($orderings) {
            $query->orderBy($orderings['fieldName'], $orderings['direction']);
        }

        if ($pagination && (int)$pagination['maxResult'] > 0) {
            $query->setMaxResults((int)$pagination['maxResult']);
            $query->setFirstResult((int)$pagination['firstResult']);
        }
        return $query->execute()->fetchAllAssociative();
    }

    public function count(string $folder, bool $recursive = false): int
    {
        $query = $this->getQueryBuilder();
        $query
            ->count('*')
            ->from($this->tableName)
            ->where($query->expr()->eq('storage', $this->storage->getUid()));

        // We should handle recursion
        $expresion = $recursive
            ? $query->expr()->like('folder', $query->expr()->literal($folder . '%'))
            : $query->expr()->eq('folder', $query->expr()->literal($folder));
        $query->andWhere($expresion);

        return (int)$query->execute()->fetchOne(0);
    }

    public function delete(string $publicId): int
    {
        $identifier['public_id'] = $publicId;
        $identifier['storage'] = $this->storage->getUid();
        return $this->getConnection()->delete($this->tableName, $identifier);
    }

    public function deleteAll(array $identifiers = []): int
    {
        $identifiers['storage'] = $this->storage->getUid();
        return $this->getConnection()->delete($this->tableName, $identifiers);
    }

    public function save(array $cloudinaryResource): array
    {
        $publicIdHash = $this->getPublicIdHash($cloudinaryResource);

        // We must also save the folder here
        $folder = $this->getFolder($cloudinaryResource);
        if ($folder) {
            $this->getCloudinaryFolderService()->save($folder);
        }

        $result = $this->exists($publicIdHash)
            ? ['updated' => $this->update($cloudinaryResource, $publicIdHash),]
            : ['created' => $this->add($cloudinaryResource),];

        return array_merge(
            $result,
            [
                'publicIdHash' => $publicIdHash,
                'resource' => $cloudinaryResource,
            ]
        );
    }

    protected function add(array $cloudinaryResource): int
    {
        return $this->getConnection()->insert($this->tableName, $this->getValues($cloudinaryResource));
    }

    protected function update(array $cloudinaryResource, string $publicIdHash): int
    {
        return $this->getConnection()->update($this->tableName, $this->getValues($cloudinaryResource), [
            'public_id_hash' => $publicIdHash,
            'storage' => $this->storage->getUid(),
        ]);
    }

    protected function exists(string $publicIdHash): int
    {
        $query = $this->getQueryBuilder();
        $query
            ->count('*')
            ->from($this->tableName)
            ->where(
                $query->expr()->eq('storage', $this->storage->getUid()),
                $query->expr()->eq('public_id_hash', $query->expr()->literal($publicIdHash)),
            );

        return (int)$query->execute()->fetchOne(0);
    }

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
            'aspect_ratio' => (float)$this->getValue('aspect_ratio', $cloudinaryResource),
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

    protected function getValue(string $key, array $cloudinaryResource): string
    {
        return isset($cloudinaryResource[$key]) ? (string)$cloudinaryResource[$key] : '';
    }

    protected function getFileName(array $cloudinaryResource): string
    {
        return basename($this->getValue('public_id', $cloudinaryResource));
    }

    protected function getFolder(array $cloudinaryResource): string
    {
        $folder = dirname($this->getValue('public_id', $cloudinaryResource));
        return $folder === '.' ? '' : $folder;
    }

    protected function getCreatedAt(array $cloudinaryResource): string
    {
        $createdAt = $this->getValue('created_at', $cloudinaryResource);
        return date('Y-m-d h:i:s', strtotime($createdAt));
    }

    protected function getPublicIdHash(array $cloudinaryResource): string
    {
        $publicId = $this->getValue('public_id', $cloudinaryResource);
        return sha1($publicId);
    }

    protected function getUpdatedAt(array $cloudinaryResource): string
    {
        $updatedAt = $this->getValue('updated_at', $cloudinaryResource)
            ?: $this->getValue('created_at', $cloudinaryResource);

        return date('Y-m-d h:i:s', strtotime($updatedAt));
    }

    protected function getCloudinaryFolderService(): CloudinaryFolderService
    {
        return GeneralUtility::makeInstance(CloudinaryFolderService::class, $this->storage->getUid());
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
}
