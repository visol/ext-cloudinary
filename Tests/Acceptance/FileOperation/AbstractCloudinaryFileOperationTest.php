<?php

namespace Visol\Cloudinary\Tests\Acceptance\FileOperation;

use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Visol\Cloudinary\Tests\Acceptance\AbstractCloudinaryTestSuite;

abstract class AbstractCloudinaryFileOperationTest
{

    /**
     * @var string
     */
    protected $fileName = '';

    /**
     * @var array
     */
    protected $fixtureFiles = [
        'image-jpg.jpg' => [
            'fileName' => 'image-jpg.jpg',
            'fileNameWithoutExtension' => 'image-jpg',
            'type' => 2,
            'mimeType' => 'image/jpeg',
        ],
        'image-png.png' => [
            'fileName' => 'image-png.png',
            'fileNameWithoutExtension' => 'image-png',
            'type' => 2,
            'mimeType' => 'image/png',
        ],
        'document.odt' => [
            'fileName' => 'document.odt',
            'fileNameWithoutExtension' => 'document',
            'type' => 5,
            'mimeType' => 'application/vnd.oasis.opendocument.text',
        ],
        'document.pdf' => [
            'fileName' => 'document.pdf',
            'fileNameWithoutExtension' => 'document',
            'type' => 5,
            'mimeType' => 'application/pdf',
        ],
        'video.youtube' => [
            'fileName' => 'video.youtube',
            'fileNameWithoutExtension' => 'video',
            'type' => 4,
            'mimeType' => 'video/youtube',
        ],
        'video.mp4' => [
            'fileName' => 'video.mp4',
            'fileNameWithoutExtension' => 'video',
            'type' => 4,
            'mimeType' => 'video/mp4',
        ],
        'sub-folder/image-jpeg.jpeg' => [
            'fileName' => 'image-jpeg.jpeg',
            'fileNameWithoutExtension' => 'image-jpeg',
            'type' => 2,
            'mimeType' => 'image/jpeg',
        ],
        'sub-folder/image-tiff.tiff' => [
            'fileName' => 'image-tiff.tiff',
            'fileNameWithoutExtension' => 'image-tiff',
            'type' => 2,
            'mimeType' => 'image/tiff',
        ],
    ];

    /**
     * @var AbstractCloudinaryTestSuite
     */
    protected $testSuiteInstance;

    /**
     * @var string
     */
    protected $fixtureDirectory = __DIR__ . '/../../Fixtures';

    /**
     * AbstractCloudinaryFileOperationTest constructor.
     *
     * @param AbstractCloudinaryTestSuite $testSuite
     * @param string $fileName
     */
    public function __construct(AbstractCloudinaryTestSuite $testSuite, string $fileName = '')
    {
        $this->testSuiteInstance = $testSuite;
        $this->fileName = $fileName;
    }

    /**
     * @return void
     */
    abstract public function run();

    /**
     * @return ResourceStorage
     */
    public function getStorage(): ResourceStorage
    {
        return $this->testSuiteInstance->getStorage();
    }

    /**
     * @return SymfonyStyle
     */
    public function getIo(): SymfonyStyle
    {
        return $this->testSuiteInstance->getIo();
    }

    /**
     * @param string $fileName
     *
     * @return string
     */
    protected function getFilePath(string $fileName): string
    {
        $filePath = realpath($this->fixtureDirectory . DIRECTORY_SEPARATOR . $fileName);

        if (!$filePath) {
            throw new \RuntimeException('Missing file ' . $fileName, 1591703650);
        }
        return $filePath;
    }

    /**
     * @param string $fileName
     *
     * @return string
     */
    protected function getFileIdentifier(string $fileName): string
    {
        return DIRECTORY_SEPARATOR . $this->sanitizeFileName($fileName);
    }

    /**
     * @param string $fileName
     *
     * @return string
     */
    protected function sanitizeFileName(string $fileName): string
    {
        return preg_replace('/jpeg$/', 'jpg', $fileName);
    }

    /**
     * @param string $fileNameAndPath
     *
     * @return string
     */
    protected function computeFolderIdentifier(string $fileNameAndPath): string
    {
        return DIRECTORY_SEPARATOR . str_replace(
                '.',
                '',
                dirname($this->fileName)
            );
    }

    /**
     * @param string $fileNameAndPath
     *
     * @return object|Folder
     */
    protected function getFolder($fileNameAndPath): Folder
    {
        $folderIdentifier = $this->computeFolderIdentifier($fileNameAndPath);
        return GeneralUtility::makeInstance(
            Folder::class,
            $this->getStorage(),
            $folderIdentifier,
            $folderIdentifier === DIRECTORY_SEPARATOR
                ? ''
                : $folderIdentifier
        );
    }

    /**
     * @param bool $expression
     * @param string $message
     *
     * @throws \Exception
     */
    protected function assertTrue(bool $expression, string $message)
    {
        if ($expression !== true) {
            $message .= chr(10) . chr(10) . 'Expected value: true';
            $message .= chr(10) . 'Actual value: ' . var_export($expression, true);
            throw new \Exception('AssertTrue! ' . $message, 1590757845);
        } else {
            $this->getIo()->success($message);
        }
    }

    /**
     * @param bool $expression
     * @param string $message
     *
     * @throws \Exception
     */
    protected function assertFalse(bool $expression, string $message)
    {
        if ($expression !== false) {
            $message .= chr(10) . chr(10) . 'Expected value: false';
            $message .= chr(10) . 'Actual value: ' . var_export($expression, true);
            throw new \Exception('AssertFalse! ' . $message, 1590757846);
        } else {
            $this->getIo()->success($message);
        }
    }

    /**
     * @param $expected
     * @param $actual
     * @param string $message
     *
     * @throws \Exception
     */
    protected function assert($expected, $actual, string $message)
    {
        if ($expected !== $actual) {
            $message .= chr(10) . chr(10) . 'Expected value: ' . $expected;
            $message .= chr(10) . 'Actual value:   ' . $actual;
            throw new \Exception('Assert! ' . $message, 1590757847);
        } else {
            $this->getIo()->success($message);
        }
    }
}
