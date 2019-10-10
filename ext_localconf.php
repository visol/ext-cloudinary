<?php

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

$GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['registeredDrivers'][\Sinso\Cloudinary\Driver\CloudinaryDriver::DRIVER_TYPE] = [
    'class' => \Sinso\Cloudinary\Driver\CloudinaryDriver::class,

    'flexFormDS' => 'FILE:EXT:cloudinary/Configuration/FlexForm/CloudinaryFlexForm.xml',
    'label' => 'Cloudinary',
    'shortName' => \Sinso\Cloudinary\Driver\CloudinaryDriver::DRIVER_TYPE,
];

$GLOBALS['TYPO3_CONF_VARS']['LOG']['Sinso']['Cloudinary']['Driver']['writerConfiguration'] = [
    // configuration for WARNING severity, including all
    // levels with higher severity (ERROR, CRITICAL, EMERGENCY)
    \TYPO3\CMS\Core\Log\LogLevel::INFO => [
        \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
            // configuration for the writer
            'logFile' => 'typo3temp/var/logs/cloudinary.log'
        ],
    ],
];
