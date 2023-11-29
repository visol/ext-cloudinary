<?php

namespace Visol\Cloudinary\Backend\Form\Container;

use TYPO3\CMS\Backend\Form\Container\InlineControlContainer;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use Visol\Cloudinary\Driver\CloudinaryDriver;
use Visol\Cloudinary\Services\ConfigurationService;

class InlineCloudinaryControlContainer extends InlineControlContainer
{

    public function render()
    {
        /** @var PageRenderer $pageRenderer */
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->loadRequireJsModule('TYPO3/CMS/Cloudinary/CloudinaryMediaLibrary');

        return parent::render();
    }

    protected function renderPossibleRecordsSelectorTypeGroupDB(array $inlineConfiguration): ?string
    {
        $typo3Buttons = parent::renderPossibleRecordsSelectorTypeGroupDB($inlineConfiguration);

        // We could have multiple cloudinary buttons / storages
        $cloudinaryButtons = $this->renderCloudinaryButtons($inlineConfiguration);
        $typo3Buttons = $this->appendButtons($typo3Buttons, $cloudinaryButtons);

        return $typo3Buttons;
    }

    protected function renderCloudinaryButtons(array $inlineConfiguration): string
    {
        $foreign_table = $inlineConfiguration['foreign_table'];
        $currentStructureDomObjectIdPrefix = $this->inlineStackProcessor->getCurrentStructureDomObjectIdPrefix(
            $this->data['inlineFirstPid'],
        );
        $objectGroup = $currentStructureDomObjectIdPrefix . '-' . $foreign_table;

        $storages = $this->getCloudinaryStorages();
        $view = $this->initializeStandaloneView('EXT:cloudinary/Resources/Private/Standalone/MediaLibrary/Show.html');
        $view->assignMultiple([
            'objectGroup' => $objectGroup,
            'cloudinaryCredentials' => $this->computeCloudinaryCredentials($storages),
        ]);

        return $view->render();
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
        $storages = array_filter(GeneralUtility::trimExplode(',', $configuration['tceform_cludinary_storage']));
        if (empty($storages)) {
            // empty... we fetch all storages

            /** @var QueryBuilder $query */
            $query = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_storage');

            $storageItems = $query
                ->select('*')
                ->from('sys_file_storage')
                ->where($query->expr()->eq('driver', $query->expr()->literal(CloudinaryDriver::DRIVER_TYPE)))
                ->execute()
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

        return $cloudinaryCredentials;
    }

    protected function appendButtons(string $typo3Buttons, string $cloudinaryButtons): ?string
    {
        // Inject button before help-block
        if (strpos($typo3Buttons, '</div><div class="help-block">') > 0) {
            $typo3Buttons = str_replace(
                '</div><div class="help-block">',
                $cloudinaryButtons . '</div><div class="help-block">',
                $typo3Buttons,
            );
            // Try to inject it into the form-control container
        } elseif (preg_match('/<\/div><\/div>$/i', $typo3Buttons)) {
            $typo3Buttons = preg_replace('/<\/div><\/div>$/i', $cloudinaryButtons . '</div></div>', $typo3Buttons);
        } else {
            $typo3Buttons .= $cloudinaryButtons;
        }
        return $typo3Buttons;
    }
}
