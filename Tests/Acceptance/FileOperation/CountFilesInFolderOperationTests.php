<?php

namespace Visol\Cloudinary\Tests\Acceptance\FileOperation;

use Exception;

class CountFilesInFolderOperationTests extends AbstractCloudinaryFileOperationTest
{

    /**
     * @var int
     */
    protected $expectedNumberOfFiles = 0;

    /**
     * @var int
     */
    protected $expectedNumberOfFolders = 0;

    /**
     * @return void
     * @throws Exception
     */
    public function run()
    {
        $numberOfFiles = $this->getStorage()->countFilesInFolder(
            $this->getFolder($this->resourceName)
        );

        $this->assert(
            $this->expectedNumberOfFiles,
            $numberOfFiles,
            'storage:countFilesInFolder() corresponds to ' . $this->expectedNumberOfFiles . ' file(s)'
        );

        $numberOfFolders = $this->getStorage()->countFoldersInFolder(
            $this->getFolder($this->resourceName)
        );

        $this->assert(
            $this->expectedNumberOfFolders,
            $numberOfFolders,
            'storage:countFoldersInFolder() corresponds to ' . $this->expectedNumberOfFolders . ' file(s)'
        );
    }

    /**
     * @param int $expectedNumberOfFiles
     *
     * @return $this
     */
    public function setExpectedNumberOfFiles(int $expectedNumberOfFiles): self
    {
        $this->expectedNumberOfFiles = $expectedNumberOfFiles;
        return $this;
    }

    /**
     * @param int $expectedNumberOfFolders
     *
     * @return self
     */
    public function setExpectedNumberOfFolders(int $expectedNumberOfFolders): self
    {
        $this->expectedNumberOfFolders = $expectedNumberOfFolders;
        return $this;
    }
}
