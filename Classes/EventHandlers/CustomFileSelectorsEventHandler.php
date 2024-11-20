<?php

namespace Visol\Cloudinary\EventHandlers;

use Visol\Cloudinary\Backend\Form\CloudinaryFileSelector;
use Visol\Cloudinary\Events\CustomFileSelectorsEvent;

class CustomFileSelectorsEventHandler
{
    public function __construct(
        protected CloudinaryFileSelector $cloudinaryFileSelector,
    ) {}

    public function __invoke(CustomFileSelectorsEvent $event): void
    {
        $result = $this->cloudinaryFileSelector->renderCloudinaryFileSelectors(
            $event->getObjectPrefix(),
        );
        $event->setControls(array_merge(
            $event->getControls(),
            $result['buttons'],
        ));
        $event->setJavaScriptModules(array_merge(
            $event->getJavaScriptModules(),
            $result['javaScriptModules'],
        ));
    }
}
