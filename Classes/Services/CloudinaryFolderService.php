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
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CloudinaryFolderService
{

    protected string $tableName = 'tx_cloudinary_folder';

    protected int $storageUid;

    public function __construct(int $storageUid)
    {
        $this->storageUid = $storageUid;
    }

    public function getFolder(string $folder): array
    {
        $query = $this->getQueryBuilder();
        $query
            ->select('*')
            ->from($this->tableName)
            ->where(
                $query->expr()->eq('storage', $this->storageUid),
                $query->expr()->eq(
                    'folder',
                    $query->expr()->literal($folder)
                )
            );

        $folder = $query->execute()->fetchAssociative();
        return $folder ?: [];
    }

    public function markAsMissing(): int
    {
        $values = ['missing' => 1,];
        $identifier['storage'] = $this->storageUid;
        return $this->getConnection()->update($this->tableName, $values, $identifier);
    }

    public function getSubFolders(string $parentFolder, array $orderings, bool $recursive = false): array
    {
        $query = $this->getQueryBuilder();
        $query
            ->select('*')
            ->from($this->tableName)
            ->where(
                $query->expr()->eq('storage', $this->storageUid)
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

        return $query->execute()->fetchAllAssociative();
    }

    public function countSubFolders(string $parentFolder, bool $recursive = false): int
    {
        $query = $this->getQueryBuilder();
        $query
            ->count('*')
            ->from($this->tableName)
            ->where(
                $query->expr()->eq('storage', $this->storageUid)
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

        return (int)$query->execute()->fetchOne(0);
    }

    public function delete(string $folder): int
    {
        $identifier['folder'] = $folder;
        $identifier['storage'] = $this->storageUid;
        return $this->getConnection()->delete($this->tableName, $identifier);
    }

    public function deleteAll(array $identifiers = []): int
    {
        $identifiers['storage'] = $this->storageUid;
        return $this->getConnection()->delete($this->tableName, $identifiers);
    }

    public function save(string $folder): array
    {
        $folderHash = sha1($folder);

        return $this->exists($folderHash)
            ? ['folder_updated' => $this->update($folder, $folderHash)]
            : ['folder_created' => $this->add($folder)];
    }

    protected function add(string $folder): int
    {
        return $this->getConnection()->insert(
            $this->tableName,
            $this->getValues($folder)
        );
    }

    protected function update(string $folder, string $folderHash): int
    {
        return $this->getConnection()->update(
            $this->tableName,
            $this->getValues($folder),
            [
                'folder_hash' => $folderHash,
                'storage' => $this->storageUid,
            ]
        );
    }

    protected function computeParentFolder(string $folderPath): string
    {
        return dirname($folderPath) === '.'
            ? ''
            : dirname($folderPath);
    }

    protected function exists(string $folderHash): int
    {
        $query = $this->getQueryBuilder();
        $query
            ->count('*')
            ->from($this->tableName)
            ->where(
                $query->expr()->eq('storage', $this->storageUid),
                $query->expr()->eq(
                    'folder_hash',
                    $query->expr()->literal($folderHash)
                )
            );

        return (int)$query->execute()->fetchOne(0);
    }

    protected function getValues(string $folder): array
    {
        return [
            'folder' => $folder,
            'folder_hash' => sha1($folder),
            'parent_folder' => $this->computeParentFolder($folder),

            // typo3 info
            'missing' => 0,
            'storage' => $this->storageUid,
        ];
    }

    protected function getValue(string $key, array $cloudinaryResource): string
    {
        return isset($cloudinaryResource[$key])
            ? (string)$cloudinaryResource[$key]
            : '';
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
