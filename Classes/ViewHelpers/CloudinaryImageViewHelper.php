<?php

namespace Visol\Cloudinary\ViewHelpers;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Services\CloudinaryPathService;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * Resizes a given image (if required) and renders the respective img tag
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
     * @var \Visol\Cloudinary\Utility\CloudinaryUtility
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
     * @param \Visol\Cloudinary\Utility\CloudinaryUtility $cloudinaryUtility
     */
    public function injectCloudinaryUtility(\TYPO3\CMS\Extbase\Service\ImageService $cloudinaryUtility)
    {
        $this->cloudinaryUtility = $cloudinaryUtility;
    }

    /**
     * @return void
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerUniversalTagAttributes();
        $this->registerTagAttribute('alt', 'string', 'Specifies an alternate text for an image', false);
        $this->registerTagAttribute('ismap', 'string', 'Specifies an image as a server-side image-map. Rarely used. Look at usemap instead', false);
        $this->registerTagAttribute('longdesc', 'string', 'Specifies the URL to a document that contains a long description of an image', false);
        $this->registerTagAttribute('usemap', 'string', 'Specifies an image as a client-side image-map', false);

        $this->registerArgument('src', 'string', 'a path to a file, a combined FAL identifier or an uid (int). If $treatIdAsReference is set, the integer is considered the uid of the sys_file_reference record. If you already got a FAL object, consider using the $image parameter instead')
            ->registerArgument('minWidth', 'int', 'minimum width of the image', false, 100)
            ->registerArgument('maxWidth', 'int', 'maximum width of the image', false, 2000)
            ->registerArgument('maxImages', 'int', 'maximum amount of images generated by Cloudinary', false, 10)
            ->registerArgument('bytesStep', 'int', 'difference between filesizes of images generated by Cloudinary', false, 40000)
            ->registerArgument('aspectRatio', 'string', 'difference between filesizes of images generated by Cloudinary')
            ->registerArgument('gravity', 'string', 'define the focus for the transformation in Cloudinary')
            ->registerArgument('crop', 'string', 'define cropping for Cloudinary')
            ->registerArgument('treatIdAsReference', 'bool', 'given src argument is a sys_file_reference record', false)
            ->registerArgument('options', 'array', 'Possible cloudinary options to transform / crop the image', false, [])
            ->registerArgument('image', FileInterface::class, 'a FAL object');
    }

    /**
     * Resize a given image (if required) and renders the respective img tag
     *
     * @see https://docs.typo3.org/typo3cms/TyposcriptReference/ContentObjects/Image/
     *
     * @throws \TYPO3\CMS\Fluid\Core\ViewHelper\Exception
     * @return string Rendered tag
     */
    public function render(): string
    {
        $src = $this->arguments['src'];
        $image = $this->arguments['image'];

        if (is_null($src) && is_null($image) || !is_null($src) && !is_null($image)) {
            throw new \TYPO3\CMS\Fluid\Core\ViewHelper\Exception('You must either specify a string src or a File object.', 1382284106);
        }

        try {

            /** @var FileInterface $image */
            $image = $this->imageService->getImage(
                $src,
                $image,
                $this->arguments['treatIdAsReference']
            );

            try {

                $publicId = $this->getCloudinaryPathService($image->getStorage())
                    ->computeCloudinaryPublicId($image->getIdentifier());

                $options = $this->cloudinaryUtility->generateOptionsFromSettings(
                    [
                        'bytesStep' => $this->arguments['bytesStep'],
                        'minWidth' => $this->arguments['minWidth'],
                        'maxWidth' => $this->arguments['maxWidth'],
                        'maxImages' => $this->arguments['maxImages'],
                        'aspectRatio' => $this->arguments['aspectRatio'],
                        'gravity' => $this->arguments['gravity'],
                        'crop' => $this->arguments['crop'],
                    ]
                );

                // True means process with default options
                // False means we have a cloudinary $options override
                if (empty($this->arguments['options'])) {
                    $breakpoints = $this->cloudinaryUtility->getResponsiveBreakpointData($publicId, $options);
                    $publicUrl = $image->getPublicUrl();
                } else {
                    // Apply custom transformation to breakpoint images
                    $options['responsive_breakpoints']['transformation'] = $this->arguments['options'];
                    $breakpoints = $this->cloudinaryUtility->getResponsiveBreakpointData($publicId, $options);

                    $resource = $this->cloudinaryUtility->getCloudinaryProcessedResource(
                        $publicId,
                        [
                            'type' => 'upload',
                            'eager' => [$this->arguments['options']],
                        ]
                    );
                    $publicUrl = $resource['secure_url'];
                }

                $cloudinarySizes = $this->cloudinaryUtility->getSizesAttribute($breakpoints);
                $cloudinarySrcset = $this->cloudinaryUtility->getSrcsetAttribute($breakpoints);

                $this->tag->addAttribute('sizes', $cloudinarySizes);
                $this->tag->addAttribute('srcset', $cloudinarySrcset);
                $this->tag->addAttribute('src', $publicUrl);
            } catch (\Exception $e) {
                $this->tag->addAttribute('src', $publicUrl);
            }

            $alt = $image->getProperty('alternative');
            $title = $image->getProperty('title');

            // The alt-attribute is mandatory to have valid html-code, therefore add it even if it is empty
            $this->tag->addAttribute('alt', $alt);
            if (!empty($this->arguments['title'])) {
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
     * @param ResourceStorage $storage
     *
     * @return object|CloudinaryPathService
     */
    protected function getCloudinaryPathService(ResourceStorage $storage)
    {
        return GeneralUtility::makeInstance(
            CloudinaryPathService::class,
            $storage
        );
    }
}
