<?php

namespace Visol\Cloudinary\Tests\Acceptance;


class GetFilesInFolderTests extends AbstractCloudinaryAcceptanceTest
{

    public function runTests()
    {
        $files = $this->storage->getFilesInFolder($this->getBaseFolder());
        $this->assert(1, count($files), 'storage:getFilesInFolder() corresponds to 1 file');
    }
}
