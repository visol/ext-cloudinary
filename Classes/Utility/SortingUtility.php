<?php

namespace Visol\Cloudinary\Utility;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

/**
 * Class SortingUtility
 * @deprecated
 */
class SortingUtility
{

    /**
     * @param array $a
     * @param array $b
     * @return int
     */
    public static function sortByTimeStampAsc(array $a, array $b): int
    {
        $valueA = strtotime($a['created_at']);
        $valueB = strtotime($b['created_at']);
        if ($valueA === $valueB) {
            return 0;
        }
        return ($valueA < $valueB) ? -1 : 1;
    }

    /**
     * @param array $a
     * @param array $b
     * @return int
     */
    public static function sortByTimeStampDesc(array $a, array $b): int
    {
        $valueA = strtotime($a['created_at']);
        $valueB = strtotime($b['created_at']);
        if ($valueA === $valueB) {
            return 0;
        }
        return ($valueA > $valueB) ? -1 : 1;
    }

    /**
     * @param array $a
     * @param array $b
     * @return int
     */
    public static function sortByFileNameAsc(array $a, array $b): int
    {
        $valueA = strtolower($a['filename']);
        $valueB = strtolower($b['filename']);
        if ($valueA === $valueB) {
            return 0;
        }
        return ($valueA < $valueB) ? -1 : 1;
    }

    /**
     * @param array $a
     * @param array $b
     * @return int
     */
    public static function sortByFileNameDesc(array $a, array $b): int
    {
        $valueA = strtolower($a['filename']);
        $valueB = strtolower($b['filename']);
        if ($valueA === $valueB) {
            return 0;
        }
        return ($valueA > $valueB) ? -1 : 1;
    }

    /**
     * Sort a multidimensional array by key in ascending order
     * Credits: https://stackoverflow.com/a/4501406
     *
     * @param $array The input array
     * @return bool Returns true on success or false on failure.
     */
    public static function ksort_recursive(array &$array): bool
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::ksort_recursive($value);
            }
        }
        return ksort($array);
    }
}
