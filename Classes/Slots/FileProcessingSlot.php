<?php

namespace Visol\Cloudinary\Slots;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */
use TYPO3\CMS\Core\Resource\Service\FileProcessingService;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Driver\CloudinaryDriver;
use Visol\Cloudinary\Services\CloudinaryImageService;

class FileProcessingSlot
{

    // We want to remove all processed files
    public function preFileProcess(FileProcessingService $fileProcessingService, DriverInterface $driver, ProcessedFile $processedFile, File $file, $taskType, array $configuration)
    {
        if (!$driver instanceof CloudinaryDriver) {
            return;
        }

        if ($processedFile->isProcessed()) {
            return;
        }

        if (strpos($processedFile->getIdentifier() ?? '', 'PROCESSEDFILE' ) === 0) {
            return;
        }

        $options = [
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
        ];

        $explicitData = $this->getCloudinaryImageService()->getExplicitData($file, $options);
        $url = $explicitData['eager'][0]['secure_url'];

        $parts = parse_url($url);
        $processedFile->setName(basename($url));
        $processedFile->setIdentifier('PROCESSEDFILE' . $parts['path']);

        $processedFile->updateProperties([
            'width' => $explicitData['eager'][0]['width'],
            'height' => $explicitData['eager'][0]['height'],
        ]);

        /** @var $processedFileRepository ProcessedFileRepository */
        $processedFileRepository = GeneralUtility::makeInstance(ProcessedFileRepository::class);
        $processedFileRepository->add($processedFile);
    }

    /**
     * @return object|CloudinaryImageService
     */
    public function getCloudinaryImageService()
    {
        return GeneralUtility::makeInstance(CloudinaryImageService::class);
    }

}
