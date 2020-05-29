<?php

namespace Visol\Cloudinary\Tests\Acceptance;

use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractCloudinaryAcceptanceTest
{

    /**
     * @var ResourceStorage
     */
    protected $storage;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * AbstractCloudinaryAcceptanceTest constructor.
     *
     * @param ResourceStorage $testingStorage
     * @param SymfonyStyle $io
     */
    public function __construct(ResourceStorage $testingStorage, SymfonyStyle $io)
    {
        $this->storage = $testingStorage;
        $this->io = $io;
    }

    /**
     * @var string
     */
    protected $fixtureDirectory = __DIR__ . '/../Fixtures';

    /**
     * @var string
     */
    protected $remoteBaseDirectory = 'acceptance-tests-1590756100';

    /**
     * @return void
     */
    abstract public function runTests();

    /**
     * @param string $fileName
     *
     * @return string
     */
    protected function getFilePath(string $fileName): string
    {
        return realpath($this->fixtureDirectory . DIRECTORY_SEPARATOR . $fileName);
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
            $this->storage,
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
            throw new \Exception('AssertTrue! ' . $message, 1590757845);
        } else {
            $this->io->success($message);
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
            throw new \Exception('AssertFalse! ' . $message, 1590757846);
        } else {
            $this->io->success($message);
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
            $message = 'Assert! ' . $message;
            $message .= $expected . ' !== ' . $actual;
            throw new \Exception($message, 1590757847);
        } else {
            $this->io->success($message);
        }
    }
}
