<?php

namespace Visol\Cloudinary\Controller;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Resource\StorageRepository;
use Visol\Cloudinary\Driver\CloudinaryDriver;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Visol\Cloudinary\Services\CloudinaryScanService;

/**
 * Class CloudinaryScanController
 */
class CloudinaryScanController extends ActionController
{

    /**
     * @var StorageRepository
     */
    protected $storageRepository;

    /**
     * @return string
     */
    public function scanAction(): ResponseInterface
    {
        foreach ($this->storageRepository->findAll() as $storage) {
            if ($storage->getDriverType() === CloudinaryDriver::DRIVER_TYPE) {

                /** @var CloudinaryScanService $cloudinaryScanService */
                $cloudinaryScanService = GeneralUtility::makeInstance(
                    CloudinaryScanService::class,
                    $storage
                );
                $cloudinaryScanService->scan();
            }
        }
        return $this->htmlResponse('done');
    }

    public function injectStorageRepository(StorageRepository $storageRepository): void
    {
        $this->storageRepository = $storageRepository;
    }

}
