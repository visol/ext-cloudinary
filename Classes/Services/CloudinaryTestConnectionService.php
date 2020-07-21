<?php

namespace Visol\Cloudinary\Services;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use Visol\Cloudinary\Driver\CloudinaryDriver;

/**
 * Class CloudinaryTestConnectionService
 */
class CloudinaryTestConnectionService
{

    /**
     * @var string
     */
    protected $languageFile = 'LLL:EXT:cloudinary/Resources/Private/Language/backend.xlf';

    /**
     * @var array
     */
    protected $configuration;

    /**
     * CloudinaryScanService constructor.
     *
     * @param array $configuration
     */
    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Test the connection
     */
    public function test()
    {
        $messageQueue = $this->getMessageQueue();
        $localizationPrefix = $this->languageFile . ':driverConfiguration.message.';
        try {
            $this->initializeApi();

            $search = new \Cloudinary\Search();
            $search
                ->expression('folder=/')
                ->execute();

            /** @var \TYPO3\CMS\Core\Messaging\FlashMessage $message */
            $message = GeneralUtility::makeInstance(
                FlashMessage::class,
                LocalizationUtility::translate($localizationPrefix . 'connectionTestSuccessful.message'),
                LocalizationUtility::translate($localizationPrefix . 'connectionTestSuccessful.title'),
                FlashMessage::OK
            );
            $messageQueue->addMessage($message);
        } catch (\Exception $exception) {
            /** @var \TYPO3\CMS\Core\Messaging\FlashMessage $message */
            $message = GeneralUtility::makeInstance(
                FlashMessage::class,
                $exception->getMessage(),
                LocalizationUtility::translate($localizationPrefix . 'connectionTestFailed.title'),
                FlashMessage::WARNING
            );
            $messageQueue->addMessage($message);
        }
    }

    /**
     * @return \TYPO3\CMS\Core\Messaging\FlashMessageQueue
     */
    protected function getMessageQueue()
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        /** @var FlashMessageService $flashMessageService */
        $flashMessageService = $objectManager->get(FlashMessageService::class);
        return $flashMessageService->getMessageQueueByIdentifier();
    }

    /**
     * @return void
     */
    protected function initializeApi()
    {
        \Cloudinary::config(
            [
                'cloud_name' => $this->configuration['cloudName'],
                'api_key' => $this->configuration['apiKey'],
                'api_secret' => $this->configuration['apiSecret'],
                'timeout' => $this->configuration['timeout'],
                'secure' => true
            ]
        );
    }

}
