<?php

namespace SugarPack\Configuration;

class PublishConfigurationManager
{
    private const PUBLISH_CONFIG_FILE_NAME = 'sugar_pack.publish.json';

    /**
     * @var PublishProfile[]
     */
    private $profiles = [];

    /**
     * Relative paths of possible locations for the config file.
     *
     * @var array
     */
    private $possibleConfigFilePaths = [
        '/' . self::PUBLISH_CONFIG_FILE_NAME,
        '/../' . self::PUBLISH_CONFIG_FILE_NAME
    ];

    /**
     * Gets the loaded profiles.
     *
     * @return PublishProfile[]
     */
    public function getProfiles(): array
    {
        return $this->profiles;
    }

    /**
     * Tries to load the publish profiles from a config file relative
     * to the package to be published.
     *
     * @param string $packagePath
     * @return boolean
     */
    public function loadFromFile(string $packagePath): bool
    {
        $configFilePath = null;

        foreach ($this->possibleConfigFilePaths as $possibleConfigFilePath) {
            $path = $packagePath . $possibleConfigFilePath;
            
            if (file_exists($path)) {
                $configFilePath = $path;
                break;
            }
        }

        if (is_null($configFilePath)) {
            return false;
        }

        $config = @json_decode(file_get_contents($configFilePath), true);
        $profiles = $config['profiles'];

        if (!$profiles || !is_array($profiles) ||  empty($profiles)) {
            return false;
        }

        foreach ($profiles as $profile) {
            $this->profiles[] = $this->mapToPublishConfiguration($profile);
        }

        return true;
    }

    /**
     * Gets a loaded profile by its name.
     *
     * @param string $name
     * @return PublishProfile|null
     */
    public function getProfileByName(string $name) : ?PublishProfile
    {   
        foreach ($this->profiles as $profile) {
            if ($profile->name == $name) {
                return $profile;
            }
        }

        return null;
    }


    /**
     * Gets the default loaded profile.
     *
     * @return PublishProfile|null
     */
    public function getDefaultProfile(): ?PublishProfile
    {
        return isset($this->profiles[0]) ? $this->profiles[0] : null;
    }

    public static function mergeProfiles(PublishProfile $a, PublishProfile $b) : PublishProfile
    {
        $merged = new PublishProfile;
        $merged->name = $b->name ?? $a->name;
        $merged->instance = $b->instance ?? $a->instance;
        $merged->username = $b->username ?? $a->username;
        $merged->password = $b->password ?? $a->password;
        $merged->platform = $b->platform ?? $a->platform;

        return $merged;
    }

    /**
     * Maps and associative array to the config class
     *
     * @param array $data
     * @return PublishProfile
     */
    private function mapToPublishConfiguration(array $data) : PublishProfile
    {
        $configuration = new PublishProfile;

        foreach ($data as $key => $value) {
            $configuration->{$key} = $value;
        }

        return $configuration;
    }
}