<?php

namespace Visol\Cloudinary\Utility;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CloudinaryFileUtility
{

    public static function getTemporaryFile($storageUid, string $fileIdentifier): string
    {
        $temporaryFileNameAndPath =
            Environment::getVarPath() .
            DIRECTORY_SEPARATOR .
            'transient' .
            DIRECTORY_SEPARATOR .
            $storageUid .
            DIRECTORY_SEPARATOR .
            $fileIdentifier;

        $temporaryFolder = GeneralUtility::dirname($temporaryFileNameAndPath);

        if (!is_dir($temporaryFolder)) {
            GeneralUtility::mkdir_deep($temporaryFolder);
        }
        return $temporaryFileNameAndPath;
    }

}
