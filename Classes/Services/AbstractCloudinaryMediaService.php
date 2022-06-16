<?php

namespace Visol\Cloudinary\Services;

use Cloudinary\Uploader;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Driver\CloudinaryDriver;
use Visol\Cloudinary\Utility\CloudinaryApiUtility;

abstract class AbstractCloudinaryMediaService
{
    /**
     * @throws \Exception
     */
    protected function initializeApi(ResourceStorage $storage): void
    {
        // Check the file is stored on the right storage
        // If not we should trigger an exception
        if ($storage->getDriverType() !== CloudinaryDriver::DRIVER_TYPE) {
            $message = sprintf(
                'Wrong storage! Can not initialize with storage type "%s".',
                $storage->getDriverType()
            );
            throw new \Exception($message, 1590401459);
        }

        CloudinaryApiUtility::initializeByConfiguration($storage->getConfiguration());
    }

    /**
     * @param File $file
     * @param array $options
     *
     * @return array
     */
    public function getExplicitData(File $file, array $options): array
    {
        $publicId = $this->getPublicIdForFile($file);

        $explicitData = $this->explicitDataCacheRepository->findByStorageAndPublicIdAndOptions($file->getStorage()->getUid(), $publicId, $options)['explicit_data'];

        if (!$explicitData) {
            $this->initializeApi($file->getStorage());
            $explicitData = Uploader::explicit($publicId, $options);
            try {
                $this->explicitDataCacheRepository->save($file->getStorage()->getUid(), $publicId, $options, $explicitData);
            } catch (UniqueConstraintViolationException $e) {
                // ignore
            }
        }

        return $explicitData;
    }

    /**
     * @param string $message
     * @param array $arguments
     * @param array $data
     */
    protected function error(string $message, array $arguments = [], array $data = [])
    {
        /** @var Logger $logger */
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $logger->log(
            LogLevel::ERROR,
            vsprintf($message, $arguments),
            $data
        );
    }

    /**
     * @return File
     */
    public function getEmergencyPlaceholderFile(): File
    {
        /** @var CloudinaryUploadService $cloudinaryUploadService */
        $cloudinaryUploadService = GeneralUtility::makeInstance(CloudinaryUploadService::class);
        return $cloudinaryUploadService->uploadLocalFile('');
    }

    /**
     * @return object|CloudinaryPathService
     */
    protected function getCloudinaryPathService(ResourceStorage $storage)
    {
        return GeneralUtility::makeInstance(
            CloudinaryPathService::class,
            $storage->getConfiguration()
        );
    }

    /**
     * @param File $file
     *
     * @return string
     */
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
        $publicId = $this
            ->getCloudinaryPathService($file->getStorage())
            ->computeCloudinaryPublicId($file->getIdentifier());
        return $publicId;
    }
}
