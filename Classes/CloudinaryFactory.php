<?php

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

namespace Visol\Cloudinary;

use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class CloudinaryFactory
 */
class CloudinaryFactory extends \Exception
{

    /**
     * @return ResourceStorage
     */
    public static function getDefaultStorage(): ResourceStorage
    {
        // TODO: change me after typo3 v9 migration
        //       GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('default_cloudinary_storage')
        $extensionConfiguration = (array)unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['cloudinary']);
        $defaultCloudinaryStorageUid = (int)$extensionConfiguration['default_cloudinary_storage'];
        return ResourceFactory::getInstance()->getStorageObject($defaultCloudinaryStorageUid);
    }

    /**
     * @param string $folderIdentifier
     * @param ResourceStorage|null $storage
     *
     * @return object|Folder
     */
    public static function getFolder($folderIdentifier, ResourceStorage $storage = null): Folder
    {
        $folderIdentifier = $folderIdentifier === DIRECTORY_SEPARATOR
            ? $folderIdentifier
            : DIRECTORY_SEPARATOR . trim($folderIdentifier, '/') . DIRECTORY_SEPARATOR;

        return GeneralUtility::makeInstance(
            Folder::class,
            $storage
                ? $storage
                : self::getDefaultStorage(),
            $folderIdentifier,
            $folderIdentifier === DIRECTORY_SEPARATOR
                ? ''
                : $folderIdentifier
        );
    }
}
