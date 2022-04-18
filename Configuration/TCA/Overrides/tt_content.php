<?php

use Swisscom\Referenceable\Utility\TcaUtility;

defined('TYPO3') or die('Access denied.');

(static function (): void {
    // Add some fields to fe_users table to show TCA fields definitions
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('tt_content', [
        'tx_examples_special' => [
            'exclude' => 0,
            'label' => 'asdf', // LLL:EXT:examples/Resources/Private/Language/locallang_db.xlf:tt_content.tx_examples_special
            'config' => [
                'type' => 'user',
                'renderType' => 'cloudinaryMediaLibraryField',
                'parameters' => [
                    'foo' => '',
                ],
            ],
        ],
    ]);
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('tt_content', 'tx_examples_special');

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
