<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die('Access denied.');

(static function (): void {
    // Add some fields to fe_users table to show TCA fields definitions
    //ExtensionManagementUtility::addTCAcolumns('tt_content', [
    //    'tx_cloudinary_resources' => [
    //        'exclude' => 0,
    //        'label' => 'LLL:EXT:cloudinary/Resources/Private/Language/backend.xlf:tt_content.tx_cloudinary_resources',
    //        'config' => [
    //            'type' => 'user',
    //            'renderType' => 'cloudinaryMediaLibraryField',
    //            'parameters' => [
    //                'foo' => '',
    //            ],
    //        ],
    //    ],
    //]);

    //ExtensionManagementUtility::addToAllTCAtypes('tt_content', 'tx_cloudinary_resources', '', 'after:header');
})();
