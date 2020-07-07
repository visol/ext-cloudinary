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

use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\ResourceStorage;
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
            $this->initializeApi($publicIdOrFileReference);
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

    /**
     * Cloudinary to FAL identifier
     *
     * @param array $cloudinaryResource
     *
     * @return string
     */
    public function computeFileIdentifier(array $cloudinaryResource): string
    {
        $extension = $cloudinaryResource['resource_type'] === 'image'
            ? '.' . $cloudinaryResource['format'] // the format (or extension) is only returned for images.
            : '';

        $rawFileIdentifier = DIRECTORY_SEPARATOR . $cloudinaryResource['public_id'] . $extension;
        return str_replace($this->getBasePath(), '', $rawFileIdentifier);
    }

    /**
     * @return string
     */
    protected function getBasePath(): string
    {
        $basePath = (string)$this->storage->getConfiguration()['basePath'];
        return $basePath
            ? DIRECTORY_SEPARATOR . trim($basePath, DIRECTORY_SEPARATOR)
            : '';
    }

    /**
     * FAL to Cloudinary identifier
     *
     * @param string $fileIdentifier
     *
     * @return string
     */
    public function computeCloudinaryPublicId(string $fileIdentifier): string
    {
        $normalizedFileIdentifier = $this->guessIsImage($fileIdentifier)
            ? $this->stripExtension($fileIdentifier)
            : $fileIdentifier;

        return $this->normalizeCloudinaryPath($normalizedFileIdentifier);
    }

    /**
     * FAL to Cloudinary identifier
     *
     * @param string $folderIdentifier
     *
     * @return string
     */
    public function computeCloudinaryFolderPath(string $folderIdentifier): string
    {
        return $this->normalizeCloudinaryPath($folderIdentifier);
    }

    /**
     * @param string $cloudinaryPath
     *
     * @return string
     */
    public function normalizeCloudinaryPath(string $cloudinaryPath): string
    {
        $normalizedCloudinaryPath = trim($cloudinaryPath, DIRECTORY_SEPARATOR);
        $basePath = $this->getBasePath();
        return $basePath
            ? trim($basePath . DIRECTORY_SEPARATOR . $normalizedCloudinaryPath, DIRECTORY_SEPARATOR)
            : $normalizedCloudinaryPath;
    }

    /**
     * @param array $fileInfo
     *
     * @return string
     */
    public function getMimeType(array $fileInfo): string
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
    public function getResourceType(string $fileIdentifier): string
    {
        return $this->guessIsImage($fileIdentifier)
            ? 'image'
            : 'raw';
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
    protected function guessIsImage(string $fileIdentifier)
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
     * @param $filename
     *
     * @return string
     */
    protected function stripExtension($filename): string
    {
        $pathParts = PathUtility::pathinfo($filename);
        return $pathParts['dirname'] . DIRECTORY_SEPARATOR . $pathParts['filename'];
    }

    /**
     * @param ResourceStorage|null $storage
     *
     * @return $this
     */
    public function setStorage(ResourceStorage $storage): self
    {
        $this->storage = $storage;
        return $this;
    }

}
