<?php

namespace Visol\Cloudinary\Tests\Acceptance\FileOperation;

class MoveFileOperationTest extends AbstractCloudinaryFileOperationTest
{

    /**
     * @var string
     */
    protected $targetFileName = '';

    /**
     * @return void
     * @throws \Exception
     */
    public function run()
    {
        $targetFileName = basename($this->targetFileName);

        $fileIdentifier = $this->getFileIdentifier($this->resourceName);
        $fileFixture = $this->getStorage()->getFile($fileIdentifier);

        $movedFile = $this->getStorage()->moveFile(
            $fileFixture,
            $fileFixture->getParentFolder(),
            $targetFileName
        );

        $expectedFileName = $this->sanitizeFileName($targetFileName);
        $this->assert(
            $expectedFileName,
            $movedFile->getName(),
            'Move file name corresponds to ' . $expectedFileName
        );

        $this->assertTrue(
            $this->getStorage()->hasFile($movedFile->getIdentifier()),
            'storage::hasFile() returns true if file was moved'
        );

        $this->assertTrue(
            $fileFixture->getUid() === $movedFile->getUid(),
            sprintf(
                'File uid %s !== %s is the same for the moved file',
                $fileFixture->getUid(),
                $movedFile->getUid()
            )
        );
    }

    /**
     * @param string $targetFileName
     *
     * @return self
     */
    public function setTargetFileName(string $targetFileName): self
    {
        $this->targetFileName = $targetFileName;
        return $this;
    }
}
