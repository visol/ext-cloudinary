<?php

namespace Visol\Cloudinary\Tests\Acceptance\FileOperation;

class GetFilesInFolderOperationTests extends AbstractCloudinaryFileOperationTest
{

    /**
     * @var int
     */
    protected $numberOfFiles = 1;

    public function run()
    {
        $files = $this->getStorage()->getFilesInFolder($this->getBaseFolder());
        $this->assert(
            $this->numberOfFiles,
            count($files),
            'storage:getFilesInFolder() corresponds to 1 file'
        );
    }
}
