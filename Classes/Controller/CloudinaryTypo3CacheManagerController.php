<?php

namespace Visol\Cloudinary\Controller;

/*
 * This file is part of the Fab/Mailing project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Visol\Cloudinary\Cache\CloudinaryTypo3Cache;
use Visol\Cloudinary\Driver\CloudinaryDriver;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Class CloudinaryTypo3CacheManagerController
 */
class CloudinaryTypo3CacheManagerController extends ActionController
{

    /**
     * @var \TYPO3\CMS\Core\Resource\StorageRepository
     * @inject
     */
    protected $storageRepository;

    /**
     * @return int
     */
    public function flushAction(): string
    {
        /** @var ResourceStorage $storage */

        foreach ($this->storageRepository->findAll() as $storage) {
            if ($storage->getDriverType() === CloudinaryDriver::DRIVER_TYPE) {
                $this->getCache($storage->getUid())->flushAll();
            }
        }
        return 'success';
    }

    /**
     * @param int $storageUid
     * @return CloudinaryTypo3Cache|object
     */
    protected function getCache(int $storageUid)
    {
        return GeneralUtility::makeInstance(CloudinaryTypo3Cache::class, $storageUid);
    }
}
