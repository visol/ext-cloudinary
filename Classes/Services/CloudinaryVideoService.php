<?php

namespace Visol\Cloudinary\Services;

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use Visol\Cloudinary\Domain\Repository\ExplicitDataCacheRepository;
use Visol\Cloudinary\Driver\CloudinaryDriver;
use Visol\Cloudinary\Utility\CloudinaryApiUtility;

class CloudinaryVideoService extends AbstractCloudinaryMediaService
{
    protected $defaultOptions = [
        'type' => 'upload',
        'resource_type' => 'video',
        'fetch_format' => 'auto',
        'quality' => 'auto',
    ];

    /**
     * @var \TYPO3\CMS\Core\Resource\StorageRepository
     * @inject
     */
    protected $storageRepository;

    public function getVideoUrl(File $file, array $options = []): string
    {
        $options = array_merge($this->defaultOptions, $options);

        $publicId = $this->getPublicIdForFile($file);

        $this->initializeApi($file->getStorage());
        return \Cloudinary::cloudinary_url($publicId,$options);
    }
}
