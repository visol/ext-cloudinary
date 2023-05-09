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
use Visol\Cloudinary\Services\CloudinaryImageService;

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

        $parts = parse_url($url);
        $path = $parts['path'] ?? '';
        $processedFile->setName(basename($url));
        $processedFile->setIdentifier('PROCESSEDFILE' . $path);

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
}
