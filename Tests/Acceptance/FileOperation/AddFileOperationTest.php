<?php

namespace Visol\Cloudinary\Tests\Acceptance\FileOperation;

use TYPO3\CMS\Core\Resource\File;

class AddFileOperationTest extends AbstractCloudinaryFileOperationTest
{

    /**
     *
     */
    public function run()
    {
        $fixtureFile = $this->getFilePath($this->fileName);
        $file = $this->getStorage()->addFile(
            $fixtureFile,
            $this->getFolder($this->fileName)
        );

        $this->assertTrue(
            $file instanceof File,
            'Adding a new file returns a file object'
        );
    }

}
