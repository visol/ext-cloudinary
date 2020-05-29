<?php

namespace Visol\Cloudinary\Tests\Acceptance;

use TYPO3\CMS\Core\Resource\File;

class AddFileTests extends AbstractCloudinaryAcceptanceTest
{

    public function runTests()
    {
        $fixtureVideoFile = $this->getFilePath('sample.youtube');
        $file = $this->storage->addFile(
            $fixtureVideoFile,
            $this->getBaseFolder()
        );

        $this->assertTrue($file instanceof File, 'Adding a new file returns a file object');
    }
}
