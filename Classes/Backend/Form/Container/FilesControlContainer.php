<?php

namespace Visol\Cloudinary\Backend\Form\Container;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Resource\Filter\FileExtensionFilter;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Events\CustomFileSelectorsEvent;

class FilesControlContainer extends \TYPO3\CMS\Backend\Form\Container\FilesControlContainer
{
    protected function getFileSelectors(array $inlineConfiguration, FileExtensionFilter $fileExtensionFilter): array
    {
        $currentStructureDomObjectIdPrefix = $this->inlineStackProcessor->getCurrentStructureDomObjectIdPrefix($this->data['inlineFirstPid']);
        $objectPrefix = $currentStructureDomObjectIdPrefix . '-' . 'sys_file_reference';

        $controls = parent::getFileSelectors($inlineConfiguration, $fileExtensionFilter);
        $event = GeneralUtility::makeInstance(EventDispatcherInterface::class)->dispatch(
            new CustomFileSelectorsEvent(
                $inlineConfiguration,
                $fileExtensionFilter,
                $objectPrefix,
                $controls,
                $this->javaScriptModules,
            )
        );
        $controls = $event->getControls();
        $this->javaScriptModules = $event->getJavaScriptModules();

        return $controls;
    }
}
