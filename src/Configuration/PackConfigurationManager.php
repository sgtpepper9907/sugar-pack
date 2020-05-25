<?php

namespace SugarPack\Configuration;

use Symfony\Component\Yaml\Yaml;

class PackConfigurationManager
{
    /**
     * Gets the config from the config file or creates it and returns the default
     *
     * @return array
     */
    public static function getConfigOrDefault(): array
    {
        try {
            return Yaml::parseFile(static::getPathToConfigFile());
        } catch (\Exception $e) {
            self::createConfigFile();
            return ConfigurationGenerator::getDefaultPackConfig();
        }
    }

    public static function getPathToConfigFile() : string
    {
        return OS::getConfigDir() . DIRECTORY_SEPARATOR . 'pack.yaml';
    }

    private static function createConfigFile(): void
    {
        mkdir(OS::getConfigDir(), 0777, true);

        @file_put_contents(
            static::getPathToConfigFile(),
            ConfigurationGenerator::getDefaultPackYamlConfig()
        );
    }
}
