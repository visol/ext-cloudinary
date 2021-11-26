<?php
namespace  Visol\Cloudinary\Hook;

use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\File\ExtendedFileUtility;
use TYPO3\CMS\Core\Utility\File\ExtendedFileUtilityProcessDataHookInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Domain\Repository\ExplicitDataCacheRepository;
use Visol\Cloudinary\Driver\CloudinaryFastDriver;
use Visol\Cloudinary\Services\CloudinaryImageService;

/**
 * Extracts metadata after uploading a file.
 */
class FileUploadHook implements ExtendedFileUtilityProcessDataHookInterface
{
    /**
     * @param string $action The action
     * @param array $cmdArr The parameter sent to the action handler
     * @param array $result The results of all calls to the action handler
     * @param ExtendedFileUtility $pObj The parent object
     * @return void
     */
    public function processData_postProcessAction($action, array $cmdArr, array $result, ExtendedFileUtility $pObj): void
    {
        if ($action === 'replace' || ($action === 'upload' && $pObj->getExistingFilesConflictMode() === DuplicationBehavior::REPLACE)) {
            if (!isset($result[0]) && !isset($result[0][0])) {
                return;
            }
            /** @var File $file */
            $file = $result[0][0];
            if ($file->getStorage()->getDriverType() !== CloudinaryFastDriver::DRIVER_TYPE) {
                return;
            }
            $cloudinaryImageService = GeneralUtility::makeInstance(CloudinaryImageService::class);
            $publicId = $cloudinaryImageService->getPublicIdForFile($file);
            $explicitDataCacheRepository = GeneralUtility::makeInstance(ExplicitDataCacheRepository::class);
            $explicitDataCacheRepository->delete($file->getStorage()->getUid(), $publicId);
        }
    }
}
