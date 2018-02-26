<?php
namespace Sinso\Cloudinary\UserFunc;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class CloudinaryImage  {

    /**
     * @var \Sinso\Cloudinary\Utility\CloudinaryUtility
     * @inject
     */
    protected $cloudinaryUtility;

    public function __construct()
    {
        // Workaround: dependency injection not supported in userFuncs
        $this->cloudinaryUtility = GeneralUtility::makeInstance('Sinso\\Cloudinary\\Utility\\CloudinaryUtility');
    }

    /**
     * Compress image and return URL (http://)
     *
     * @param string $content Source image
     * @param array $conf
     *
     * @return bool
     * @throws \Sinso\Cloudinary\CloudinaryException
     */
    public function getUrl($content='', $conf = [])
    {
        if (empty($content)) {
            return false;
        }

        $imageData = $this->getImageData($content, $conf);

        return $imageData->url;
    }

    /**
     * Compress image and return secure image URL (https://)
     *
     * @param string $content Source image
     * @param array $conf
     *
     * @return bool
     * @throws \Sinso\Cloudinary\CloudinaryException
     */
    public function getSecureUrl($content='', $conf = [])
    {
        if (empty($content)) {
            return false;
        }

        $imageData = $this->getImageData($content, $conf);

        return $imageData->secure_url;
    }

    /**
     * @param $imageUri
     * @param $conf
     *
     * @return mixed
     * @throws \Sinso\Cloudinary\CloudinaryException
     */
    protected function getImageData($imageUri, $conf)
    {
        $publicId = $this->cloudinaryUtility->getPublicId(ltrim($imageUri, '/'));
        $options = $this->cloudinaryUtility->generateOptionsFromSettings($conf);
        $imageData = current($this->cloudinaryUtility->getResponsiveBreakpointData($publicId,$options));

        return $imageData;
    }

}
