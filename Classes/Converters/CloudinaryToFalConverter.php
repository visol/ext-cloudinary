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
 */
class CloudinaryToFalConverter
{

    /**
     * @param array $cloudinaryResource
     * @return string
     */
    public static function toFileIdentifier(array $cloudinaryResource): string
    {
        return sprintf(
            '%s.%s',
            DIRECTORY_SEPARATOR . ltrim($cloudinaryResource['public_id'], DIRECTORY_SEPARATOR),
            $cloudinaryResource['format']
        );
    }

}
