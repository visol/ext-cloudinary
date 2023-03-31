<?php

namespace Visol\Cloudinary\Services;

use Cloudinary\Asset\Video;
use TYPO3\CMS\Core\Resource\File;
use Visol\Cloudinary\Utility\CloudinaryApiUtility;

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

        $configuration = CloudinaryApiUtility::getConfiguration($file->getStorage());
        return Video::fromParams($publicId)
            ->configuration($configuration)
            ->toUrl();
    }
}
