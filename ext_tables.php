<?php

if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

if (TYPO3_MODE === 'BE') {

    /** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager */
    $objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class);

    /** @var $signalSlotDispatcher \TYPO3\CMS\Extbase\SignalSlot\Dispatcher */
    $signalSlotDispatcher = $objectManager->get(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);

    // Connect some signals with slots.
    $signalSlotDispatcher->connect(
        \TYPO3\CMS\Core\Resource\ResourceStorage::class,
        \TYPO3\CMS\Core\Resource\Service\FileProcessingService::SIGNAL_PreFileProcess,
        \Visol\Cloudinary\Slots\FileProcessingSlot::class,
        \TYPO3\CMS\Core\Resource\Service\FileProcessingService::SIGNAL_PreFileProcess
    );

}
