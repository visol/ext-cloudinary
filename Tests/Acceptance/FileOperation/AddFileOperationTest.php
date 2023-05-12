<?php

namespace Visol\Cloudinary\Tests\Acceptance\FileOperation;

use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;

class AddFileOperationTest extends AbstractCloudinaryFileOperationTest
{

    /**
     * @return void
     * @throws \Exception
     */
    public function run()
    {
        $fixtureFile = $this->getFilePath($this->resourceName);

        $file = $this->getStorage()->addFile(
            $fixtureFile,
            $this->getContainingFolder($this->resourceName),
            basename($this->resourceName),
            DuplicationBehavior::REPLACE
        );

        $this->assertTrue(
            $file instanceof File,
            'Adding a new file returns a file object'
        );
    }

}
