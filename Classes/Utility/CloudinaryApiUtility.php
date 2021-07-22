<?php

namespace Visol\Cloudinary\Utility;

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
use Visol\Cloudinary\Services\ConfigurationService;

/**
 * Class CloudinaryApiService
 */
class CloudinaryApiUtility
{

    public static function initializeByConfiguration(array $configuration)
    {
        /** @var ConfigurationService $configurationService */
        $configurationService = GeneralUtility::makeInstance(
            ConfigurationService::class,
            $configuration
        );

        \Cloudinary::config(
            [
                'cloud_name' => $configurationService->get('cloudName'),
                'api_key' => $configurationService->get('apiKey'),
                'api_secret' => $configurationService->get('apiSecret'),
                'timeout' => $configurationService->get('timeout'),
                'secure' => true
            ]
        );
    }

    public static function getApiByConfiguration(array $configuration) {
        self::initializeByConfiguration($configuration);

        // The object \Cloudinary\Api behaves like a singleton object.
        // The problem: if we have multiple driver instances / configuration, we don't get the expected result
        // meaning we are wrongly fetching resources from other cloudinary "buckets" because of the singleton behaviour
        // Therefore it is better to create a new instance upon each API call to avoid driver confusion
        return new \Cloudinary\Api();
    }

}
