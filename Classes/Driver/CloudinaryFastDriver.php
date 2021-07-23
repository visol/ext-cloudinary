<?php

namespace Visol\Cloudinary\Driver;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Cloudinary;
use Cloudinary\Api;
use Cloudinary\Uploader;
use RuntimeException;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Type\File\FileInfo;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Resource\Driver\AbstractHierarchicalFilesystemDriver;
use TYPO3\CMS\Core\Resource\Exception;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use Visol\Cloudinary\Domain\Repository\ExplicitDataCacheRepository;
use Visol\Cloudinary\Services\CloudinaryFolderService;
use Visol\Cloudinary\Services\CloudinaryImageService;
use Visol\Cloudinary\Services\CloudinaryResourceService;
use Visol\Cloudinary\Services\CloudinaryPathService;
use Visol\Cloudinary\Services\CloudinaryTestConnectionService;
use Visol\Cloudinary\Services\ConfigurationService;

/**
 * Class CloudinaryFastDriver
 */
class CloudinaryFastDriver extends AbstractHierarchicalFilesystemDriver
{

    public const DRIVER_TYPE = 'VisolCloudinary';
    const ROOT_FOLDER_IDENTIFIER = '/';
    const UNSAFE_FILENAME_CHARACTER_EXPRESSION = '\\x00-\\x2C\\/\\x3A-\\x3F\\x5B-\\x60\\x7B-\\xBF';

    /**
     * The base URL that points to this driver's storage. As long is this is not set, it is assumed that this folder
     * is not publicly available
     *
     * @var string
     */
    protected $baseUrl = '';

    /**
     * Object permissions are cached here in subarrays like:
     * $identifier => ['r' => bool, 'w' => bool]
     *
     * @var array
     */
    protected $cachedPermissions = [];

    /**
     * @var \TYPO3\CMS\Core\Resource\ResourceStorage
     */
    protected $storage = null;

    /**
     * @var \TYPO3\CMS\Core\Charset\CharsetConverter
     */
    protected $charsetConversion = null;

    /**
     * @var CloudinaryPathService
     */
    protected $cloudinaryPathService;

    /**
     * @var CloudinaryResourceService
     */
    protected $cloudinaryResourceService;

    /**
     * @var CloudinaryFolderService
     */
    protected $cloudinaryFolderService;

    /**
     * @param array $configuration
     */
    public function __construct(array $configuration = [])
    {
        $this->configuration = $configuration;
        parent::__construct($configuration);

        // The capabilities default of this driver. See CAPABILITY_* constants for possible values
        $this->capabilities =
            ResourceStorage::CAPABILITY_BROWSABLE
            | ResourceStorage::CAPABILITY_PUBLIC
            | ResourceStorage::CAPABILITY_WRITABLE;
    }

    /**
     * @return void
     */
    public function processConfiguration()
    {
    }

    /**
     * @return void
     */
    public function initialize()
    {
        // Test connection if we are in the edit view of this storage
        if (TYPO3_MODE === 'BE' && !empty($_GET['edit']['sys_file_storage'])) {
            $this->getCloudinaryTestConnectionService()->test();
        }
    }

    /**
     * @param string $fileIdentifier
     *
     * @return string
     */
    public function getPublicUrl($fileIdentifier)
    {
        $cloudinaryResource = $this->getCloudinaryResourceService()->getResource(
            $this->getCloudinaryPathService()->computeCloudinaryPublicId($fileIdentifier)
        );
        return $cloudinaryResource
            ? $cloudinaryResource['secure_url']
            : '';
    }

    /**
     * @param string $message
     * @param array $arguments
     * @param array $data
     */
    protected function log(string $message, array $arguments = [], array $data = [])
    {
        /** @var Logger $logger */
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $logger->log(
            LogLevel::INFO,
            vsprintf($message, $arguments),
            $data
        );
    }

    /**
     * Creates a (cryptographic) hash for a file.
     *
     * @param string $fileIdentifier
     * @param string $hashAlgorithm
     *
     * @return string
     */
    public function hash($fileIdentifier, $hashAlgorithm)
    {
        return $this->hashIdentifier($fileIdentifier);
    }

    /**
     * Returns the identifier of the default folder new files should be put into.
     *
     * @return string
     */
    public function getDefaultFolder()
    {
        return $this->getRootLevelFolder();
    }

    /**
     * Returns the identifier of the root level folder of the storage.
     *
     * @return string
     */
    public function getRootLevelFolder()
    {
        return DIRECTORY_SEPARATOR;
    }

    /**
     * Returns information about a file.
     *
     * @param string $fileIdentifier
     * @param array $propertiesToExtract Array of properties which are be extracted
     *                                    If empty all will be extracted
     *
     * @return array
     * @throws \Exception
     */
    public function getFileInfoByIdentifier($fileIdentifier, array $propertiesToExtract = [])
    {

        $publicId = $this->getCloudinaryPathService()->computeCloudinaryPublicId($fileIdentifier);
        $cloudinaryResource = $this->getCloudinaryResourceService()->getResource($publicId);

        // We have a problem Hudson!
        if (!$cloudinaryResource) {
            throw new \Exception(
                'I could not find a corresponding cloudinary resource for file ' . $fileIdentifier,
                1591775048
            );
        }

        $mimeType = $this->getCloudinaryPathService()->guessMimeType($cloudinaryResource);
        if (!$mimeType) {
            $this->log('Just a notice! Time consuming action ahead. I am going to download a file "%s"', [$fileIdentifier], ['getFileInfoByIdentifier']);

            // We are force to download the file in order to correctly find the mime type.
            $localFile = $this->getFileForLocalProcessing($fileIdentifier);

            /** @var FileInfo $fileInfo */
            $fileInfo = GeneralUtility::makeInstance(FileInfo::class, $localFile);

            $mimeType = $fileInfo->getMimeType();
        }

        return [
            'identifier_hash' => $this->hashIdentifier($fileIdentifier),
            'folder_hash' => sha1(
                $this->canonicalizeAndCheckFolderIdentifier(
                    PathUtility::dirname($fileIdentifier)
                )
            ),
            'creation_date' => strtotime($cloudinaryResource['created_at']),
            'modification_date' => strtotime($cloudinaryResource['created_at']),
            'mime_type' => $mimeType,
            'extension' => $this->getResourceInfo($cloudinaryResource, 'format'),
            'size' => $this->getResourceInfo($cloudinaryResource, 'bytes'),
            'width' => $this->getResourceInfo($cloudinaryResource, 'width'),
            'height' => $this->getResourceInfo($cloudinaryResource, 'height'),
            'storage' => $this->storageUid,
            'identifier' => $fileIdentifier,
            'name' => PathUtility::basename($fileIdentifier),
        ];
    }

    /**
     * @param array $resource
     * @param string $name
     *
     * @return string
     */
    protected function getResourceInfo(array $resource, string $name): string
    {
        return isset($resource[$name])
            ? $resource[$name]
            : '';
    }

    /**
     * Checks if a file exists
     *
     * @param string $fileIdentifier
     *
     * @return bool
     */
    public function fileExists($fileIdentifier)
    {
        $cloudinaryResource = $this->getCloudinaryResourceService()->getResource(
            $this->getCloudinaryPathService()->computeCloudinaryPublicId($fileIdentifier)
        );

        return !empty($cloudinaryResource);
    }

    /**
     * Checks if a folder exists
     *
     * @param string $folderIdentifier
     *
     * @return bool
     */
    public function folderExists($folderIdentifier)
    {
        if ($folderIdentifier === self::ROOT_FOLDER_IDENTIFIER) {
            return true;
        }
        $cloudinaryFolder = $this->getCloudinaryFolderService()->getFolder(
            $this->getCloudinaryPathService()->computeCloudinaryFolderPath($folderIdentifier)
        );
        return !empty($cloudinaryFolder);
    }

    /**
     * @param string $fileName
     * @param string $folderIdentifier
     *
     * @return bool
     */
    public function fileExistsInFolder($fileName, $folderIdentifier)
    {
        $fileIdentifier = $this->canonicalizeFolderIdentifierAndFileName(
            $folderIdentifier,
            $fileName
        );

        return $this->fileExists($fileIdentifier);
    }

    /**
     * Checks if a folder exists inside a storage folder
     *
     * @param string $folderName
     * @param string $folderIdentifier
     *
     * @return bool
     */
    public function folderExistsInFolder($folderName, $folderIdentifier)
    {
        return $this->folderExists(
            $this->canonicalizeFolderIdentifierAndFolderName($folderIdentifier, $folderName)
        );
    }

    /**
     * Returns the Identifier for a folder within a given folder.
     *
     * @param string $folderName The name of the target folder
     * @param string $folderIdentifier
     *
     * @return string
     */
    public function getFolderInFolder($folderName, $folderIdentifier)
    {
        return $folderIdentifier . DIRECTORY_SEPARATOR . $folderName;
    }

    /**
     * @param string $localFilePath (within PATH_site)
     * @param string $targetFolderIdentifier
     * @param string $newFileName optional, if not given original name is used
     * @param bool $removeOriginal if set the original file will be removed
     *                                after successful operation
     *
     * @return string the identifier of the new file
     * @throws \Exception
     */
    public function addFile($localFilePath, $targetFolderIdentifier, $newFileName = '', $removeOriginal = true)
    {
        $fileName = $this->sanitizeFileName(
            $newFileName !== ''
                ? $newFileName :
                PathUtility::basename($localFilePath)
        );

        $fileIdentifier = $this->canonicalizeFolderIdentifierAndFileName(
            $targetFolderIdentifier,
            $fileName
        );

        // We remove a possible existing transient file to avoid bad surprise.
        $this->cleanUpTemporaryFile($fileIdentifier);

        // We compute the cloudinary public id
        $cloudinaryPublicId = $this->getCloudinaryPathService()->computeCloudinaryPublicId($fileIdentifier);

        $this->log('[API][UPLOAD] Cloudinary\Uploader::upload() - add resource "%s"', [$cloudinaryPublicId], ['addFile()']);

        // Before calling API, make sure we are connected with the right "bucket"
        $this->initializeApi();

        // Upload the file
        $cloudinaryResource = Uploader::upload(
            $localFilePath,
            [
                'public_id' => PathUtility::basename($cloudinaryPublicId),
                'folder' => $this->getCloudinaryPathService()->computeCloudinaryFolderPath($targetFolderIdentifier),
                'resource_type' => $this->getCloudinaryPathService()->getResourceType($fileIdentifier),
                'overwrite' => true,
            ]
        );

        $this->checkCloudinaryUploadStatus($cloudinaryResource, $fileIdentifier);

        // We persist the uploaded resource.
        $this->getCloudinaryResourceService()->save($cloudinaryResource);

        return $fileIdentifier;
    }

    /**
     * @param string $fileIdentifier
     * @param string $targetFolderIdentifier
     * @param string $newFileName
     *
     * @return string
     */
    public function moveFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $newFileName)
    {
        $targetIdentifier = $targetFolderIdentifier . $newFileName;
        return $this->renameFile($fileIdentifier, $targetIdentifier);
    }

    /**
     * Copies a file *within* the current storage.
     * Note that this is only about an inner storage copy action,
     * where a file is just copied to another folder in the same storage.
     *
     * @param string $fileIdentifier
     * @param string $targetFolderIdentifier
     * @param string $fileName
     *
     * @return string the Identifier of the new file
     */
    public function copyFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $fileName)
    {
        $targetFileIdentifier = $this->canonicalizeFolderIdentifierAndFileName($targetFolderIdentifier, $fileName);

        // Before calling API, make sure we are connected with the right "bucket"
        $this->initializeApi();

        $cloudinaryResource = Uploader::upload(
            $this->getPublicUrl($fileIdentifier),
            [
                'public_id' => PathUtility::basename(
                    $this->getCloudinaryPathService()->computeCloudinaryPublicId($targetFileIdentifier)
                ),
                'folder' => $this->getCloudinaryPathService()->computeCloudinaryFolderPath($targetFolderIdentifier),
                'resource_type' => $this->getCloudinaryPathService()->getResourceType($fileIdentifier),
                'overwrite' => true,
            ]
        );

        $this->checkCloudinaryUploadStatus($cloudinaryResource, $fileIdentifier);

        // We persist the uploaded resource
        $this->getCloudinaryResourceService()->save($cloudinaryResource);

        return $targetFileIdentifier;
    }

    /**
     * Replaces a file with file in local file system.
     *
     * @param string $fileIdentifier
     * @param string $localFilePath
     *
     * @return bool
     */
    public function replaceFile($fileIdentifier, $localFilePath)
    {
        // We remove a possible existing transient file to avoid bad surprise.
        $this->cleanUpTemporaryFile($fileIdentifier);

        $cloudinaryPublicId = PathUtility::basename(
            $this->getCloudinaryPathService()->computeCloudinaryPublicId($fileIdentifier)
        );

        // Before calling the API, make sure we are connected with the right "bucket"
        $this->initializeApi();

        // Upload the file
        $cloudinaryResource = Uploader::upload(
            $localFilePath,
            [
                'public_id' => PathUtility::basename($cloudinaryPublicId),
                'folder' => $this->getCloudinaryPathService()->computeCloudinaryFolderPath(
                    PathUtility::dirname($fileIdentifier)
                ),
                'resource_type' => $this->getCloudinaryPathService()->getResourceType($fileIdentifier),
                'overwrite' => true,
            ]
        );

        $this->checkCloudinaryUploadStatus($cloudinaryResource, $fileIdentifier);

        // We persist the uploaded resource.
        $this->getCloudinaryResourceService()->save($cloudinaryResource);

        return true;
    }

    /**
     * Removes a file from the filesystem. This does not check if the file is
     * still used or if it is a bad idea to delete it for some other reason
     * this has to be taken care of in the upper layers (e.g. the Storage)!
     *
     * @param string $fileIdentifier
     *
     * @return bool TRUE if deleting the file succeeded
     */
    public function deleteFile($fileIdentifier)
    {

        $cloudinaryPublicId = $this->getCloudinaryPathService()->computeCloudinaryPublicId($fileIdentifier);
        $this->log('[API][DELETE] Cloudinary\Api::delete_resources - delete resource "%s"', [$cloudinaryPublicId], ['deleteFile']);

        $response = $this->getApi()->delete_resources(
            $cloudinaryPublicId,
            [
                'resource_type' => $this->getCloudinaryPathService()->getResourceType($fileIdentifier),
            ]
        );

        $isDeleted = false;

        foreach ($response['deleted'] as $publicId => $status) {
            if ($status === 'deleted') {
                $isDeleted = (bool)$this->getCloudinaryResourceService()->delete($publicId);
            }
        }

        return $isDeleted;
    }

    /**
     * Removes a folder in filesystem.
     *
     * @param string $folderIdentifier
     * @param bool $deleteRecursively
     *
     * @return bool
     * @throws Api\GeneralError
     */
    public function deleteFolder($folderIdentifier, $deleteRecursively = false)
    {
        $cloudinaryFolder = $this->getCloudinaryPathService()->computeCloudinaryFolderPath($folderIdentifier);

        if ($deleteRecursively) {
            $this->log('[API][DELETE] Cloudinary\Api::delete_resources_by_prefix() - folder "%s"', [$cloudinaryFolder], ['deleteFolder']);
            $response = $this->getApi()->delete_resources_by_prefix($cloudinaryFolder);

            foreach ($response['deleted'] as $publicId => $status) {
                if ($status === 'deleted') {
                    $this->getCloudinaryResourceService()->delete($publicId);
                }
            }
        }

        // We make sure the folder exists first. It will also delete sub-folder if those ones are empty.
        if ($this->folderExists($folderIdentifier)) {
            $this->log('[API][DELETE] Cloudinary\Api::delete_folder() - folder "%s"', [$cloudinaryFolder], ['deleteFolder']);
            $response = $this->getApi()->delete_folder($cloudinaryFolder);

            foreach ($response['deleted'] as $folder) {
                $this->getCloudinaryFolderService()->delete($folder);
            }
        }

        return true;
    }

    /**
     * @param string $fileIdentifier
     * @param bool $writable
     *
     * @return string
     */
    public function getFileForLocalProcessing($fileIdentifier, $writable = true)
    {
        $temporaryPath = $this->getTemporaryPathForFile($fileIdentifier);

        if (!is_file($temporaryPath) || !filesize($temporaryPath)) {
            $this->log('[SLOW] Downloading for local processing "%s"', [$fileIdentifier], ['getFileForLocalProcessing']);

            file_put_contents(
                $temporaryPath,
                file_get_contents($this->getPublicUrl($fileIdentifier))
            );
            $this->log('File downloaded into "%s"', [$temporaryPath], ['getFileForLocalProcessing']);
        }

        return $temporaryPath;
    }

    /**
     * Creates a new (empty) file and returns the identifier.
     *
     * @param string $fileName
     * @param string $parentFolderIdentifier
     *
     * @return string
     */
    public function createFile($fileName, $parentFolderIdentifier)
    {
        throw new RuntimeException('createFile: not implemented action! Cloudinary Driver is limited to images.', 1570728107);
    }

    /**
     * Creates a folder, within a parent folder.
     * If no parent folder is given, a root level folder will be created
     *
     * @param string $newFolderName
     * @param string $parentFolderIdentifier
     * @param bool $recursive
     *
     * @return string the Identifier of the new folder
     */
    public function createFolder($newFolderName, $parentFolderIdentifier = '', $recursive = false)
    {
        $canonicalFolderPath = $this->canonicalizeFolderIdentifierAndFolderName($parentFolderIdentifier, $newFolderName);
        $cloudinaryFolder = $this->getCloudinaryPathService()->normalizeCloudinaryPath($canonicalFolderPath);

        $this->log('[API][CREATE] Cloudinary\Api::createFolder() - folder "%s"', [$cloudinaryFolder], ['createFolder']);
        $response = $this->getApi()->create_folder(
            $cloudinaryFolder
        );

        if (!$response['success']) {
            throw new \Exception(
                'Folder creation failed: ' . $cloudinaryFolder,
                1591775050
            );
        }
        $this->getCloudinaryFolderService()->save($cloudinaryFolder);

        return $canonicalFolderPath;
    }

    /**
     * @param string $fileIdentifier
     *
     * @return string
     */
    public function getFileContents($fileIdentifier)
    {
        // Will download the file to be faster next time the content is required.
        $localFileNameAndPath = $this->getFileForLocalProcessing($fileIdentifier);
        return file_get_contents($localFileNameAndPath);
    }

    /**
     * Sets the contents of a file to the specified value.
     *
     * @param string $fileIdentifier
     * @param string $contents
     *
     * @return int
     */
    public function setFileContents($fileIdentifier, $contents)
    {
        throw new RuntimeException('setFileContents: not implemented action!', 1570728106);
    }

    /**
     * Renames a file in this storage.
     *
     * @param string $fileIdentifier
     * @param string $newFileIdentifier The target path (including the file name!)
     *
     * @return string The identifier of the file after renaming
     */
    public function renameFile($fileIdentifier, $newFileIdentifier)
    {
        if (!$this->isFileIdentifier($newFileIdentifier)) {
            $sanitizedFileName = $this->sanitizeFileName(PathUtility::basename($newFileIdentifier));
            $folderPath = PathUtility::dirname($fileIdentifier);
            $newFileIdentifier = $this->canonicalizeAndCheckFileIdentifier(
                $this->canonicalizeAndCheckFolderIdentifier($folderPath) . $sanitizedFileName
            );
        }

        $cloudinaryPublicId = $this->getCloudinaryPathService()->computeCloudinaryPublicId($fileIdentifier);
        $newCloudinaryPublicId = $this->getCloudinaryPathService()->computeCloudinaryPublicId($newFileIdentifier);

        if ($cloudinaryPublicId !== $newCloudinaryPublicId) {

            // Before calling API, make sure we are connected with the right "bucket"
            $this->initializeApi();

            // Rename the file
            $cloudinaryResource = Uploader::rename(
                $cloudinaryPublicId,
                $newCloudinaryPublicId,
                [
                    'resource_type' => $this->getCloudinaryPathService()->getResourceType($fileIdentifier),
                    'overwrite' => true,
                ]
            );

            $this->checkCloudinaryUploadStatus($cloudinaryResource, $fileIdentifier);

            // We remove the old public id
            $this->getCloudinaryResourceService()->delete($cloudinaryPublicId);

            // ... and insert the new cloudinary resource
            $this->getCloudinaryResourceService()->save($cloudinaryResource);
        }

        return $newFileIdentifier;
    }

    /**
     * @param array $cloudinaryResource
     * @param string $fileIdentifier
     *
     * @throws Api\GeneralError
     */
    protected function checkCloudinaryUploadStatus(array $cloudinaryResource, $fileIdentifier): void
    {
        if (!$cloudinaryResource && $cloudinaryResource['type'] !== 'upload') {
            throw new RuntimeException('Cloudinary upload failed for ' . $fileIdentifier, 1591954950);
        }
    }

    /**
     * Renames a folder in this storage.
     *
     * @param string $folderIdentifier
     * @param string $newFolderName
     *
     * @return array A map of old to new file identifiers of all affected resources
     */
    public function renameFolder($folderIdentifier, $newFolderName)
    {
        $renamedFiles = [];

        $pathSegments = GeneralUtility::trimExplode('/', $folderIdentifier);
        $numberOfSegments = count($pathSegments);

        if ($numberOfSegments > 1) {

            // Replace last folder name by the new folder name
            $pathSegments[$numberOfSegments - 2] = $newFolderName;
            $newFolderIdentifier = implode('/', $pathSegments);

            // Before calling the API, make sure we are connected with the right "bucket"
            $this->initializeApi();

            $renamedFiles[$folderIdentifier] = $newFolderIdentifier;

            foreach ($this->getFilesInFolder($folderIdentifier, 0, -1, true) as $oldFileIdentifier) {

                $newFileIdentifier = str_replace($folderIdentifier, $newFolderIdentifier, $oldFileIdentifier);

                if ($oldFileIdentifier !== $newFileIdentifier) {
                    $renamedFiles[$oldFileIdentifier] = $this->renameFile($oldFileIdentifier, $newFileIdentifier);
                }
            }

            // After working so hard, delete the old empty folder.
            $this->deleteFolder($folderIdentifier);
        }

        return $renamedFiles;
    }

    /**
     * @param string $sourceFolderIdentifier
     * @param string $targetFolderIdentifier
     * @param string $newFolderName
     *
     * @return array All files which are affected, map of old => new file identifiers
     */
    public function moveFolderWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName)
    {
        // Compute the new folder identifier and then create it.
        $newTargetFolderIdentifier = $this->canonicalizeFolderIdentifierAndFolderName(
            $targetFolderIdentifier,
            $newFolderName
        );

        if (!$this->folderExists($newTargetFolderIdentifier)) {
            $this->createFolder($newTargetFolderIdentifier);
        }

        $movedFiles = [];
        $files = $this->getFilesInFolder($sourceFolderIdentifier, 0, -1);
        foreach ($files as $fileIdentifier) {
            $movedFiles[$fileIdentifier] = $this->moveFileWithinStorage($fileIdentifier, $newTargetFolderIdentifier, PathUtility::basename($fileIdentifier));
        }

        // Delete the old and empty folder
        $this->deleteFolder($sourceFolderIdentifier);

        return $movedFiles;
    }

    /**
     * @param string $sourceFolderIdentifier
     * @param string $targetFolderIdentifier
     * @param string $newFolderName
     *
     * @return bool
     */
    public function copyFolderWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName)
    {
        // Compute the new folder identifier and then create it.
        $newTargetFolderIdentifier = $this->canonicalizeFolderIdentifierAndFolderName($targetFolderIdentifier, $newFolderName);

        if (!$this->folderExists($newTargetFolderIdentifier)) {
            $this->createFolder($newTargetFolderIdentifier);
        }

        $files = $this->getFilesInFolder($sourceFolderIdentifier, 0, -1, true);
        foreach ($files as $fileIdentifier) {

            $newFileIdentifier = str_replace(
                $sourceFolderIdentifier,
                $newTargetFolderIdentifier,
                $fileIdentifier
            );

            $this->copyFileWithinStorage(
                $fileIdentifier,
                GeneralUtility::dirname($newFileIdentifier),
                PathUtility::basename($fileIdentifier)
            );
        }

        return true;
    }

    /**
     * Checks if a folder contains files and (if supported) other folders.
     *
     * @param string $folderIdentifier
     *
     * @return bool TRUE if there are no files and folders within $folder
     */
    public function isFolderEmpty($folderIdentifier)
    {
        return $this->getCloudinaryFolderService()->countSubFolders($folderIdentifier);
    }

    /**
     * Checks if a given identifier is within a container, e.g. if
     * a file or folder is within another folder.
     *
     * Hint: this also needs to return TRUE if the given identifier
     * matches the container identifier to allow access to the root
     * folder of a filemount.
     *
     * @param string $folderIdentifier
     * @param string $identifier identifier to be checked against $folderIdentifier
     *
     * @return bool TRUE if $content is within or matches $folderIdentifier
     */
    public function isWithin($folderIdentifier, $identifier)
    {
        $folderIdentifier = $this->canonicalizeAndCheckFileIdentifier($folderIdentifier);
        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($identifier);
        if ($folderIdentifier === $fileIdentifier) {
            return true;
        }

        // File identifier canonicalization will not modify a single slash so
        // we must not append another slash in that case.
        if ($folderIdentifier !== DIRECTORY_SEPARATOR) {
            $folderIdentifier .= DIRECTORY_SEPARATOR;
        }

        return GeneralUtility::isFirstPartOfStr($fileIdentifier, $folderIdentifier);
    }

    /**
     * Returns information about a file.
     *
     * @param string $folderIdentifier
     *
     * @return array
     */
    public function getFolderInfoByIdentifier($folderIdentifier)
    {
        $canonicalFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        return [
            'identifier' => $canonicalFolderIdentifier,
            'name' => PathUtility::basename(
                $this->getCloudinaryPathService()->normalizeCloudinaryPath($canonicalFolderIdentifier)
            ),
            'storage' => $this->storageUid
        ];
    }

    /**
     * Returns a file inside the specified path
     *
     * @param string $fileName
     * @param string $folderIdentifier
     *
     * @return string
     */
    public function getFileInFolder($fileName, $folderIdentifier)
    {
        $folderIdentifier = $folderIdentifier . DIRECTORY_SEPARATOR . $fileName;
        return $folderIdentifier;
    }

    /**
     * Returns a list of files inside the specified path
     *
     * @param string $folderIdentifier
     * @param int $start
     * @param int $numberOfItems
     * @param bool $recursive
     * @param array $filterCallbacks callbacks for filtering the items
     * @param string $sort Property name used to sort the items.
     *                      Among them may be: '' (empty, no sorting), name,
     *                      fileext, size, tstamp and rw.
     *                      If a driver does not support the given property, it
     *                      should fall back to "name".
     * @param bool $sortRev TRUE to indicate reverse sorting (last to first)
     *
     * @return array of FileIdentifiers
     */
    public function getFilesInFolder(
        $folderIdentifier, $start = 0, $numberOfItems = 40, $recursive = false, array $filterCallbacks = [], $sort = '', $sortRev = false
    ) {
        $cloudinaryFolder = $this->getCloudinaryPathService()->computeCloudinaryFolderPath(
            $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier)
        );

        // Set default orderings
        $parameters = (array)GeneralUtility::_GP('SET');
        if ($parameters['sort'] === 'file') {
            $parameters['sort'] = 'filename';
        } elseif ($parameters['sort'] === 'tstamp') {
            $parameters['sort'] = 'created_at';
        } else {
            $parameters['sort'] = 'filename';
            $parameters['reverse'] = 'ASC';
        }

        $orderings = [
            'fieldName' => $parameters['sort'],
            'direction' => isset($parameters['reverse']) && (int)$parameters['reverse']
                ? 'DESC'
                : 'ASC',
        ];

        $pagination = [
            'maxResult' => $numberOfItems,
            'firstResult' => (int)GeneralUtility::_GP('pointer')
        ];

        $cloudinaryResources = $this->getCloudinaryResourceService()->getResources($cloudinaryFolder, $orderings, $pagination, $recursive);

        // Generate list of folders for the file module.
        $files = [];
        foreach ($cloudinaryResources as $cloudinaryResource) {

            // Compute file identifier
            $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier(
                $this->getCloudinaryPathService()->computeFileIdentifier($cloudinaryResource)
            );

            $result = $this->applyFilterMethodsToDirectoryItem(
                $filterCallbacks,
                basename($fileIdentifier),
                $fileIdentifier,
                dirname($fileIdentifier)
            );

            if ($result) {
                $files[] = $fileIdentifier;
            }
        }

        return $files;
    }

    /**
     * Returns the number of files inside the specified path
     *
     * @param string $folderIdentifier
     * @param bool $recursive
     * @param array $filterCallbacks callbacks for filtering the items
     *
     * @return int
     */
    public function countFilesInFolder($folderIdentifier, $recursive = false, array $filterCallbacks = [])
    {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);

        // true means we have non-core filters that has been added and we must filter on the PHP side.
        if (count($filterCallbacks) > 1) {
            $files = $this->getFilesInFolder($folderIdentifier, 0, 0, $recursive, $filterCallbacks);
            $result = count($files);
        } else {
            $result = $this->getCloudinaryResourceService()->count(
                $this->getCloudinaryPathService()->computeCloudinaryFolderPath($folderIdentifier),
                $recursive
            );
        }
        return $result;
    }

    /**
     * Returns a list of folders inside the specified path
     *
     * @param string $folderIdentifier
     * @param int $start
     * @param int $numberOfItems
     * @param bool $recursive
     * @param array $filterCallbacks
     * @param string $sort Property name used to sort the items.
     *                      Among them may be: '' (empty, no sorting), name,
     *                      fileext, size, tstamp and rw.
     *                      If a driver does not support the given property, it
     *                      should fall back to "name".
     * @param bool $sortRev TRUE to indicate reverse sorting (last to first)
     *
     * @return array
     */
    public function getFoldersInFolder(
        $folderIdentifier,
        $start = 0,
        $numberOfItems = 40,
        $recursive = false,
        array $filterCallbacks = [],
        $sort = '',
        $sortRev = false
    ) {
        $parameters = (array)GeneralUtility::_GP('SET');

        $cloudinaryFolder = $this->getCloudinaryPathService()->computeCloudinaryFolderPath(
            $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier)
        );

        $cloudinaryFolders = $this->getCloudinaryFolderService()->getSubFolders(
            $cloudinaryFolder,
            [
                'fieldName' => 'folder',
                'direction' => isset($parameters['reverse']) && (int)$parameters['reverse']
                    ? 'DESC'
                    : 'ASC',
            ],
            $recursive
        );

        // Generate list of folders for the file module.
        $folders = [];
        foreach ($cloudinaryFolders as $cloudinaryFolder) {
            $folderIdentifier = $this->getCloudinaryPathService()->computeFolderIdentifier($cloudinaryFolder['folder']);

            $result = $this->applyFilterMethodsToDirectoryItem(
                $filterCallbacks,
                basename($folderIdentifier),
                $folderIdentifier,
                dirname($folderIdentifier)
            );

            if ($result) {
                $folders[] = $folderIdentifier;
            }
        }

        return $folders;
    }

    /**
     * Returns the number of folders inside the specified path
     *
     * @param string $folderIdentifier
     * @param bool $recursive
     * @param array $filterCallbacks
     *
     * @return int
     */
    public function countFoldersInFolder($folderIdentifier, $recursive = false, array $filterCallbacks = [])
    {
        // true means we have non-core filters that has been added and we must filter on the PHP side.
        if (count($filterCallbacks) > 1) {
            $folders = $this->getFoldersInFolder($folderIdentifier, 0, 0, $recursive, $filterCallbacks);
            $result = count($folders);
        } else {
            $cloudinaryFolder = $this->getCloudinaryPathService()->computeCloudinaryFolderPath(
                $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier)
            );

            $result = $this->getCloudinaryFolderService()->countSubFolders($cloudinaryFolder, $recursive);
        }

        return $result;
    }

    /**
     * @param string $identifier
     *
     * @return string
     */
    public function dumpFileContents($identifier)
    {
        return $this->getFileContents($identifier);
    }

    /**
     * Returns the permissions of a file/folder as an array
     * (keys r, w) of bool flags
     *
     * @param string $identifier
     *
     * @return array
     */
    public function getPermissions($identifier)
    {
        if (!isset($this->cachedPermissions[$identifier])) {
            // Cloudinary does not handle permissions
            $permissions = ['r' => true, 'w' => true];
            $this->cachedPermissions[$identifier] = $permissions;
        }
        return $this->cachedPermissions[$identifier];
    }

    /**
     * Merges the capabilites merged by the user at the storage
     * configuration into the actual capabilities of the driver
     * and returns the result.
     *
     * @param int $capabilities
     *
     * @return int
     */
    public function mergeConfigurationCapabilities($capabilities)
    {
        $this->capabilities &= $capabilities;
        return $this->capabilities;
    }

    /**
     * Returns a string where any character not matching [.a-zA-Z0-9_-] is
     * substituted by '_'
     * Trailing dots are removed
     *
     * @param string $fileName Input string, typically the body of a fileName
     * @param string $charset Charset of the a fileName (defaults to current charset; depending on context)
     *
     * @return string Output string with any characters not matching [.a-zA-Z0-9_-] is substituted by '_' and trailing dots removed
     * @throws Exception\InvalidFileNameException
     */
    public function sanitizeFileName($fileName, $charset = '')
    {
        $fileName = $this->getCharsetConversion()->specCharsToASCII('utf-8', $fileName);

        // Replace unwanted characters by underscores
        $cleanFileName = preg_replace(
            '/[' . self::UNSAFE_FILENAME_CHARACTER_EXPRESSION . '\\xC0-\\xFF]/',
            '_',
            trim($fileName)
        );

        // Strip trailing dots and return
        $cleanFileName = rtrim($cleanFileName, '.');
        if ($cleanFileName === '') {
            throw new Exception\InvalidFileNameException(
                'File name "' . $fileName . '" is invalid.',
                1320288991
            );
        }

        $pathParts = PathUtility::pathinfo($cleanFileName);

        $cleanFileName = str_replace('.', '_', $pathParts['filename']) . ($pathParts['extension'] ? '.' . $pathParts['extension'] : '');

        // Handle the special jpg case which does not correspond to the file extension.
        return preg_replace('/jpeg$/', 'jpg', $cleanFileName);
    }

    /**
     * Applies a set of filter methods to a file name to find out if it should be used or not. This is e.g. used by
     * directory listings.
     *
     * @param array $filterMethods The filter methods to use
     * @param string $itemName
     * @param string $itemIdentifier
     * @param string $parentIdentifier
     *
     * @return bool
     * @throws \RuntimeException
     */
    protected function applyFilterMethodsToDirectoryItem(array $filterMethods, $itemName, $itemIdentifier, $parentIdentifier)
    {
        foreach ($filterMethods as $filter) {
            if (is_callable($filter)) {
                $result = call_user_func($filter, $itemName, $itemIdentifier, $parentIdentifier, [], $this);
                // We have to use -1 as the „don't include“ return value, as call_user_func() will return FALSE
                // If calling the method succeeded and thus we can't use that as a return value.
                if ($result === -1) {
                    return false;
                }
                if ($result === false) {
                    throw new \RuntimeException(
                        'Could not apply file/folder name filter ' . $filter[0] . '::' . $filter[1],
                        1596795500
                    );
                }
            }
        }
        return true;
    }

    /**
     * Returns a temporary path for a given file, including the file extension.
     *
     * @param string $fileIdentifier
     *
     * @return string
     */
    protected function getTemporaryPathForFile($fileIdentifier): string
    {
        $temporaryFileNameAndPath = PATH_site . 'typo3temp/var/transient/' . $this->storageUid . $fileIdentifier;

        $temporaryFolder = GeneralUtility::dirname($temporaryFileNameAndPath);

        if (!is_dir($temporaryFolder)) {
            GeneralUtility::mkdir_deep($temporaryFolder);
        }
        return $temporaryFileNameAndPath;
    }

    /**
     * We want to remove the local temporary file
     */
    protected function cleanUpTemporaryFile(string $fileIdentifier): void
    {
        $temporaryLocalFile = $this->getTemporaryPathForFile($fileIdentifier);
        if (is_file($temporaryLocalFile)) {
            unlink($temporaryLocalFile);
        }

        // very coupled.... via signal slot?
        $this->getExplicitDataCacheRepository()->delete($this->storageUid, $fileIdentifier);
    }

    /**
     * @return object|ExplicitDataCacheRepository
     */
    public function getExplicitDataCacheRepository()
    {
        return GeneralUtility::makeInstance(ExplicitDataCacheRepository::class);
    }

    /**
     * @param string $newFileIdentifier
     *
     * @return bool
     */
    protected function isFileIdentifier(string $newFileIdentifier): bool
    {
        return false !== strpos($newFileIdentifier, DIRECTORY_SEPARATOR);
    }

    /**
     * @param string $folderIdentifier
     * @param string $folderName
     *
     * @return string
     */
    protected function canonicalizeFolderIdentifierAndFolderName(string $folderIdentifier, string $folderName): string
    {
        $canonicalFolderPath = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        return $this->canonicalizeAndCheckFolderIdentifier($canonicalFolderPath . trim($folderName, DIRECTORY_SEPARATOR));
    }

    /**
     * @param string $folderIdentifier
     * @param string $fileName
     *
     * @return string
     */
    protected function canonicalizeFolderIdentifierAndFileName(string $folderIdentifier, string $fileName): string
    {
        return $this->canonicalizeAndCheckFileIdentifier(
            $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier) . $fileName
        );
    }

    /**
     * @return object|CloudinaryPathService
     */
    protected function getCloudinaryPathService()
    {
        if (!$this->cloudinaryPathService) {
            $this->cloudinaryPathService = GeneralUtility::makeInstance(
                CloudinaryPathService::class,
                ResourceFactory::getInstance()->getStorageObject($this->storageUid)
            );
        }

        return $this->cloudinaryPathService;
    }

    /**
     * @return CloudinaryResourceService
     */
    protected function getCloudinaryResourceService()
    {
        if (!$this->cloudinaryResourceService) {
            $this->cloudinaryResourceService = GeneralUtility::makeInstance(
                CloudinaryResourceService::class,
                ResourceFactory::getInstance()->getStorageObject($this->storageUid)
            );
        }

        return $this->cloudinaryResourceService;
    }

    /**
     * @return object|CloudinaryTestConnectionService
     */
    protected function getCloudinaryTestConnectionService()
    {
        return GeneralUtility::makeInstance(
            CloudinaryTestConnectionService::class,
            $this->configuration
        );
    }

    /**
     * @return CloudinaryFolderService
     */
    protected function getCloudinaryFolderService()
    {
        if (!$this->cloudinaryFolderService) {
            $this->cloudinaryFolderService = GeneralUtility::makeInstance(
                CloudinaryFolderService::class,
                ResourceFactory::getInstance()->getStorageObject($this->storageUid)
            );
        }

        return $this->cloudinaryFolderService;
    }

    /**
     * @return void
     */
    protected function initializeApi()
    {

        /** @var ConfigurationService $configurationService */
        $configurationService = GeneralUtility::makeInstance(
            ConfigurationService::class,
            $this->configuration
        );

        Cloudinary::config(
            [
                'cloud_name' => $configurationService->get('cloudName'),
                'api_key' => $configurationService->get('apiKey'),
                'api_secret' => $configurationService->get('apiSecret'),
                'timeout' => $configurationService->get('timeout'),
                'secure' => true
            ]
        );
    }

    /**
     * @return Api
     */
    protected function getApi()
    {
        $this->initializeApi();

        // The object \Cloudinary\Api behaves like a singleton object.
        // The problem: if we have multiple driver instances / configuration, we don't get the expected result
        // meaning we are wrongly fetching resources from other cloudinary "buckets" because of the singleton behaviour
        // Therefore it is better to create a new instance upon each API call to avoid driver confusion
        return new Api();
    }
}
