<?php

namespace Visol\Cloudinary\Tests\Acceptance\FileOperation;

use Exception;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;

class GetFileOperationTest extends AbstractCloudinaryFileOperationTest
{

    /**
     * @var string
     */
    private $fixtureFileIdentifier;

    /**
     * @var FileInterface
     */
    private $fixtureFile;

    /**
     * @return void
     * @throws Exception
     */
    public function run()
    {
        $this->fixtureFileIdentifier = $this->getFileIdentifier($this->fileName);
        $this->fixtureFile = $this->getStorage()->getFile($this->fixtureFileIdentifier);

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
            $this->fixtureFile instanceof File,
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
            $this->fixtureFileIdentifier,
            $this->fixtureFile->getIdentifier(),
            sprintf(
                'Given file identifiers corresponds to "%s"',
                $this->fixtureFileIdentifier
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
            $this->getStorage()->hasFile($this->fixtureFileIdentifier),
            sprintf(
                'File has existence "%s"',
                $this->fixtureFileIdentifier
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
            $this->fixtureFiles[$this->fileName]['mimeType'],
            $this->fixtureFile->getMimeType(),
            sprintf(
                'Mime type corresponds to "%s"',
                $this->fixtureFiles[$this->fileName]['mimeType']
            )
        );
    }

    /**
     * @throws Exception
     */
    protected function assertFileName()
    {

        $expectedFileName = $this->sanitizeFileName(
            $this->fixtureFiles[$this->fileName]['fileName']
        );

        // Check name
        $this->assert(
            $expectedFileName,
            $this->fixtureFile->getName(),
            sprintf(
                'File name corresponds to "%s"',
                $expectedFileName
            )
        );

        // Check file name without extension
        $this->assert(
            $this->fixtureFiles[$this->fileName]['fileNameWithoutExtension'],
            $this->fixtureFile->getNameWithoutExtension(),
            sprintf(
                'File names without extension correspond to "%s"',
                $this->fixtureFiles[$this->fileName]['fileNameWithoutExtension']
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
            $this->fixtureFiles[$this->fileName]['type'],
            $this->fixtureFile->getType(),
            sprintf(
                'File type correspond to "%s"',
                $this->fixtureFiles[$this->fileName]['type']
            )
        );
    }

    /**
     * @throws Exception
     */
    protected function assertFileContent()
    {
        // Only applicable for online video
        if ($this->fixtureFile->getMimeType() === 'video/youtube') {

            // Get contents
            $expectedContent = file_get_contents($this->getFilePath($this->fileName));
            $this->assert(
                $expectedContent,
                $this->fixtureFile->getContents(),
                sprintf(
                    'Given contents corresponds to "%s"',
                    $expectedContent
                )
            );
        } else {

            $this->assertTrue(
                (bool)preg_match('/^https:\/\/res.cloudinary.com\/' . $this->getCloudinaryName() . '/', $this->fixtureFile->getPublicUrl()),
                'Public URL contains cloudinary cloud name'
            );

            $this->assertTrue(
                (bool)preg_match('/' . str_replace('/', '\/', $this->fixtureFileIdentifier) . '$/', $this->fixtureFile->getPublicUrl()),
                'Public URL ends like file identifier ' . $this->fixtureFileIdentifier
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
