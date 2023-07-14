<?php

namespace Visol\Cloudinary\Services;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */


class ConfigurationService
{
    protected array $configuration = [];

    /**
     * ConfigurationService constructor.
     */
    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * We retrieve / compute the final configuration
     */
    public function get(string $key): string
    {
        $rawValue = $this->configuration[$key] ?? '';
        $value = trim((string)$rawValue);
        if (preg_match('/^%\w+\((.*)\)%$/', $value, $matches) || preg_match('/^%(.*)%$/', $value, $matches)) {
            $value = getenv($matches[1]);

            if ($value === false) {
                throw new \RuntimeException(sprintf('No value found for environment variable "%s"', $matches[1]), 1626948978);
            }

            $value = (string)$value;
        }
        return $value;
    }
}
