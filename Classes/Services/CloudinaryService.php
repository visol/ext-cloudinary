<?php

namespace Visol\Cloudinary\Services;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Resource\ResourceStorage;

/**
 * Class CloudinaryService
 */
class CloudinaryService
{


    /**
     * @var ResourceStorage
     */
    protected $storage;

    /**
     * CloudinaryScanService constructor.
     *
     * @param ResourceStorage $storage
     */
    public function __construct(ResourceStorage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Cloudinary to FAL identifier
     *
     * @param array $cloudinaryResource
     *
     * @return string
     */
    public function computeFileIdentifier(array $cloudinaryResource): string
    {
        $extension = $cloudinaryResource['resource_type'] === 'image'
            ? '.' . $cloudinaryResource['format'] // the format (or extension) is only returned for images.
            : '';

        $rawFileIdentifier = DIRECTORY_SEPARATOR . $cloudinaryResource['public_id'] . $extension;
        return str_replace($this->getBasePath(), '', $rawFileIdentifier);
    }

    /**
     * @return string
     */
    protected function getBasePath(): string
    {
        $basePath = (string)$this->storage->getConfiguration()['basePath'];
        return $basePath
            ? DIRECTORY_SEPARATOR . trim($basePath, DIRECTORY_SEPARATOR)
            : '';
    }

    /**
     * FAL to Cloudinary identifier
     *
     * @param string $fileIdentifier
     *
     * @return string
     */
    public function computeCloudinaryPublicId(string $fileIdentifier): string
    {
        $normalizedFileIdentifier = $this->guessIsImage($fileIdentifier)
            ? $this->stripExtension($fileIdentifier)
            : $fileIdentifier;

        return $this->normalizeCloudinaryPath($normalizedFileIdentifier);
    }

    /**
     * FAL to Cloudinary identifier
     *
     * @param string $folderIdentifier
     *
     * @return string
     */
    public function computeCloudinaryFolderPath(string $folderIdentifier): string
    {
        return $this->normalizeCloudinaryPath($folderIdentifier);
    }

    /**
     * @param string $cloudinaryPath
     *
     * @return string
     */
    public function normalizeCloudinaryPath(string $cloudinaryPath): string
    {
        $normalizedCloudinaryPath = trim($cloudinaryPath, DIRECTORY_SEPARATOR);
        $basePath = $this->getBasePath();
        return $basePath
            ? trim($basePath . DIRECTORY_SEPARATOR . $normalizedCloudinaryPath, DIRECTORY_SEPARATOR)
            : $normalizedCloudinaryPath;
    }

    /**
     * @param array $fileInfo
     *
     * @return string
     */
    public function getMimeType(array $fileInfo): string
    {
        return isset($fileInfo['mime_type'])
            ? $fileInfo['mime_type']
            : '';
    }

    /**
     * @param string $fileIdentifier
     *
     * @return string
     */
    public function getResourceType(string $fileIdentifier): string
    {
        return $this->guessIsImage($fileIdentifier)
            ? 'image'
            : 'raw';
    }

    /**
     * See if that is OK like that. The alternatives requires to "heavy" processing
     * like downloading the file to check the mime time or use the API SDK to fetch whether
     * we are in presence of an image.
     *
     * @param string $fileIdentifier
     *
     * @return bool
     */
    protected function guessIsImage(string $fileIdentifier)
    {
        $extension = strtolower(PathUtility::pathinfo($fileIdentifier, PATHINFO_EXTENSION));
        $commonMimeTypes = [
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            'webp' => 'image/image/webp',
        ];

        return isset($commonMimeTypes[$extension]);
    }

    /**
     * @param $filename
     *
     * @return string
     */
    protected function stripExtension($filename): string
    {
        $pathParts = PathUtility::pathinfo($filename);
        return $pathParts['dirname'] . DIRECTORY_SEPARATOR . $pathParts['filename'];
    }
}
