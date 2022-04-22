<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Swisscom\Referenceable\Utility\TcaUtility;

defined('TYPO3') or die('Access denied.');

(static function (): void {
    // Add some fields to fe_users table to show TCA fields definitions
    ExtensionManagementUtility::addTCAcolumns('tt_content', [
        'tx_cloudinary_resources' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:cloudinary/Resources/Private/Language/backend.xlf:tt_content.tx_cloudinary_resources',
            'config' => [
                'type' => 'user',
                'renderType' => 'cloudinaryMediaLibraryField',
                'parameters' => [
                    'foo' => '',
                ],
            ],
        ],
    ]);

    ExtensionManagementUtility::addToAllTCAtypes('tt_content', 'tx_cloudinary_resources');

    //        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('tt_address', 'quote', '', 'after:description');

    //        $GLOBALS['TCA']['tx_news_domain_model_news']['palettes']['sitemap'] = [
    //            'label' => 'LLL:EXT:seo/Resources/Private/Language/locallang_tca.xlf:pages.palettes.sitemap',
    //            'showitem' => 'sitemap_changefreq,sitemap_priority'
    //        ];
    //
    //        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    //            'tx_news_domain_model_news',
    //            '--palette--;;sitemap',
    //            '',
    //            'after:alternative_title'
    //        );
})();
