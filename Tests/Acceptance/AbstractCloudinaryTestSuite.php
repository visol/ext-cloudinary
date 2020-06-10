<?php

namespace Visol\Cloudinary\Tests\Acceptance;

use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;

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
        $this->storage = ResourceFactory::getInstance()->getStorageObject($fakeStorageId);;
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
}
