<?php

namespace Visol\Cloudinary\Services;

use Cloudinary\Api\Upload\UploadApi;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Utility\CloudinaryApiUtility;

abstract class AbstractCloudinaryMediaService
{

    public function getExplicitData(File $file, array $options): array
    {
        $publicId = $this->getPublicIdForFile($file);

        $explicitData = $this->explicitDataCacheRepository->findByStorageAndPublicIdAndOptions($file->getStorage()->getUid(), $publicId, $options)['explicit_data'];

        if (!$explicitData) {
            $explicitData = $this->getUploadApi($file->getStorage())->explicit($publicId, $options);
            try {
                $this->explicitDataCacheRepository->save($file->getStorage()->getUid(), $publicId, $options, $explicitData);
            } catch (UniqueConstraintViolationException $e) {
                // ignore
            }
        }

        return $explicitData;
    }

    protected function error(string $message, array $arguments = [], array $data = []): void
    {
        /** @var Logger $logger */
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $logger->log(
            LogLevel::ERROR,
            vsprintf($message, $arguments),
            $data
        );
    }

    public function getEmergencyPlaceholderFile(): File
    {
        /** @var CloudinaryUploadService $cloudinaryUploadService */
        $cloudinaryUploadService = GeneralUtility::makeInstance(CloudinaryUploadService::class);
        return $cloudinaryUploadService->getEmergencyFile();
    }

    protected function getCloudinaryPathService(ResourceStorage $storage): CloudinaryPathService
    {
        return GeneralUtility::makeInstance(
            CloudinaryPathService::class,
            $storage
        );
    }

    public function getPublicIdForFile(File $file): string
    {

        // It should never happen but in case... we prefer to have an empty file instead of an exception
        if (!$file->exists()) {
            // We should log this incident...
            $this->error('I could not find file ' . $file->getIdentifier());

            // We want to avoid an exception
            $file = $this->getEmergencyPlaceholderFile();
        }

        // Compute the cloudinary public id
        return $this
            ->getCloudinaryPathService($file->getStorage())
            ->computeCloudinaryPublicId($file->getIdentifier());
    }

    protected function getUploadApi(ResourceStorage $storage): UploadApi
    {
        return CloudinaryApiUtility::getCloudinary($storage)->uploadApi();
    }

}
