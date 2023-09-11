<?php

namespace Visol\Cloudinary\Tests\Acceptance\FileOperation;

use Exception;

class RenameFolderOperationTests extends AbstractCloudinaryFileOperationTest
{

    /**
     * @var string
     */
    protected $targetFolderName = '';

    /**
     * @return void
     * @throws Exception
     */
    public function run()
    {
        $folder = $this->getStorage()->renameFolder(
            $this->getFolder(
                $this->resourceName
            ),
            $this->targetFolderName
        );

        $expectedFolderIdentifier = DIRECTORY_SEPARATOR . $this->targetFolderName . DIRECTORY_SEPARATOR;

        $this->assert(
            $expectedFolderIdentifier,
            $folder->getIdentifier(),
            'Renamed folder identifier corresponds to ' . $expectedFolderIdentifier
        );
    }

    /**
     * @param string $targetFolderName
     *
     * @return $this
     */
    public function setTargetFolderName(string $targetFolderName): self
    {
        $this->targetFolderName = $targetFolderName;
        return $this;
    }
}
