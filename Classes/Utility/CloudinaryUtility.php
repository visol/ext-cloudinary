<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with TYPO3 source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Visol\Cloudinary\Utility;

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Utility\PathUtility;
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
     * @var array
     */
    protected $extensionConfiguration;

    /**
     * CloudinaryUtility constructor.
     */
    public function __construct()
    {
        # TODO: change me after TYPO3 v9 migration
        #       GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('cloudinary')
        $this->extensionConfiguration = (array)unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['cloudinary']);

        // Workaround: dependency injection not supported in userFuncs
        $this->mediaRepository = GeneralUtility::makeInstance(\Visol\Cloudinary\Domain\Repository\MediaRepository::class);
        $this->responsiveBreakpointsRepository = GeneralUtility::makeInstance(\Visol\Cloudinary\Domain\Repository\ResponsiveBreakpointsRepository::class);

        // todo: remove obsolete code since the config is to be found in the storage
        \Cloudinary::config(
            [
                'cloud_name' => $this->extensionConfiguration['cloudName'],
                'api_key' => $this->extensionConfiguration['apiKey'],
                'api_secret' => $this->extensionConfiguration['apiSecret'],
                'timeout' => $this->extensionConfiguration['timeout'],
            ]
        );
    }

    /**
     * @param $filename
     *
     * @return string
     * @throws CloudinaryException
     * @deprecated use FalToCloudinaryConverter::toPublicId instead
     */
    public function getPublicId($filename)
    {
        try {
            $filename = $this->cleanFilename($filename);
            $imagePathAndFilename = GeneralUtility::getFileAbsFileName($filename);
            $modificationDate = filemtime($imagePathAndFilename);

            $possibleMedias = $this->mediaRepository->findByFilename($filename);

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

            switch ($this->extensionConfiguration['hash_position']) {
                case self::HASH_POSITION_APPEND_FILENAME:
                    $folder = dirname($filenameWithoutExtension);
                    $publicId = basename($filenameWithoutExtension) . self::SEPERATOR . $sha1;
                    break;
                case self::HASH_POSITION_PREPEND_FILENAME:
                    $folder = dirname($filenameWithoutExtension);
                    $publicId = $sha1 . self::SEPERATOR . basename($filenameWithoutExtension);
                    break;
                case self::HASH_POSITION_APPEND_FOLDERNAME:
                    $folder = dirname($filenameWithoutExtension) . '/' . $sha1;
                    $publicId = basename($filenameWithoutExtension);
                    break;
                case self::HASH_POSITION_PREPEND_FOLDERNAME:
                    $folder = $sha1 . '/' . dirname($filenameWithoutExtension);
                    $publicId = basename($filenameWithoutExtension);
                    break;
                default:
                    $folder = dirname($filenameWithoutExtension);
                    $publicId = basename($filenameWithoutExtension);
            }


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
            $this->initializeApi($publicIdOrFileReference);
            $publicId = CloudinaryUtility::computeCloudinaryPublicId($publicIdOrFileReference->getIdentifier());
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
    protected function initializeApi(FileReference $fileReference)
    {
        $storage = $fileReference->getStorage();

        // Check the file is stored on the right storage
        // If not we should trigger an execption
        if ($storage->getDriverType() !== CloudinaryDriver::DRIVER_TYPE) {
            $message = sprintf(
                'CloudinaryUtility: wrong storage! Can not initialize Cloudinary API with file reference "%s" original file "%s:%s"',
                $fileReference->getUid(),
                $fileReference->getOriginalFile()->getUid(),
                $fileReference->getOriginalFile()->getIdentifier()
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
     * @param $items
     *
     * @return mixed
     */
    public function min($items)
    {
        return min($items);
    }

    /**
     * @param array $items
     *
     * @return mixed
     */
    public function median(array $items)
    {
        sort($items);
        $medianIndex = ceil((count($items) / 2)) - 1;
        return $items[$medianIndex];
    }

    /**
     * @param $items
     *
     * @return mixed
     */
    public function max($items)
    {
        return max($items);
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
    public function cleanFilename(string $filename): string
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

    /**
     * @param array $cloudinaryResource
     * @return string
     */
    public static function computeFileIdentifier(array $cloudinaryResource): string
    {
        $baseFileName = DIRECTORY_SEPARATOR . $cloudinaryResource['public_id'];
        $extension = $cloudinaryResource['resource_type'] === 'image'
            ? '.' . $cloudinaryResource['format'] // the format (or extension) is only returned for images.
            : '';
        return $baseFileName . $extension;
    }

    /**
     * @param string $fileIdentifier
     *
     * @return string
     */
    public static function computeCloudinaryPublicId(string $fileIdentifier): string
    {
        $normalizedFileIdentifier = self::guessIsImage($fileIdentifier)
            ? self::stripExtension($fileIdentifier)
            : $fileIdentifier;

        return self::computeCloudinaryPath($normalizedFileIdentifier);
    }

    /**
     * See if that is OK like that. The alternatives requires to "heavy" processing
     * like downloading the file to check the mime time or use the API SDK to fetch whether
     * we are in presence of an image.
     *
     * @param string $fileIdentifier
     *
     * @return bool
     */
    protected static function guessIsImage(string $fileIdentifier)
    {
        $extension = PathUtility::pathinfo($fileIdentifier, PATHINFO_EXTENSION);
        $commonMimeTypes = [
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            'webp' => 'image/image/webp',
        ];

        return isset($commonMimeTypes[$extension]);
    }

    /**
     * @param string $fileIdentifier
     * @return string
     */
    public static function computeCloudinaryPath(string $fileIdentifier): string
    {
        return trim($fileIdentifier, DIRECTORY_SEPARATOR);
    }

    /**
     * @param $filename
     * @return string
     */
    protected static function stripExtension($filename): string
    {
        $pathParts = PathUtility::pathinfo($filename);
        return $pathParts['dirname'] . DIRECTORY_SEPARATOR . $pathParts['filename'];
    }

    /**
     * @param string $folderName
     * @param string $folderIdentifier
     * @return string
     */
    public static function normalizeFolderNameAndPath(string $folderName, string $folderIdentifier): string
    {
        return self::normalizeFolderPath($folderIdentifier) . DIRECTORY_SEPARATOR . $folderName;
    }

    /**
     * @param string $folderIdentifier
     * @return string
     */
    public static function normalizeFolderPath(string $folderIdentifier): string
    {
        return trim($folderIdentifier, DIRECTORY_SEPARATOR);
    }

    /**
     * @param array $fileInfo
     *
     * @return string
     */
    public static function getMimeType(array $fileInfo): string
    {
        return isset($fileInfo['mime_type'])
            ? $fileInfo['mime_type']
            : '';
    }

    /**
     * @param string $fileIdentifier
     *
     * @return string
     */
    public static function getResourceType(string $fileIdentifier): string
    {
        return self::guessIsImage($fileIdentifier)
            ? 'image'
            : 'raw';
    }

    /**
     * @param string|array $fileInfoOrMimeType
     *
     * @return int
     */
//    public static function getFileType($fileInfoOrMimeType): int
//    {
//        $fileType = 0;
//
//        $mimeType = is_array($fileInfoOrMimeType)
//            ? self::getMimeType($fileInfoOrMimeType)
//            : $fileInfoOrMimeType;
//
//        if (self::isText($mimeType)) {
//            $fileType = File::FILETYPE_TEXT;
//        } elseif (self::isImage($mimeType)) {
//            $fileType = File::FILETYPE_IMAGE;
//        } elseif (self::isAudio($mimeType)) {
//            $fileType = File::FILETYPE_AUDIO;
//        } elseif (self::isVideo($mimeType)) {
//            $fileType = File::FILETYPE_VIDEO;
//        } elseif (self::isApplication($mimeType)) {
//            $fileType = File::FILETYPE_APPLICATION;
//        }
//        return $fileType;
//    }

    /**
     * @param string $mimeType
     *
     * @return bool
     */
    public static function isText(string $mimeType): bool
    {
        return (bool)strstr($mimeType, 'text/');
    }

    /**
     * @param string $mimeType
     *
     * @return bool
     */
    public static function isImage(string $mimeType): bool
    {
        return (bool)strstr($mimeType, 'image/');
    }

    /**
     * @param string $mimeType
     *
     * @return bool
     */
    public static function isAudio(string $mimeType): bool
    {
        return (bool)strstr($mimeType, 'audio/');
    }

    /**
     * @param string $mimeType
     *
     * @return bool
     */
    public static function isVideo(string $mimeType): bool
    {
        return (bool)strstr($mimeType, 'video/');
    }

    /**
     * @param string $mimeType
     *
     * @return bool
     */
    public static function isApplication(string $mimeType): bool
    {
        return (bool)strstr($mimeType, 'application/');
    }
}
