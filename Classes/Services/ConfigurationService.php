<?php

namespace Visol\Cloudinary\Services;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */


/**
 * Class ConfigurationService
 */
class ConfigurationService
{
    /**
     * @var array
     */
    protected $configuration = [];

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
        $value = '';
        if (preg_match('/%(.*)%/', $key, $matches)) {
            $value = (string)getenv($matches[1]);
        } elseif(isset($this->configuration[$key])) {
            $value = (string)$this->configuration[$key];
        }
        return $value;
    }
}
