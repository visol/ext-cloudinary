<?php

namespace Visol\Cloudinary\Driver;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Resource\Exception\InvalidFileNameException;
use Cloudinary;
use Cloudinary\Api;
use Cloudinary\Uploader;
use RuntimeException;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Core\Environment;
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
use Visol\Cloudinary\Services\CloudinaryResourceService;
use Visol\Cloudinary\Services\CloudinaryPathService;
use Visol\Cloudinary\Services\CloudinaryTestConnectionService;
use Visol\Cloudinary\Services\ConfigurationService;
use Visol\Cloudinary\Utility\CloudinaryFileUtility;

class CloudinaryDriver extends AbstractHierarchicalFilesystemDriver
{
    public const DRIVER_TYPE = 'VisolCloudinary';

    protected const ROOT_FOLDER_IDENTIFIER = '/';

    protected const UNSAFE_FILENAME_CHARACTER_EXPRESSION = '\\x00-\\x2C\\/\\x3A-\\x3F\\x5B-\\x60\\x7B-\\xBF';

    static public array $knownRawFormats = ['youtube', 'vimeo'];

    /**
     * The base URL that points to this driver's storage. As long is this is not set, it is assumed that this folder
     * is not publicly available
     */
    protected string $baseUrl = '';

    /**
     * Object permissions are cached here in subarrays like:
     * $identifier => ['r' => bool, 'w' => bool]
     */
    protected array $cachedPermissions = [];

    protected ConfigurationService $configurationService;

    protected ?ResourceStorage $storage = null;

    protected CharsetConverter $charsetConversion;

    protected ?CloudinaryPathService $cloudinaryPathService = null;

    protected ?CloudinaryResourceService $cloudinaryResourceService = null;

    protected ?CloudinaryFolderService $cloudinaryFolderService = null;

    public function __construct(array $configuration = [])
    {
        $this->configuration = $configuration;
        parent::__construct($configuration);

        // The capabilities default of this driver. See CAPABILITY_* constants for possible values
        $this->capabilities =
            ResourceStorage::CAPABILITY_BROWSABLE |
            ResourceStorage::CAPABILITY_PUBLIC |
            ResourceStorage::CAPABILITY_WRITABLE;

        $this->configurationService = GeneralUtility::makeInstance(ConfigurationService::class, $this->configuration);

        $this->charsetConversion = GeneralUtility::makeInstance(CharsetConverter::class);
    }

    public function processConfiguration(): void
    {
    }

    public function initialize(): void
    {
        // Test connection if we are in the edit view of this storage
        if (
            !Environment::isCli() &&
            ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isBackend() &&
            !empty($_GET['edit']['sys_file_storage'])
        ) {
            $this->getCloudinaryTestConnectionService()->test();
        }
    }

    /**
     * @param string $identifier
     */
    public function getPublicUrl($identifier): string
    {
        if ($processedPath = $this->getProcessedPath($identifier)) {
            return 'https://res.cloudinary.com/' . $processedPath;
        }

        $cloudinaryResource = $this->getCloudinaryResourceService()->getResource(
            $this->getCloudinaryPathService()->computeCloudinaryPublicId($identifier),
        );

        return $cloudinaryResource ? $cloudinaryResource['secure_url'] : '';
    }

    protected function log(string $message, array $arguments = [], array $data = []): void
    {
        /** @var Logger $logger */
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $logger->log(LogLevel::INFO, vsprintf($message, $arguments), $data);
    }

    /**
     * Creates a (cryptographic) hash for a file.
     *
     * @param string $fileIdentifier
     * @param string $hashAlgorithm
     */
    public function hash($fileIdentifier, $hashAlgorithm): string
    {
        return $this->hashIdentifier($fileIdentifier);
    }

    public function getDefaultFolder(): string
    {
        return $this->getRootLevelFolder();
    }

    public function getRootLevelFolder(): string
    {
        return DIRECTORY_SEPARATOR;
    }

    /**
     * @param string $fileIdentifier
     * @param array $propertiesToExtract Array of properties which are be extracted
     *                                    If empty all will be extracted
     */
    public function getFileInfoByIdentifier($fileIdentifier, array $propertiesToExtract = []): array
    {
        if ($this->isProcessedFile($fileIdentifier)) {
            return [];
        }
        $publicId = $this->getCloudinaryPathService()->computeCloudinaryPublicId($fileIdentifier);
        $cloudinaryResource = $this->getCloudinaryResourceService()->getResource($publicId);
        // We have a problem Hudson!
        if (!$cloudinaryResource) {
            throw new \Exception(
                'I could not find a corresponding cloudinary resource for file ' . $fileIdentifier,
                1591775048,
            );
        }

        $mimeType = $this->getCloudinaryPathService()->guessMimeType($cloudinaryResource);
        if (!$mimeType) {
            $this->log(
                'Just a notice! Time consuming action ahead. I am going to download a file "%s"',
                [$fileIdentifier],
                ['getFileInfoByIdentifier'],
            );

            // We are force to download the file in order to correctly find the mime type.
            $localFile = $this->getFileForLocalProcessing($fileIdentifier);

            /** @var FileInfo $fileInfo */
            $fileInfo = GeneralUtility::makeInstance(FileInfo::class, $localFile);

            $mimeType = $fileInfo->getMimeType();
        }

        return [
            'identifier_hash' => $this->hashIdentifier($fileIdentifier),
            'folder_hash' => sha1($this->canonicalizeAndCheckFolderIdentifier(PathUtility::dirname($fileIdentifier))),
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

    protected function getResourceInfo(array $resource, string $name): string
    {
        return $resource[$name] ?? '';
    }

    /**
     * @param string $fileIdentifier
     */
    public function fileExists($fileIdentifier): bool
    {
        // Early return in case we have a processed file.
        if ($this->isProcessedFile($fileIdentifier)) {
            return true;
        }

        $cloudinaryResource = $this->getCloudinaryResourceService()->getResource(
            $this->getCloudinaryPathService()->computeCloudinaryPublicId($fileIdentifier),
        );

        return !empty($cloudinaryResource);
    }

    /**
     * @param string $folderIdentifier
     */
    public function folderExists($folderIdentifier): bool
    {
        if ($folderIdentifier === self::ROOT_FOLDER_IDENTIFIER) {
            return true;
        }
        $cloudinaryFolder = $this->getCloudinaryFolderService()->getFolder(
            $this->getCloudinaryPathService()->computeCloudinaryFolderPath($folderIdentifier),
        );
        return !empty($cloudinaryFolder);
    }

    /**
     * @param string $fileName
     * @param string $folderIdentifier
     */
    public function fileExistsInFolder($fileName, $folderIdentifier): bool
    {
        $fileIdentifier = $this->canonicalizeFolderIdentifierAndFileName($folderIdentifier, $fileName);

        return $this->fileExists($fileIdentifier);
    }

    /**
     * @param string $folderName
     * @param string $folderIdentifier
     */
    public function folderExistsInFolder($folderName, $folderIdentifier): bool
    {
        return $this->folderExists($this->canonicalizeFolderIdentifierAndFolderName($folderIdentifier, $folderName));
    }

    /**
     * @param string $folderName The name of the target folder
     * @param string $folderIdentifier
     */
    public function getFolderInFolder($folderName, $folderIdentifier): string
    {
        return $folderIdentifier . DIRECTORY_SEPARATOR . $folderName;
    }

    /**
     * @param string $localFilePath
     * @param string $targetFolderIdentifier
     * @param string $newFileName optional, if not given original name is used
     * @param bool $removeOriginal if set the original file will be removed
     *                                after successful operation
     */
    public function addFile($localFilePath, $targetFolderIdentifier, $newFileName = '', $removeOriginal = true): string
    {
        $fileName = $this->sanitizeFileName($newFileName !== '' ? $newFileName : PathUtility::basename($localFilePath));

        $fileIdentifier = $this->canonicalizeFolderIdentifierAndFileName($targetFolderIdentifier, $fileName);

        // We remove a possible existing transient file to avoid bad surprise.
        $this->cleanUpTemporaryFile($fileIdentifier);

        // We compute the cloudinary public id
        $cloudinaryPublicId = $this->getCloudinaryPathService()->computeCloudinaryPublicId($fileIdentifier);

        $this->log(
            '[API] Cloudinary\Uploader::upload() - add resource "%s"',
            [$cloudinaryPublicId],
            ['addFile()'],
        );

        // Before calling API, make sure we are connected with the right "bucket"
        $this->initializeApi();

        // Upload the file
        $cloudinaryResource = Uploader::upload($localFilePath, [
            'public_id' => PathUtility::basename($cloudinaryPublicId),
            'folder' => $this->getCloudinaryPathService()->computeCloudinaryFolderPath($targetFolderIdentifier),
            'resource_type' => $this->getCloudinaryPathService()->getResourceType($fileIdentifier),
            'overwrite' => true,
        ]);

        $this->checkCloudinaryUploadStatus($cloudinaryResource, $fileIdentifier);

        // We persist the uploaded resource.
        $this->getCloudinaryResourceService()->save($cloudinaryResource);

        return $fileIdentifier;
    }

    /**
     * @param string $fileIdentifier
     * @param string $targetFolderIdentifier
     * @param string $newFileName
     */
    public function moveFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $newFileName): string
    {
        $targetIdentifier = $targetFolderIdentifier . $newFileName;
        return $this->renameFile($fileIdentifier, $targetIdentifier);
    }

    /**
     * @param string $fileIdentifier
     * @param string $targetFolderIdentifier
     * @param string $fileName
     */
    public function copyFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $fileName): string
    {
        $targetFileIdentifier = $this->canonicalizeFolderIdentifierAndFileName($targetFolderIdentifier, $fileName);

        // Before calling API, make sure we are connected with the right "bucket"
        $this->initializeApi();

        $cloudinaryResource = Uploader::upload($this->getPublicUrl($fileIdentifier), [
            'public_id' => PathUtility::basename(
                $this->getCloudinaryPathService()->computeCloudinaryPublicId($targetFileIdentifier),
            ),
            'folder' => $this->getCloudinaryPathService()->computeCloudinaryFolderPath($targetFolderIdentifier),
            'resource_type' => $this->getCloudinaryPathService()->getResourceType($fileIdentifier),
            'overwrite' => true,
        ]);

        $this->checkCloudinaryUploadStatus($cloudinaryResource, $fileIdentifier);

        // We persist the uploaded resource
        $this->getCloudinaryResourceService()->save($cloudinaryResource);

        return $targetFileIdentifier;
    }

    /**
     * @param string $fileIdentifier
     * @param string $localFilePath
     */
    public function replaceFile($fileIdentifier, $localFilePath): bool
    {
        // We remove a possible existing transient file to avoid bad surprise.
        $this->cleanUpTemporaryFile($fileIdentifier);

        $cloudinaryPublicId = PathUtility::basename(
            $this->getCloudinaryPathService()->computeCloudinaryPublicId($fileIdentifier),
        );

        // Before calling the API, make sure we are connected with the right "bucket"
        $this->initializeApi();

        // Upload the file
        $cloudinaryResource = Uploader::upload($localFilePath, [
            'public_id' => PathUtility::basename($cloudinaryPublicId),
            'folder' => $this->getCloudinaryPathService()->computeCloudinaryFolderPath(
                PathUtility::dirname($fileIdentifier),
            ),
            'resource_type' => $this->getCloudinaryPathService()->getResourceType($fileIdentifier),
            'overwrite' => true,
        ]);

        $this->checkCloudinaryUploadStatus($cloudinaryResource, $fileIdentifier);

        // We persist the uploaded resource.
        $this->getCloudinaryResourceService()->save($cloudinaryResource);

        return true;
    }

    /**
     * @param string $fileIdentifier
     */
    public function deleteFile($fileIdentifier): bool
    {
        $cloudinaryPublicId = $this->getCloudinaryPathService()->computeCloudinaryPublicId($fileIdentifier);
        $this->log(
            '[API] Cloudinary\Api::delete_resources - delete resource "%s"',
            [$cloudinaryPublicId],
            ['deleteFile'],
        );

        $response = $this->getApi()->delete_resources($cloudinaryPublicId, [
            'resource_type' => $this->getCloudinaryPathService()->getResourceType($fileIdentifier),
        ]);

        $isDeleted = false;

        foreach ($response['deleted'] as $publicId => $status) {
            if ($status === 'deleted') {
                $isDeleted = (bool)$this->getCloudinaryResourceService()->delete($publicId);
            }
        }

        return $isDeleted;
    }

    /**
     * @param string $folderIdentifier
     * @param bool $deleteRecursively
     */
    public function deleteFolder($folderIdentifier, $deleteRecursively = false): bool
    {
        $cloudinaryFolder = $this->getCloudinaryPathService()->computeCloudinaryFolderPath($folderIdentifier);

        if ($deleteRecursively) {
            $this->log(
                '[API] Cloudinary\Api::delete_resources_by_prefix() - folder "%s"',
                [$cloudinaryFolder],
                ['deleteFolder'],
            );
            $response = $this->getApi()->delete_resources_by_prefix($cloudinaryFolder);

            foreach ($response['deleted'] as $publicId => $status) {
                if ($status === 'deleted') {
                    $this->getCloudinaryResourceService()->delete($publicId);
                }
            }
        }

        // We make sure the folder exists first. It will also delete sub-folder if those ones are empty.
        if ($this->folderExists($folderIdentifier)) {
            $this->log(
                '[API] Cloudinary\Api::delete_folder() - folder "%s"',
                [$cloudinaryFolder],
                ['deleteFolder'],
            );
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
     */
    public function getFileForLocalProcessing($fileIdentifier, $writable = true): string
    {
        $temporaryPath = $this->getTemporaryPathForFile($fileIdentifier);

        if (!is_file($temporaryPath) || !filesize($temporaryPath)) {
            $this->log(
                '[SLOW] Downloading for local processing "%s"',
                [$fileIdentifier],
                ['getFileForLocalProcessing'],
            );

            file_put_contents($temporaryPath, file_get_contents($this->getPublicUrl($fileIdentifier)));
            $this->log('File downloaded into "%s"', [$temporaryPath], ['getFileForLocalProcessing']);
        }

        return $temporaryPath;
    }

    /**
     * @param string $fileName
     * @param string $parentFolderIdentifier
     */
    public function createFile($fileName, $parentFolderIdentifier): string
    {
        throw new RuntimeException(
            'createFile: not implemented action! Cloudinary Driver is limited to images.',
            1570728107,
        );
    }

    /**
     * @param string $newFolderName
     * @param string $parentFolderIdentifier
     * @param bool $recursive
     */
    public function createFolder($newFolderName, $parentFolderIdentifier = '', $recursive = false): string
    {
        $canonicalFolderPath = $this->canonicalizeFolderIdentifierAndFolderName(
            $parentFolderIdentifier,
            $newFolderName,
        );
        $cloudinaryFolder = $this->getCloudinaryPathService()->normalizeCloudinaryPublicId($canonicalFolderPath);

        $this->log('[API] Cloudinary\Api::createFolder() - folder "%s"', [$cloudinaryFolder], ['createFolder']);
        $response = $this->getApi()->create_folder($cloudinaryFolder);

        if (!$response['success']) {
            throw new \Exception('Folder creation failed: ' . $cloudinaryFolder, 1591775050);
        }
        $this->getCloudinaryFolderService()->save($cloudinaryFolder);

        return $canonicalFolderPath;
    }

    /**
     * @param string $fileIdentifier
     */
    public function getFileContents($fileIdentifier): string
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
     */
    public function setFileContents($fileIdentifier, $contents)
    {
        throw new RuntimeException('setFileContents: not implemented action!', 1570728106);
    }

    /**
     * @param string $fileIdentifier
     * @param string $newFileIdentifier The target path (including the file name!)
     */
    public function renameFile($fileIdentifier, $newFileIdentifier): string
    {
        if (!$this->isFileIdentifier($newFileIdentifier)) {
            $sanitizedFileName = $this->sanitizeFileName(PathUtility::basename($newFileIdentifier));
            $folderPath = PathUtility::dirname($fileIdentifier);
            $newFileIdentifier = $this->canonicalizeAndCheckFileIdentifier(
                $this->canonicalizeAndCheckFolderIdentifier($folderPath) . $sanitizedFileName,
            );
        }

        $cloudinaryPublicId = $this->getCloudinaryPathService()->computeCloudinaryPublicId($fileIdentifier);
        $newCloudinaryPublicId = $this->getCloudinaryPathService()->computeCloudinaryPublicId($newFileIdentifier);

        if ($cloudinaryPublicId !== $newCloudinaryPublicId) {
            // Before calling API, make sure we are connected with the right "bucket"
            $this->initializeApi();

            // Rename the file
            $cloudinaryResource = Uploader::rename($cloudinaryPublicId, $newCloudinaryPublicId, [
                'resource_type' => $this->getCloudinaryPathService()->getResourceType($fileIdentifier),
                'overwrite' => true,
            ]);

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
     */
    protected function checkCloudinaryUploadStatus(array $cloudinaryResource, $fileIdentifier): void
    {
        if (!$cloudinaryResource && $cloudinaryResource['type'] !== 'upload') {
            throw new RuntimeException('Cloudinary upload failed for ' . $fileIdentifier, 1591954950);
        }
    }

    /**
     * @param string $folderIdentifier
     * @param string $newFolderName
     *
     * @return array A map of old to new file identifiers of all affected resources
     */
    public function renameFolder($folderIdentifier, $newFolderName): array
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
    public function moveFolderWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName): array
    {
        // Compute the new folder identifier and then create it.
        $newTargetFolderIdentifier = $this->canonicalizeFolderIdentifierAndFolderName(
            $targetFolderIdentifier,
            $newFolderName,
        );

        if (!$this->folderExists($newTargetFolderIdentifier)) {
            $this->createFolder($newTargetFolderIdentifier);
        }

        $movedFiles = [];
        $files = $this->getFilesInFolder($sourceFolderIdentifier, 0, -1);
        foreach ($files as $fileIdentifier) {
            $movedFiles[$fileIdentifier] = $this->moveFileWithinStorage(
                $fileIdentifier,
                $newTargetFolderIdentifier,
                PathUtility::basename($fileIdentifier),
            );
        }

        // Delete the old and empty folder
        $this->deleteFolder($sourceFolderIdentifier);

        return $movedFiles;
    }

    /**
     * @param string $sourceFolderIdentifier
     * @param string $targetFolderIdentifier
     * @param string $newFolderName
     */
    public function copyFolderWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName): bool
    {
        // Compute the new folder identifier and then create it.
        $newTargetFolderIdentifier = $this->canonicalizeFolderIdentifierAndFolderName(
            $targetFolderIdentifier,
            $newFolderName,
        );

        if (!$this->folderExists($newTargetFolderIdentifier)) {
            $this->createFolder($newTargetFolderIdentifier);
        }

        $files = $this->getFilesInFolder($sourceFolderIdentifier, 0, -1, true);
        foreach ($files as $fileIdentifier) {
            $newFileIdentifier = str_replace($sourceFolderIdentifier, $newTargetFolderIdentifier, $fileIdentifier);

            $this->copyFileWithinStorage(
                $fileIdentifier,
                GeneralUtility::dirname($newFileIdentifier),
                PathUtility::basename($fileIdentifier),
            );
        }

        return true;
    }

    /**
     * Checks if a folder contains files and (if supported) other folders.
     *
     * @param string $folderIdentifier
     */
    public function isFolderEmpty($folderIdentifier): bool
    {
        return $this->getCloudinaryFolderService()->countSubFolders($folderIdentifier);
    }

    /**
     * @param string $folderIdentifier
     * @param string $identifier identifier to be checked against $folderIdentifier
     */
    public function isWithin($folderIdentifier, $identifier): bool
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

        return \str_starts_with($fileIdentifier, $folderIdentifier);
    }

    /**
     * @param string $folderIdentifier
     */
    public function getFolderInfoByIdentifier($folderIdentifier): array
    {
        $canonicalFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        return [
            'identifier' => $canonicalFolderIdentifier,
            'name' => PathUtility::basename(
                $this->getCloudinaryPathService()->normalizeCloudinaryPublicId($canonicalFolderIdentifier),
            ),
            'storage' => $this->storageUid,
        ];
    }

    /**
     * @param string $fileName
     * @param string $folderIdentifier
     */
    public function getFileInFolder($fileName, $folderIdentifier): string
    {
        $folderIdentifier = $folderIdentifier . DIRECTORY_SEPARATOR . $fileName;
        return $folderIdentifier;
    }

    /**
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
     */
    public function getFilesInFolder(
        $folderIdentifier,
        $start = 0,
        $numberOfItems = 40,
        $recursive = false,
        array $filterCallbacks = [],
        $sort = '',
        $sortRev = false
    ): array
    {
        $cloudinaryFolder = $this->getCloudinaryPathService()->computeCloudinaryFolderPath(
            $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier),
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
            'direction' => isset($parameters['reverse']) && (int)$parameters['reverse'] ? 'DESC' : 'ASC',
        ];

        $pagination = [
            'maxResult' => $numberOfItems,
            'firstResult' => (int)GeneralUtility::_GP('pointer'),
        ];

        $cloudinaryResources = $this->getCloudinaryResourceService()->getResources(
            $cloudinaryFolder,
            $orderings,
            $pagination,
            $recursive,
        );

        // Generate list of folders for the file module.
        $files = [];
        foreach ($cloudinaryResources as $cloudinaryResource) {
            // Compute file identifier
            $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier(
                $this->getCloudinaryPathService()->computeFileIdentifier($cloudinaryResource),
            );

            $result = $this->applyFilterMethodsToDirectoryItem(
                $filterCallbacks,
                basename($fileIdentifier),
                $fileIdentifier,
                dirname($fileIdentifier),
            );

            if ($result) {
                $files[] = $fileIdentifier;
            }
        }

        return $files;
    }

    /**
     * @param string $folderIdentifier
     * @param bool $recursive
     * @param array $filterCallbacks callbacks for filtering the items
     */
    public function countFilesInFolder($folderIdentifier, $recursive = false, array $filterCallbacks = []): int
    {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);

        // true means we have non-core filters that has been added and we must filter on the PHP side.
        if (count($filterCallbacks) > 1) {
            $files = $this->getFilesInFolder($folderIdentifier, 0, 0, $recursive, $filterCallbacks);
            $result = count($files);
        } else {
            $result = $this->getCloudinaryResourceService()->count(
                $this->getCloudinaryPathService()->computeCloudinaryFolderPath($folderIdentifier),
                $recursive,
            );
        }
        return $result;
    }

    /**
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
     */
    public function getFoldersInFolder(
        $folderIdentifier,
        $start = 0,
        $numberOfItems = 40,
        $recursive = false,
        array $filterCallbacks = [],
        $sort = '',
        $sortRev = false
    ): array
    {
        $parameters = (array)GeneralUtility::_GP('SET');

        $cloudinaryFolder = $this->getCloudinaryPathService()->computeCloudinaryFolderPath(
            $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier),
        );

        $cloudinaryFolders = $this->getCloudinaryFolderService()->getSubFolders(
            $cloudinaryFolder,
            [
                'fieldName' => 'folder',
                'direction' => isset($parameters['reverse']) && (int)$parameters['reverse'] ? 'DESC' : 'ASC',
            ],
            $recursive,
        );

        // Generate list of folders for the file module.
        $folders = [];
        foreach ($cloudinaryFolders as $cloudinaryFolder) {
            $folderIdentifier = $this->getCloudinaryPathService()->computeFolderIdentifier($cloudinaryFolder['folder']);

            $result = $this->applyFilterMethodsToDirectoryItem(
                $filterCallbacks,
                basename($folderIdentifier),
                $folderIdentifier,
                dirname($folderIdentifier),
            );

            if ($result) {
                $folders[] = $folderIdentifier;
            }
        }

        return $folders;
    }

    /**
     * @param string $folderIdentifier
     * @param bool $recursive
     * @param array $filterCallbacks
     */
    public function countFoldersInFolder($folderIdentifier, $recursive = false, array $filterCallbacks = []): int
    {
        // true means we have non-core filters that has been added and we must filter on the PHP side.
        if (count($filterCallbacks) > 1) {
            $folders = $this->getFoldersInFolder($folderIdentifier, 0, 0, $recursive, $filterCallbacks);
            $result = count($folders);
        } else {
            $cloudinaryFolder = $this->getCloudinaryPathService()->computeCloudinaryFolderPath(
                $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier),
            );

            $result = $this->getCloudinaryFolderService()->countSubFolders($cloudinaryFolder, $recursive);
        }

        return $result;
    }

    /**
     * @param string $identifier
     */
    public function dumpFileContents($identifier): string
    {
        return $this->getFileContents($identifier);
    }

    /**
     * Returns the permissions of a file/folder as an array
     * (keys r, w) of bool flags
     *
     * @param string $identifier
     */
    public function getPermissions($identifier): array
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
     */
    public function mergeConfigurationCapabilities($capabilities): int
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
     */
    public function sanitizeFileName($fileName, $charset = ''): string
    {
        $fileName = $this->charsetConversion->specCharsToASCII('utf-8', $fileName);

        // Replace unwanted characters by underscores
        $cleanFileName = preg_replace(
            '/[' . self::UNSAFE_FILENAME_CHARACTER_EXPRESSION . '\\xC0-\\xFF]/',
            '_',
            trim($fileName),
        );

        // Strip trailing dots and return
        $cleanFileName = rtrim($cleanFileName, '.');
        if ($cleanFileName === '') {
            throw new InvalidFileNameException('File name "' . $fileName . '" is invalid.', 1320288991);
        }

        $pathParts = PathUtility::pathinfo($cleanFileName);

        $cleanFileName =
            str_replace('.', '_', $pathParts['filename']) .
            ($pathParts['extension'] ? '.' . $pathParts['extension'] : '');

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
     */
    protected function applyFilterMethodsToDirectoryItem(
        array $filterMethods,
              $itemName,
              $itemIdentifier,
              $parentIdentifier
    ): bool
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
                        1596795500,
                    );
                }
            }
        }
        return true;
    }

    /**
     * We want to remove the local temporary file
     */
    protected function cleanUpTemporaryFile(string $fileIdentifier): void
    {
        $temporaryLocalFile = CloudinaryFileUtility::getTemporaryFile($this->storageUid, $fileIdentifier);
        if (is_file($temporaryLocalFile)) {
            unlink($temporaryLocalFile);
        }

        // very coupled.... via signal slot?
        $this->getExplicitDataCacheRepository()->delete($this->storageUid, $fileIdentifier);
    }

    public function getExplicitDataCacheRepository(): ExplicitDataCacheRepository
    {
        return GeneralUtility::makeInstance(ExplicitDataCacheRepository::class);
    }

    protected function getProcessedFilePattern(): string
    {
        return sprintf('/^PROCESSEDFILE\/(%s\/.*)/', $this->configurationService->get('cloudName'));
    }

    protected function isProcessedFile(string $identifier): bool
    {
        return (bool)preg_match($this->getProcessedFilePattern(), $identifier);
    }

    protected function getProcessedPath(string $identifier): string|null
    {
        $cloudinaryPath = null;
        if (preg_match($this->getProcessedFilePattern(), $identifier, $matches)) {
            [, $cloudinaryPath] = $matches;
        }
        return $cloudinaryPath;
    }

    protected function isFileIdentifier(string $newFileIdentifier): bool
    {
        return str_contains($newFileIdentifier, DIRECTORY_SEPARATOR);
    }

    protected function canonicalizeFolderIdentifierAndFolderName(string $folderIdentifier, string $folderName): string
    {
        $canonicalFolderPath = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        return $this->canonicalizeAndCheckFolderIdentifier(
            $canonicalFolderPath . trim($folderName, DIRECTORY_SEPARATOR),
        );
    }

    protected function canonicalizeFolderIdentifierAndFileName(string $folderIdentifier, string $fileName): string
    {
        return $this->canonicalizeAndCheckFileIdentifier(
            $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier) . $fileName,
        );
    }

    protected function getCloudinaryPathService(): CloudinaryPathService
    {
        if (!$this->cloudinaryPathService) {
            if ($this->storageUid) {
                $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
                $storage = $resourceFactory->getStorageObject($this->storageUid);
            }
            $this->cloudinaryPathService = GeneralUtility::makeInstance(
                CloudinaryPathService::class,
                $this->storageUid
                    ? $storage
                    : $this->configuration,
            );
        }

        return $this->cloudinaryPathService;
    }

    protected function getCloudinaryResourceService(): CloudinaryResourceService
    {
        if (!$this->cloudinaryResourceService) {
            /** @var ResourceFactory $resourceFactory */
            $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

            $this->cloudinaryResourceService = GeneralUtility::makeInstance(
                CloudinaryResourceService::class,
                $resourceFactory->getStorageObject($this->storageUid),
            );
        }

        return $this->cloudinaryResourceService;
    }

    protected function getCloudinaryTestConnectionService(): CloudinaryTestConnectionService
    {
        return GeneralUtility::makeInstance(CloudinaryTestConnectionService::class, $this->configuration);
    }

    protected function getCloudinaryFolderService(): CloudinaryFolderService
    {
        if (!$this->cloudinaryFolderService) {
            $this->cloudinaryFolderService = GeneralUtility::makeInstance(
                CloudinaryFolderService::class,
                $this->storageUid,
            );
        }

        return $this->cloudinaryFolderService;
    }

    protected function initializeApi(): void
    {
        Cloudinary::config([
            'cloud_name' => $this->configurationService->get('cloudName'),
            'api_key' => $this->configurationService->get('apiKey'),
            'api_secret' => $this->configurationService->get('apiSecret'),
            'timeout' => $this->configurationService->get('timeout'),
            'secure' => true,
        ]);
    }

    protected function getApi(): Api
    {
        $this->initializeApi();

        // The object \Cloudinary\Api behaves like a singleton object.
        // The problem: if we have multiple driver instances / configuration, we don't get the expected result
        // meaning we are wrongly fetching resources from other cloudinary "buckets" because of the singleton behaviour
        // Therefore it is better to create a new instance upon each API call to avoid driver confusion
        return new Api();
    }
}
