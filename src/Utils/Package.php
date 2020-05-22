<?php

namespace SugarPack\Utils;

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class Package
{
    /**
     * Path to the package folder containing the manifest
     *
     * @var string
     */
    private $path = null;

    /**
     * The package's manifest
     *
     * @var Manifest
     */
    public $manifest = null;

    /**
     * Files allowed by SugarCrm Module loader
     *
     * @var array
     */
    private $allowedFiles = [
        'css', 'gif', 'hbs', 'htm', 'html', 'jpg', 'js', 'md5',
        'pdf', 'php', 'png', 'tpl', 'txt', 'xml'
    ];

    public function __construct(string $path)
    {
        $this->path = $path;
        if (!$this->isValid()) {
            throw new InvalidArgumentException('The specified path does not contain a valid SugarCRM© Module Loadable Package.');
        }

        $this->manifest = new Manifest($this->path . '/manifest.php');
    }

    private function isValid() : bool
    {
        if (!is_dir($this->path)) {
            return false;
        }

        $files = scandir($this->path);

        if (!in_array('manifest.php', $files)) {
            return false;
        }

        return true;
    }

    public function compress(string $output) : bool
    {
        $zip = new ZipArchive;

        if (!$zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            return false;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            // Skip directories (they would be added automatically)
            if ($file->isDir()) {
                continue;
            }

            if (!in_array($file->getExtension(), $this->allowedFiles)) {
                continue;
            }

            // Get real and relative path for current file
            $filePath = $file->getRealPath();
            $relativePath = $this->removeEverythingBefore($filePath, basename($this->path) . DIRECTORY_SEPARATOR);

            // Add current file to archive
            $zip->addFile($filePath, $relativePath);
        }

        // Zip archive will be created only after closing object
        $zip->close();

        return true;
    }

    private function removeEverythingBefore($in, $before)
    {
        $pos = strpos($in, $before);
        return $pos !== false
        ? substr($in, $pos + strlen($before), strlen($in))
        : "";
    }
}
