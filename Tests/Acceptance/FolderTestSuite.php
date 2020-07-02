<?php

namespace Visol\Cloudinary\Tests\Acceptance;


use Visol\Cloudinary\Tests\Acceptance\FileOperation\AddFileOperationTest;
use Visol\Cloudinary\Tests\Acceptance\FileOperation\CopyFolderOperationTests;
use Visol\Cloudinary\Tests\Acceptance\FileOperation\CountFilesInFolderOperationTests;
use Visol\Cloudinary\Tests\Acceptance\FileOperation\DeleteFileOperationTest;
use Visol\Cloudinary\Tests\Acceptance\FileOperation\DeleteFolderOperationTest;
use Visol\Cloudinary\Tests\Acceptance\FileOperation\ReadFileOperationTest;
use Visol\Cloudinary\Tests\Acceptance\FileOperation\GetFilesInFolderOperationTests;
use Visol\Cloudinary\Tests\Acceptance\FileOperation\RenameFolderOperationTests;

class FolderTestSuite extends AbstractCloudinaryTestSuite
{

    /**
     * @var
     */
    protected $files = [
        'sub-folder/image-jpeg.jpeg',
        'sub-folder/image-tiff.tiff',
        'image-jpg.jpg',
        'image-png.png',
        'document.odt',
        'document.pdf',
        'video.youtube',
        'video.mp4',
    ];

    public function runTests()
    {
        $test = new DeleteFolderOperationTest($this);
        $test->run();

        foreach ($this->files as $fileName) {

            // Upload oll files
            $test = new AddFileOperationTest($this, $fileName);
            $test->run();
        }

        // Count files
        $test = new CountFilesInFolderOperationTests($this, '/');
        $test->setExpectedNumberOfFiles(6)
            ->setExpectedNumberOfFolders(1) // _processed_ folder must be taken into consideration in the root folder
            ->run();

        // Count files
        $test = new CountFilesInFolderOperationTests($this, '/sub-folder/');
        $test->setExpectedNumberOfFiles(2)
            ->setExpectedNumberOfFolders(0)
            ->run();

        $test = new CopyFolderOperationTests($this, '/sub-folder/');
        $test->setTargetFolderName('sub-folder-copied');
        $test->run();

        $test = new RenameFolderOperationTests($this, '/sub-folder/');
        $test->setTargetFolderName('sub-folder-renamed');
        $test->run();

        # todo rename is handled differently from move
        #$test = new MoveFolderOperationTests($this, '/sub-folder/');
        #$test->setTargetFolderName('sub-folder-renamed');
        #$test->run();
    }
}
