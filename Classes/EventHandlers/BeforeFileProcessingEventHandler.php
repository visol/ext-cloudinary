<?php

namespace Visol\Cloudinary\EventHandlers;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Cloudinary\Transformation\BaseAction;
use Cloudinary\Transformation\Format;
use TYPO3\CMS\Core\Resource\Event\BeforeFileProcessingEvent;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Driver\CloudinaryDriver;
use Visol\Cloudinary\Exceptions\InvalidResourceUrlException;
use Visol\Cloudinary\Exceptions\NoCloudinaryTransformation;
use Visol\Cloudinary\Services\CloudinaryImageService;
use Visol\Cloudinary\Services\ProcessingTaskConverter;

final class BeforeFileProcessingEventHandler
{
    public function __construct(
        protected ProcessingTaskConverter $processingInstructionConverter,
    ) { }

    public const ALLOWED_TASKS = [
        ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
        ProcessedFile::CONTEXT_IMAGEPREVIEW,
    ];

    public function __invoke(BeforeFileProcessingEvent $event): void
    {
        $driver = $event->getDriver();
        if (!$driver instanceof CloudinaryDriver) {
            return;
        }

        $processedFile = $event->getProcessedFile();
        if(! in_array($processedFile->getTaskIdentifier(), self::ALLOWED_TASKS)) {
            return;
        }
        if ($processedFile->isProcessed()) {
            return;
        }
        if (str_starts_with($processedFile->getIdentifier(), 'PROCESSEDFILE')) {
            return;
        }

        /** @var File $file */
        $file = $event->getFile();

        try {
            $transformations = $this->processingInstructionConverter->convertProcessingConfiguration($event->getProcessedFile()->getTask());
        } catch (NoCloudinaryTransformation $e) {
            // Cloudinary is unable to do the required processing
            return;
        }

        if ($file->getType() === $file::FILETYPE_VIDEO) {
            $firstTransformation = $transformations[0];
            if ($firstTransformation instanceof BaseAction) {
                $firstTransformation->addQualifiers(new Format('png'));
            }
        }

        $transformations[] = [
            'fetch_format' => 'auto',
            'quality' => 'auto:eco',
        ];

        $explicitData = $this->getCloudinaryImageService()->getExplicitData($file, [
            'type' => 'upload',
            'eager' => [$transformations],
        ]);

        $url = $explicitData['eager'][0]['secure_url'] ?? null;
        if (!isset($url)) {
            // cloudinary is unable to render
            return;
        }

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
}
