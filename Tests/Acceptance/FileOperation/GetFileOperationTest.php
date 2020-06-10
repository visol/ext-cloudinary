<?php

namespace Visol\Cloudinary\Tests\Acceptance\FileOperation;

use TYPO3\CMS\Core\Resource\File;

class GetFileOperationTest extends AbstractCloudinaryFileOperationTest
{

    /**
     * @return void
     * @throws \Exception
     */
    public function run()
    {
        $fileIdentifier = $this->getFileIdentifier($this->fileName);
        $file = $this->getStorage()->getFile($fileIdentifier);

        $this->getStorage()->getPublicUrl($file);

        // Check file instance
        $this->assertTrue(
            $file instanceof File,
            'storage::getFile() returns a file Object'
        );

        // Check file identifier
        $this->assert(
            $fileIdentifier,
            $file->getIdentifier(),
            sprintf(
                'Given file identifiers corresponds to "%s"',
                $fileIdentifier
            )
        );

        // Check file existence
        $this->assertTrue(
            $this->getStorage()->hasFile($fileIdentifier),
            sprintf(
                'File has existence "%s"',
                $fileIdentifier
            )
        );

        // Check mime type
        $this->assert(
            $this->fixtureFiles[$this->fileName]['mimeType'],
            $file->getMimeType(),
            sprintf(
                'Mime type corresponds to "%s"',
                $this->fixtureFiles[$this->fileName]['mimeType']
            )
        );

        // Check name
        $this->assert(
            $this->fixtureFiles[$this->fileName]['fileName'],
            $file->getName(),
            sprintf(
                'File name correspond to "%s"',
                $this->fixtureFiles[$this->fileName]['fileName']
            )
        );

        // Check file name without extension
        $this->assert(
            $this->fixtureFiles[$this->fileName]['fileNameWithoutExtension'],
            $file->getNameWithoutExtension(),
            sprintf(
                'File names without extension correspond to "%s"',
                $this->fixtureFiles[$this->fileName]['fileNameWithoutExtension']
            )
        );

        // File type
        $this->assert(
            $this->fixtureFiles[$this->fileName]['type'],
            $file->getType(),
            sprintf(
                'File type correspond to "%s"',
                $this->fixtureFiles[$this->fileName]['type']
            )
        );

        // Only applicable for online video
        if ($file->getMimeType() === 'video/youtube') {

            // Get contents
            $expectedContent = file_get_contents($this->getFilePath($this->fileName));
            $this->assert(
                $expectedContent,
                $file->getContents(),
                sprintf(
                    'Given contents corresponds to "%s"',
                    $expectedContent
                )
            );
        } else {

            $this->assertTrue(
                (bool)preg_match('/^https:\/\/res.cloudinary.com\/' . $this->getCloudinaryName() . '/', $file->getPublicUrl()),
                'Public URL contains cloudinary cloud name'
            );

            $this->assertTrue(
                (bool)preg_match('/' . str_replace('/', '\/', $fileIdentifier) . '$/', $file->getPublicUrl()),
                'Public URL ends like file identifier ' . $fileIdentifier
            );
        }
    }

    /**
     * @return string
     */
    public function getCloudinaryName(): string
    {
        $configuration = $this->getStorage()->getConfiguration();
        return $configuration['cloudName'];
    }

    /**
     * @param string $fileIdentifier
     * @param string $fileVersion
     *
     * @return string
     */
    public function getExpectedPublicUrl(string $fileIdentifier, string $fileVersion): string
    {

        $configuration = $this->getStorage()->getConfiguration();
        return sprintf(
            'https://res.cloudinary.com/%s/raw/upload/%s%s',
            $configuration['cloudName'],
            $fileVersion,
            $fileIdentifier
        );
    }
}
