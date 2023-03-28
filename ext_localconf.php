<?php

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Visol\Cloudinary\Backend\Form\Container\InlineCloudinaryControlContainer;
use TYPO3\CMS\Core\Resource\Driver\DriverRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Controller\CloudinaryWebHookController;
use Visol\Cloudinary\Driver\CloudinaryDriver;
use TYPO3\CMS\Core\Log\Writer\FileWriter;
use Visol\Cloudinary\Hook\FileUploadHook;

defined('TYPO3') || die('Access denied.');
call_user_func(callback: function () {

    // Override default class to add cloudinary button
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1652423292] = [
        'nodeName' => 'inline',
        'priority' => 50,
        'class' => InlineCloudinaryControlContainer::class,
    ];

    ExtensionUtility::configurePlugin(
        \Cloudinary::class,
        'WebHook',
        [
            CloudinaryWebHookController::class => 'process',
        ],
        // non-cacheable actions
        [
            CloudinaryWebHookController::class => 'process',
        ],
    );

    /** @var DriverRegistry $driverRegistry */
    $driverRegistry = GeneralUtility::makeInstance(DriverRegistry::class);
    $driverRegistry->registerDriverClass(
        CloudinaryDriver::class,
        CloudinaryDriver::DRIVER_TYPE,
        \Cloudinary::class,
        'FILE:EXT:cloudinary/Configuration/FlexForm/CloudinaryFlexForm.xml',
    );

    /* @var \TYPO3\CMS\Core\Resource\Index\ExtractorRegistry $metaDataExtractorRegistry */
    $metaDataExtractorRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\Index\ExtractorRegistry::class);
    $metaDataExtractorRegistry->registerExtractionService(\Visol\Cloudinary\Services\Extractor\CloudinaryMetaDataExtractor::class);

    // Log configuration for cloudinary web hook
    $GLOBALS['TYPO3_CONF_VARS']['LOG']['Visol']['Cloudinary']['Controller']['CloudinaryWebHookController']['writerConfiguration'] = [
        LogLevel::DEBUG => [
            FileWriter::class => [
                'logFile' => Environment::getVarPath() . '/log/cloudinary-web-hook.log'
            ],
        ],

        // Configuration for WARNING severity, including all
        // levels with higher severity (ERROR, CRITICAL, EMERGENCY)
        LogLevel::WARNING => [
            \TYPO3\CMS\Core\Log\Writer\SyslogWriter::class => [],
        ],
    ];

    // Log configuration for cloudinary driver
    $GLOBALS['TYPO3_CONF_VARS']['LOG']['Visol']['Cloudinary']['Service']['writerConfiguration']
        = $GLOBALS['TYPO3_CONF_VARS']['LOG']['Visol']['Cloudinary']['Cache']['writerConfiguration']
        = $GLOBALS['TYPO3_CONF_VARS']['LOG']['Visol']['Cloudinary']['Driver']['writerConfiguration']
        = [
        // configuration for WARNING severity, including all
        // levels with higher severity (ERROR, CRITICAL, EMERGENCY)
        LogLevel::INFO => [
            FileWriter::class => [
                // configuration for the writer
                'logFile' => Environment::getVarPath() . '/log/cloudinary.log',
            ],
        ],
    ];

    // Hook for traditional file upload, replace
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_extfilefunc.php']['processData'][] = FileUploadHook::class;

});
