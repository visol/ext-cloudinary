<?php

namespace Visol\Cloudinary\Utility;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Class CloudinaryPathUtility
 */
class CloudinaryPathUtility
{

    /**
     * @param array $cloudinaryResource
     * @return string
     */
    public static function computeFileIdentifier(array $cloudinaryResource): string
    {
        $baseFileName = DIRECTORY_SEPARATOR . $cloudinaryResource['public_id'];
        $extension = $cloudinaryResource['resource_type'] === 'image'
            ? '.' . $cloudinaryResource['format'] // the format (or extension) is only returned for images.
            : '';
        return $baseFileName . $extension;
    }

    /**
     * @param string $fileIdentifier
     * @return string
     */
    public static function computeCloudinaryPublicId(string $fileIdentifier, array $fileInfo = []): string
    {
        $normalizedFileIdentifier = !isset($fileInfo['mime_type']) || self::isImage($fileInfo['mime_type'])
            ? self::stripExtension($fileIdentifier)
            : $fileIdentifier;

        return self::computeCloudinaryPath($normalizedFileIdentifier);
    }

    /**
     * @param string $fileIdentifier
     * @return string
     */
    public static function computeCloudinaryPath(string $fileIdentifier): string
    {
        return trim($fileIdentifier, DIRECTORY_SEPARATOR);
    }

    /**
     * @param $filename
     * @return string
     */
    protected static function stripExtension($filename): string
    {
        $pathParts = PathUtility::pathinfo($filename);
        return $pathParts['dirname'] . DIRECTORY_SEPARATOR . $pathParts['filename'];
    }

    /**
     * @param string $folderName
     * @param string $folderIdentifier
     * @return string
     */
    public static function normalizeFolderNameAndPath(string $folderName, string $folderIdentifier): string
    {
        return self::normalizeFolderPath($folderIdentifier) . DIRECTORY_SEPARATOR . $folderName;
    }

    /**
     * @param string $folderIdentifier
     * @return string
     */
    public static function normalizeFolderPath(string $folderIdentifier): string
    {
        return trim($folderIdentifier, DIRECTORY_SEPARATOR);
    }

    /**
     * @param string $mimeType
     * @return bool
     */
    protected static function isImage(string $mimeType): bool
    {
        return (bool)strstr($mimeType, 'image/');
    }
}
