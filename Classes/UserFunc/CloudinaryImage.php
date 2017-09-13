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

        $options = [
            'type' => 'upload',
            'responsive_breakpoints' => [
                'create_derived' => false,
                'bytes_step' => $conf['bytesStep'],
                'min_width' => $conf['minWidth'],
                'max_width' => $conf['maxWidth'],
                'max_images' => $conf['maxImages'],
                'transformation' => 'f_auto,fl_lossy,q_auto,c_crop'
                    . ($conf['aspectRatio'] ? ',ar_' . $conf['aspectRatio'] : '')
                    . ($conf['gravity'] ? ',g_' . $conf['gravity'] : '')
                    . ($conf['crop'] ? ',c_' . $conf['crop'] : '')
                    . ($conf['background'] ? ',b_' . $conf['background'] : ''),
            ]
        ];
        
        $imageData = current($this->cloudinaryUtility->getResponsiveBreakpointData($publicId,$options));

        return $imageData->url;

    }

}
