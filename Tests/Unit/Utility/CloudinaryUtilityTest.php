<?php

namespace Visol\Cloudinary\Tests\Unit\Utility;

/*
 * This file is part of the Visol/Cloudinary project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use FluidTYPO3\Flux\Utility\ExtensionConfigurationUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Visol\Cloudinary\Utility\CloudinaryUtility;

/**
 * Class CloudinaryUtilityTest
 *
 * @package Visol\Cloudinary\Tests\Unit\Utility
 */
class CloudinaryUtilityTest extends UnitTestCase
{

    /**
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
    }

    /**
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
    }

    /**
     * @test
     */
    public function instantiateMe()
    {
        $fixture = new \Visol\Cloudinary\Utility\CloudinaryUtility();
        $this->assertInstanceOf(CloudinaryUtility::class, $fixture);
    }

    /**
     * @test
     */
    public function testComputeCloudinaryFolderPath()
    {
        $this->markTestSkipped(
            'Database is not initialized, we should rather use `nemut/testing-framework`'
        );

        $fakeFileIdentifier = '/foo/bar';
        $expected = 'foo/bar';

        $fixture = new CloudinaryUtility();
        $actual = $fixture->computeCloudinaryFolderPath($fakeFileIdentifier);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * @dataProvider propertyProvider
     *
     * @param string $fakeFileIdentifier
     * @param string $expected
     */
    public function testComputeCloudinaryPublicIdBase(string $fakeFileIdentifier, string $expected)
    {
        $this->markTestSkipped(
            'Database is not initialized, we should rather use `nemut/testing-framework`'
        );
        $fixture = new CloudinaryUtility();
        $actual = $fixture->computeCloudinaryPublicId($fakeFileIdentifier);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Provider
     */
    public function propertyProvider()
    {
        return [
            ['foo/bar.jpg', 'foo/bar'],
            ['foo/bar.png', 'foo/bar'],
            ['foo/bar.pdf', 'foo/bar.pdf'],
            ['foo/bar.txt', 'foo/bar.txt'],
            ['foo/bar.mp4', 'foo/bar.mp4'],
        ];
    }

}
