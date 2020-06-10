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
        'images.jpeg' => [
            'fileName' => 'images.jpeg',
            'fileNameWithoutExtension' => 'images',
            'type' => 2,
            'mimeType' => 'image/jpeg',
        ],
        'images.jpg' => [
            'fileName' => 'images.jpg',
            'fileNameWithoutExtension' => 'images',
            'type' => 2,
            'mimeType' => 'image/jpeg',
        ],
        'sample.odt' => [
            'fileName' => 'sample.odt',
            'fileNameWithoutExtension' => 'sample',
            'type' => 5,
            'mimeType' => 'application/vnd.oasis.opendocument.text',
        ],
        'sample.pdf' => [
            'fileName' => 'sample.pdf',
            'fileNameWithoutExtension' => 'sample',
            'type' => 5,
            'mimeType' => 'application/pdf',
        ],
        'sample.png' => [
            'fileName' => 'sample.png',
            'fileNameWithoutExtension' => 'sample',
            'type' => 2,
            'mimeType' => 'image/png',
        ],
        'sample.youtube' => [
            'fileName' => 'sample.youtube',
            'fileNameWithoutExtension' => 'sample',
            'type' => 4,
            'mimeType' => 'video/youtube',
        ],
        'sample.mp4' => [
            'fileName' => 'sample.mp4',
            'fileNameWithoutExtension' => 'sample',
            'type' => 4,
            'mimeType' => 'video/mp4',
        ],
        'DummyFolder/dummy.jpg' => [
            'fileName' => 'dummy.jpg',
            'fileNameWithoutExtension' => 'dummy',
            'type' => 2,
            'mimeType' => 'image/jpg',
        ],
        'DummyFolder/dummy.odt' => [
            'fileName' => 'dummy.odt',
            'fileNameWithoutExtension' => 'dummy',
            'type' => 2,
            'mimeType'=> 'image/jpg',
        ],
        'DummyFolder/dummy.pdf' => [
            'fileName' => 'dummy.pdf',
            'fileNameWithoutExtension' => 'dummy',
            'type' => 5,
            'mimeType' => 'applicaton/pdf',
        ],
        'DummyFolder/dummy.youtube' => [
            'fileName' => 'dummy.youtube',
            'fileNameWithoutExtension' => 'dummy',
            'type' => 2,
            'mimeType' => 'image/jpg',
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
     * @var string
     */
    protected $remoteBaseDirectory = 'acceptance-tests-1590756100';

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
        return DIRECTORY_SEPARATOR . $this->remoteBaseDirectory . DIRECTORY_SEPARATOR . $fileName;
    }

    /**
     * @return object|Folder
     */
    protected function getBaseFolder(): Folder
    {
        $folderIdentifier = DIRECTORY_SEPARATOR . $this->remoteBaseDirectory . DIRECTORY_SEPARATOR;
        return GeneralUtility::makeInstance(
            Folder::class,
            $this->getStorage(),
            $folderIdentifier,
            $this->remoteBaseDirectory
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
