<?php

namespace Visol\Cloudinary\Services;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Class CloudinaryPathService
 */
class CloudinaryPathService
{

    /**
     * @var array
     */
    protected $storageConfiguration;

    /**
     * CloudinaryPathService constructor.
     *
     * @param array $storageConfiguration
     */
    public function __construct(array $storageConfiguration)
    {
        $this->storageConfiguration = $storageConfiguration;
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
        $fileIdentifier = $cloudinaryResource['resource_type'] === 'raw'
            ? $cloudinaryResource['public_id']
            : $cloudinaryResource['public_id'] . '.' . $cloudinaryResource['format'];

        return self::stripBasePathFromIdentifier(
            DIRECTORY_SEPARATOR . $fileIdentifier,
            $this->getBasePath()
        );
    }

    /**
     * @param string $cloudinaryFolder
     *
     * @return string
     */
    public function computeFolderIdentifier(string $cloudinaryFolder): string
    {
        return self::stripBasePathFromIdentifier(
            DIRECTORY_SEPARATOR . $cloudinaryFolder,
            $this->getBasePath()
        );
    }

    /**
     * Return the basePath.
     * The basePath never has a trailing slash
     * @return string
     */
    protected function getBasePath(): string
    {
        $basePath = (string)$this->storageConfiguration['basePath'];
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
        $normalizedFileIdentifier = $this->guessIsImage($fileIdentifier) || $this->guessIsVideo($fileIdentifier)
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
        $resourceType = 'raw';
        if ($this->guessIsImage($fileIdentifier)) {
            $resourceType = 'image';
        } elseif ($this->guessIsVideo($fileIdentifier)) {
            $resourceType = 'video';
        }

        return $resourceType;
    }

    /**
     * @param array $cloudinaryResource
     *
     * @return string
     */
    public function guessMimeType(array $cloudinaryResource): string
    {
        $mimeType = '';
        if ($cloudinaryResource['format'] === 'pdf') {
            $mimeType = 'application/pdf';
        } elseif ($cloudinaryResource['format'] === 'jpg') {
            $mimeType = 'image/jpeg';
        } elseif ($cloudinaryResource['format'] === 'png') {
            $mimeType = 'image/png';
        } elseif ($cloudinaryResource['format'] === 'mp4') {
            $mimeType = 'video/mp4';
        }
        return $mimeType;
    }

    /**
     * @param string $fileIdentifier
     *
     * @return bool
     */
    protected function guessIsVideo(string $fileIdentifier)
    {
        $extension = strtolower(PathUtility::pathinfo($fileIdentifier, PATHINFO_EXTENSION));
        $rawExtensions = [
            'mp4',
            'mov',

            'mp3', // As documented @see https://cloudinary.com/documentation/image_upload_api_reference
        ];

        return in_array($extension, $rawExtensions, true);
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
        $imageExtensions = [
            'png',
            'jpe',
            'jpeg',
            'jpg',
            'gif',
            'bmp',
            'ico',
            'tiff',
            'tif',
            'svg',
            'svgz',
            'webp',

            'pdf', // Cloudinary handles pdf as image
        ];

        return in_array($extension, $imageExtensions, true);
    }

    /**
     * @param $filename
     *
     * @return string
     */
    protected function stripExtension(string $filename): string
    {
        $pathParts = PathUtility::pathinfo($filename);

        if ($pathParts['dirname'] === '.') {
            return $pathParts['filename'];
        }

        return $pathParts['dirname'] . DIRECTORY_SEPARATOR . $pathParts['filename'];
    }

    public static function stripBasePathFromIdentifier(string $identifierWithBasePath, string $basePath): string
    {
        return preg_replace(
            sprintf(
                '/^%s($|%s)/',
                preg_quote($basePath, '/'),
                preg_quote(DIRECTORY_SEPARATOR, '/')
            ),
            '',
            $identifierWithBasePath
        );
    }
}
