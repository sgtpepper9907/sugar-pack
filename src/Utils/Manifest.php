<?php

namespace SugarPack\Utils;

class Manifest
{
    private $path = null;
    private $contents = null;

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->read();
    }

    private function read()
    {
        $contents = file_get_contents($this->path);
        $contents = str_replace(['<?php', '?>'], '', $contents);

        $this->contents = $contents;
    }

    private function write(string $contents)
    {
        file_put_contents($this->path, $contents);
    }

    public function getVersion()
    {
        $this->read();
        eval($this->contents);

        return $manifest['version'];
    }

    public function getName()
    {
        $this->read();
        eval($this->contents);

        return $manifest['name'];
    }

    public static function isValidUpgradeType(string $type) : bool
    {
        return in_array($type, ['PATCH', 'MINOR', 'MAJOR']);
    }

    /**
     * Upgrades the version of the manifest
     *
     * @param string $type Type of upgrade PATCH|MINOR|MAJOR
     * @return string The new version
     */
    public function upgrade(string $type = 'PATCH') : string
    {
        eval($this->contents);

        $currentVersion = $manifest['version'];

        $subVersions = explode('.', $currentVersion);
        $subVersionsCount = count($subVersions);
        
        $type = static::isValidUpgradeType($type) ? $type : 'PATCH';

        switch ($type) {
            case 'PATCH':
                $subVersions[$subVersionsCount - 1] += 1;
                break;
            case 'MINOR':
                $subVersions[$subVersionsCount < 3 ? 1 : $subVersionsCount - 2] += 1;
                break;
            case 'MAJOR':
                $subVersions[0] += 1;
                break;
        }

        $newVersion = implode('.', $subVersions);
        $manifest['version'] = $newVersion;


        $newContents = "<?php \n\n";
        $newContents .= '$manifest = ' . $this->prettyPrintArray($manifest) . ';';
        $newContents .= "\n\n";

        if (isset($installdefs)) {
            $newContents .= '$installdefs = ' . $this->prettyPrintArray($installdefs) . ';';
            $newContents .= "\n\n";
        }

        $this->write($newContents);

        return $newVersion;
    }

    private function prettyPrintArray($expression)
    {
        $export = var_export($expression, true);
        $export = preg_replace('/^([ ]*)(.*)/m', '$1$1$2', $export);
        $array = preg_split("/\r\n|\n|\r/", $export);
        $array = preg_replace(["/\s*array\s\($/", "/\)(,)?$/", "/\s=>\s$/"], [null, ']$1', ' => ['], $array);
        $export = join(PHP_EOL, array_filter(['['] + $array));
        
        return $export;
    }

    public function toString()
    {
        return $this->__toString();
    }

    public function __toString()
    {
        $this->read();
        return $this->contents;
    }
}
