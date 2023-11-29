<?php

namespace Visol\Cloudinary\Controller;

use Cloudinary\Api\Search\SearchApi;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Services\CloudinaryPathService;
use Visol\Cloudinary\Services\CloudinaryResourceService;
use Visol\Cloudinary\Utility\CloudinaryApiUtility;

class CloudinaryAjaxController
{
    public function addFilesAction(ServerRequestInterface $request): ResponseInterface
    {
        $storageUid = (int)$request->getParsedBody()['storageUid'];
        $cloudinaryIds = (array)$request->getParsedBody()['cloudinaryIds'];

        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

        $files = [];
        $result = 'ok';
        $possibleError = '';
        try {
            // Initialize objects
            $storage = $resourceFactory->getStorageObject($storageUid);
            $cloudinaryPathService = GeneralUtility::makeInstance(
                CloudinaryPathService::class,
                $storage->getConfiguration(),
            );
            $cloudinaryResourceService = GeneralUtility::makeInstance(CloudinaryResourceService::class, $storage);

            foreach ($cloudinaryIds as $publicId) {

                // We must retrieve the resources so that we can determine the format
                $response = $this->getSearchApi($storage)
                    ->expression('public_id:' . $publicId)
                    ->maxResults(1)
                    ->execute();

                if (empty($response['resources'])) {
                    throw new RuntimeException('Missing resources ' . $publicId, 1657125439);
                } else {
                    $resource = $response['resources'][0];
                }

                $identifier = $cloudinaryPathService->computeFileIdentifier((array)$resource);
                if ($storage->hasFile($identifier)) {
                    $files[] = $storage->getFile($identifier)->getUid();
                } else {

                    //$cloudinaryPathService->computeFileIdentifier((array) $resource);
                    // Save mirrored file
                    $cloudinaryResourceService->save((array)$resource);

                    // This will trigger a file indexation
                    $files[] = $storage->getFile($identifier)->getUid();
                }
            }
        } catch (RuntimeException $e) {
            $result = 'ko';
            $possibleError = $e->getMessage();
        }

        $response = new JsonResponse();
        $response->getBody()->write(json_encode(['result' => $result, 'files' => $files, 'error' => $possibleError]));
        return $response;
    }

    protected function getSearchApi(ResourceStorage $storage): SearchApi
    {
        return CloudinaryApiUtility::getCloudinary($storage)->searchApi();
    }

}
