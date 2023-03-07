<?php

namespace Visol\Cloudinary\Controller;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Visol\Cloudinary\Exceptions\CloudinaryNotFoundException;
use Visol\Cloudinary\Exceptions\PublicIdMissingException;
use Visol\Cloudinary\Exceptions\UnknownRequestTypeException;
use Visol\Cloudinary\Services\CloudinaryPathService;
use Visol\Cloudinary\Services\CloudinaryResourceService;
use Visol\Cloudinary\Services\CloudinaryScanService;
use Visol\Cloudinary\Utility\CloudinaryFileUtility;

class CloudinaryWebHookController extends ActionController
{

    protected const NOTIFICATION_TYPE_UPLOAD = 'upload';
    protected const NOTIFICATION_TYPE_RENAME = 'rename';
    protected const NOTIFICATION_TYPE_DELETE = 'delete';

    protected CloudinaryResourceService $cloudinaryResourceService;
    protected CloudinaryScanService $scanService;
    protected CloudinaryPathService $cloudinaryPathService;
    protected ProcessedFileRepository $processedFileRepository;
    protected ResourceStorage $storage;

    protected function initializeAction(): void
    {
        $this->checkEnvironment();

        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

        $storage = $resourceFactory->getStorageObject((int)$this->settings['storage']);
        $this->cloudinaryResourceService = GeneralUtility::makeInstance(
            CloudinaryResourceService::class,
            $storage,
        );

        $this->scanService = GeneralUtility::makeInstance(
            CloudinaryScanService::class,
            $storage
        );

        $this->cloudinaryPathService = GeneralUtility::makeInstance(
            CloudinaryPathService::class,
            $storage->getConfiguration()
        );

        $this->storage = $storage;

        $this->processedFileRepository = GeneralUtility::makeInstance(ProcessedFileRepository::class);
    }

    public function processAction(): ResponseInterface
    {
        $parsedBody = (string)file_get_contents('php://input');
        $payload = json_decode($parsedBody, true);
        self::getLogger()->debug($parsedBody);

        if ($this->shouldStopProcessing($payload)) {
            return $this->sendResponse(['result' => 'ok', 'message' => 'Nothing to do...']);
        }

        try {
            [$requestType, $publicIds] = $this->getRequestInfo($payload);

            self::getLogger()->debug(sprintf('Start cache flushing for file "%s". ', $requestType));

            foreach ($publicIds as $publicId) {
                $cloudinaryResource = $this->getCloudinaryResource($publicId);

                // #. retrieve the source file
                $file = $this->getFile($cloudinaryResource);

                // #. flush the process files
                $this->clearProcessedFiles($file);

                // #. clean up local temporary file - var/variant folder
                $this->cleanUpTemporaryFile($file);

                // #. flush cache pages
                $this->clearCachePages($file);
            }
        } catch (\Exception $e) {
            return $this->sendResponse([
                'result' => 'ko',
                'message' => $e->getMessage(),
            ]);
        }

        return $this->sendResponse(['result' => 'ok', 'message' => 'Cache flushed']);
    }

    protected function getFile(array $cloudinaryResource): File
    {
        $fileIdentifier = $this->cloudinaryPathService->computeFileIdentifier($cloudinaryResource);
        return $this->storage->getFileByIdentifier($fileIdentifier);
    }

    protected function getRequestInfo(array $payload): array
    {
        if ($this->isRequestUploadOverwrite($payload)) {
            $requestType = self::NOTIFICATION_TYPE_UPLOAD;
            $publicIds = [$payload['public_id']];
        } elseif ($this->isRequestRename($payload)) {
            $requestType = self::NOTIFICATION_TYPE_RENAME;
            $publicIds = [$payload['from_public_id']];
            //$nextPublicId = $payload['to_public_id'];
        } elseif ($this->isRequestDelete($payload)) {
            $requestType = self::NOTIFICATION_TYPE_DELETE;
            $publicIds = [];
            foreach ($payload['resources'] as $resource) {
                $publicIds[] = $resource['public_id'];
            }
        } else {
            throw new UnknownRequestTypeException('Unknown request type', 1677860080);
        }

        if (empty($publicIds)) {
            throw new PublicIdMissingException('Missing public id', 1677860090);
        }

        return [$requestType, $publicIds,];
    }

    protected function getCloudinaryResource(string $publicId): array
    {
        $cloudinaryResource = $this->cloudinaryResourceService->getResource($publicId);

        // The resource does not exist, time to fetch
        if (!$cloudinaryResource) {
            $result = $this->scanService->scanOne($publicId);
            if (!$result) {
                $message = sprintf('I could not find a corresponding resource for public id %s', $publicId);
                throw new CloudinaryNotFoundException($message, 1677859470);
            }
            $cloudinaryResource = $this->cloudinaryResourceService->getResource($publicId);
        }

        return $cloudinaryResource;
    }

    protected function clearProcessedFiles(File $file): void
    {
        $processedFiles = $this->processedFileRepository->findAllByOriginalFile($file);

        foreach ($processedFiles as $processedFile) {
            $processedFile->getStorage()->setEvaluatePermissions(false);
            $processedFile->delete();
        }
    }

    protected function cleanUpTemporaryFile(File $file): void
    {
        $temporaryFileNameAndPath = CloudinaryFileUtility::getTemporaryFile($file->getStorage()->getUid(), $file->getIdentifier());
        if (is_file($temporaryFileNameAndPath)) {
            unlink($temporaryFileNameAndPath);
        }
    }

    protected function clearCachePages(File $file): void
    {
        $tags = [];
        foreach ($this->findPagesWithFileReferences($file) as $page) {
            $tags[] = 'pageId_' . $page['pid'];
        }

        GeneralUtility::makeInstance(CacheManager::class)
            ->flushCachesInGroupByTags('pages', $tags);
    }

    protected function findPagesWithFileReferences(File $file): array
    {
        $queryBuilder = $this->getQueryBuilder('sys_file_reference');
        return $queryBuilder
            ->select('pid')
            ->from('sys_file_reference')
            ->groupBy('pid') // no support for distinct
            ->andWhere(
                'pid > 0',
                'uid_local = ' . $file->getUid()
            )
            ->execute()
            ->fetchAllAssociative();
    }

    protected function shouldStopProcessing(mixed $payload): bool
    {
        return !($this->isRequestUploadOverwrite($payload) || $this->isRequestRename($payload));
    }

    protected function isRequestUploadOverwrite(mixed $payload): bool
    {
        return is_array($payload) &&
            array_key_exists('notification_type', $payload) &&
            array_key_exists('overwritten', $payload) &&
            $payload['notification_type'] === self::NOTIFICATION_TYPE_UPLOAD
            && $payload['overwritten'];
    }

    protected function isRequestRename(mixed $payload): bool
    {
        return is_array($payload) &&
            array_key_exists('notification_type', $payload) &&
            $payload['notification_type'] === self::NOTIFICATION_TYPE_RENAME;
    }

    protected function isRequestDelete(mixed $payload): bool
    {
        return is_array($payload) &&
            array_key_exists('notification_type', $payload) &&
            $payload['notification_type'] === self::NOTIFICATION_TYPE_DELETE;
    }

    protected function sendResponse(array $data): ResponseInterface
    {
        return $this->jsonResponse(
            json_encode($data)
        );
    }

    protected function checkEnvironment(): void
    {
        $storageUid = $this->settings['storage'] ?? 0;
        if ($storageUid <= 0) {
            throw new \RuntimeException('Check your configuration while calling the cloudinary web hook. I am missing a storage id', 1677583654);
        }
    }

    protected function getQueryBuilder($tableName): QueryBuilder
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        return $connectionPool->getQueryBuilderForTable($tableName);
    }

    protected static function getLogger(): Logger
    {
        /** @var Logger $logger */
        static $logger = null;
        if ($logger === null) {
            $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        }
        return $logger;
    }

}
