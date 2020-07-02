<?php

namespace Visol\Cloudinary\Tests\Acceptance\FileOperation;

class CopyFolderOperationTests extends AbstractCloudinaryFileOperationTest
{

    /**
     * @var string
     */
    protected $targetFolderName = '';

    /**
     * @return void
     * @throws \Exception
     */
    public function run()
    {
        $folder = $this->getFolder($this->resourceName);
        $folder = $this->getStorage()->copyFolder(
            $folder,
            $folder->getParentFolder(),
            $this->targetFolderName
        );

        $expectedFolderIdentifier = DIRECTORY_SEPARATOR . $this->targetFolderName . DIRECTORY_SEPARATOR;

        $this->assert(
            $expectedFolderIdentifier,
            $folder->getIdentifier(),
            'Copied folder identifier corresponds to ' . $expectedFolderIdentifier
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
