<?php
declare(strict_types=1);

namespace Visol\Cloudinary\Form\Element;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

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

        $result = $this->initializeResultArray();
        $view = $this->initializeStandaloneView('EXT:cloudinary/Resources/Private/Standalone/MediaLibrary/Show.html');
        $view->assignMultiple([
            'parameters' => $this->data['parameterArray']['fieldConf']['config']['parameters'],
            'name' => $this->data['parameterArray']['itemFormElName'],
            'value' => $this->data['parameterArray']['itemFormElValue'],
            'onchange' => htmlspecialchars(implode('', $this->data['parameterArray']['fieldChangeFunc'])),
        ]);

        $result['html'] = $view->render();
        return $result;
    }

    /**
     * @param string $templateNameAndPath
     * @return StandaloneView
     */
    protected function initializeStandaloneView($templateNameAndPath): StandaloneView
    {
        $templateNameAndPath = GeneralUtility::getFileAbsFileName($templateNameAndPath);

        /** @var StandaloneView $view */
        $view = GeneralUtility::makeInstance(StandaloneView::class);

        $view->setTemplatePathAndFilename($templateNameAndPath);
        return $view;
    }
}
