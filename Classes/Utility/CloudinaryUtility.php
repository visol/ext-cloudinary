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
        \Cloudinary::config([
            'cloud_name' => $this->extensionConfiguration['cloudName'],
            'api_key' => $this->extensionConfiguration['apiKey'],
            'api_secret' => $this->extensionConfiguration['apiSecret'],
            'timeout' => $this->extensionConfiguration['timeout'],
        ]);
    }

    /**
     * @param $filename
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
     * @param string|FileReference $publicIdOrFileReference
     * @param array $options
     * @return array
     */
    public function getResponsiveBreakpointData($publicIdOrFileReference, array $options): array
    {
        $responsiveBreakpoints = null;

        if ($publicIdOrFileReference instanceof FileReference) {
            $this->initializeApi($publicIdOrFileReference);
            $publicId = CloudinaryPathUtility::computeCloudinaryPublicId($publicIdOrFileReference->getIdentifier());
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
     * @return string
     */
    public function getSrcsetAttribute(array $breakpoints): string
    {
        return implode(',' . PHP_EOL, $this->getSrcset($breakpoints));
    }

    /**
     * @param array $breakpoints
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
     * @return string
     */
    public function getSizesAttribute(array $breakpoints): string
    {
        $maxImageObject = $this->getImage($breakpoints, 'max');
        return '(max-width: ' . $maxImageObject->width . 'px) 100vw, ' . $maxImageObject->width . 'px';
    }

    /**
     * @param array $breakpoints
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
     * @return mixed
     */
    public function min($items)
    {
        return min($items);
    }

    /**
     * @param array $items
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
     * @return mixed
     */
    public function max($items)
    {
        return max($items);
    }

    /**
     * @param array $breakpoints
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
     * @return string
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
     * @return string
     */
    public function removeAbsRefPrefix(string $filename): string
    {
        $uriPrefix = $GLOBALS['TSFE']->absRefPrefix;

        if ($uriPrefix && (substr($filename, 0, strlen($uriPrefix)) == $uriPrefix)) {
            $filename = substr($filename, strlen($uriPrefix));
        }

        return $filename;
    }
}
