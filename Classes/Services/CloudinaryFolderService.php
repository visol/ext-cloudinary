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
 * Class CloudinaryFolderService
 */
class CloudinaryFolderService
{

    /**
     * @var string
     */
    protected $tableName = 'tx_cloudinary_folder';

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
     * @param string $folder
     *
     * @return array
     */
    public function getFolder(string $folder): array
    {
        $query = $this->getQueryBuilder();
        $query
            ->select('*')
            ->from($this->tableName)
            ->where(
                $query->expr()->eq('storage', $this->storage->getUid()),
                $query->expr()->eq(
                    'folder',
                    $query->expr()->literal($folder)
                )
            );

        $folder = $query->execute()->fetch();
        return $folder
            ? $folder
            : [];
    }

    /**
     * @return int
     */
    public function markAsMissing(): int
    {
        $values = ['missing' => 1,];
        $identifier['storage'] = $this->storage->getUid();
        return $this->getConnection()->update($this->tableName, $values, $identifier);
    }

    /**
     * @param string $parentFolder
     * @param array $orderings
     *
     * @return array
     */
    public function getSubFolders(string $parentFolder, array $orderings, bool $recursive = false): array
    {
        $query = $this->getQueryBuilder();
        $query
            ->select('*')
            ->from($this->tableName)
            ->where(
                $query->expr()->eq('storage', $this->storage->getUid())
            )
            ->orderBy($orderings['fieldName'], $orderings['direction']);

        // We should handle recursion
        $expresion = $recursive
            ? $query->expr()->like(
                'parent_folder',
                $query->expr()->literal($parentFolder . '%')
            )
            : $query->expr()->eq(
                'parent_folder',
                $query->expr()->literal($parentFolder)
            );
        $query->andWhere($expresion);

        return $query->execute()->fetchAll();
    }

    /**
     * @param string $parentFolder
     * @param bool $recursive
     *
     * @return int
     */
    public function countSubFolders(string $parentFolder, bool $recursive = false): int
    {
        $query = $this->getQueryBuilder();
        $query
            ->count('*')
            ->from($this->tableName)
            ->where(
                $query->expr()->eq('storage', $this->storage->getUid()),
                $query->expr()->eq(
                    'parent_folder',
                    $query->expr()->literal($parentFolder)
                )
            );

        // We should handle recursion
        $expresion = $recursive
            ? $query->expr()->like(
                'parent_folder',
                $query->expr()->literal($parentFolder . '%')
            )
            : $query->expr()->eq(
                'parent_folder',
                $query->expr()->literal($parentFolder)
            );
        $query->andWhere($expresion);

        return (int)$query->execute()->fetchColumn(0);
    }

    /**
     * @param string $folder
     *
     * @return int
     */
    public function delete(string $folder): int
    {
        $identifier['folder'] = $folder;
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
     * @param string $folder
     *
     * @return array
     */
    public function save(string $folder): array
    {
        $folderHash = sha1($folder);

        return $this->exists($folderHash)
            ? ['folder_updated' => $this->update($folder, $folderHash)]
            : ['folder_created' => $this->add($folder)];
    }

    /**
     * @param string $folder
     *
     * @return int
     */
    protected function add(string $folder): int
    {
        return $this->getConnection()->insert(
            $this->tableName,
            $this->getValues($folder)
        );
    }

    /**
     * @param string $folder
     * @param string $folderHash
     *
     * @return int
     */
    protected function update(string $folder, string $folderHash): int
    {
        return $this->getConnection()->update(
            $this->tableName,
            $this->getValues($folder),
            [
                'folder_hash' => $folderHash,
                'storage' => $this->storage->getUid(),
            ]
        );
    }

    /**
     * @param string $folderPath
     *
     * @return string
     */
    protected function computeParentFolder(string $folderPath): string
    {
        return dirname($folderPath) === '.'
            ? ''
            : dirname($folderPath);
    }

    /**
     * @param string $folderHash
     *
     * @return int
     */
    protected function exists(string $folderHash): int
    {
        $query = $this->getQueryBuilder();
        $query
            ->count('*')
            ->from($this->tableName)
            ->where(
                $query->expr()->eq('storage', $this->storage->getUid()),
                $query->expr()->eq(
                    'folder_hash',
                    $query->expr()->literal($folderHash)
                )
            );

        return (int)$query->execute()->fetchColumn(0);
    }

    /**
     * @param string $folder
     *
     * @return array
     */
    protected function getValues(string $folder): array
    {
        return [
            'folder' => $folder,
            'folder_hash' => sha1($folder),
            'parent_folder' => $this->computeParentFolder($folder),

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
