<?php

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

namespace Visol\Cloudinary\Services;

use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\CloudinaryFactory;

class CloudinaryUploadService
{
    protected string $emergencyFileIdentifier = '/typo3conf/ext/cloudinary/Resources/Public/Images/emergency-placeholder-image.png';

    protected ResourceStorage $storage;

    public function __construct(ResourceStorage $storage = null)
    {
        $this->storage = $storage ?: CloudinaryFactory::getDefaultStorage();
    }

    public function uploadLocalFile(string $fileIdentifier): File
    {
        // Cleanup file identifier in case
        $fileIdentifier = $this->cleanUp($fileIdentifier);

        if (!$this->fileExists($fileIdentifier)) {
            $fileIdentifier = $this->emergencyFileIdentifier;
            $this->error(
                'I am using a default emergency placeholder file since I could not find file ' . $fileIdentifier,
            );
        }

        // Fetch the file if existing or create one
        return $this->storage->hasFile($fileIdentifier)
            ? $this->storage->getFile($fileIdentifier)
            : $this->storage->addFile(
                Environment::getPublicPath() . DIRECTORY_SEPARATOR . ltrim($fileIdentifier, DIRECTORY_SEPARATOR),
                CloudinaryFactory::getFolder(GeneralUtility::dirname($fileIdentifier)),
            );
    }

    public function getEmergencyFile(): File
    {
        return $this->uploadLocalFile($this->emergencyFileIdentifier);
    }

    protected function cleanUp(string $fileIdentifier): string
    {
        return DIRECTORY_SEPARATOR . ltrim($fileIdentifier, DIRECTORY_SEPARATOR);
    }

    protected function fileExists(string $fileIdentifier): bool
    {
        $fileNameAndPath =
            Environment::getPublicPath() . DIRECTORY_SEPARATOR . ltrim($fileIdentifier, DIRECTORY_SEPARATOR);
        return is_file($fileNameAndPath);
    }

    protected function error(string $message, array $arguments = [], array $data = []): void
    {
        /** @var Logger $logger */
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $logger->log(LogLevel::ERROR, vsprintf($message, $arguments), $data);
    }
}
