<?php

namespace Visol\Cloudinary\Driver;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Cloudinary\Api;
use Cloudinary\Search;
use Visol\Cloudinary\Cache\CloudinaryTypo3Cache;
use Visol\Cloudinary\Utility\CloudinaryPathUtility;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Resource\Driver\AbstractHierarchicalFilesystemDriver;
use TYPO3\CMS\Core\Resource\Exception;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class CloudinaryDriver
 */
class CloudinaryDriver extends AbstractHierarchicalFilesystemDriver
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
     * @var array[]
     */
    protected $cachedCloudinaryResources = [];

    /**
     * @var array
     */
    protected $cachedFolders = [];

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
     * @var string
     */
    protected $languageFile = 'LLL:EXT:cloudinary/Resources/Private/Language/backend.xlf';

    /**
     * @var Dispatcher
     */
    protected $signalSlotDispatcher;

    /**
     * @var Api $api
     */
    protected $api;

    /**
     * @var CloudinaryTypo3Cache
     */
    protected $cloudinaryTypo3Cache;

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
     * @param string $key
     * @return string
     */
    public function getConfiguration(string $key): string
    {
        return isset($this->configuration[$key])
            ? (string)$this->configuration[$key]
            : '';
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
            $this->testConnection();
        }
    }

    /**
     * @param string $fileIdentifier
     * @return string
     */
    public function getPublicUrl($fileIdentifier)
    {
        return $this->resourceExists($fileIdentifier)
            ? $this->getCloudinaryResource($fileIdentifier)['secure_url']
            : '';
    }

    /**
     * @param string $message
     * @param array $arguments
     */
    public function log(string $message, array $arguments = [])
    {
        /** @var \TYPO3\CMS\Core\Log\Logger $logger */
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $logger->log(
            LogLevel::INFO,
            vsprintf('[DRIVER] ' . $message, $arguments)
        );
    }

    /**
     * Creates a (cryptographic) hash for a file.
     *
     * @param string $fileIdentifier
     * @param string $hashAlgorithm
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
     * @return array
     * @throws \Exception
     */
    public function getFileInfoByIdentifier($fileIdentifier, array $propertiesToExtract = [])
    {
        if (!$this->resourceExists($fileIdentifier)) {

            // Make a new attempt since caching may interfere...
            $publicId = CloudinaryPathUtility::computeCloudinaryPublicId($fileIdentifier);
            $this->log('[API] method "getFileInfoByIdentifier": fetch resource with identifier "%s"', [$fileIdentifier]);
            $cloudinaryResource = (array)$this->getApi()->resource($publicId);

            $this->flushFileCache(); // We flush the cache again....

            // This time we have a problem...
            if (!$cloudinaryResource) {
                throw new \Exception(
                    sprintf(
                        'I could not retrieve the file info since no file was found for identifier "%s"',
                        $fileIdentifier
                    ),
                    1574783174
                );
            }
        } else {
            $cloudinaryResource = $this->getCloudinaryResource($fileIdentifier);
        }

        $canonicalFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier(dirname($fileIdentifier));

        return [
            'identifier_hash' => $this->hashIdentifier($fileIdentifier),
            'folder_hash' => sha1($canonicalFolderIdentifier),
            'creation_date' => strtotime($cloudinaryResource['created_at']),
            'modification_date' => strtotime($cloudinaryResource['created_at']),
            'mime_type' => $this->getMimeType($cloudinaryResource),
            'extension' => $cloudinaryResource['format'],
            'size' => $cloudinaryResource['bytes'],
            'width' => $cloudinaryResource['width'],
            'height' => $cloudinaryResource['height'],
            'storage' => $this->storageUid,
            'identifier' => $fileIdentifier,
            'type' => File::FILETYPE_IMAGE,
            'name' => PathUtility::basename($cloudinaryResource['secure_url'])
        ];
    }

    /**
     * Checks if a file exists
     *
     * @param string $identifier
     * @return bool
     */
    public function fileExists($identifier)
    {
        if (substr($identifier, -1) === DIRECTORY_SEPARATOR || $identifier === '') {
            return false;
        }
        return $this->resourceExists($identifier);
    }

    /**
     * Checks if a folder exists
     *
     * @param string $folderIdentifier
     * @return bool
     */
    public function folderExists($folderIdentifier)
    {
        try {
            // Will trigger an exception if the folder identifier does not exist.
            $this->getFoldersInFolder($folderIdentifier);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * @param string $fileName
     * @param string $folderIdentifier
     * @return bool
     */
    public function fileExistsInFolder($fileName, $folderIdentifier)
    {
        $fileIdentifier = $folderIdentifier . $fileName;
        $publicId = CloudinaryPathUtility::computeCloudinaryPublicId($fileIdentifier);
        return $this->resourceExists($publicId);
    }

    /**
     * Checks if a folder exists inside a storage folder
     *
     * @param string $folderName
     * @param string $folderIdentifier
     * @return bool
     */
    public function folderExistsInFolder($folderName, $folderIdentifier)
    {
        return $this->folderExists(
            $this->normalizeCloudinaryFolderNameWithPath($folderName, $folderIdentifier)
        );
    }

    /**
     * Returns the Identifier for a folder within a given folder.
     *
     * @param string $folderName The name of the target folder
     * @param string $folderIdentifier
     * @return string
     */
    public function getFolderInFolder($folderName, $folderIdentifier)
    {
        $identifier = $folderIdentifier . DIRECTORY_SEPARATOR . $folderName;
        return $identifier;
    }

    /**
     * @param string $localFilePath (within PATH_site)
     * @param string $targetFolderIdentifier
     * @param string $newFileName optional, if not given original name is used
     * @param bool $removeOriginal if set the original file will be removed
     *                                after successful operation
     * @return string the identifier of the new file
     * @throws \Exception
     */
    public function addFile($localFilePath, $targetFolderIdentifier, $newFileName = '', $removeOriginal = true)
    {
        // Necessary to happen in an early stage.
        $this->flushFileCache();

        $fileName = $this->sanitizeFileName(
            $newFileName !== ''
                ? $newFileName :
                PathUtility::basename($localFilePath)
        );

        $publicId = PathUtility::basename(
            CloudinaryPathUtility::computeCloudinaryPublicId($fileName)
        );

        // Before calling API, make sure we are connected with the right "bucket"
        $this->initializeApi();

        // Upload the file
        \Cloudinary\Uploader::upload(
            $localFilePath,
            [
                'public_id' => $publicId,
                'folder' => CloudinaryPathUtility::computeCloudinaryPath($targetFolderIdentifier),
                'overwrite' => true,
            ]
        );

        $targetIdentifier = $targetFolderIdentifier . $fileName;
        return $targetIdentifier;
    }

    /**
     * @return void
     */
    protected function initializeApi()
    {
        \Cloudinary::config(
            [
                'cloud_name' => $this->getConfiguration('cloudName'),
                'api_key' => $this->getConfiguration('apiKey'),
                'api_secret' => $this->getConfiguration('apiSecret'),
                'timeout' => $this->getConfiguration('timeout'),
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
        return new \Cloudinary\Api();
    }

    /**
     * @param $filename
     * @return string
     */
    protected function stripExtension($filename): string
    {
        // Other possible way of computing the file extension using TYPO3 API
        // '.' . PathUtility::pathinfo($filename, PATHINFO_EXTENSION)
        return preg_replace('/\\.[^.\\s]{3,4}$/', '', $filename);
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
     * @return string the Identifier of the new file
     */
    public function copyFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $fileName)
    {
        // Flush the file cache entries
        $this->flushFileCache();

        // Before calling API, make sure we are connected with the right "bucket"
        $this->initializeApi();

        \Cloudinary\Uploader::upload(
            $this->getPublicUrl($fileIdentifier),
            [
                'public_id' => PathUtility::basename(
                    CloudinaryPathUtility::computeCloudinaryPublicId($fileName)
                ),
                'folder' => CloudinaryPathUtility::computeCloudinaryPath($targetFolderIdentifier),
                'overwrite' => true,
            ]
        );

        $targetIdentifier = $targetFolderIdentifier . $fileName;
        return $targetIdentifier;
    }

    /**
     * Replaces a file with file in local file system.
     *
     * @param string $fileIdentifier
     * @param string $localFilePath
     * @return bool TRUE if the operation succeeded
     */
    public function replaceFile($fileIdentifier, $localFilePath)
    {
        $publicId = PathUtility::basename(CloudinaryPathUtility::computeCloudinaryPublicId($fileIdentifier));
        $folderPath = CloudinaryPathUtility::computeCloudinaryPath(dirname($fileIdentifier));

        $options = [
            'public_id' => $publicId,
            'folder' => $folderPath,
            'overwrite' => true,
        ];

        // Before calling the API, make sure we are connected with the right "bucket"
        $this->initializeApi();

        // Upload the file
        \Cloudinary\Uploader::upload($localFilePath, $options);

        // Flush the file cache entries
        $this->flushFileCache();

        return true;
    }

    /**
     * Removes a file from the filesystem. This does not check if the file is
     * still used or if it is a bad idea to delete it for some other reason
     * this has to be taken care of in the upper layers (e.g. the Storage)!
     *
     * @param string $fileIdentifier
     * @return bool TRUE if deleting the file succeeded
     */
    public function deleteFile($fileIdentifier)
    {
        // Necessary to happen in an early stage.
        $this->flushFileCache();

        $this->log('[API] method "deleteFile": delete resource with identifier "%s"', [$fileIdentifier]);
        $response = $this->getApi()->delete_resources([
            CloudinaryPathUtility::computeCloudinaryPublicId($fileIdentifier)
        ]);

        return is_array($response['deleted']);
    }

    /**
     * Removes a folder in filesystem.
     *
     * @param string $folderIdentifier
     * @param bool $deleteRecursively
     * @return bool
     */
    public function deleteFolder($folderIdentifier, $deleteRecursively = false)
    {
        // Flush the folder cache entries
        $this->flushFolderCache();

        $normalizedFolderIdentifier = CloudinaryPathUtility::computeCloudinaryPath($folderIdentifier);

        if ($deleteRecursively) {
            $this->getApi()->delete_resources_by_prefix($normalizedFolderIdentifier);
        }

        $this->log('[API] method "deleteFolder": delete folder with folder identifier "%s"', [$folderIdentifier]);
        $this->getApi()->delete_folder($normalizedFolderIdentifier);

        return true;
    }

    /**
     * Returns a path to a local copy of a file for processing it. When changing the
     * file, you have to take care of replacing the current version yourself!
     * The file will be removed by the driver automatically on destruction.
     *
     * @param string $fileIdentifier
     * @param bool $writable Set this to FALSE if you only need the file for read
     *                         operations. This might speed up things, e.g. by using
     *                         a cached local version. Never modify the file if you
     *                         have set this flag!
     * @return string The path to the file on the local disk
     * @throws \RuntimeException
     */
    public function getFileForLocalProcessing($fileIdentifier, $writable = true)
    {
        $temporaryPath = $this->getTemporaryPathForFile($fileIdentifier);

        if ((!is_file($temporaryPath) || !filesize($temporaryPath)) && $this->resourceExists($fileIdentifier)) {

            $resource = $this->getCloudinaryResource($fileIdentifier);

            $this->log('Downloading for local processing file "%s"', [$fileIdentifier]);
            file_put_contents($temporaryPath, fopen($resource['secure_url'], 'r'));
            $this->emitGetFileForLocalProcessingSignal($fileIdentifier, $temporaryPath, $writable);
        }

        return $temporaryPath;
    }

    /**
     * @param string $fileIdentifier
     * @param string $temporaryPath
     * @param bool $writable
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    protected function emitGetFileForLocalProcessingSignal(&$fileIdentifier, &$temporaryPath, &$writable)
    {
        list($fileIdentifier, $temporaryPath, $writable) = $this->getSignalSlotDispatcher()->dispatch(
            self::class,
            'getFileForLocalProcessing',
            [$fileIdentifier, $temporaryPath, $writable]
        );
    }

    /**
     * Creates a new (empty) file and returns the identifier.
     *
     * @param string $fileName
     * @param string $parentFolderIdentifier
     * @return string
     */
    public function createFile($fileName, $parentFolderIdentifier)
    {
        throw new \RuntimeException('Not implemented action! Cloudinary Driver is limited to images.', 1570728107);
    }

    /**
     * Creates a folder, within a parent folder.
     * If no parent folder is given, a root level folder will be created
     *
     * @param string $newFolderName
     * @param string $parentFolderIdentifier
     * @param bool $recursive
     * @return string the Identifier of the new folder
     */
    public function createFolder($newFolderName, $parentFolderIdentifier = '', $recursive = false)
    {
        // Flush the folder cache entries
        $this->flushFolderCache();

        $normalizedFolderIdentifier = $this->normalizeCloudinaryFolderNameWithPath($newFolderName, $parentFolderIdentifier);

        $this->log('[API] method "createFolder": create folder with identifier "%s"', [$normalizedFolderIdentifier]);
        $this->getApi()->create_folder(
            $normalizedFolderIdentifier
        );

        return $normalizedFolderIdentifier;
    }

    /**
     * Returns the contents of a file. Beware that this requires to load the
     * complete file into memory and also may require fetching the file from an
     * external location. So this might be an expensive operation (both in terms
     * of processing resources and money) for large files.
     *
     * @param string $fileIdentifier
     * @return string The file contents
     */
    public function getFileContents($fileIdentifier)
    {
        throw new \RuntimeException('Not implemented action! Cloudinary Driver is limited to images.', 1570728105);
    }

    /**
     * Sets the contents of a file to the specified value.
     *
     * @param string $fileIdentifier
     * @param string $contents
     * @return int
     */
    public function setFileContents($fileIdentifier, $contents)
    {
        throw new \RuntimeException('Not implemented action! Cloudinary Driver is limited to images.', 1570728106);
    }

    /**
     * Renames a file in this storage.
     *
     * @param string $fileIdentifier
     * @param string $newFileIdentifier The target path (including the file name!)
     * @return string The identifier of the file after renaming
     */
    public function renameFile($fileIdentifier, $newFileIdentifier)
    {
        if (!$this->isFileIdentifier($newFileIdentifier)) {
            $newFileIdentifier = $this->computeNewFileNameAndIdentifier($fileIdentifier, PathUtility::basename($newFileIdentifier));
        }

        $cloudinaryPublicId = CloudinaryPathUtility::computeCloudinaryPublicId($fileIdentifier);
        $newCloudinaryPublicId = CloudinaryPathUtility::computeCloudinaryPublicId($newFileIdentifier);

        if ($cloudinaryPublicId !== $newCloudinaryPublicId) {

            // Necessary to happen in an early stage.
            $this->flushFileCache();

            // Before calling API, make sure we are connected with the right "bucket"
            $this->initializeApi();

            // Rename the file
            \Cloudinary\Uploader::rename($cloudinaryPublicId, $newCloudinaryPublicId);
        }

        return $newFileIdentifier;
    }

    /**
     * @param string $newFileIdentifier
     * @return bool
     */
    public function isFileIdentifier(string $newFileIdentifier): bool
    {
        return false !== strpos($newFileIdentifier, DIRECTORY_SEPARATOR);
    }

    /**
     * Renames a folder in this storage.
     *
     * @param string $folderIdentifier
     * @param string $newFolderName
     * @return array A map of old to new file identifiers of all affected resources
     */
    public function renameFolder($folderIdentifier, $newFolderName)
    {
        $renamedFiles = [];

        foreach ($this->getFilesInFolder($folderIdentifier, 0, -1) as $fileIdentifier) {
            $resource = $this->getCloudinaryResource($fileIdentifier);
            $cloudinaryPublicId = $resource['public_id'];

            $pathSegments = GeneralUtility::trimExplode('/', $cloudinaryPublicId);

            $numberOfSegments = count($pathSegments);
            if ($numberOfSegments > 1) {

                // Replace last folder name by the new folder name
                $pathSegments[$numberOfSegments - 2] = $newFolderName;
                $newCloudinaryPublicId = implode('/', $pathSegments);

                if ($cloudinaryPublicId !== $newCloudinaryPublicId) {

                    // Flush files + folder cache
                    $this->flushCache();

                    // Before calling the API, make sure we are connected with the right "bucket"
                    $this->initializeApi();

                    // Rename the file
                    \Cloudinary\Uploader::rename($cloudinaryPublicId, $newCloudinaryPublicId);
                    $oldFileIdentifier = CloudinaryPathUtility::computeFileIdentifier($resource);
                    $newFileIdentifier = CloudinaryPathUtility::computeFileIdentifier(
                        [
                            'public_id' => $newCloudinaryPublicId,
                            'format' => $resource['format'],
                        ]
                    );
                    $renamedFiles[$oldFileIdentifier] = $newFileIdentifier;
                }
            }
        }

        // After working so hard, delete the old empty folder.
        $this->deleteFolder($folderIdentifier);

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
        $newTargetFolderIdentifier = $targetFolderIdentifier . $newFolderName . DIRECTORY_SEPARATOR;
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
        $newTargetFolderIdentifier = $targetFolderIdentifier . $newFolderName . DIRECTORY_SEPARATOR;
        if (!$this->folderExists($newTargetFolderIdentifier)) {
            $this->createFolder($newTargetFolderIdentifier);
        }

        $files = $this->getFilesInFolder($sourceFolderIdentifier, 0, -1);
        foreach ($files as $fileIdentifier) {
            $this->copyFileWithinStorage($fileIdentifier, $newTargetFolderIdentifier, PathUtility::basename($fileIdentifier));
        }

        return true;
    }

    /**
     * Checks if a folder contains files and (if supported) other folders.
     *
     * @param string $folderIdentifier
     * @return bool TRUE if there are no files and folders within $folder
     */
    public function isFolderEmpty($folderIdentifier)
    {
        $normalizedIdentifier = CloudinaryPathUtility::computeCloudinaryPath($folderIdentifier);
        $this->log('[API] method "isFolderEmpty": fetch files with identifier "%s"', [$normalizedIdentifier]);
        $response = $this->getApi()->resources([
            'resource_type' => 'image',
            'type' => 'upload',
            'max_results' => 1,
            'prefix' => $normalizedIdentifier,
        ]);

        return empty($response['resources']);
    }

    /**
     * Checks if a given identifier is within a container, e.g. if
     * a file or folder is within another folder.
     * This can e.g. be used to check for web-mounts.
     *
     * Hint: this also needs to return TRUE if the given identifier
     * matches the container identifier to allow access to the root
     * folder of a filemount.
     *
     * @param string $folderIdentifier
     * @param string $identifier identifier to be checked against $folderIdentifier
     * @return bool TRUE if $content is within or matches $folderIdentifier
     */
    public function isWithin($folderIdentifier, $identifier)
    {
        $folderIdentifier = $this->canonicalizeAndCheckFileIdentifier($folderIdentifier);
        $entryIdentifier = $this->canonicalizeAndCheckFileIdentifier($identifier);
        if ($folderIdentifier === $entryIdentifier) {
            return true;
        }

        // File identifier canonicalization will not modify a single slash so
        // we must not append another slash in that case.
        if ($folderIdentifier !== DIRECTORY_SEPARATOR) {
            $folderIdentifier .= DIRECTORY_SEPARATOR;
        }

        return GeneralUtility::isFirstPartOfStr($entryIdentifier, $folderIdentifier);
    }

    /**
     * Returns information about a file.
     *
     * @param string $folderIdentifier
     * @return array
     */
    public function getFolderInfoByIdentifier($folderIdentifier)
    {
        $canonicalFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        return [
            'identifier' => $canonicalFolderIdentifier,
            'name' => PathUtility::basename(rtrim($canonicalFolderIdentifier, DIRECTORY_SEPARATOR)),
            'storage' => $this->storageUid
        ];
    }

    /**
     * Returns a file inside the specified path
     *
     * @param string $fileName
     * @param string $folderIdentifier
     * @return string File Identifier
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
     * @param array $filenameFilterCallbacks callbacks for filtering the items
     * @param string $sort Property name used to sort the items.
     *                      Among them may be: '' (empty, no sorting), name,
     *                      fileext, size, tstamp and rw.
     *                      If a driver does not support the given property, it
     *                      should fall back to "name".
     * @param bool $sortRev TRUE to indicate reverse sorting (last to first)
     *
     * @return array of FileIdentifiers
     */
    public function getFilesInFolder($folderIdentifier, $start = 0, $numberOfItems = 40, $recursive = false, array $filenameFilterCallbacks = [], $sort = '', $sortRev = false)
    {
        if ($folderIdentifier === '') {
            throw new \RuntimeException(
                'Something went wrong in method "getFilesInFolder"! $folderIdentifier can not be empty',
                1574754623
            );
        }

        if (!isset($this->cachedCloudinaryResources[$folderIdentifier])) {

            // Try to fetch from the cache
            $this->cachedCloudinaryResources[$folderIdentifier] = $this->getCache()->getCachedFiles($folderIdentifier);

            // If not found in TYPO3 cache, ask Cloudinary
            if (!is_array($this->cachedCloudinaryResources[$folderIdentifier])) {

                $this->log('[API] method "getFilesInFolder": fetch resources with folder identifier "%s"', [$folderIdentifier]);

                $this->cachedCloudinaryResources[$folderIdentifier] = $this->getCloudinaryResources($folderIdentifier);
            }
        }

        // Set default sorting
        $parameters = (array)GeneralUtility::_GP('SET');
        if (empty($parameters)) {
            $parameters['sort'] = 'file';
            $parameters['reverse'] = 0;
        }

        // Sort files
        if ($parameters['sort'] === 'file') {

            if ((int)$parameters['reverse']) {
                uasort(
                    $this->cachedCloudinaryResources[$folderIdentifier],
                    '\Visol\Cloudinary\Utility\SortingUtility::sortByFileNameDesc'
                );
            } else {
                uasort(
                    $this->cachedCloudinaryResources[$folderIdentifier],
                    '\Visol\Cloudinary\Utility\SortingUtility::sortByFileNameAsc'
                );
            }
        } elseif ($parameters['sort'] === 'tstamp') {

            if ((int)$parameters['reverse']) {
                uasort(
                    $this->cachedCloudinaryResources[$folderIdentifier],
                    '\Visol\Cloudinary\Utility\SortingUtility::sortByTimeStampDesc'
                );
            } else {
                uasort(
                    $this->cachedCloudinaryResources[$folderIdentifier],
                    '\Visol\Cloudinary\Utility\SortingUtility::sortByTimeStampAsc'
                );
            }
        }

        // Pagination
        if ($numberOfItems > 0) {
            $files = array_slice(
                $this->cachedCloudinaryResources[$folderIdentifier],
                (int)GeneralUtility::_GP('pointer'),
                $numberOfItems
            );
        } else {
            $files = $this->cachedCloudinaryResources[$folderIdentifier];
        }

        return array_keys($files);
    }

    /**
     * Returns the number of files inside the specified path
     *
     * @param string $folderIdentifier
     * @param bool $recursive
     * @param array $filenameFilterCallbacks callbacks for filtering the items
     * @return int Number of files in folder
     */
    public function countFilesInFolder($folderIdentifier, $recursive = false, array $filenameFilterCallbacks = [])
    {
        if (!isset($this->cachedCloudinaryResources[$folderIdentifier])) {
            $this->getFilesInFolder($folderIdentifier, 0, -1, $recursive, $filenameFilterCallbacks);
        }
        return count($this->cachedCloudinaryResources[$folderIdentifier]);
    }

    /**
     * Returns a list of folders inside the specified path
     * @param string $folderIdentifier
     * @param int $start
     * @param int $numberOfItems
     * @param bool $recursive
     * @param array $folderNameFilterCallbacks callbacks for filtering the items
     * @param string $sort Property name used to sort the items.
     *                      Among them may be: '' (empty, no sorting), name,
     *                      fileext, size, tstamp and rw.
     *                      If a driver does not support the given property, it
     *                      should fall back to "name".
     * @param bool $sortRev TRUE to indicate reverse sorting (last to first)
     * @return array
     */
    public function getFoldersInFolder($folderIdentifier, $start = 0, $numberOfItems = 40, $recursive = false, array $folderNameFilterCallbacks = [], $sort = '', $sortRev = false)
    {
        if (!isset($this->cachedFolders[$folderIdentifier])) {

            // Try to fetch from the cache
            $this->cachedFolders[$folderIdentifier] = $this->getCache()->getCachedFolders($folderIdentifier);

            // If not found in TYPO3 cache, ask Cloudinary
            if (!is_array($this->cachedFolders[$folderIdentifier])) {

                $this->log('[API] method "getFoldersInFolder": fetch sub-folders with folder identifier "%s"', [$folderIdentifier]);

                $this->cachedFolders[$folderIdentifier] = $this->getCloudinaryFolders($folderIdentifier);
            }
        }

        // Sort
        $parameters = (array)GeneralUtility::_GP('SET');
        if (isset($parameters['sort']) && $parameters['sort'] === 'file') {
            (int)$parameters['reverse']
                ? krsort($this->cachedFolders[$folderIdentifier])
                : ksort($this->cachedFolders[$folderIdentifier]);
        }

        return $this->cachedFolders[$folderIdentifier];
    }

    /**
     * Returns the number of folders inside the specified path
     *
     * @param string $folderIdentifier
     * @param bool $recursive
     * @param array $folderNameFilterCallbacks callbacks for filtering the items
     * @return int Number of folders in folder
     */
    public function countFoldersInFolder($folderIdentifier, $recursive = false, array $folderNameFilterCallbacks = [])
    {
        return count($this->getFoldersInFolder($folderIdentifier, 0, -1, $recursive, $folderNameFilterCallbacks));
    }

    /**
     * Directly output the contents of the file to the output
     * buffer. Should not take care of header files or flushing
     * buffer before. Will be taken care of by the Storage.
     *
     * @param string $identifier
     * @return void
     */
    public function dumpFileContents($identifier)
    {
        throw new \RuntimeException('Not implemented action! Cloudinary Driver is limited to images.', 1570728104);
    }

    /**
     * Returns the permissions of a file/folder as an array
     * (keys r, w) of bool flags
     *
     * @param string $identifier
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
     * @param string $fileName Input string, typically the body of a fileName
     * @param string $charset Charset of the a fileName (defaults to current charset; depending on context)
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
                'File name ' . $fileName . ' is invalid.',
                1320288991
            );
        }
        return $cleanFileName;
    }

    /**
     * @param string $folderIdentifier
     * @return array
     */
    protected function getCloudinaryFolders(string $folderIdentifier): array
    {
        $folders = [];

        $resources = (array)$this->getApi()->subfolders(
            CloudinaryPathUtility::computeCloudinaryPath($folderIdentifier)
        );

        if (!empty($resources['folders'])) {
            foreach ($resources['folders'] as $cloudinaryFolder) {
                $folderIdentifierAndSubFolderName = $folderIdentifier . $cloudinaryFolder['name'];
                $folders[] = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifierAndSubFolderName);
            }
        }

        // Add result into typo3 cache to spare [API] Calls the next time...
        $this->getCache()->setCachedFolders($folderIdentifier, $folders);

        return $folders;
    }

    /**
     * @param string $folderIdentifier
     * @return array
     */
    protected function getCloudinaryResources(string $folderIdentifier): array
    {
        $resources = [];

        $cloudinaryFolder = $folderIdentifier === self::ROOT_FOLDER_IDENTIFIER
            ? self::ROOT_FOLDER_IDENTIFIER . '*'
            : CloudinaryPathUtility::computeCloudinaryPath($folderIdentifier);

        // Before calling the Search API, make sure we are connected with the right cloudinary account
        $this->initializeApi();

        do {

            $nextCursor = isset($response)
                ? $response['next_cursor']
                : '';

            $this->log(
                '[API] method "getCloudinaryResources": fetch resources with folder identifier "%s" %s',
                [
                    $folderIdentifier,
                    $nextCursor ? 'and cursor ' . $nextCursor : '',
                ]
            );

            /** @var Search $search */
            $search = new \Cloudinary\Search();
            $response = $search
                ->expression('resource_type:image AND folder=' . $cloudinaryFolder)
                ->sort_by('public_id', 'asc')
                ->max_results(500)
                ->next_cursor($nextCursor)
                ->execute();

            if (is_array($response['resources'])) {
                foreach ($response['resources'] as $resource) {

                    // Compute file identifier
                    $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier(
                        CloudinaryPathUtility::computeFileIdentifier($resource)
                    );

                    // Compute folder identifier
                    $computedFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier(
                        GeneralUtility::dirname($fileIdentifier)
                    );

                    // We manually filter the resources belonging to the given folder.
                    if ($computedFolderIdentifier === $folderIdentifier) {
                        $resources[$fileIdentifier] = $resource;
                    }
                }
            }

        } while (!empty($response) && array_key_exists('next_cursor', $response));

        // Add result into typo3 cache to spare API calls next time...
        $this->getCache()->setCachedFiles($folderIdentifier, $resources);

        return $resources;
    }

    /**
     * @param string $fileIdentifier
     * @return array|false
     */
    protected function getCloudinaryResource(string $fileIdentifier)
    {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier(
            GeneralUtility::dirname($fileIdentifier)
        );

        // Warm up the cache!
        if (!isset($this->cachedCloudinaryResources[$folderIdentifier][$fileIdentifier])) {
            $this->getFilesInFolder($folderIdentifier, 0, -1);
        }

        return isset($this->cachedCloudinaryResources[$folderIdentifier][$fileIdentifier])
            ? $this->cachedCloudinaryResources[$folderIdentifier][$fileIdentifier]
            : false;
    }

    /**
     * @param string $fileIdentifier
     * @param string $newName
     * @return string
     */
    protected function computeNewFileNameAndIdentifier(string $fileIdentifier, string $newName): string
    {
        $strippedPath = rtrim(PathUtility::dirname($fileIdentifier), DIRECTORY_SEPARATOR);
        return $strippedPath . DIRECTORY_SEPARATOR . $this->sanitizeFileName($newName);
    }

    /**
     * Gets the charset conversion object.
     *
     * @return \TYPO3\CMS\Core\Charset\CharsetConverter
     */
    protected function getCharsetConversion()
    {
        if (!isset($this->charsetConversion)) {
            $this->charsetConversion = GeneralUtility::makeInstance(CharsetConverter::class);
        }
        return $this->charsetConversion;
    }

    /**
     * Test the connection
     */
    protected function testConnection()
    {
        $messageQueue = $this->getMessageQueue();
        $localizationPrefix = $this->languageFile . ':driverConfiguration.message.';
        try {
            $this->getFilesInFolder(self::ROOT_FOLDER_IDENTIFIER);
            /** @var \TYPO3\CMS\Core\Messaging\FlashMessage $message */
            $message = GeneralUtility::makeInstance(
                FlashMessage::class,
                LocalizationUtility::translate($localizationPrefix . 'connectionTestSuccessful.message'),
                LocalizationUtility::translate($localizationPrefix . 'connectionTestSuccessful.title'),
                FlashMessage::OK
            );
            $messageQueue->addMessage($message);
        } catch (\Exception $exception) {
            /** @var \TYPO3\CMS\Core\Messaging\FlashMessage $message */
            $message = GeneralUtility::makeInstance(
                FlashMessage::class,
                $exception->getMessage(),
                LocalizationUtility::translate($localizationPrefix . 'connectionTestFailed.title'),
                FlashMessage::WARNING
            );
            $messageQueue->addMessage($message);
        }
    }

    /**
     * @return \TYPO3\CMS\Core\Messaging\FlashMessageQueue
     */
    protected function getMessageQueue()
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        /** @var FlashMessageService $flashMessageService */
        $flashMessageService = $objectManager->get(FlashMessageService::class);
        return $flashMessageService->getMessageQueueByIdentifier();
    }

    /**
     * Checks if an object exists
     *
     * @param string $fileIdentifier
     * @return bool
     */
    protected function resourceExists(string $fileIdentifier)
    {
        $resource = $this->getCloudinaryResource($fileIdentifier);
        if (empty($resource)) {
            $this->log(
                'Method "resourcesExists": I could not find file with identifier "%s"',
                [
                    $fileIdentifier,
                ]
            );
        }
        return !empty($resource);
    }

    /**
     * @param array $cloudinaryResource
     * @return string
     */
    protected function getMimeType(array $cloudinaryResource): string
    {
        $mimeType = $cloudinaryResource['resource_type'] . DIRECTORY_SEPARATOR . $cloudinaryResource['format'];

        // Handle the special jpg case which does not correspond to the file extension.
        return str_replace('jpg', 'jpeg', $mimeType);
    }

    /**
     * @param string $folderName
     * @param string $folderIdentifier
     * @return string
     */
    protected function normalizeCloudinaryFolderNameWithPath(string $folderName, string $folderIdentifier): string
    {
        $normalizedFolderIdentifier = trim($folderIdentifier, DIRECTORY_SEPARATOR);
        return $normalizedFolderIdentifier . DIRECTORY_SEPARATOR . $folderName;
    }

    /**
     * @return void
     */
    protected function flushCache(): void
    {
        $this->flushFolderCache();
        $this->flushFileCache();
    }

    /**
     * @return void
     */
    protected function flushFileCache(): void
    {
        // Flush the file cache entries
        $this->getCache()->flushFileCache();

        $this->cachedCloudinaryResources = [];
    }

    /**
     * @return void
     */
    protected function flushFolderCache(): void
    {
        // Flush the file cache entries
        $this->getCache()->flushFolderCache();

        $this->cachedFolders = [];
    }

    /**
     * Get the SignalSlot dispatcher
     *
     * @return Dispatcher
     */
    protected function getSignalSlotDispatcher()
    {
        if (!isset($this->signalSlotDispatcher)) {
            $this->signalSlotDispatcher = GeneralUtility::makeInstance(ObjectManager::class)->get(Dispatcher::class);
        }
        return $this->signalSlotDispatcher;
    }

    /**
     * @return CloudinaryTypo3Cache|object
     */
    protected function getCache()
    {
        if ($this->cloudinaryTypo3Cache === null) {
            $this->cloudinaryTypo3Cache = GeneralUtility::makeInstance(CloudinaryTypo3Cache::class, (int)$this->storageUid);
        }
        return $this->cloudinaryTypo3Cache;
    }
}
