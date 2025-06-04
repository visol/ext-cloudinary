<?php

namespace Visol\Cloudinary\Backend\Form;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use Visol\Cloudinary\Driver\CloudinaryDriver;
use Visol\Cloudinary\Services\ConfigurationService;

class CloudinaryFileSelector
{
    protected string $moduleName = 'TYPO3/CMS/Cloudinary/CloudinaryMediaLibrary';

    /**
     * @return array{'buttons': string[], 'javaScriptModules': JavaScriptModuleInstruction[]} HTML snippets
     */
    public function renderCloudinaryFileSelectors(
        string $irreObject
    ): array {
        $storages = $this->getCloudinaryStorages();

        $view = $this->initializeStandaloneView('EXT:cloudinary/Resources/Private/Standalone/MediaLibrary/Show.html');
        $buttons = array_map(fn(ResourceStorage $storage): string => $view->assignMultiple([
            'objectGroup' => $irreObject,
            'cloudinaryCredentials' => $this->computeCloudinaryCredentials($storage),
        ])->render(), $storages);

        return [
            'buttons' => $buttons,
            'javaScriptModules' => [JavaScriptModuleInstruction::forRequireJS('TYPO3/CMS/Cloudinary/CloudinaryMediaLibrary')],
        ];
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
        $configuration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('cloudinary');

        // fetch the storage from the configuration
        $storages = array_filter(GeneralUtility::intExplode(',', $configuration['tceform_cludinary_storage'], true));
        if (empty($storages)) {
            // empty... we fetch all storages

            /** @var QueryBuilder $query */
            $query = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_storage');

            $storageItems = $query
                ->select('*')
                ->from('sys_file_storage')
                ->where($query->expr()->eq('driver', $query->expr()->literal(CloudinaryDriver::DRIVER_TYPE)))
                ->executeQuery()
                ->fetchAllAssociativeIndexed();

            $storages = array_keys($storageItems);
        }

        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

        $storageObjects = [];
        foreach ($storages as $storage) {
            $storageObjects[] = $resourceFactory->getStorageObject($storage);
        }
        return $storageObjects;
    }

    protected function computeCloudinaryCredentials(ResourceStorage $storage): array
    {
        $configurationService = GeneralUtility::makeInstance(
            ConfigurationService::class,
            $storage->getConfiguration(),
        );

        return [
            'storageName' => $storage->getName(),
            'storageUid' => $storage->getUid(),
            'cloudName' => $configurationService->get('cloudName'),
            'apiKey' => $configurationService->get('apiKey'),
            'username' => $configurationService->get('authenticationEmail'),
            'timestamp' => $_SERVER['REQUEST_TIME'],
            'signature' => hash(
                'sha256',
                sprintf(
                    'cloud_name=%s&timestamp=%s&username=%s%s',
                    $configurationService->get('cloudName'),
                    $_SERVER['REQUEST_TIME'],
                    $configurationService->get('authenticationEmail'),
                    $configurationService->get('apiSecret'),
                ),
            ),
        ];
    }
}
