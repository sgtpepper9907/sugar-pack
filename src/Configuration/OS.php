<?php

namespace SugarPack\Configuration;

class OS
{
    private function __construct()
    {
    }
    
    public static function getConfigDir() : string
    {
        $dir = '$HOME/.config/sugar-pack';

        if (static::isWindows()) {
            $dir = '%APPDATA%\SugarPack';
        }

        return exec('echo ' . $dir);
    }

    public static function getDefaultDownloadsDir(): string
    {
        $homeDirVariable = '$HOME';

        if (static::isWindows()) {
            $homeDirVariable = '%USERPROFILE%';
        }

        return exec('echo ' . $homeDirVariable . DIRECTORY_SEPARATOR . 'Downloads');
    }

    public static function isWindows(): bool
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return true;
        }

        return false;
    }
}