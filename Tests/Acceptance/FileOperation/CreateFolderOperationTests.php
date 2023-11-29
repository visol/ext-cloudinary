<?php

namespace Visol\Cloudinary\Tests\Acceptance\FileOperation;

use Exception;

class CreateFolderOperationTests extends AbstractCloudinaryFileOperationTest
{

    /**
     * @return void
     * @throws Exception
     */
    public function run()
    {
        $folder = $this->getStorage()->createFolder(
            $this->resourceName
        );

        $this->assert(
            $this->resourceName,
            $folder->getIdentifier(),
            'Folder identifier corresponds to ' . $this->resourceName
        );

        $this->assertTrue(
            $this->getStorage()->hasFolder($this->resourceName),
            'Created folder exists ' . $this->resourceName
        );
    }

}
