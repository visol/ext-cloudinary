<?php

namespace Visol\Cloudinary\Services;

use TYPO3\CMS\Core\Resource\File;

class CloudinaryVideoService extends AbstractCloudinaryMediaService
{
    protected $defaultOptions = [
        'type' => 'upload',
        'resource_type' => 'video',
        'fetch_format' => 'auto',
        'quality' => 'auto',
    ];

    public function getVideoUrl(File $file, array $options = []): string
    {
        $options = array_merge($this->defaultOptions, $options);

        $publicId = $this->getPublicIdForFile($file);

        $this->initializeApi($file->getStorage());
        return \Cloudinary::cloudinary_url($publicId, $options);
    }
}
