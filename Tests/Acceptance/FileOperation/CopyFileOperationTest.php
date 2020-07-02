<?php

namespace Visol\Cloudinary\Tests\Acceptance\FileOperation;

class CopyFileOperationTest extends AbstractCloudinaryFileOperationTest
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
        $copiedFile = $this->getStorage()->copyFile(
            $fileFixture,
            $fileFixture->getParentFolder(),
            $targetFileName
        );

        $expectedFileName = $this->sanitizeFileName($targetFileName);
        $this->assert(
            $expectedFileName,
            $copiedFile->getName(),
            'Copied file name corresponds to ' . $expectedFileName
        );

        $this->assertTrue(
            $this->getStorage()->hasFile($copiedFile->getIdentifier()),
            'storage::hasFile() returns true if file was copied'
        );

        $this->assertFalse(
            $fileFixture->getUid() === $copiedFile->getUid(),
            sprintf(
                'File uid %s !== %s is different from copied file',
                $fileFixture->getUid(),
                $copiedFile->getUid()
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
