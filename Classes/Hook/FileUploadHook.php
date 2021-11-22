<?php
namespace  Visol\Cloudinary\Hook;

/*
 * This file is part of the Fab/Media project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\File\ExtendedFileUtility;
use TYPO3\CMS\Core\Utility\File\ExtendedFileUtilityProcessDataHookInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Domain\Repository\ExplicitDataCacheRepository;
use Visol\Cloudinary\Services\CloudinaryPathService;

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
            /** @var File $file */
            $file = $result[0][0];
            $explicitDataCacheRepository = GeneralUtility::makeInstance(ExplicitDataCacheRepository::class);
            $explicitDataCacheRepository->delete($file->getStorage()->getUid(), 'fileadmin' . str_replace('.' . $file->getExtension(), '', $file->getIdentifier()));
        }
    }
}
