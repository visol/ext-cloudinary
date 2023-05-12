<?php

namespace Visol\Cloudinary\Tests\Acceptance;


use Visol\Cloudinary\Tests\Acceptance\FileOperation\AddFileOperationTest;
use Visol\Cloudinary\Tests\Acceptance\FileOperation\CopyFolderOperationTests;
use Visol\Cloudinary\Tests\Acceptance\FileOperation\CountFilesInFolderOperationTests;
use Visol\Cloudinary\Tests\Acceptance\FileOperation\CreateFolderOperationTests;
use Visol\Cloudinary\Tests\Acceptance\FileOperation\DeleteFolderOperationTest;
use Visol\Cloudinary\Tests\Acceptance\FileOperation\RenameFolderOperationTests;

class FolderTestSuite extends AbstractCloudinaryTestSuite
{

    protected array $files = [
        'sub-folder/image-jpeg.jpeg',
        'sub-folder/image-tiff.tiff',
        'sub-folder/sub-sub-folder/image-jpeg.jpeg',
        'sub-folder/sub-sub-folder/image-tiff.tiff',
        'image-jpg.jpg',
        'image-png.png',
        #'document.odt',
        'document.pdf',
        #'video.youtube',
        'video.mp4',
    ];

    public function runTests()
    {

        foreach ($this->files as $fileName) {

            // Upload oll files
            $test = new AddFileOperationTest($this, $fileName);
            $test->run();
        }

        // Count files
        $test = new CountFilesInFolderOperationTests($this, '/');
        $test->setExpectedNumberOfFiles(4)
            ->setExpectedNumberOfFolders(1) // _processed_ folder must be taken into consideration in the root folder
            ->run();

        // Count files
        $test = new CountFilesInFolderOperationTests($this, '/sub-folder/');
        $test->setExpectedNumberOfFiles(2)
            ->setExpectedNumberOfFolders(1)
            ->run();

        $test = new CopyFolderOperationTests($this, '/sub-folder/');
        $test->setTargetFolderName('sub-folder-copied');
        $test->run();

        $test = new RenameFolderOperationTests($this, '/sub-folder/');
        $test->setTargetFolderName('sub-folder-renamed');
        $test->run();

        $test = new CreateFolderOperationTests($this, '/sub-folder-created/');
        $test->run();

        # todo rename is handled differently from move
        #$test = new MoveFolderOperationTests($this, '/sub-folder/');
        #$test->setTargetFolderName('sub-folder-renamed');
        #$test->run();

        $test = new DeleteFolderOperationTest($this);
        $test->run();
    }
}
