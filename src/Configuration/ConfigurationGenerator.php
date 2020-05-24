<?php

namespace SugarPack\Configuration;

use Symfony\Component\Yaml\Yaml;

class ConfigurationGenerator
{
    private function __construct() {}

    public static function getDefaultPackYamlConfig(): string
    {
        return Yaml::dump(static::getDefaultPackConfig());
    }

    public static function getDefaultPackConfig(): array
    {
        return [
            'output_dir' => OS::getDefaultDownloadsDir(),
            'package_naming' => 'Versioned'
        ];
    }
}