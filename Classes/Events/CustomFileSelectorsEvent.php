<?php

namespace Visol\Cloudinary\Events;

use TYPO3\CMS\Core\Resource\Filter\FileExtensionFilter;

class CustomFileSelectorsEvent
{
    public function __construct(
        protected readonly array $inlineConfiguration,
        protected readonly FileExtensionFilter $fileExtensionFilter,
        protected readonly string $objectPrefix,
        protected array $controls,
        protected array $javaScriptModules,
    ) {}

    public function getInlineConfiguration(): array
    {
        return $this->inlineConfiguration;
    }

    public function getFileExtensionFilter(): FileExtensionFilter
    {
        return $this->fileExtensionFilter;
    }

    public function getObjectPrefix(): string
    {
        return $this->objectPrefix;
    }

    public function getControls(): array
    {
        return $this->controls;
    }

    public function getJavaScriptModules(): array
    {
        return $this->javaScriptModules;
    }

    public function setControls(array $controls): void
    {
        $this->controls = $controls;
    }

    public function setJavaScriptModules(array $javaScriptModules): void
    {
        $this->javaScriptModules = $javaScriptModules;
    }
}