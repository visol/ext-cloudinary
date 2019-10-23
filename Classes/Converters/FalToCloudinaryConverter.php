<?php

namespace Sinso\Cloudinary\Converters;

/*
 * This file is part of the Sinso/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

/**
 * Class FalToCloudinaryConverter
 * @package Sinso\Cloudinary\Converters
 */
class FalToCloudinaryConverter
{
    /**
     * @param string $fileIdentifier
     * @return string
     */
    public static function toPublicId(string $fileIdentifier): string
    {
        $fileIdentifierWithoutExtension = self::stripExtension($fileIdentifier);
        return self::toCloudinaryPath($fileIdentifierWithoutExtension);
    }

    /**
     * @param string $identifier
     * @return string
     */
    protected static function toCloudinaryPath(string $identifier): string
    {
        return trim($identifier, DIRECTORY_SEPARATOR);
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
