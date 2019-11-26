<?php

namespace Visol\Cloudinary\Utility;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

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
        return sprintf(
            '%s.%s',
            DIRECTORY_SEPARATOR . ltrim($cloudinaryResource['public_id'], DIRECTORY_SEPARATOR),
            $cloudinaryResource['format']
        );
    }

    /**
     * @param string $fileIdentifier
     * @return string
     */
    public static function computeCloudinaryPublicId(string $fileIdentifier): string
    {
        $fileIdentifierWithoutExtension = self::stripExtension($fileIdentifier);
        return self::computeCloudinaryPath($fileIdentifierWithoutExtension);
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
        // Other possible way of computing the file extension using TYPO3 API
        // '.' . PathUtility::pathinfo($filename, PATHINFO_EXTENSION)
        return preg_replace('/\\.[^.\\s]{3,4}$/', '', $filename);
    }
}
