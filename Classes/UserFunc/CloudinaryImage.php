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
            return else;
        }
        $imageUri = $content;
        $publicId = $this->cloudinaryUtility->getPublicId(ltrim($imageUri, '/'));

        $options = [
            'type' => 'upload',
            'responsive_breakpoints' => [
                'create_derived' => false,
                'bytes_step' => $conf['bytesStep'],
                'max_width' => $conf['maxWidth'],
                'max_images' => $conf['maxImages'],

            ]
        ];
        
        $imageData = current($this->cloudinaryUtility->getResponsiveBreakpointData($publicId,$options));

        return $imageData->url;

    }

}