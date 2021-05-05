<?php

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

namespace Visol\Cloudinary\Services;

use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\CloudinaryFactory;

/**
 * Class CloudinaryUploadService
 */
class CloudinaryUploadService
{

    /**
     * @var string
     */
    protected $emergencyFileIdentifier = '/typo3conf/ext/cloudinary/Resources/Public/Images/emergency-placeholder-image.png';

    /**
     * @var ResourceStorage
     */
    protected $storage;

    /**
     * @param ResourceStorage $storage
     */
    public function __construct(ResourceStorage $storage = null)
    {
        $this->storage = $storage
            ?: CloudinaryFactory::getDefaultStorage();
    }

    /**
     * @param string $fileIdentifier
     *
     * @return File|FileInterface
     */
    public function uploadLocalFile(string $fileIdentifier)
    {
        // Cleanup file identifier in case
        $fileIdentifier = $this->cleanUp($fileIdentifier);

        if (!$this->fileExists($fileIdentifier)) {
            $fileIdentifier = $this->emergencyFileIdentifier;
            $this->error('I am using a default emergency placeholder file since I could not find file ' . $fileIdentifier);
        }

        // Fetch the file if existing or create one
        return $this->storage->hasFile($fileIdentifier)
            ? $this->storage->getFile($fileIdentifier)
            : $this->storage->addFile(
                PATH_site . ltrim($fileIdentifier, DIRECTORY_SEPARATOR),
                CloudinaryFactory::getFolder(
                    GeneralUtility::dirname($fileIdentifier)
                )
            );
    }

    /**
     * @param string $fileIdentifier
     */
    protected function cleanUp(string $fileIdentifier)
    {
        return DIRECTORY_SEPARATOR . ltrim($fileIdentifier, DIRECTORY_SEPARATOR);
    }

    /**
     * @param string $fileIdentifier
     *
     * @return bool
     */
    protected function fileExists(string $fileIdentifier): bool
    {
        $fileNameAndPath = PATH_site . ltrim($fileIdentifier, DIRECTORY_SEPARATOR);
        return is_file($fileNameAndPath);
    }

    /**
     * @param string $message
     * @param array $arguments
     * @param array $data
     */
    protected function error(string $message, array $arguments = [], array $data = [])
    {
        /** @var \TYPO3\CMS\Core\Log\Logger $logger */
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $logger->log(
            LogLevel::ERROR,
            vsprintf($message, $arguments),
            $data
        );
    }
}
