<?php

namespace SugarPack\Configuration;

use Symfony\Component\Yaml\Yaml;

class PackConfigurationManager
{
    public static function getConfigOrDefault(): array
    {
        try {
            return Yaml::parseFile(static::getPathToConfigFile());
        } catch (\Exception $e) {
            return ConfigurationGenerator::getDefaultPackConfig();
        }
    }

    public static function getPathToConfigFile() : string
    {
        return OS::getConfigDir() . DIRECTORY_SEPARATOR . 'pack.yaml';
    }
}