<?php
namespace Sinso\Cloudinary\ViewHelpers;

/*                                                                        *
 * This script is part of the TYPO3 project - inspiring people to share!  *
 *                                                                        *
 * TYPO3 is free software; you can redistribute it and/or modify it under *
 * the terms of the GNU General Public License version 2 as published by  *
 * the Free Software Foundation.                                          *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
 * Public License for more details.                                       *
 *                                                                        */

use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Extbase\Domain\Model\AbstractFileFolder;

/**
 * Resizes a given image (if required) and renders the respective img tag
 *
 * = Examples =
 *
 * <code title="Default">
 * <f:image src="EXT:myext/Resources/Public/typo3_logo.png" alt="alt text" />
 * </code>
 * <output>
 * <img alt="alt text" src="typo3conf/ext/myext/Resources/Public/typo3_logo.png" width="396" height="375" />
 * or (in BE mode):
 * <img alt="alt text" src="../typo3conf/ext/viewhelpertest/Resources/Public/typo3_logo.png" width="396" height="375" />
 * </output>
 *
 * <code title="Image Object">
 * <f:image image="{imageObject}" />
 * </code>
 * <output>
 * <img alt="alt set in image record" src="fileadmin/_processed_/323223424.png" width="396" height="375" />
 * </output>
 *
 * <code title="Inline notation">
 * {f:image(src: 'EXT:viewhelpertest/Resources/Public/typo3_logo.png', alt: 'alt text', minWidth: 30, maxWidth: 40)}
 * </code>
 * <output>
 * <img alt="alt text" src="../typo3temp/pics/f13d79a526.png" width="40" height="38" />
 * (depending on your TYPO3s encryption key)
 * </output>
 *
 * <code title="Other resource type (e.g. PDF)">
 * <f:image src="fileadmin/user_upload/example.pdf" alt="foo" />
 * </code>
 * <output>
 * If your graphics processing library is set up correctly then it will output a thumbnail of the first page of your PDF document.
 * <img src="fileadmin/_processed_/1/2/csm_example_aabbcc112233.gif" width="200" height="284" alt="foo">
 * </output>
 *
 * <code title="Non-existant image">
 * <f:image src="NonExistingImage.png" alt="foo" />
 * </code>
 * <output>
 * Could not get image resource for "NonExistingImage.png".
 * </output>
 */
class ResponsiveImageDataViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper
{

    /**
     * @var \TYPO3\CMS\Extbase\Service\ImageService
     */
    protected $imageService;

    /**
     * @var \Sinso\Cloudinary\Utility\CloudinaryUtility
     * @inject
     */
    protected $cloudinaryUtility;

    /**
     * @param \TYPO3\CMS\Extbase\Service\ImageService $imageService
     */
    public function injectImageService(\TYPO3\CMS\Extbase\Service\ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * @param \Sinso\Cloudinary\Utility\CloudinaryUtility $cloudinaryUtility
     */
    public function injectCloudinaryUtility(\TYPO3\CMS\Extbase\Service\ImageService $cloudinaryUtility)
    {
        $this->cloudinaryUtility = $cloudinaryUtility;
    }

    /**
     * Resizes a given image (if required) and renders the respective img tag
     *
     * @see https://docs.typo3.org/typo3cms/TyposcriptReference/ContentObjects/Image/
     * @param string $src a path to a file, a combined FAL identifier or an uid (int). If $treatIdAsReference is set, the integer is considered the uid of the sys_file_reference record. If you already got a FAL object, consider using the $image parameter instead
     * @param int $minWidth minimum width of the image
     * @param int $maxWidth maximum width of the image
     * @param int $maxImages maximum amount of images generated by Cloudinary
     * @param int $bytesStep difference between filesizes of images generated by Cloudinary
     * @param string $aspectRatio difference between filesizes of images generated by Cloudinary
     * @param string $gravity define the focus for the transformation in Cloudinary
     * @param string $crop define cropping for Cloudinary
     * @param bool $treatIdAsReference given src argument is a sys_file_reference record
     * @param mixed $image a FAL object
     * @param string $data Name for variable with responsive image data within the viewhelper
     *
     * @throws \TYPO3\CMS\Fluid\Core\ViewHelper\Exception
     * @return string Rendered tag
     */
    public function render($src = null, $minWidth = 100, $maxWidth = 2000, $maxImages = 10, $bytesStep = 40000, $aspectRatio = null, $gravity = null, $crop = null, $treatIdAsReference = false, $image = null, $data = 'responsiveImageData')
    {
        if (is_null($src) && is_null($image) || !is_null($src) && !is_null($image)) {
            throw new \TYPO3\CMS\Fluid\Core\ViewHelper\Exception('You must either specify a string src or a File object.', 1382284106);
        }

        if (!is_int($src)) {
            $parsedUrl = parse_url($src);
            $src = $parsedUrl['path'];
        }

        try {
            $image = $this->imageService->getImage($src, $image, $treatIdAsReference);

            $preCrop = $image instanceof FileReference ? $image->getProperty('crop') : null;
            $processingInstructions = [
                'crop' => $preCrop,
            ];
            $processedImage = $this->imageService->applyProcessingInstructions($image, $processingInstructions);
            $imageUri = $this->imageService->getImageUri($processedImage);

            try {
                // decode URLs from RealURL
                $imageUri = rawurldecode($imageUri);

                $publicId = $this->cloudinaryUtility->getPublicId(ltrim($imageUri, '/'));

                $settings = [
                    'bytesStep' => $bytesStep,
                    'minWidth' => $minWidth,
                    'maxWidth' => $maxWidth,
                    'maxImages' => $maxImages,
                    'aspectRatio' => $aspectRatio,
                    'gravity' => $gravity,
                    'crop' => $crop,
                ];
                $options = $this->cloudinaryUtility->generateOptionsFromSettings($settings);

                $breakpointData = $this->cloudinaryUtility->getResponsiveBreakpointData($publicId, $options);
                $responsiveImageData = [
                    'images' => $this->cloudinaryUtility->getImageObjects($breakpointData),
                    'minImage' => $this->cloudinaryUtility->getImage($breakpointData, 'min'),
                    'medianImage' => $this->cloudinaryUtility->getImage($breakpointData, 'median'),
                    'maxImage' => $this->cloudinaryUtility->getImage($breakpointData, 'max'),
                ];
            } catch (\Exception $e) {
                $responsiveImageData = [
                    'images' => [
                        1 => [
                            'width' => 1,
                            'height' => 1,
                            'url' => $imageUri,
                            'secure_url' => $imageUri,
                        ]
                    ],
                    'minImage' => $imageUri,
                    'medianImage' => $imageUri,
                    'maxImage' => $imageUri,
                ];
            }
        } catch (ResourceDoesNotExistException $e) {
            // thrown if file does not exist
        } catch (\UnexpectedValueException $e) {
            // thrown if a file has been replaced with a folder
        } catch (\RuntimeException $e) {
            // RuntimeException thrown if a file is outside of a storage
        } catch (\InvalidArgumentException $e) {
            // thrown if file storage does not exist
        }

        $this->templateVariableContainer->add($data, $responsiveImageData);
        $output = $this->renderChildren();
        $this->templateVariableContainer->remove($data);

        return $output;
    }

}
