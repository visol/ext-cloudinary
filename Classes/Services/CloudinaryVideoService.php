<?php

namespace Visol\Cloudinary\Services;

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use Visol\Cloudinary\Domain\Repository\ExplicitDataCacheRepository;
use Visol\Cloudinary\Driver\CloudinaryDriver;
use Visol\Cloudinary\Utility\CloudinaryApiUtility;

class CloudinaryVideoService
{
    public const VIDEO_HD = 2;

    /**
     * @var ExplicitDataCacheRepository
     */
    protected $explicitDataCacheRepository;

    /**
     * @var \TYPO3\CMS\Core\Resource\StorageRepository
     * @inject
     */
    protected $storageRepository;

    /**
     *
     */
    public function __construct(
        ExplicitDataCacheRepository $explicitDataCacheRepository
    )
    {
        $this->explicitDataCacheRepository = $explicitDataCacheRepository;
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
        $options['public_id'] = $publicId;
        $explicitData = $this->explicitDataCacheRepository->findByStorageAndPublicIdAndOptions($file->getStorage()->getUid(), $publicId, $options)['explicit_data'];

        if (!$explicitData) {
            $this->initializeApi($file->getStorage());
            $explicitData = \Cloudinary\Uploader::upload($file->getContents(), $options);
            try {
                $this->explicitDataCacheRepository->save($file->getStorage()->getUid(), $publicId, $options, $explicitData);
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                // ignore
            }
        }

        return $explicitData;
    }

    public function getHDVideoUrl(File $file, array $options = []): string
    {
        $videoExplicitDate = $this->getVideoExplicitData($file);
        return $videoExplicitDate['eager'][self::VIDEO_HD]['secure_url'];
    }

    protected function getVideoExplicitData(File $file, array $options = []): array
    {
        // override required video options
        $options['type'] = 'upload';
        $options['resource_type'] = 'video';
        return $this->getExplicitData($file, $options);
    }

    /**
     * @throws \Exception
     */
    protected function initializeApi(ResourceStorage $storage)
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
     *
     * @return string
     */
    public function getPublicIdForFile(File $file): string
    {
        $regEx = '/(\/v\d+\/)(.*)(.mp4)/';
        preg_match($regEx, $file->getContents(), $matches, PREG_OFFSET_CAPTURE, 0);
        return $matches[2][0];
    }
}
