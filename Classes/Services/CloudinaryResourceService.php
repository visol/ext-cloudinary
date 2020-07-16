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
     * @var string
     */
    protected $folderTableName = 'tx_cloudinary_folder';

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
     * @param array $values
     * @param array $identifier
     *
     * @return int
     */
    public function updateFolders(array $values, array $identifier = []): int
    {
        $identifier['storage'] = $this->storage->getUid();
        return $this->getFolderConnection()->update($this->folderTableName, $values, $identifier);
    }

    /**
     * @param array $values
     * @param array $identifier
     *
     * @return int
     */
    public function updateResources(array $values, array $identifier = []): int
    {
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

        return (array)$query->execute()->fetch();
    }

    /**
     * @param string $folderPath
     * @param array $orderings
     * @param array $pagination
     *
     * @return array
     */
    public function getResources(string $folderPath, array $orderings, array $pagination): array
    {
        $query = $this->getQueryBuilder();
        $query
            ->select('*')
            ->from($this->tableName)
            ->where(
                $query->expr()->eq('storage', $this->storage->getUid()),
                $query->expr()->eq(
                    'folder',
                    $query->expr()->literal($folderPath)
                )
            )
            ->orderBy($orderings['fieldName'], $orderings['direction']);

        if ($pagination && (int)$pagination['maxResult'] > 0) {
            $query->setMaxResults((int)$pagination['maxResult']);
            $query->setFirstResult((int)$pagination['firstResult']);
        }
        return $query->execute()->fetchAll();
    }

    /**
     * @param string $folderPath
     * @param array $orderings
     *
     * @return array
     */
    public function getSubFolders(string $folderPath, array $orderings): array
    {
        $query = $this->getQueryBuilder();
        $query
            ->select('*')
            ->from($this->folderTableName)
            ->where(
                $query->expr()->eq('storage', $this->storage->getUid()),
                $query->expr()->eq(
                    'parent_folder',
                    $query->expr()->literal($folderPath)
                )
            )
            ->orderBy($orderings['fieldName'], $orderings['direction']);

        return $query->execute()->fetchAll();
    }

    /**
     * @param string $folderPath
     *
     * @return int
     */
    public function countSubFolders(string $folderPath): int
    {
        $query = $this->getQueryBuilder();
        $query
            ->count('*')
            ->from($this->folderTableName)
            ->where(
                $query->expr()->eq('storage', $this->storage->getUid()),
                $query->expr()->eq(
                    'parent_folder',
                    $query->expr()->literal($folderPath)
                )
            );

        return (int)$query->execute()->fetchColumn(0);
    }

    /**
     * @param array $identifier
     *
     * @return int
     */
    public
    function deleteFolders(
        array $identifier = []
    ): int {
        $identifier['storage'] = $this->storage->getUid();
        return $this->getFolderConnection()->delete($this->folderTableName, $identifier);
    }

    /**
     * @param array $identifier
     *
     * @return int
     */
    public
    function deleteResources(
        array $identifier = []
    ): int {
        $identifier['storage'] = $this->storage->getUid();
        return $this->getConnection()->delete($this->tableName, $identifier);
    }

    /**
     * @param array $cloudinaryResource
     *
     * @return array
     */
    public
    function save(
        array $cloudinaryResource
    ): array {
        $publicId = $this->getValue('public_id', $cloudinaryResource);
        $publicIdHash = sha1($publicId);
        $fileIdentifier = $this->getValue('file_identifier', $cloudinaryResource);
        $fileIdentifierHash = sha1($fileIdentifier);

        $values = [
            'public_id' => $publicId,
            'public_id_hash' => $publicIdHash,
            'folder' => $cloudinaryResource['folder'],
            'filename' => $this->getValue('filename', $cloudinaryResource),
            'format' => $this->getValue('format', $cloudinaryResource),
            'version' => (int)$this->getValue('version', $cloudinaryResource),
            'resource_type' => $this->getValue('resource_type', $cloudinaryResource),
            'type' => $this->getValue('type', $cloudinaryResource),
            'created_at' => date(
                'Y-m-d h:i:s',
                strtotime(
                    $this->getValue('created_at', $cloudinaryResource)
                )
            ),
            'uploaded_at' => date(
                'Y-m-d h:i:s',
                strtotime(
                    $this->getValue('uploaded_at', $cloudinaryResource)
                )
            ),
            'bytes' => (int)$this->getValue('bytes', $cloudinaryResource),
            'backup_bytes' => (int)$this->getValue('backup_bytes', $cloudinaryResource),
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
            'missing' => 0,

            // typo3 info
            'file_identifier' => $fileIdentifier,
            'file_identifier_hash' => $fileIdentifierHash,
            'storage' => $this->storage->getUid(),
        ];

        return $this->exists($publicIdHash)
            ? ['updated' => $this->update($publicIdHash, $values)]
            : ['created' => $this->create($values)];
    }

    /**
     * @param array $values
     *
     * @return int
     */
    protected
    function create(
        array $values
    ): int {
        $connection = $this->getConnection();
        return $connection->insert(
            $this->tableName,
            $values
        );
    }

    /**
     * @param string $publicIdHash
     * @param array $values
     *
     * @return int
     */
    protected
    function update(
        string $publicIdHash, array $values
    ): int {
        $connection = $this->getConnection();
        return $connection->update(
            $this->tableName,
            $values,
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
    protected
    function exists(
        string $publicIdHash
    ): int {
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
     * @param string $folderPath
     *
     * @return array
     */
    public
    function saveFolder(
        string $folderPath
    ): array {
        $folderHash = sha1($folderPath);
        $values = [
            'folder' => $folderPath,
            'folder_hash' => sha1($folderPath),
            'missing' => 0,
            'parent_folder' => $this->computeParentFolder($folderPath),

            // typo3 info
            'storage' => $this->storage->getUid(),
        ];

        return $this->folderExists($folderHash)
            ? ['folder_updated' => $this->folderUpdate($folderHash, $values)]
            : ['folder_created' => $this->folderCreate($values)];
    }

    /**
     * @param $folderPath
     *
     * @return string
     */
    protected
    function computeParentFolder(
        $folderPath
    ): string {
        return dirname($folderPath) === '.'
            ? ''
            : dirname($folderPath);
    }

    /**
     * @param array $values
     *
     * @return int
     */
    protected
    function folderCreate(
        array $values
    ): int {
        $connection = $this->getConnection();
        return $connection->insert(
            $this->folderTableName,
            $values
        );
    }

    /**
     * @param string $folderHash
     * @param array $values
     *
     * @return int
     */
    protected
    function folderUpdate(
        string $folderHash, array $values
    ): int {
        $connection = $this->getConnection();
        return $connection->update(
            $this->folderTableName,
            $values,
            [
                'folder_hash' => $folderHash,
                'storage' => $this->storage->getUid(),
            ]
        );
    }

    /**
     * @param string $folderHash
     *
     * @return int
     */
    protected
    function folderExists(
        string $folderHash
    ): int {
        $query = $this->getQueryBuilder();
        $query
            ->count('*')
            ->from($this->folderTableName)
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
     * @param string $key
     * @param array $cloudinaryResource
     *
     * @return string
     */
    protected
    function getValue(
        string $key, array $cloudinaryResource
    ): string {
        return isset($cloudinaryResource[$key])
            ? (string)$cloudinaryResource[$key]
            : '';
    }

    /**
     * @return object|QueryBuilder
     */
    protected
    function getQueryBuilder(): QueryBuilder
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        return $connectionPool->getQueryBuilderForTable($this->tableName);
    }

    /**
     * @return object|Connection
     */
    protected
    function getConnection(): Connection
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        return $connectionPool->getConnectionForTable($this->tableName);
    }

    /**
     * @return object|Connection
     */
    protected
    function getFolderConnection(): Connection
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        return $connectionPool->getConnectionForTable($this->folderTableName);
    }
}
