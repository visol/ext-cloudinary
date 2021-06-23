<?php

defined('TYPO3_MODE') || die('Access denied.');
call_user_func(
    function () {

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript(
            'cloudinary',
            'setup',
            '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:cloudinary/Configuration/TypoScript/setup.typoscript">'
        );

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
            'Visol.Cloudinary',
            'Cache',
            [
                'CloudinaryScan' => 'scan',
            ],
            // non-cacheable actions
            [
                'CloudinaryScan' => 'scan',
            ]
        );

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['registeredDrivers'][\Visol\Cloudinary\Driver\CloudinaryFastDriver::DRIVER_TYPE] = [
            'class' => \Visol\Cloudinary\Driver\CloudinaryFastDriver::class,

            'flexFormDS' => 'FILE:EXT:cloudinary/Configuration/FlexForm/CloudinaryFlexForm.xml',
            'label' => 'Cloudinary',
            'shortName' => \Visol\Cloudinary\Driver\CloudinaryFastDriver::DRIVER_TYPE,
        ];

        $GLOBALS['TYPO3_CONF_VARS']['LOG']['Visol']['Cloudinary']['Service']['writerConfiguration'] =
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['Visol']['Cloudinary']['Cache']['writerConfiguration'] =
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['Visol']['Cloudinary']['Driver']['writerConfiguration'] = [

            // configuration for WARNING severity, including all
            // levels with higher severity (ERROR, CRITICAL, EMERGENCY)
            \TYPO3\CMS\Core\Log\LogLevel::INFO => [
                \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
                    // configuration for the writer
                    'logFile' => 'typo3temp/var/logs/cloudinary.log'
                ],
            ],
        ];

        if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['cloudinary'])) {
            // cache configuration, see https://docs.typo3.org/typo3cms/CoreApiReference/ApiOverview/CachingFramework/Configuration/Index.html#cache-configurations
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['cloudinary']['frontend'] = \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class;
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['cloudinary']['groups'] = ['all', 'cloudinary'];
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['cloudinary']['options']['defaultLifetime'] = 2592000;
        }
    }
);
