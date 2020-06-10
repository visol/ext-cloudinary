<?php

namespace Visol\Cloudinary\Cache;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class CloudinaryTypo3Cache
 */
class CloudinaryTypo3Cache
{

    const TAG_FOLDER = 'folder';

    const TAG_FILE = 'file';

    const LIFETIME = 3600;

    /**
     * Define whether the cache is enabled or not.
     * This can be configured in the Extension Manager.
     *
     * @var bool
     */
    private $isCacheEnabled = true;

    /**
     * @var int
     */
    protected $storageUid = 0;

    /**
     * @param int $storageUid
     */
    public function __construct(int $storageUid)
    {
        $this->storageUid = $storageUid;

        // TODO: change me after typo3 v9 migration
        //       GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('cloudinary')
        $extensionConfiguration = (array)unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['cloudinary']);
        if (isset($extensionConfiguration['is_cache_enabled'])) {
            $this->isCacheEnabled = (bool)$extensionConfiguration['is_cache_enabled'];
        }
    }

    /**
     * @param string $folderIdentifier
     * @return array|false
     */
    public function getCachedFiles(string $folderIdentifier)
    {
        return $this->get(
            $this->computeFileCacheKey($folderIdentifier)
        );
    }

    /**
     * @param string $folderIdentifier
     * @param array $files
     */
    public function setCachedFiles(string $folderIdentifier, array $files): void
    {
        $this->set(
            $this->computeFileCacheKey($folderIdentifier),
            $files,
            self::TAG_FILE
        );
    }

    /**
     * @param string $folderIdentifier
     * @return array|false
     */
    public function getCachedFolders(string $folderIdentifier)
    {
        return $this->get(
            $this->computeFolderCacheKey($folderIdentifier)
        );
    }

    /**
     * @param string $folderIdentifier
     * @param array $folders
     */
    public function setCachedFolders(string $folderIdentifier, array $folders): void
    {
        $this->set(
            $this->computeFolderCacheKey($folderIdentifier),
            $folders,
            self::TAG_FOLDER
        );
    }

    /**
     * @param string $identifier
     * @return array|false
     */
    protected function get(string $identifier)
    {
        return $this->isCacheEnabled
            ? $this->getCacheInstance()->get($identifier)
            : false;
    }

    /**
     * @param string $identifier
     * @param array $data
     * @param string $tag
     */
    protected function set(string $identifier, array $data, $tag): void
    {
        if ($this->isCacheEnabled) {
            $this->getCacheInstance()->set(
                $identifier,
                $data,
                [
                    $tag
                ],
                self::LIFETIME
            );

            $this->log('Caching "%s" data with folder identifier "%s"', [$tag, $identifier]);
        }
    }

    /**
     * @param string $folderIdentifier
     * @return mixed
     */
    protected function computeFolderCacheKey($folderIdentifier): string
    {
        // Sanitize the cache format as the key can not contains certain characters such as "/", ":", etc..
        return sprintf(
            'storage-%s-folders-%s',
            $this->storageUid,
            str_replace('/', '%', $folderIdentifier)
        );
    }

    /**
     * @param string $folderIdentifier
     * @return mixed
     */
    protected function computeFileCacheKey($folderIdentifier): string
    {
        // Sanitize the cache format as the key can not contains certain characters such as "/", ":", etc..
        return sprintf(
            'storage-%s-files-%s',
            $this->storageUid,
            str_replace('/', '%', $folderIdentifier)
        );
    }

    /**
     * @return void
     */
    public function flushFileCache(): void
    {
        $this->getCacheInstance()->flushByTags([self::TAG_FILE]);
        $this->log('Method "flushFileCache": file cache flushed');
    }

    /**
     * @return void
     */
    public function flushFolderCache(): void
    {
        $this->getCacheInstance()->flushByTags([self::TAG_FOLDER]);
        $this->log('Method "flushFolderCache": folder cache flushed');
    }

    /**
     * @return void
     */
    public function flushAll(): void
    {
        $this->getCacheInstance()->flush();
        $this->log('Method "flushAll": all cache flushed');
    }

    /**
     * @return \TYPO3\CMS\Core\Cache\Frontend\AbstractFrontend
     */
    protected function getCacheInstance()
    {
        return $this->getCacheManager()->getCache('cloudinary');
    }

    /**
     * Return the Cache Manager
     *
     * @return \TYPO3\CMS\Core\Cache\CacheManager|object
     */
    protected function getCacheManager()
    {
        return GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class);
    }


    /**
     * @param string $message
     * @param array $arguments
     */
    public function log(string $message, array $arguments = [])
    {
        /** @var \TYPO3\CMS\Core\Log\Logger $logger */
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        #$logger->log(
        #    LogLevel::INFO,
        #    vsprintf('[CACHE] ' . $message, $arguments)
        #);
    }
}
