<?php

namespace Visol\Cloudinary\Services\Extractor;

use Cloudinary;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Resource;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Index\ExtractorInterface;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Type\File\ImageInfo;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Driver\CloudinaryFastDriver;
use Visol\Cloudinary\Services\CloudinaryPathService;
use Visol\Cloudinary\Services\CloudinaryResourceService;
use Visol\Cloudinary\Services\ConfigurationService;

class CloudinaryMetaDataExtractor implements ExtractorInterface
{
    protected int $priority = 77;

    /**
     * Returns an array of supported file types
     *
     * @return array
     */
    public function getFileTypeRestrictions(): array
    {
        return [];
    }

    /**
     * Get all supported DriverClasses
     *
     * @return string[] names of supported drivers/driver classes
     */
    public function getDriverRestrictions(): array
    {
        return [CloudinaryFastDriver::DRIVER_TYPE];
    }

    /**
     * Returns the data priority of the extraction Service.
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Returns the execution priority of the extraction Service
     */
    public function getExecutionPriority(): int
    {
        return $this->priority;
    }

    public function canProcess(File $file): bool
    {
        return true;
    }

    public function extractMetaData(File $file, array $previousExtractedData = []): array
    {
        $cloudinaryResourceService = GeneralUtility::makeInstance(
            CloudinaryResourceService::class,
            $file->getStorage(),
        );

        $cloudinaryPathService = GeneralUtility::makeInstance(
            CloudinaryPathService::class,
            $file->getStorage()->getConfiguration(),
        );
        $publicId = $cloudinaryPathService->computeCloudinaryPublicId($file->getIdentifier());
        $resource = $cloudinaryResourceService->getResource($publicId);

        // We are force calling cloudinary API
        if (!$resource) {
            // Ask cloudinary to fetch the resource for us
            $this->initializeApi($file->getStorage());

            $api = new Cloudinary\Api();
            $resource = $api->resource($publicId);
        }

        return [
            'width' => $resource['width'] ?? 0,
            'height' => $resource['height'] ?? 0,
        ];
    }

    protected function initializeApi(ResourceStorage $storage): void
    {
        $configurationService = GeneralUtility::makeInstance(ConfigurationService::class, $storage->getConfiguration());

        Cloudinary::config([
            'cloud_name' => $configurationService->get('cloudName'),
            'api_key' => $configurationService->get('apiKey'),
            'api_secret' => $configurationService->get('apiSecret'),
            'timeout' => $configurationService->get('timeout'),
            'secure' => true,
        ]);
    }
}
