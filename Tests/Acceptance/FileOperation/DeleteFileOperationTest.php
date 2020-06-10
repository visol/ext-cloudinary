<?php

namespace Visol\Cloudinary\Tests\Acceptance\FileOperation;

class DeleteFileOperationTest extends AbstractCloudinaryFileOperationTest
{

    /**
     * @return void
     * @throws \Exception
     */
    public function run()
    {
        $fileIdentifier = $this->getFileIdentifier($this->fileName);
        $file = $this->getStorage()->getFile($fileIdentifier);
        $this->assertTrue(
            $this->getStorage()->deleteFile($file),
            'storage::deleteFile() returns true'
        );

        $this->assertFalse(
            $this->getStorage()->hasFile($fileIdentifier),
            'storage::hasFile() returns false if file was deleted'
        );
    }
}
