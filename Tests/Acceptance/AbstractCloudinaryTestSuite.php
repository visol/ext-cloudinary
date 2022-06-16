<?php

namespace Visol\Cloudinary\Tests\Acceptance;

use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractCloudinaryTestSuite
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
     * @param int $fakeStorageId
     * @param SymfonyStyle $io
     */
    public function __construct(int $fakeStorageId, SymfonyStyle $io)
    {
        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $this->storage = $resourceFactory->getStorageObject($fakeStorageId);
        $this->io = $io;
    }

    /**
     * @return void
     */
    abstract public function runTests();

    /**
     * @return ResourceStorage
     */
    public function getStorage(): ResourceStorage
    {
        return $this->storage;
    }

    /**
     * @return SymfonyStyle
     */
    public function getIo(): SymfonyStyle
    {
        return $this->io;
    }

    /**
     * @param string $fileName
     * @param string $suffix
     *
     * @return string
     */
    protected function getAlternativeName(string $fileName, string $suffix): string
    {
        $pathParts = pathinfo($fileName);
        return str_replace('.' . $pathParts['extension'], '-' . $suffix . '.' . $pathParts['extension'], $fileName);
    }
}
