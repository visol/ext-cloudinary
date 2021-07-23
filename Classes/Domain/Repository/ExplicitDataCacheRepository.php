<?php

namespace Visol\Cloudinary\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Utility\SortingUtility;

class ExplicitDataCacheRepository
{

    /**
     * @var $tableName
     */
    protected $tableName = 'tx_cloudinary_explicit_data_cache';

    /**
     * @param int $storageId
     * @param string $publicId
     * @param array $options
     * @return array
     */
    public function findByStorageAndPublicIdAndOptions(int $storageId, string $publicId, array $options): ?array
    {
        $query = $this->getQueryBuilder();
        $query->select('*')
            ->from($this->tableName)
            ->where(
                $this->getQueryBuilder()->expr()->eq(
                    'storage',
                    $query->expr()->literal(
                        $storageId
                    )
                ),
                $this->getQueryBuilder()->expr()->eq(
                    'public_id_hash',
                    $query->expr()->literal(
                        sha1($publicId)
                    )
                ),
                $this->getQueryBuilder()->expr()->eq(
                    'options_hash',
                    $query->expr()->literal(
                        $this->calculateHashFromOptions($options)
                    )
                )
            );
        $item = $query->execute()->fetch();

        if (!$item) {
            return null;
        }

        $item['options'] = json_decode($item['options'], true);
        $item['explicit_data'] = json_decode($item['explicit_data'], true);

        return $item;
    }

    /**
     * @param int $storageId
     * @param string $publicId
     * @param array $options
     * @param array $explicitData
     *
     * @return int
     */
    public function save(int $storageId, string $publicId, array $options, array $explicitData): int
    {
        SortingUtility::ksort_recursive($options);

        $connection = $this->getConnection();
        $connection->insert(
            $this->tableName,
            [
                'storage' => $storageId,
                'public_id' => $publicId,
                'public_id_hash' => sha1($publicId),
                'options' => json_encode($options),
                'options_hash' => $this->calculateHashFromOptions($options),
                'explicit_data' => json_encode($explicitData),
                'tstamp' => (int)$GLOBALS['EXEC_TIME'],
                'crdate' => (int)$GLOBALS['EXEC_TIME'],

            ]
        );
        return (int)$connection->lastInsertId();
    }

    /**
     *
     */
    public function delete(int $storageId, string $publicId): void
    {
        $connection = $this->getConnection();
        $connection->delete(
            $this->tableName,
            [
                'storage' => $storageId,
                'public_id_hash' => sha1($publicId),
            ]
        );
    }

    /**
     * @param array $options
     * @return string
     */
    protected function calculateHashFromOptions(array $options): string
    {
        return sha1(json_encode(SortingUtility::ksort_recursive($options)));
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
