<?php

namespace Visol\Cloudinary\Controller;

use Cloudinary;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Services\CloudinaryPathService;
use Visol\Cloudinary\Services\CloudinaryResourceService;
use Visol\Cloudinary\Services\ConfigurationService;

class CloudinaryAjaxController
{
    public function addFilesAction(ServerRequestInterface $request): ResponseInterface
    {
        $storageUid = (int) $request->getParsedBody()['storageUid'];
        $cloudinaryIds = (array) $request->getParsedBody()['cloudinaryIds'];

        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

        $files = [];
        $result = 'ok';
        $possibleError = '';
        try {
            // Initialize objects
            $storage = $resourceFactory->getStorageObject($storageUid);
            $this->initializeApi($storage);
            $cloudinaryPathService = GeneralUtility::makeInstance(
                CloudinaryPathService::class,
                $storage->getConfiguration(),
            );
            $cloudinaryResourceService = GeneralUtility::makeInstance(CloudinaryResourceService::class, $storage);

            foreach ($cloudinaryIds as $publicId) {
                // We must retrieve the resources so that we can determinte the format
                $api = new Cloudinary\Api();
                $resource = $api->resource($publicId);

                $identifier = $cloudinaryPathService->computeFileIdentifier((array)$resource);
                if ($storage->hasFile($identifier)) {
                    $files[] = $storage->getFile($identifier)->getUid();
                } else {

                    //$cloudinaryPathService->computeFileIdentifier((array) $resource);
                    // Save mirrored file
                    $cloudinaryResourceService->save((array) $resource);

                    // This will trigger a file indexation
                    $files[] = $storage->getFile($identifier)->getUid();
                }
            }
        } catch (\RuntimeException $e) {
            $result = 'ko';
            $possibleError = $e->getMessage();
        }

        $response = new JsonResponse();
        $response->getBody()->write(json_encode(['result' => $result, 'files' => $files, 'error' => $possibleError]));
        return $response;
    }

    protected function initializeApi(ResourceStorage $storage): void
    {
        $configurationService = GeneralUtility::makeInstance(ConfigurationService::class, $storage->getConfiguration());

        Cloudinary::config([
            'cloud_name' => $configurationService->get('cloudName'),
            'api_key' => $configurationService->get('apiKey'),
            'api_secret' => $configurationService->get('apiSecret'),
            'timeout' => $configurationService->get('timeout'),
            'secure' => true,
        ]);
    }
}
