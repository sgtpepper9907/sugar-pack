<?php

namespace SugarPack\Tests\Utils;

use PHPUnit\Framework\TestCase;
use SugarPack\Utils\Manifest;

class ManifestTest extends TestCase
{
    private static $MANIFEST_PATH = __DIR__ . '/../assets/TestPackage/manifest.php';


    /** @test */
    public function it_reads_the_version_of_manifest_file()
    {
        $manifest = new Manifest(self::$MANIFEST_PATH);
        $expectedVersion = '1.0.0';
        $actualVersion = $manifest->getVersion();

        $this->assertEquals($actualVersion, $expectedVersion);
    }

    /** @test */
    public function it_reads_the_name_of_a_manifest_file()
    {
        $manifest = new Manifest(self::$MANIFEST_PATH);
        $expectedName = 'TestPackage';
        $actualName = $manifest->getName();

        $this->assertEquals($actualName, $expectedName);
    }
    
    /** @test */
    public function it_defaults_to_a_patch_upgrade_when_no_valid_upgrade_type_is_provided()
    {
        $manifest = new Manifest(self::$MANIFEST_PATH);
        $manifest->upgrade('Not valid');

        $expectedVersion = '1.0.1';
        $actualVersion = $manifest->getVersion();

        $this->assertEquals($actualVersion, $expectedVersion);
    }

    /** @test */
    public function it_applies_a_patch_upgrade_to_the_manifest_version()
    {
        $manifest = new Manifest(self::$MANIFEST_PATH);
        $manifest->upgrade('PATCH');

        $expectedVersion = '1.0.1';
        $actualVersion = $manifest->getVersion();

        $this->assertEquals($actualVersion, $expectedVersion);
    }

    /** @test */
    public function it_applies_a_minor_upgrade_to_the_manifest_version()
    {
        $manifest = new Manifest(self::$MANIFEST_PATH);
        $manifest->upgrade('MINOR');

        $expectedVersion = '1.1.0';
        $actualVersion = $manifest->getVersion();

        $this->assertEquals($actualVersion, $expectedVersion);
    }

    /** @test */
    public function it_applies_a_major_upgrade_to_the_manifest_version()
    {
        $manifest = new Manifest(self::$MANIFEST_PATH);
        $manifest->upgrade('MAJOR');

        $expectedVersion = '2.0.0';
        $actualVersion = $manifest->getVersion();

        $this->assertEquals($actualVersion, $expectedVersion);
    }

    protected function tearDown(): void
    {
        $this->restoreManifest();
    }

    private function restoreManifest()
    {
        $contents = <<<'MANIFEST'
<?php

$manifest = [
    'acceptable_sugar_versions' => [
        0 => '9.*',
        1 => '10.*',
    ],
    'acceptable_sugar_flavors' => [
        0 => 'ENT',
        1 => 'ULT',
        2 => 'PRO',
    ],
    'author' => 'David Angulo',
    'description' => 'Test package',
    'icon' => '',
    'is_uninstallable' => true,
    'name' => 'TestPackage',
    'published_date' => '2020-05-22',
    'type' => 'module',
    'version' => '1.0.0',
    'remove_tables' => '',
];

$installdefs = [
    'id' => 'TestPackage',
    'pre_execute' => [
        0 => '<basepath>/fix.php',
    ],
];
MANIFEST;

        file_put_contents(SELF::$MANIFEST_PATH, $contents);
    }
}
