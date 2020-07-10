<?php

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

namespace Visol\Cloudinary\Utility;

use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use Visol\Cloudinary\CloudinaryException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Driver\CloudinaryDriver;

/**
 * Class CloudinaryUtility
 */
class CloudinaryUtility
{

    const SEPERATOR = '---';
    const HASH_POSITION_DISABLED = 0;
    const HASH_POSITION_APPEND_FILENAME = 1;
    const HASH_POSITION_PREPEND_FILENAME = 2;
    const HASH_POSITION_APPEND_FOLDERNAME = 3;
    const HASH_POSITION_PREPEND_FOLDERNAME = 4;

    /**
     * @var \Visol\Cloudinary\Domain\Repository\MediaRepository
     * @inject
     */
    protected $mediaRepository;

    /**
     * @var \Visol\Cloudinary\Domain\Repository\ResponsiveBreakpointsRepository
     * @inject
     */
    protected $responsiveBreakpointsRepository;

    /**
     * @var \Visol\Cloudinary\Domain\Repository\CloudinaryProcessedResourceRepository
     * @inject
     */
    protected $cloudinaryProcessedResourceRepository;

    /**
     * @var \TYPO3\CMS\Core\Resource\StorageRepository
     * @inject
     */
    protected $storageRepository;

    /**
     * @var ResourceStorage|null
     */
    protected $storage;

    /**
     * CloudinaryUtility constructor.
     *
     * @param ResourceStorage|null $storage
     */
    public function __construct(ResourceStorage $storage = null)
    {
        $this->storage = $storage;

        // Workaround: dependency injection not supported in userFuncs
        $this->mediaRepository = GeneralUtility::makeInstance(\Visol\Cloudinary\Domain\Repository\MediaRepository::class);
        $this->responsiveBreakpointsRepository = GeneralUtility::makeInstance(\Visol\Cloudinary\Domain\Repository\ResponsiveBreakpointsRepository::class);
    }

    /**
     * @param $filename
     *
     * @return string
     * @throws CloudinaryException
     */
    public function uploadLocalFileAndGetPublicId($filename, ResourceStorage $storage = null)
    {
        if (!$storage) {
            $storage = $this->storageRepository->findByUid(2);
        }
        $this->initializeApi($storage);
        if ($storage->getDriverType() !== CloudinaryDriver::DRIVER_TYPE) {
            throw new \Exception('adsg');
        }

        try {
            $filename = $this->cleanFilename($filename);
            $imagePathAndFilename = GeneralUtility::getFileAbsFileName($filename);
            $modificationDate = filemtime($imagePathAndFilename);

            $possibleMedias = $this->mediaRepository->findByFilename($filename); // TODO: Add storageID

            // check modification date
            $media = null;
            foreach ($possibleMedias as $possibleMedia) {
                if (intval($possibleMedia['modification_date']) !== $modificationDate) {
                    continue;
                }

                $media = $possibleMedia;
            }

            // fallback and check sha1
            if (!$media) {
                $sha1 = sha1_file($imagePathAndFilename);
                $media = $this->mediaRepository->findOneByFilenameAndSha1($filename, $sha1);
            }

            // new image
            if (!$media) {
                return $this->uploadImage($filename);
            }

            return $media['public_id'];
        } catch (\Exception $e) {
            /** @var \TYPO3\CMS\Core\Log\Logger $logger */
            $logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Log\LogManager')->getLogger(__CLASS__);
            $logger->error('Error getting Cloudinary public ID for file "' . $filename . '"');

            throw new CloudinaryException('Error getting Cloudinary public ID for file "' . $filename . '" / ' . $e->getMessage(), 1484152954);
        }
    }

    /**
     * @param $filename
     *
     * @return string
     * @throws CloudinaryException
     * @deprecated not used anymore
     */
    public function uploadImage($filename)
    {
        try {
            $filename = $this->cleanFilename($filename);
            $imagePathAndFilename = GeneralUtility::getFileAbsFileName($filename);
            $sha1 = sha1_file($imagePathAndFilename);
            $modificationDate = filemtime($imagePathAndFilename);

            $filenameWithoutExtension = preg_replace('/\\.[^.\\s]{3,4}$/', '', $filename);

            $folder = dirname($filenameWithoutExtension);
            $publicId = basename($filenameWithoutExtension);

            $options = [
                'public_id' => $publicId,
                'folder' => $folder,
                'overwrite' => true,
            ];

            $response = \Cloudinary\Uploader::upload($imagePathAndFilename, $options);
            $publicId = $response['public_id'];

            if (!$publicId) {
                throw new \Exception('Error while uploading image to Cloudinary', 1479469830);
            }

            $this->mediaRepository->save($filename, $publicId, $sha1, $modificationDate);
            $this->mediaRepository->save($filename, $publicId, $sha1, $modificationDate);

            return $publicId;
        } catch (\Exception $e) {
            /** @var \TYPO3\CMS\Core\Log\Logger $logger */
            $logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Log\LogManager')->getLogger(__CLASS__);
            $logger->error('Error uploading file "' . $filename . '" to Cloudinary.');

            throw new CloudinaryException('Error uploading file "' . $filename . '" to Cloudinary.', 1484153176);
        }
    }

    /**
     * Todo check this method if we have to accept $publicIdOrFileReference as method below "getResponsiveBreakpointData"
     * We might call again "initializeApi".
     *
     * @param string $publicId
     * @param array $options
     *
     * @return array
     */
    public function getCloudinaryProcessedResource(string $publicId, array $options): array
    {
        $record = $this->cloudinaryProcessedResourceRepository->findByPublicIdAndOptions($publicId, $options);

        if (!$record) {
            $response = \Cloudinary\Uploader::explicit($publicId, $options);
            $cloudinaryProcessedResource = $response['eager'][0];
            $this->cloudinaryProcessedResourceRepository->save($publicId, $options, json_encode($cloudinaryProcessedResource));
        } else {
            $cloudinaryProcessedResource = json_decode($record['breakpoints'], true);
        }

        return $cloudinaryProcessedResource;
    }

    /**
     * Todo normalize this method, argument $publicIdOrFileReference should be a unique type.
     *
     * @param string|FileReference $publicIdOrFileReference
     * @param array $options
     *
     * @return array
     */
    public function getResponsiveBreakpointData($publicIdOrFileReference, array $options): array
    {
        $responsiveBreakpoints = null;

        if ($publicIdOrFileReference instanceof FileReference) {
            $this->initializeApi($publicIdOrFileReference->getStorage());
            // todo fix me!
            throw new \Exception('Fab: refactor me! We could have a service here', 123456789);
            $publicId = $this->setStorage($publicIdOrFileReference->getStorage())
                ->computeCloudinaryPublicId($publicIdOrFileReference->getIdentifier());
        } else {
            $publicId = $publicIdOrFileReference;
        }

        $responsiveBreakpoints = $this->responsiveBreakpointsRepository->findByPublicIdAndOptions($publicId, $options);

        if (!$responsiveBreakpoints) {
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
     * @param FileReference $fileReference
     */
    protected function initializeApi(ResourceStorage $storage)
    {
        // Check the file is stored on the right storage
        // If not we should trigger an execption
        if ($storage->getDriverType() !== CloudinaryDriver::DRIVER_TYPE) {
            $message = sprintf(
                'CloudinaryUtility: wrong storage! Can not initialize with storage type "%s".',
                $storage->getDriverType()
            );
            throw new \Exception($message, 1590401459);
        }

        // Get the configuration
        $configuration = $storage->getConfiguration();
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
     * @param array $settings
     *
     * @return array
     */
    public function generateOptionsFromSettings(array $settings)
    {
        $options = [
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

        return $options;
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
     *
     * @return string
     * @deprecated use $file->getPublicUrl() instead
     */
    public function getSrc(array $breakpoints): string
    {
        $maxImageObject = $this->getImage($breakpoints, 'max');
        return $maxImageObject->secure_url;
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

        $width = call_user_func_array(array($this, $functionName), array($widths));

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
     * @param string $filename
     *
     * @return string
     * @deprecated
     */
    protected function cleanFilename(string $filename): string
    {
        $filename = $this->removeAbsRefPrefix($filename);
        $parsedUrl = parse_url($filename);
        $filename = $parsedUrl['path'];
        $filename = urldecode($filename);

        return $filename;
    }

    /**
     * Remove absRefPrefix from filename
     *
     * This utility only supports filenames on a local filesystem. If absRefPrefix is enabled all URLs generated in
     * TYPO3 probably contain schema and domain.
     *
     * @param string $filename
     *
     * @return string
     */
    protected function removeAbsRefPrefix(string $filename): string
    {
        $uriPrefix = $GLOBALS['TSFE']->absRefPrefix;

        if ($uriPrefix && (substr($filename, 0, strlen($uriPrefix)) == $uriPrefix)) {
            $filename = substr($filename, strlen($uriPrefix));
        }

        return $filename;
    }

}
