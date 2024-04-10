<?php

namespace Visol\Cloudinary\Services;

use Cloudinary\Transformation\Crop;
use Cloudinary\Transformation\CropMode;
use Cloudinary\Transformation\Scale;
use TYPO3\CMS\Core\Imaging\ImageManipulation\Area;
use TYPO3\CMS\Core\Resource\Processing\TaskInterface;
use Visol\Cloudinary\Exceptions\NoCloudinaryTransformation;

class ProcessingTaskConverter
{
    public function convertProcessingConfiguration(
        TaskInterface $task,
    ): array {
        $processingConfiguration = $task->getConfiguration();

        if (isset($processingConfiguration['maskImages'])) {
            throw new NoCloudinaryTransformation('Unable to maskImages', 1712760855588);
        }

        $transformations = [];

        $cropTransformation = $this->getCropTransformation($task);
        if (isset($cropTransformation)) {
            $transformations[] = $cropTransformation;
        }

        $scaleTransformation = $this->getScaleTransformation($task);
        if (isset($scaleTransformation)) {
            $transformations[] = $scaleTransformation;
        }

        return $transformations;
    }

    protected function getCropTransformation(TaskInterface $task): ?Crop
    {
        $processingConfiguration = $task->getConfiguration();
        $crop = $processingConfiguration['crop'] ?? null;
        if (!isset($crop)) {
            return null;
        }

        if (!$crop instanceof Area) {
            throw new NoCloudinaryTransformation('Crop is not of type area', 1712760863923);
        }

        return new Crop(
            CropMode::CROP,
            (int)$crop->getWidth(),
            (int)$crop->getHeight(),
            null,
            (int)$crop->getOffsetLeft(),
            (int)$crop->getOffsetTop(),
        );
    }

    protected function getScaleTransformation(TaskInterface $task): ?Scale
    {
        $processingConfiguration = $task->getConfiguration();

        $setProperties = array_filter(array_intersect_key($processingConfiguration, array_flip(['width', 'height'])));
        $maxProperties = array_filter(array_intersect_key($processingConfiguration, array_flip(['maxWidth', 'maxHeight'])));
        $minProperties = array_filter(array_intersect_key($processingConfiguration, array_flip(['minWidth', 'minHeight'])));

        if (!empty($minProperties)) {
            throw new NoCloudinaryTransformation('min* is not yet supported', 1712780611488);
        }
        if (empty(array_merge($maxProperties, $setProperties))) {
            return null;
        }

        foreach ($maxProperties as $propertyName => $value) {
            $equivalentSetPropertyValue = sprintf('%sm', (int)$value);
            $equivalentSetPropertyName = strtolower(str_replace('max', '', $propertyName));
            if (($setProperties[$equivalentSetPropertyName] ?? '') === $equivalentSetPropertyValue) {
                unset($setProperties[$equivalentSetPropertyName]);
            }
        }

        if (!(empty($setProperties) || empty($maxProperties))) {
            throw new NoCloudinaryTransformation('max* and width/height at the same time is not yet supported', 1712780615883);
        }

        if (!empty($maxProperties)) {
            $scaleTransformation = [
                'crop' => count($maxProperties) >= 2 ? CropMode::FIT : CropMode:: FILL,
            ];
            foreach ($maxProperties as $key => $value) {
                $scaleTransformation[strtolower(str_replace('max', '', $key))] = (int)$value;
            }

            return Scale::fromParams($scaleTransformation);
        }

        $scaleTransformation = [
            'crop' => CropMode::SCALE,
        ];

        foreach (['width', 'height'] as $property) {
            $stringValue = $processingConfiguration[$property] ?? '';
            preg_match('/^(\d*)(c)?/', $stringValue, $matches);
            $pixels = $matches[1] ?? 0;
            if ((int)$pixels > 0) {
                $scaleTransformation[$property] = $pixels;
            }
            if ($matches[2] === 'c') {
                $scaleTransformation['crop'] = CropMode::FILL;
            }
        }

        return Scale::fromParams($scaleTransformation);
    }
}