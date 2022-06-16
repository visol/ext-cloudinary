<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Visol\Cloudinary\Backend\Form\Container\InlineCloudinaryControlContainer;
use Visol\Cloudinary\Controller\CloudinaryScanController;
use TYPO3\CMS\Core\Resource\Driver\DriverRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Driver\CloudinaryFastDriver;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\Writer\FileWriter;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use Visol\Cloudinary\Hook\FileUploadHook;

defined('TYPO3') || die('Access denied.');
call_user_func(function () {
    ExtensionManagementUtility::addTypoScript(
        'cloudinary',
        'setup',
        '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:cloudinary/Configuration/TypoScript/setup.typoscript">',
    );

    // Override default class to add cloudinary button
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1652423292] = [
        'nodeName' => 'inline',
        'priority' => 50,
        'class' => InlineCloudinaryControlContainer::class,
    ];

    ExtensionUtility::configurePlugin(
        \Cloudinary::class,
        'Cache',
        [
            CloudinaryScanController::class => 'scan',
        ],
        // non-cacheable actions
        [
            CloudinaryScanController::class => 'scan',
        ],
    );

    /** @var DriverRegistry $driverRegistry */
    $driverRegistry = GeneralUtility::makeInstance(DriverRegistry::class);
    $driverRegistry->registerDriverClass(
        CloudinaryFastDriver::class,
        CloudinaryFastDriver::DRIVER_TYPE,
        \Cloudinary::class,
        'FILE:EXT:cloudinary/Configuration/FlexForm/CloudinaryFlexForm.xml',
    );

    /* @var \TYPO3\CMS\Core\Resource\Index\ExtractorRegistry $metaDataExtractorRegistry */
    $metaDataExtractorRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\Index\ExtractorRegistry::class);
    $metaDataExtractorRegistry->registerExtractionService(\Visol\Cloudinary\Services\Extractor\CloudinaryMetaDataExtractor::class);

    $GLOBALS['TYPO3_CONF_VARS']['LOG']['Visol'][\Cloudinary::class]['Service']['writerConfiguration']
        = $GLOBALS['TYPO3_CONF_VARS']['LOG']['Visol'][\Cloudinary::class]['Cache']['writerConfiguration']
        = $GLOBALS['TYPO3_CONF_VARS']['LOG']['Visol'][\Cloudinary::class]['Driver']['writerConfiguration']
        = [
        // configuration for WARNING severity, including all
        // levels with higher severity (ERROR, CRITICAL, EMERGENCY)
        LogLevel::INFO => [
            FileWriter::class => [
                // configuration for the writer
                'logFile' => 'typo3temp/var/logs/cloudinary.log',
            ],
        ],
    ];

    if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['cloudinary'])) {
        // cache configuration, see https://docs.typo3.org/typo3cms/CoreApiReference/ApiOverview/CachingFramework/Configuration/Index.html#cache-configurations
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['cloudinary']['frontend'] = VariableFrontend::class;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['cloudinary']['groups'] = ['all', 'cloudinary'];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['cloudinary']['options']['defaultLifetime'] = 2592000;
    }

    // Hook for traditional file upload, replace
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_extfilefunc.php']['processData'][] = FileUploadHook::class;

});
