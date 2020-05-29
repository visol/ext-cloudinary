<?php

namespace Visol\Cloudinary\Tests\Acceptance;

use TYPO3\CMS\Core\Resource\File;

class DeleteFileTests extends AbstractCloudinaryAcceptanceTest
{

    /**
     * @return void
     * @throws \Exception
     */
    public function runTests()
    {
        $fileIdentifier = $this->getFileIdentifier('sample.youtube');
        $file = $this->storage->getFile($fileIdentifier);

        $this->assertTrue(
            $this->storage->deleteFile($file),
            'storage::deleteFile() returns true'
        );

        $this->assertFalse(
            $this->storage->hasFile($fileIdentifier),
            'storage::hasFile() returns false if file was deleted'
        );
    }
}
