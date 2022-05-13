<?php
declare(strict_types=1);

namespace Visol\Cloudinary\Form\Element;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use Visol\Cloudinary\Driver\CloudinaryFastDriver;
use Visol\Cloudinary\Services\ConfigurationService;

class CloudinaryMediaLibraryPicker extends AbstractFormElement
{
    public function render(): array
    {
        #$pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        // language labels for JavaScript files
        #$pageRenderer->addInlineLanguageLabelFile(
        #    ExtensionManagementUtility::extPath('cloudinary') . 'Resources/Private/Language/backend.xlf',
        #    'media_file_upload',
        #);

        $storages = $this->getCloudinaryStorages();
        $result = $this->initializeResultArray();
        $view = $this->initializeStandaloneView('EXT:cloudinary/Resources/Private/Standalone/MediaLibrary/Show.html');
        $view->assignMultiple([
            'parameters' => $this->data['parameterArray']['fieldConf']['config']['parameters'],
            'name' => $this->data['parameterArray']['itemFormElName'],
            'value' => $this->data['parameterArray']['itemFormElValue'],
            'onChange' => htmlspecialchars(implode('', $this->data['parameterArray']['fieldChangeFunc'])),
            'cloudinaryCredentials' => json_encode($this->computeCloudinaryCredentials($storages)),
        ]);

        $result['html'] = $view->render();
        return $result;
    }

    protected function initializeStandaloneView(string $templateNameAndPath): StandaloneView
    {
        $templateNameAndPath = GeneralUtility::getFileAbsFileName($templateNameAndPath);

        /** @var StandaloneView $view */
        $view = GeneralUtility::makeInstance(StandaloneView::class);

        $view->setTemplatePathAndFilename($templateNameAndPath);
        return $view;
    }

    /**
     * @return ResourceStorage[]
     */
    protected function getCloudinaryStorages(): array
    {
        /** @var QueryBuilder $query */
        $query = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_storage');

        $storages = $query
            ->select('*')
            ->from('sys_file_storage')
            ->where($query->expr()->eq('driver', $query->expr()->literal(CloudinaryFastDriver::DRIVER_TYPE)))
            ->execute()
            ->fetchAllAssociative();

        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

        $storageObjects = [];
        foreach ($storages as $storage) {
            $storageObjects[] = $resourceFactory->getStorageObject($storage['uid']);
        }
        return $storageObjects;
    }

    /**
     * @param ResourceStorage[] $storages
     */
    protected function computeCloudinaryCredentials(array $storages): array
    {
        $cloudinaryCredentials = [];

        foreach ($storages as $storage) {
            $configurationService = GeneralUtility::makeInstance(
                ConfigurationService::class,
                $storage->getConfiguration(),
            );

            $cloudinaryCredentials[] = [
                'name' => $storage->getName(),
                'cloudName' => $configurationService->get('cloudName'),
                'apiKey' => $configurationService->get('apiKey'),
                'username' => 'fabien.udriot@visol.ch', // webmaster@jungfrau.ch
                'timestamp' => $_SERVER['REQUEST_TIME'],
                'signature' => hash(
                    'sha256',
                    sprintf(
                        'cloud_name=%s&timestamp=%s&username=%s%s',
                        $configurationService->get('cloudName'),
                        $_SERVER['REQUEST_TIME'],
                        'fabien.udriot@visol.ch', // webmaster@jungfrau.ch
                        $configurationService->get('apiSecret'),
                    ),
                ),
            ];
        }

        return $cloudinaryCredentials;
    }
}
