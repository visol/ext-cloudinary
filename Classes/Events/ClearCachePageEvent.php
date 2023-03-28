<?php

namespace Visol\Cloudinary\Events;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

final class ClearCachePageEvent
{
    protected array $tags = [];

    public function __construct(array $tags)
    {
        $this->tags = $tags;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): ClearCachePageEvent
    {
        $this->tags = $tags;
        return $this;
    }

}
