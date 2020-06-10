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
        $fixtureVideoFile = $this->getFilePath($this->fileName);
        $file = $this->getStorage()->addFile(
            $fixtureVideoFile,
            $this->getBaseFolder()
        );

        $this->assertTrue(
            $file instanceof File,
            'Adding a new file returns a file object'
        );
    }

}
