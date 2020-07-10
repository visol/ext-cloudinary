<?php

namespace Visol\Cloudinary\UserFunc;

use Visol\Cloudinary\Utility\CloudinaryUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class CloudinaryImage
 * @deprecated
 */
class CloudinaryImage
{

    /**
     * @var \Visol\Cloudinary\Utility\CloudinaryUtility
     * @inject
     */
    protected $cloudinaryUtility;

    public function __construct()
    {
        // Workaround: dependency injection not supported in userFuncs
        $this->cloudinaryUtility = GeneralUtility::makeInstance(CloudinaryUtility::class);
    }

    /**
     * Compress image and return URL (http://)
     *
     * @param string $content Source image
     * @param array $conf
     *
     * @return bool
     * @throws \Visol\Cloudinary\CloudinaryException
     */
    public function getUrl($content = '', $conf = [])
    {
        if (empty($content)) {
            return false;
        }

        $functionName = $conf['functionName'];
        unset($conf['functionName']);
        $breakpointData = $this->getBreakpointData($content, $conf);
        $imageData = $this->cloudinaryUtility->getImage($breakpointData, $functionName);
        return $imageData->secure_url;
    }

    /**
     * @param string $content Source image
     * @param array $conf
     *
     * @return bool
     * @throws \Visol\Cloudinary\CloudinaryException
     */
    public function getSrcSet($content = '', $conf = [])
    {
        if (empty($content)) {
            return false;
        }

        $breakpointData = $this->getBreakpointData($content, $conf);

        return $this->cloudinaryUtility->getSrcsetAttribute($breakpointData);
    }

    /**
     * @param $content
     * @param $conf
     * @return mixed
     */
    public function getBreakpointData($content, $conf)
    {
        $publicId = $this->cloudinaryUtility->uploadLocalFileAndGetPublicId(ltrim($content, '/'));
        $options = $this->cloudinaryUtility->generateOptionsFromSettings($conf);
        $breakpointData = $this->cloudinaryUtility->getResponsiveBreakpointData($publicId, $options);

        return $breakpointData;
    }
}
