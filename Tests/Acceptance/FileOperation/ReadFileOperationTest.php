<?php

namespace Visol\Cloudinary\Tests\Acceptance\FileOperation;

use Exception;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;

class ReadFileOperationTest extends AbstractCloudinaryFileOperationTest
{

    /**
     * @var string
     */
    private $fileIdentifierFixture;

    /**
     * @var FileInterface
     */
    private $fileFixture;

    /**
     * @return void
     * @throws Exception
     */
    public function run()
    {
        $this->fileIdentifierFixture = $this->getFileIdentifier($this->resourceName);
        $this->fileFixture = $this->getStorage()->getFile($this->fileIdentifierFixture);

        $this->assertFileInstance();
        $this->assertFileIdentifier();
        $this->assertFileExistence();
        $this->assertFileMimeType();
        $this->assertFileName();
        $this->assertFileType();
        $this->assertFileContent();
    }

    /**
     * @throws Exception
     */
    protected function assertFileInstance()
    {

        // Check file instance
        $this->assertTrue(
            $this->fileFixture instanceof File,
            'storage::getFile() returns a file Object'
        );
    }

    /**
     * @throws Exception
     */
    protected function assertFileIdentifier()
    {
        // Check file identifier
        $this->assert(
            $this->fileIdentifierFixture,
            $this->fileFixture->getIdentifier(),
            sprintf(
                'Given file identifiers corresponds to "%s"',
                $this->fileIdentifierFixture
            )
        );
    }

    /**
     * @throws Exception
     */
    protected function assertFileExistence()
    {
        // Check file existence
        $this->assertTrue(
            $this->getStorage()->hasFile($this->fileIdentifierFixture),
            sprintf(
                'File has existence "%s"',
                $this->fileIdentifierFixture
            )
        );
    }

    /**
     * @throws Exception
     */
    protected function assertFileMimeType()
    {
        // Check mime type
        $this->assert(
            $this->fixtureFiles[$this->resourceName]['mimeType'],
            $this->fileFixture->getMimeType(),
            sprintf(
                'Mime type corresponds to "%s"',
                $this->fixtureFiles[$this->resourceName]['mimeType']
            )
        );
    }

    /**
     * @throws Exception
     */
    protected function assertFileName()
    {
        $expectedFileName = $this->sanitizeFileName(
            $this->fixtureFiles[$this->resourceName]['fileName']
        );

        // Check name
        $this->assert(
            $expectedFileName,
            $this->fileFixture->getName(),
            sprintf(
                'File name corresponds to "%s"',
                $expectedFileName
            )
        );

        // Check file name without extension
        $this->assert(
            $this->fixtureFiles[$this->resourceName]['fileNameWithoutExtension'],
            $this->fileFixture->getNameWithoutExtension(),
            sprintf(
                'File names without extension correspond to "%s"',
                $this->fixtureFiles[$this->resourceName]['fileNameWithoutExtension']
            )
        );

    }

    /**
     * @throws Exception
     */
    protected function assertFileType()
    {
        // File type
        $this->assert(
            $this->fixtureFiles[$this->resourceName]['type'],
            $this->fileFixture->getType(),
            sprintf(
                'File type correspond to "%s"',
                $this->fixtureFiles[$this->resourceName]['type']
            )
        );
    }

    /**
     * @throws Exception
     */
    protected function assertFileContent()
    {
        // Only applicable for online video
        if ($this->fileFixture->getMimeType() === 'video/youtube') {

            // Get contents
            $expectedContent = file_get_contents($this->getFilePath($this->resourceName));
            $this->assert(
                $expectedContent,
                $this->fileFixture->getContents(),
                sprintf(
                    'Given contents corresponds to "%s"',
                    $expectedContent
                )
            );
        } else {

            $this->assertTrue(
                (bool)preg_match('/^https:\/\/res.cloudinary.com\/' . $this->getCloudinaryName() . '/', $this->fileFixture->getPublicUrl()),
                'Public URL contains cloudinary cloud name'
            );

            $this->assertTrue(
                (bool)preg_match('/' . str_replace('/', '\/', $this->fileIdentifierFixture) . '$/', $this->fileFixture->getPublicUrl()),
                'Public URL ends like file identifier ' . $this->fileIdentifierFixture
            );
        }
    }

    /**
     * @return string
     */
    protected function getCloudinaryName(): string
    {
        $configuration = $this->getStorage()->getConfiguration();
        return $configuration['cloudName'];
    }
}
