<?php

namespace Visol\Cloudinary\Utility;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Driver\CloudinaryDriver;
use Visol\Cloudinary\Services\ConfigurationService;

/**
 * Class CloudinaryApiService
 */
class CloudinaryApiUtility
{

    public static function getCloudinary(ResourceStorage|array $storage): Cloudinary
    {
        return new Cloudinary(self::getConfiguration($storage));
    }

    public static function getConfiguration(ResourceStorage|array $storage): Configuration
    {
        if ($storage->getDriverType() !== CloudinaryDriver::DRIVER_TYPE) {
            // Check the file is stored on the right storage
            // If not we should trigger an exception
            $message = sprintf(
                'Wrong storage! Can not initialize with storage type "%s".',
                $storage->getDriverType()
            );
            throw new \Exception($message, 1590401459);
        }
        $storageConfiguration = $storage->getConfiguration();

        /** @var ConfigurationService $configurationService */
        $configurationService = GeneralUtility::makeInstance(
            ConfigurationService::class,
            $storageConfiguration
        );

        return Configuration::instance([
                'cloud_name' => $configurationService->get('cloudName'),
                'api_key' => $configurationService->get('apiKey'),
                'api_secret' => $configurationService->get('apiSecret'),
                'timeout' => $configurationService->get('timeout'),
                'secure' => true
            ]
        );

    }
}
