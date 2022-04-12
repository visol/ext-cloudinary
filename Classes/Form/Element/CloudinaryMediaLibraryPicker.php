<?php
declare(strict_types=1);

namespace Visol\Cloudinary\Form\Element;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;

class CloudinaryMediaLibraryPicker extends AbstractFormElement
{
    public function render()
    {
        // Custom TCA properties and other data can be found in $this->data, for example the above
        // parameters are available in $this->data['parameterArray']['fieldConf']['config']['parameters']
        $result = $this->initializeResultArray();
        $result['html'] = 'my map content';
        return $result;
    }
}
