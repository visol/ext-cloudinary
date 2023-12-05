<?php

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

namespace Visol\Cloudinary\Services;

use TYPO3\CMS\Core\Resource\StorageRepository;
use Cloudinary\Uploader;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Domain\Repository\ExplicitDataCacheRepository;

class CloudinaryImageService extends AbstractCloudinaryMediaService
{
    protected ExplicitDataCacheRepository $explicitDataCacheRepository;

    protected ?StorageRepository $storageRepository = null;

    protected array $defaultOptions = [
        'type' => 'upload',
        'resource_type' => 'image',
        'fetch_format' => 'auto',
        'quality' => 'auto',
    ];

    public function __construct()
    {
        $this->explicitDataCacheRepository = GeneralUtility::makeInstance(ExplicitDataCacheRepository::class);
    }

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

    public function getResponsiveBreakpointData(File $file, array $options): array
    {
        $explicitData = $this->getExplicitData($file, $options);

        return $explicitData['responsive_breakpoints'][0]['breakpoints'];
    }

    public function getSrcsetAttribute(array $breakpoints): string
    {
        return implode(',' . PHP_EOL, $this->getSrcset($breakpoints));
    }

    public function getSrcset(array $breakpoints): array
    {
        $imageObjects = $this->getImageObjects($breakpoints);
        $srcset = [];
        foreach ($imageObjects as $imageObject) {
            $srcset[] = $imageObject['secure_url'] . ' ' . $imageObject['width'] . 'w';
        }

        return $srcset;
    }

    public function getSizesAttribute(array $breakpoints): string
    {
        $maxImageObject = $this->getImage($breakpoints, 'max');
        return '(max-width: ' . $maxImageObject['width'] . 'px) 100vw, ' . $maxImageObject['width'] . 'px';
    }

    /**
     * @return mixed
     */
    public function getImage(array $breakpoints, string $functionName)
    {
        if (!in_array($functionName, ['min', 'median', 'max'])) {
            $functionName = 'max';
        }
        $imageObjects = $this->getImageObjects($breakpoints);
        $widths = array_keys($imageObjects);

        $width = call_user_func_array([$this, $functionName], [$widths]);

        return $imageObjects[$width];
    }

    public function min($items) {
        return min($items);
    }

    public function median($items) {
        sort($items);
        $medianIndex = ceil((count($items)/2))-1;
        return $items[$medianIndex];
    }

    public function max($items) {
        return max($items);
    }

    public function getImageUrl(File $file, array $options = []): string
    {
        $options = array_merge($this->defaultOptions, $options);

        $publicId = $this->getPublicIdForFile($file);

        $this->initializeApi($file->getStorage());
        return \Cloudinary::cloudinary_url($publicId, $options);
    }

    public function getImageObjects(array $breakpoints): array
    {
        $widthMap = [];
        foreach ($breakpoints as $breakpoint) {
            $widthMap[$breakpoint['width']] = $breakpoint;
        }

        return $widthMap;
    }

    public function generateOptionsFromSettings(array $settings, bool $enableResponsiveBreakpoints = true): array
    {
        $transformations = [];

        // Add pre transformation to apply cropping
        if (
            isset($settings['width'])
            && isset($settings['height'])
            && isset($settings['x'])
            && isset($settings['y'])
        ) {
            $transformations[] = [
                'crop' => 'crop',
                'width' => $settings['width'],
                'height' => $settings['height'],
                'x' => $settings['x'],
                'y' => $settings['y'],
            ];
        }

        $transformation = [
            'format' => 'auto',
            'flags' => 'lossy',
            'quality' => 'auto',
        ];

        if (isset($settings['aspectRatio']) && $settings['aspectRatio']) {
            $transformation['aspect_ratio'] = $settings['aspectRatio'];
            $transformation['crop'] = 'crop';
        }

        if (isset($settings['gravity']) && $settings['gravity']) {
            $transformation['gravity'] = $settings['gravity'];
        }

        if (isset($settings['crop']) && $settings['crop']) {
            $transformation['crop'] = $settings['crop'] !== true ? $settings['crop'] : 'crop';
        }

        if (isset($settings['background']) && $settings['background']) {
            $transformation['background'] = $settings['background'];
        }

        if (isset($settings['fetch_format']) && $settings['fetch_format']) {
            $transformation['fetch_format'] = $settings['fetch_format'];
        }

        $transformations[] = $transformation;

        if (!$enableResponsiveBreakpoints) {
            return [
                'type' => 'upload',
                'transformation' => $transformations,
            ];
        }

        return [
            'type' => 'upload',
            'responsive_breakpoints' => [
                'create_derived' => false,
                'bytes_step' => $settings['bytesStep'],
                'min_width' => $settings['minWidth'],
                'max_width' => $settings['maxWidth'],
                'max_images' => $settings['maxImages'],
                'transformation' => $transformations,
            ]
        ];
    }

    public function injectExplicitDataCacheRepository(ExplicitDataCacheRepository $explicitDataCacheRepository): void
    {
        $this->explicitDataCacheRepository = $explicitDataCacheRepository;
    }

    public function injectStorageRepository(StorageRepository $storageRepository): void
    {
        $this->storageRepository = $storageRepository;
    }
}
