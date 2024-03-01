<?php

namespace Visol\Cloudinary\EventHandlers;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Resource\Event\BeforeFileProcessingEvent;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Driver\CloudinaryDriver;
use Visol\Cloudinary\Exceptions\InvalidResourceUrlException;
use Visol\Cloudinary\Services\CloudinaryImageService;
use Visol\Cloudinary\Services\ConfigurationService;

final class BeforeFileProcessingEventHandler
{
    public function __invoke(BeforeFileProcessingEvent $event): void
    {
        $driver = $event->getDriver();
        $processedFile = $event->getProcessedFile();
        /** @var File $file */
        $file = $event->getFile();

        if (!$driver instanceof CloudinaryDriver) {
            return;
        }

        if ($processedFile->isProcessed()) {
            return;
        }

        if (str_starts_with($processedFile->getIdentifier(), 'PROCESSEDFILE')) {
            return;
        }

        $explicitData = $this->getCloudinaryImageService()->getExplicitData(
            $file,
            [
                'type' => 'upload',
                'eager' => [
                    [
                        //'format' => 'jpg', // `Invalid transformation component - auto`
                        'fetch_format' => 'auto',
                        'quality' => 'auto:eco',
                        'width' => 64,
                        'height' => 64,
                        'crop' => 'fit',
                    ]
                ]
            ]
        );
        $url = $explicitData['eager'][0]['secure_url'];
        $publicBaseUrl = $driver->getPublicBaseUrl();

        if (! str_starts_with($url, $publicBaseUrl)) {
            throw new InvalidResourceUrlException($url, $publicBaseUrl, 1709284880259);
        }

        $identifier = CloudinaryDriver::PROCESSEDFILE_IDENTIFIER_PREFIX . substr($url, strlen($publicBaseUrl));
        $processedFile->setName(basename($url));
        $processedFile->setIdentifier($identifier);

        $processedFile->updateProperties([
            'width' => $explicitData['eager'][0]['width'],
            'height' => $explicitData['eager'][0]['height'],
        ]);

        $processedFileRepository = GeneralUtility::makeInstance(ProcessedFileRepository::class);
        $processedFileRepository->add($processedFile);
    }

    public function getCloudinaryImageService(): CloudinaryImageService
    {
        return GeneralUtility::makeInstance(CloudinaryImageService::class);
    }

    protected function getCloudNameForFile(File $file): string
    {
        $configurationService = GeneralUtility::makeInstance(ConfigurationService::class, $file->getStorage()->getConfiguration());
        return $configurationService->get('cloudName');
    }
}
