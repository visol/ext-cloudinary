<?php

namespace Visol\Cloudinary\Utility;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */
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

}
