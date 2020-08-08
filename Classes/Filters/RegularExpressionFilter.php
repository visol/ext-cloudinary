<?php

namespace Visol\Cloudinary\Filters;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Resource\Driver\DriverInterface;

/**
 * Class RegularExpressionFilter
 */
class RegularExpressionFilter
{

    /**
     * @var string
     */
    protected static $regularExpression = '';

    /**
     * @param string $itemName
     * @param string $itemIdentifier
     * @param string $parentIdentifier
     * @param array $additionalInformation Additional information (driver dependent) about the inspected item
     * @param DriverInterface $driverInstance
     *
     * @return bool:int
     */
    public static function filter($itemName, $itemIdentifier, $parentIdentifier, array $additionalInformation, DriverInterface $driverInstance)
    {
        // early return if the regular expression is not defined
        if (!self::$regularExpression) {
            return true;
        }

        return preg_match(self::getFinalRegularExpression(), $itemIdentifier)
            ? true
            : -1;
    }

    /**
     * Gets the info whether the hidden files are also displayed currently
     *
     * @static
     * @return bool
     */
    protected static function getFinalRegularExpression()
    {
        return '#' . self::$regularExpression . '#';
    }

    /**
     * @param string $regularExpression
     */
    public static function setRegularExpression(string $regularExpression)
    {
        self::$regularExpression = $regularExpression;
    }
}
