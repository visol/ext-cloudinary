<?php

namespace Visol\Cloudinary\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class CloudinaryProcessedResourceRepository
 */
class CloudinaryProcessedResourceRepository
{

    /**
     * @var $tableName
     */
    protected $tableName = 'tx_cloudinary_processedresources';

    /**
     * @param string $publicId
     * @param array $options
     * @return array
     */
    public function findByPublicIdAndOptions(string $publicId, array $options): array
    {
        $query = $this->getQueryBuilder();
        $query->select('*')
            ->from($this->tableName)
            ->where(
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
        return is_array($item)
            ? $item
            : [];
    }

    /**
     * @param string $publicId
     * @param array $options
     * @param string $breakpoints
     * @return int
     */
    public function save(string $publicId, array $options, string $breakpoints): int
    {
        $connection = $this->getConnection();
        $connection->insert(
            $this->tableName,
            [
                'public_id' => $publicId,
                'public_id_hash' => sha1($publicId),
                'options_hash' => $this->calculateHashFromOptions($options),
                'breakpoints' => $breakpoints,
            ]
        );
        return (int)$connection->lastInsertId();
    }

    /**
     * @param array $options
     * @return string
     */
    protected function calculateHashFromOptions($options): string
    {
        return sha1(json_encode($options));
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
