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

namespace Sinso\Cloudinary\Utility;

use Sinso\Cloudinary\CloudinaryException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\ArrayUtility;

class CloudinaryUtility
{

    const SEPERATOR = '---';

    /**
     * @var \Sinso\Cloudinary\Domain\Repository\MediaRepository
     * @inject
     */
    protected $mediaRepository;

    /**
     * @var \Sinso\Cloudinary\Domain\Repository\ResponsiveBreakpointsRepository
     * @inject
     */
    protected $responsiveBreakpointsRepository;


    /**
     * CloudinaryUtility constructor.
     */
    public function __construct()
    {
        $extConf = (array)unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['cloudinary']);

        \Cloudinary::config([
            'cloud_name' => $extConf['cloudName'],
            'api_key' => $extConf['apiKey'],
            'api_secret' => $extConf['apiSecret'],
            'timeout' => $extConf['timeout'],
        ]);
    }


    public function getPublicId($filename) {
        try {
            $filename = $this->cleanFilename($filename);
            $imagePathAndFilename = GeneralUtility::getFileAbsFileName($filename);
            $modificationDate = filemtime($imagePathAndFilename);

            $possibleMedias = $this->mediaRepository->findByFilename($filename);

            // check modification date
            $media = null;
            foreach($possibleMedias as $possibleMedia) {
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
            $logger->error('Error getting Cloudinary public ID for file "' . $filename .'"');

            throw new CloudinaryException('Error getting Cloudinary public ID for file "' . $filename .'"', 1484152954);
        }
    }

    public function uploadImage($filename)
    {
        try {

            $filename = $this->cleanFilename($filename);
            $imagePathAndFilename = GeneralUtility::getFileAbsFileName($filename);
            $sha1 = sha1_file($imagePathAndFilename);
            $modificationDate = filemtime($imagePathAndFilename);

            $filenameWithoutExtension = preg_replace('/\\.[^.\\s]{3,4}$/', '', $filename);
            $folder = dirname($filenameWithoutExtension);
            $publicId = basename($filenameWithoutExtension) . self::SEPERATOR . $sha1;

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

            return $publicId;
        } catch (\Exception $e) {
            /** @var \TYPO3\CMS\Core\Log\Logger $logger */
            $logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Log\LogManager')->getLogger(__CLASS__);
            $logger->error('Error uploading file "' . $filename .'" to Cloudinary.');

            throw new CloudinaryException('Error uploading file "' . $filename .'" to Cloudinary.', 1484153176);
        }
    }

    public function getResponsiveBreakpointData($publicId, $options) {
        $responsiveBreakpoints = $this->responsiveBreakpointsRepository->findByPublicIdAndOptions($publicId, $options);

        if (!$responsiveBreakpoints) {
            $response = \Cloudinary\Uploader::explicit($publicId, $options);
            $breakpointData = json_encode($response['responsive_breakpoints'][0]['breakpoints']);
            $this->responsiveBreakpointsRepository->save($publicId, $options, $breakpointData);
        } else {
            $breakpointData = $responsiveBreakpoints['breakpoints'];
        }

        return json_decode($breakpointData);
    }

    public function getSrcsetAttribute($breakpointData) {
        return implode(',' . PHP_EOL, $this->getSrcset($breakpointData));
    }

    public function getSrcset($breakpointData) {
        $imageObjects = $this->getImageObjects($breakpointData);

        $srcset = [];
        foreach ($imageObjects as $imageObject) {
            $srcset[] = $imageObject->secure_url . ' ' . $imageObject->width . 'w';
        }

        return $srcset;
    }

    public function getSizesAttribute($breakpointData) {
        $maxImageObject = $this->getImage($breakpointData, 'max');
        return '(max-width: ' . $maxImageObject->width . 'px) 100vw, ' . $maxImageObject->width . 'px';
    }

    public function getSrc($breakpointData) {
        $maxImageObject = $this->getImage($breakpointData, 'max');
        return $maxImageObject->secure_url;
    }


    public function getImage($breakpointData, $functionName) {
        $imageObjects = $this->getImageObjects($breakpointData);
        $widths = array_keys($imageObjects);

        $width = call_user_func_array(array($this, $functionName), array($widths));

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

    public function getImageObjects($breakpointData) {
        $widthMap = [];
        foreach ($breakpointData as $breakpoint) {
            $widthMap[$breakpoint->width] = $breakpoint;
        }

        return $widthMap;
    }

    public function cleanFilename($filename) {
        $filename = $this->removeAbsRefPrefix($filename);
        $parsedUrl = parse_url($filename);
        $filename = $parsedUrl['path'];

        return $filename;
    }

    /**
     * Remove absRefPrefix from filename
     *
     * This utility only supports filenames on a local filesystem. If absRefPrefix is enabled all URLs generated in
     * TYPO3 probably contain schema and domain.
     *
     * @param $filename
     * @return string
     */
    public function removeAbsRefPrefix($filename) {
        $uriPrefix = $GLOBALS['TSFE']->absRefPrefix;

        if ($uriPrefix && (substr($filename, 0, strlen($uriPrefix)) == $uriPrefix)) {
            $filename = substr($filename, strlen($uriPrefix));
        }

        return $filename;
    }



    /**
     * Return DatabaseConnection
     *
     * @return \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected function getDatabaseConnection() {
        return $GLOBALS['TYPO3_DB'];
    }

}
