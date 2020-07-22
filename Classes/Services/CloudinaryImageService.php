<?php

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

namespace Visol\Cloudinary\Services;

use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Domain\Repository\ResponsiveBreakpointsRepository;
use Visol\Cloudinary\Driver\CloudinaryDriver;

/**
 * Class CloudinaryImageService
 */
class CloudinaryImageService
{

    /**
     * @var ResponsiveBreakpointsRepository
     * @inject
     */
    protected $responsiveBreakpointsRepository;

    /**
     * @var \TYPO3\CMS\Core\Resource\StorageRepository
     * @inject
     */
    protected $storageRepository;

    /**
     * @var ResourceStorage
     */
    protected $storage;

    /**
     *
     */
    public function __construct()
    {
        $this->responsiveBreakpointsRepository = GeneralUtility::makeInstance(ResponsiveBreakpointsRepository::class);
    }

    /**
     * @param File $file
     * @param array $options
     *
     * @return array
     */
    public function getResponsiveBreakpointData(File $file, array $options): array
    {
        // We store the storage
        $this->storage = $file->getStorage();

        // It should never happen but in case... we prefer to have an empty file instead of an exception
        if (!$file->exists()) {
            // We should log this incident...
            $this->error('I could not find file ' . $file->getIdentifier());

            // We want to avoid an exception
            $file = $this->getEmergencyPlaceholderFile();
        }

        // Compute the cloudinary public id
        $publicId = $this
            ->getCloudinaryPathService()
            ->computeCloudinaryPublicId($file->getIdentifier());

        $responsiveBreakpoints = $this->responsiveBreakpointsRepository->findByPublicIdAndOptions($publicId, $options);

        if (!$responsiveBreakpoints) {
            $this->initializeApi();
            $response = \Cloudinary\Uploader::explicit($publicId, $options);
            $breakpointData = json_encode($response['responsive_breakpoints'][0]['breakpoints']);
            $this->responsiveBreakpointsRepository->save($publicId, $options, $breakpointData);
        } else {
            $breakpointData = $responsiveBreakpoints['breakpoints'];
        }

        $breakpoints = json_decode($breakpointData);
        return is_array($breakpoints)
            ? $breakpoints
            : [];
    }

    /**
     * @throws \Exception
     */
    protected function initializeApi()
    {
        // Check the file is stored on the right storage
        // If not we should trigger an exception
        if ($this->storage->getDriverType() !== CloudinaryDriver::DRIVER_TYPE) {
            $message = sprintf(
                'Wrong storage! Can not initialize with storage type "%s".',
                $this->storage->getDriverType()
            );
            throw new \Exception($message, 1590401459);
        }

        // Get the configuration
        $configuration = $this->storage->getConfiguration();
        \Cloudinary::config(
            [
                'cloud_name' => $configuration['cloudName'],
                'api_key' => $configuration['apiKey'],
                'api_secret' => $configuration['apiSecret'],
                'timeout' => $configuration['timeout'],
                'secure' => true
            ]
        );
    }

    /**
     * @param array $breakpoints
     *
     * @return string
     */
    public function getSrcsetAttribute(array $breakpoints): string
    {
        return implode(',' . PHP_EOL, $this->getSrcset($breakpoints));
    }

    /**
     * @param array $breakpoints
     *
     * @return array
     */
    public function getSrcset(array $breakpoints): array
    {
        $imageObjects = $this->getImageObjects($breakpoints);
        $srcset = [];
        foreach ($imageObjects as $imageObject) {
            $srcset[] = $imageObject->secure_url . ' ' . $imageObject->width . 'w';
        }

        return $srcset;
    }

    /**
     * @param array $breakpoints
     *
     * @return string
     */
    public function getSizesAttribute(array $breakpoints): string
    {
        $maxImageObject = $this->getImage($breakpoints, 'max');
        return '(max-width: ' . $maxImageObject->width . 'px) 100vw, ' . $maxImageObject->width . 'px';
    }

    /**
     * @param array $breakpoints
     * @param string $functionName
     *
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

    /**
     * @param array $breakpoints
     *
     * @return array
     */
    public function getImageObjects(array $breakpoints): array
    {
        $widthMap = [];
        foreach ($breakpoints as $breakpoint) {
            $widthMap[$breakpoint->width] = $breakpoint;
        }

        return $widthMap;
    }

    /**
     * @param array $settings
     *
     * @return array
     */
    public function generateOptionsFromSettings(array $settings): array
    {
        return [
            'type' => 'upload',
            'responsive_breakpoints' => [
                'create_derived' => false,
                'bytes_step' => $settings['bytesStep'],
                'min_width' => $settings['minWidth'],
                'max_width' => $settings['maxWidth'],
                'max_images' => $settings['maxImages'],
                'transformation' => 'f_auto,fl_lossy,q_auto,c_crop'
                    . ($settings['aspectRatio'] ? ',ar_' . $settings['aspectRatio'] : '')
                    . ($settings['gravity'] ? ',g_' . $settings['gravity'] : '')
                    . ($settings['crop'] ? ',c_' . $settings['crop'] : '')
                    . ($settings['background'] ? ',b_' . $settings['background'] : ''),
            ]
        ];
    }

    /**
     * @param string $message
     * @param array $arguments
     * @param array $data
     */
    protected function error(string $message, array $arguments = [], array $data = [])
    {
        /** @var \TYPO3\CMS\Core\Log\Logger $logger */
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
    protected function getCloudinaryPathService()
    {
        return GeneralUtility::makeInstance(
            CloudinaryPathService::class,
            $this->storage
        );
    }
}
