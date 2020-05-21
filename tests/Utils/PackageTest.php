<?php

namespace SugarPack\Tests\Utils;

use PHPUnit\Framework\TestCase;
use SugarPack\Utils\Package;
use ZipArchive;

class PackageTest extends TestCase
{
    private static $PACKAGE_PATH = __DIR__ . '/../assets/TestPackage';
    private static $ZIP_PATH = __DIR__ . '/test.zip';

    /** @test */
    public function it_validates_package_by_looking_for_a_manifest()
    {
        $package = new Package(self::$PACKAGE_PATH);
        
        $expected = true;
        $actual = $package->isValid();

        $this->assertEquals($actual, $expected);
    }

    /** @test */
    public function it_determines_a_package_as_invalid_if_no_manifest_is_found()
    {
        $package = new Package('./');
        
        $expected = false;
        $actual = $package->isValid();

        $this->assertEquals($actual, $expected);
    }

    /** @test */
    public function it_compresses_the_package()
    {
        $package = new Package(self::$PACKAGE_PATH);
        $package->compress(self::$ZIP_PATH);

        $expectedFilesCount = 3;

        $zip = new ZipArchive;
        $zip->open(self::$ZIP_PATH);
        $actualFilesCount = $zip->count();
        $zip->close();

        $this->assertEquals($actualFilesCount, $expectedFilesCount);
    }

    protected function tearDown(): void
    {
        @unlink(self::$ZIP_PATH);
    }
}
