<?php

namespace SugarPack;

use Composer\Installer\PackageEvent;

class ComposerScripts
{
    public static function createConfigFilesIfNotExists(PackageEvent $event)
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        require $vendorDir . '/autoload.php';

        $configPath = \SugarPack\Configuration\PackConfigurationManager::getPathToConfigFile();

        if (!file_exists($configPath)) {
            file_put_contents(
                $configPath, \SugarPack\Configuration\ConfigurationGenerator::getDefaultPackYamlConfig()
            );
        }
    }
}