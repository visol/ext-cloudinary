<?php

namespace Visol\Cloudinary\Tests\Acceptance;

use TYPO3\CMS\Core\Resource\File;

class GetFileTests extends AbstractCloudinaryAcceptanceTest
{

    /**
     * @return void
     * @throws \Exception
     */
    public function runTests()
    {
        $fileIdentifier = $this->getFileIdentifier('sample.youtube');
        $file = $this->storage->getFile($fileIdentifier);

        $this->assertTrue($file instanceof File, 'storage::getFile() returns a file Object');
        $this->assert($fileIdentifier, $file->getIdentifier(), 'Given file identifiers corresponds to object file identifier');

        // Get contents
        $expectedContent = file_get_contents($this->getFilePath('sample.youtube'));
        $this->assert(
            $expectedContent,
            $file->getContents(),
            'Given contents corresponds to object file contents'
        );
    }
}
