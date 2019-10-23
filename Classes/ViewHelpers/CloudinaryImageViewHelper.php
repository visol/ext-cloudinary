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
use TYPO3\CMS\Core\Resource\ResourceFactory;
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
 * @deprecated use CloudinaryImageViewHelper instead
 */
class CloudinaryImageViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper
{
    /**
     * @var string
     */
    protected $tagName = 'img';

    /**
     * @var \TYPO3\CMS\Extbase\Service\ImageService
     */
    protected $imageService;

    /**
     * @var \Sinso\Cloudinary\Utility\CloudinaryUtility
     * @inject
     */
    protected $cloudinaryUtility;

//    /**
//     * @param \TYPO3\CMS\Extbase\Service\ImageService $imageService
//     */
//    public function injectImageService(\TYPO3\CMS\Extbase\Service\ImageService $imageService)
//    {
//        $this->imageService = $imageService;
//    }
//
//    /**
//     * @param \Sinso\Cloudinary\Utility\CloudinaryUtility $cloudinaryUtility
//     */
//    public function injectCloudinaryUtility(\TYPO3\CMS\Extbase\Service\ImageService $cloudinaryUtility)
//    {
//        $this->cloudinaryUtility = $cloudinaryUtility;
//    }

    /**
     * @return void
     */
    public function initializeArguments(): void
    {

        $this->registerArgument('src', 'string', 'a path to a file, a combined FAL identifier or an uid (int). If $treatIdAsReference is set, the integer is considered the uid of the sys_file_reference record. If you already got a FAL object, consider using the $image parameter instead')
            ->registerArgument('minWidth', 'int', 'minimum width of the image', false, 100)
            ->registerArgument('maxWidth', 'int', 'maximum width of the image', false, 2000)
            ->registerArgument('maxImages', 'int', 'maximum amount of images generated by Cloudinary', false, 10)
            ->registerArgument('bytesStep', 'int', 'difference between filesizes of images generated by Cloudinary', false, 40000)
            ->registerArgument('aspectRatio', 'string', 'difference between filesizes of images generated by Cloudinary')
            ->registerArgument('gravity', 'string', 'define the focus for the transformation in Cloudinary')
            ->registerArgument('crop', 'string', 'define cropping for Cloudinary')
            ->registerArgument('treatIdAsReference', 'bool', 'given src argument is a sys_file_reference record')
            ->registerArgument('image', FileInterface::class, 'a FAL object');

        $src = null, $minWidth = 100, $maxWidth = 2000, $maxImages = 10, $bytesStep = 40000, $aspectRatio = null, $gravity = null, $crop = null, $treatIdAsReference = false, $image = null
    }

    /**
     * Resizes a given image (if required) and renders the respective img tag
     *
     * @see https://docs.typo3.org/typo3cms/TyposcriptReference/ContentObjects/Image/
     *
     * @throws \TYPO3\CMS\Fluid\Core\ViewHelper\Exception
     * @return string Rendered tag
     */
    public function render()
    {
        $src = $this->arguments['src'];
        $minWidth = $this->arguments['minWidth'];
        $maxWidth = $this->arguments['maxWidth'];
        $maxImages = $this->arguments['maxImages'];
        $bytesStep = $this->arguments['bytesStep'];
        $aspectRatio = $this->arguments['aspectRatio'];
        $gravity = $this->arguments['gravity'];
        $crop = $this->arguments['crop'];
        $treatIdAsReference = $this->arguments['treatIdAsReference'];
        $image = $this->arguments['image'];

        if (is_null($src) && is_null($image) || !is_null($src) && !is_null($image)) {
            throw new \TYPO3\CMS\Fluid\Core\ViewHelper\Exception('You must either specify a string src or a File object.', 1382284106);
        }

        try {
//            ResourceFactory::getInstance()->getFileObjectByStorageAndIdentifier()

            $image = $this->imageService->getImage($src, $image, $treatIdAsReference);
            $preCrop = $image instanceof FileReference ? $image->getProperty('crop') : null;
            $processingInstructions = [
                'crop' => $preCrop,
            ];
            $processedImage = $this->imageService->applyProcessingInstructions($image, $processingInstructions);
            $imageUri = $this->imageService->getImageUri($processedImage);

            try {
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
                $cloudinarySizes = $this->cloudinaryUtility->getSizesAttribute($breakpointData);
                $cloudinarySrcset = $this->cloudinaryUtility->getSrcsetAttribute($breakpointData);
                $cloudinarySrc = $this->cloudinaryUtility->getSrc($breakpointData);

                $this->tag->addAttribute('sizes', $cloudinarySizes);
                $this->tag->addAttribute('srcset', $cloudinarySrcset);
                $this->tag->addAttribute('src', $cloudinarySrc);
            } catch (\Exception $e) {
                $this->tag->addAttribute('src', $imageUri);
            }

            $alt = $image->getProperty('alternative');
            $title = $image->getProperty('title');

            // The alt-attribute is mandatory to have valid html-code, therefore add it even if it is empty
            if (empty($this->arguments['alt'])) {
                $this->tag->addAttribute('alt', $alt);
            }
            if (empty($this->arguments['title']) && $title) {
                $this->tag->addAttribute('title', $title);
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

        return $this->tag->render();
    }

    /**
     * Initialize arguments.
     *
     * @return void
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerUniversalTagAttributes();
        $this->registerTagAttribute('alt', 'string', 'Specifies an alternate text for an image', false);
        $this->registerTagAttribute('ismap', 'string', 'Specifies an image as a server-side image-map. Rarely used. Look at usemap instead', false);
        $this->registerTagAttribute('longdesc', 'string', 'Specifies the URL to a document that contains a long description of an image', false);
        $this->registerTagAttribute('usemap', 'string', 'Specifies an image as a client-side image-map', false);
    }
}