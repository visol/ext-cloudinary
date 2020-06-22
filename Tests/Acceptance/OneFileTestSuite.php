<?php

namespace Visol\Cloudinary\Tests\Acceptance;


use Visol\Cloudinary\Tests\Acceptance\FileOperation\AddFileOperationTest;
use Visol\Cloudinary\Tests\Acceptance\FileOperation\DeleteFileOperationTest;
use Visol\Cloudinary\Tests\Acceptance\FileOperation\DeleteFolderOperationTest;
use Visol\Cloudinary\Tests\Acceptance\FileOperation\GetFileOperationTest;
use Visol\Cloudinary\Tests\Acceptance\FileOperation\GetFilesInFolderOperationTests;

class OneFileTestSuite extends AbstractCloudinaryTestSuite
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

            $test = new AddFileOperationTest($this, $fileName);
            $test->run();

            $test = new GetFileOperationTest($this, $fileName);
            $test->run();

            $test = new GetFilesInFolderOperationTests($this, $fileName);
            $test->run();

            $test = new DeleteFileOperationTest($this, $fileName);
            $test->run();
        }
    }
}
