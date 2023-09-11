<?php

namespace Visol\Cloudinary\Services;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use RuntimeException;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use Visol\Cloudinary\Driver\CloudinaryDriver;
use Visol\Cloudinary\Utility\MimeTypeUtility;

class CloudinaryPathService
{
    protected ?ResourceStorage $storage;

    protected array $storageConfiguration;

    protected array $cachedCloudinaryResources = [];

    public function __construct(array|ResourceStorage $storageObjectOrConfiguration)
    {
        if ($storageObjectOrConfiguration instanceof ResourceStorage) {
            $this->storage = $storageObjectOrConfiguration;
            $this->storageConfiguration = $this->storage->getConfiguration();
        } else {
            $this->storageConfiguration = $storageObjectOrConfiguration;
        }
    }

    public function computeFileIdentifier(array $cloudinaryResource): string
    {
        $fileIdentifier = $cloudinaryResource['resource_type'] === 'raw'
            ? $cloudinaryResource['public_id']
            : $cloudinaryResource['public_id'] . '.' . $cloudinaryResource['format'];

        return self::stripBasePathFromIdentifier(
            DIRECTORY_SEPARATOR . $fileIdentifier,
            $this->getBasePath()
        );
    }

    public function computeFolderIdentifier(string $cloudinaryFolder): string
    {
        return self::stripBasePathFromIdentifier(
            DIRECTORY_SEPARATOR . $cloudinaryFolder,
            $this->getBasePath()
        );
    }

    /**
     * Return the basePath.
     * The basePath never has a trailing slash
     */
    protected function getBasePath(): string
    {
        $basePath = (string)$this->storageConfiguration['basePath'];
        return $basePath
            ? DIRECTORY_SEPARATOR . trim($basePath, DIRECTORY_SEPARATOR)
            : '';
    }

    public function computeCloudinaryPublicId(string $fileIdentifier): string
    {
        $fileExtension = $this->getFileExtension($fileIdentifier);
        $publicId = in_array($fileExtension, CloudinaryDriver::$knownRawFormats)
            ? $fileIdentifier
            : $this->stripFileExtension($fileIdentifier);

        return $this->normalizeCloudinaryPublicId($publicId);
    }

    public function computeCloudinaryFolderPath(string $folderIdentifier): string
    {
        return $this->normalizeCloudinaryPublicId($folderIdentifier);
    }

    public function normalizeCloudinaryPublicId(string $cloudinaryPublicId): string
    {
        $normalizedCloudinaryPath = trim($cloudinaryPublicId, DIRECTORY_SEPARATOR);
        $basePath = $this->getBasePath();
        return $basePath
            ? trim($basePath . DIRECTORY_SEPARATOR . $normalizedCloudinaryPath, DIRECTORY_SEPARATOR)
            : $normalizedCloudinaryPath;
    }

    public function getMimeType(array $fileInfo): string
    {
        return $fileInfo['mime_type'] ?? '';
    }

    public function getResourceType(string $fileIdentifier): string
    {
        try {
            // Find the resource type from the cloudinary resource.
            $cloudinaryResource = $this->getCloudinaryResource($fileIdentifier);
        } catch (RuntimeException $e) {
            $fileExtension = $this->getFileExtension($fileIdentifier);
            $mimeType = MimeTypeUtility::guessMimeType($fileExtension);

            // Get the primary resource type from the mime type such as image, video, audio, raw
            $type = explode('/', $mimeType)[0];

            // Equivalence table.
            if ($type === 'application') {
                $type = 'image';
            } elseif ($type === 'text') {
                $type = 'raw';
            }
            return $type;
        }
        return $cloudinaryResource['resource_type'] ?? 'unknown';
    }

    protected function getCloudinaryResource(string $fileIdentifier): array
    {
        $possiblePublicId = $this->stripFileExtension($fileIdentifier);

        // We cache the resource for performance reasons.
        if (!isset($this->cachedCloudinaryResources[$possiblePublicId])) {

            // We need to check whether the public id really exists.
            $cloudinaryResourceService = GeneralUtility::makeInstance(
                CloudinaryResourceService::class,
                $this->storage
            );

            $cloudinaryResource = $cloudinaryResourceService->getResource($possiblePublicId);

            // Try to retrieve the cloudinary with the file identifier.
            // That will be the case for raw resources.
            if (!$cloudinaryResource) {
                $cloudinaryResource = $cloudinaryResourceService->getResource($fileIdentifier);
            }

            // Houston, we have a problem. The public id does not exist, meaning the file does not exist.
            if (!$cloudinaryResource) {
                throw new RuntimeException('Cloudinary resource not found for ' . $fileIdentifier, 1623157880);
            }

            $this->cachedCloudinaryResources[$possiblePublicId] = $cloudinaryResource;
        }

        return $this->cachedCloudinaryResources[$possiblePublicId];
    }

    protected function stripFileExtension(string $filename): string
    {
        $pathParts = PathUtility::pathinfo($filename);

        if ($pathParts['dirname'] === '.') {
            return $pathParts['filename'];
        }

        return $pathParts['dirname'] . DIRECTORY_SEPARATOR . $pathParts['filename'];
    }

    protected function getFileExtension(string $filename): string
    {
        $pathInfo = PathUtility::pathinfo($filename);
        return $pathInfo['extension'] ?? '';
    }

    public static function stripBasePathFromIdentifier(string $identifierWithBasePath, string $basePath): string
    {
        return preg_replace(
            sprintf(
                '/^%s($|%s)/',
                preg_quote($basePath, '/'),
                preg_quote(DIRECTORY_SEPARATOR, '/')
            ),
            '',
            $identifierWithBasePath
        );
    }
}
