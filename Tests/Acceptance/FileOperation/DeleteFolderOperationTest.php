<?php

namespace Visol\Cloudinary\Tests\Acceptance\FileOperation;

class DeleteFolderOperationTest extends AbstractCloudinaryFileOperationTest
{

    /**
     * @return void
     * @throws \Exception
     */
    public function run()
    {
        $this->getStorage()->deleteFolder($this->getBaseFolder(), true);
        $files = $this->getStorage()->getFilesInFolder($this->getBaseFolder());
        $this->assert(
            0,
            count($files),
            'Base folder is empty'
        );
    }
}
