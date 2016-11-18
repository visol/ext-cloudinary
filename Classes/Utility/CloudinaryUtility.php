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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\ArrayUtility;

class CloudinaryUtility
{


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
        ]);
    }


    public function getPublicId($filename) {
        $media = $this->mediaRepository->findByFilename($filename);

        if (!$media) {
            return $this->uploadImage($filename);
        }

        return $media['public_id'];
    }

    public function uploadImage($filename)
    {
        $imagePathAndFilename = GeneralUtility::getFileAbsFileName($filename);

        $filenameWithoutExtension = preg_replace('/\\.[^.\\s]{3,4}$/', '', $filename);
        $folder = dirname($filenameWithoutExtension);
        $publicId = basename($filenameWithoutExtension);

        $options = [
            'public_id' => $publicId,
            'folder' => $folder,
            'overwrite' => TRUE,
        ];

        $response = \Cloudinary\Uploader::upload($imagePathAndFilename, $options);
        $publicId = $response['public_id'];

        if (!$publicId) {
            throw new \Exception('Error while uploading image to Cloudinary', 1479469830);
        }

        $this->mediaRepository->save($filename, $publicId);

        return $publicId;
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
        $widthUriMap = $this->simplifyBreakpointData($breakpointData);

        $srcset = [];
        foreach ($widthUriMap as $width => $uri) {
            $srcset[] = $uri . ' ' . $width . 'w';
        }

        return $srcset;
    }

    public function getSizesAttribute($breakpointData) {
        $defaultWidth = $this->getDefaultImageWidth($breakpointData);
        return '(max-width: ' . $defaultWidth . 'px) 100vw, ' . $defaultWidth . 'px';
    }

    public function getSrc($breakpointData) {
        return $this->getDefaultImageUri($breakpointData);
    }


    protected function getDefaultImageUri($breakpointData) {
        $widthUriMap = $this->simplifyBreakpointData($breakpointData);

        $defaultWidth = $this->getDefaultImageWidth($breakpointData);
        return $widthUriMap[$defaultWidth];
    }

    protected function getDefaultImageWidth($breakpointData) {
        $widthUriMap = $this->simplifyBreakpointData($breakpointData);

        return max(array_keys($widthUriMap));
    }

    protected function simplifyBreakpointData($breakpointData) {
        $widthUriMap = [];
        foreach ($breakpointData as $breakpoint) {
            $widthUriMap[$breakpoint->width] = $breakpoint->secure_url;
        }

        return $widthUriMap;
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
