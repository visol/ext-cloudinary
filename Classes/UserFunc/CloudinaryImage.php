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

    public function getUrl($content='', $conf = [])
    {
        if (empty($content)) {
            return false;
        }
        $imageUri = $content;
        $publicId = $this->cloudinaryUtility->getPublicId(ltrim($imageUri, '/'));
        $options = $this->cloudinaryUtility->generateOptionsFromSettings($conf);
        $imageData = current($this->cloudinaryUtility->getResponsiveBreakpointData($publicId,$options));

        return $imageData->url;

    }

}
