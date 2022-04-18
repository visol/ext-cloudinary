<?php
declare(strict_types=1);

namespace Visol\Cloudinary\Form\Element;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class CloudinaryMediaLibraryPicker extends AbstractFormElement
{
    public function render()
    {
        // Custom TCA properties and other data can be found in $this->data, for example the above
        // parameters are available in $this->data['parameterArray']['fieldConf']['config']['parameters']
        $result = $this->initializeResultArray();
        $view = $this->initializeStandaloneView('EXT:cloudinary/Resources/Private/Standalone/MediaLibrary/Show.html');
        $result['html'] = 'my map content';
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
