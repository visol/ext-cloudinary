<?php

namespace Visol\Cloudinary\Backend\Form\Container;

use TYPO3\CMS\Backend\Form\Container\InlineControlContainer;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use Visol\Cloudinary\Driver\CloudinaryFastDriver;
use Visol\Cloudinary\Services\ConfigurationService;

class InlineCloudinaryControlContainer extends InlineControlContainer
{
    /**
     * @param array $inlineConfiguration
     * @return string
     */
    protected function renderPossibleRecordsSelectorTypeGroupDB(array $inlineConfiguration)
    {
        $selector = parent::renderPossibleRecordsSelectorTypeGroupDB($inlineConfiguration);

        $button = $this->renderCloudinaryButtons($inlineConfiguration);

        // Inject button before help-block
        if (strpos($selector, '</div><div class="help-block">') > 0) {
            $selector = str_replace(
                '</div><div class="help-block">',
                $button . '</div><div class="help-block">',
                $selector,
            );
            // Try to inject it into the form-control container
        } elseif (preg_match('/<\/div><\/div>$/i', $selector)) {
            $selector = preg_replace('/<\/div><\/div>$/i', $button . '</div></div>', $selector);
        } else {
            $selector .= $button;
        }

        return $selector;
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
            'cloudinaryCredentials' => json_encode($this->computeCloudinaryCredentials($storages)),
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
                'storageName' => $storage->getName(),
                'storageUid' => $storage->getUid(),
                'cloudName' => $configurationService->get('cloudName'),
                'apiKey' => $configurationService->get('apiKey'),
                'username' => 'thomas.imboden@jungfrau.ch',
                'timestamp' => $_SERVER['REQUEST_TIME'],
                'signature' => hash(
                    'sha256',
                    sprintf(
                        'cloud_name=%s&timestamp=%s&username=%s%s',
                        $configurationService->get('cloudName'),
                        $_SERVER['REQUEST_TIME'],
                        'thomas.imboden@jungfrau.ch',
                        $configurationService->get('apiSecret'),
                    ),
                ),
            ];
        }

        return $cloudinaryCredentials;
    }
}
