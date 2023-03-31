<?php

namespace Visol\Cloudinary\Services\Extractor;

use Cloudinary\Api\Admin\AdminApi;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Index\ExtractorInterface;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Driver\CloudinaryDriver;
use Visol\Cloudinary\Services\CloudinaryPathService;
use Visol\Cloudinary\Services\CloudinaryResourceService;
use Visol\Cloudinary\Utility\CloudinaryApiUtility;

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
        return [CloudinaryDriver::DRIVER_TYPE];
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
            $file->getStorage(),
        );
        $publicId = $cloudinaryPathService->computeCloudinaryPublicId($file->getIdentifier());
        $resource = $cloudinaryResourceService->getResource($publicId);

        // We are force calling cloudinary API
        if (!$resource) {

            // Ask cloudinary to fetch the resource for us
            $resource = $this->getAdminApi($file->getStorage())
                ->asset($publicId);
        }

        return [
            'width' => $resource['width'] ?? 0,
            'height' => $resource['height'] ?? 0,
        ];
    }

    protected function getAdminApi(ResourceStorage $storage): AdminApi
    {
        return CloudinaryApiUtility::getCloudinary($storage)->adminApi();
    }

}
