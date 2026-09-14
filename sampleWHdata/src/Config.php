<?php
namespace Src;

class Config
{
    private string $configFile;
    private string $dictFile;

    public function __construct()
    {
        $this->configFile = dirname(dirname(__DIR__)) . '/db/config.json';
        $this->dictFile = dirname(__DIR__) . '/dictionary.json';
    }

    public function loadConfig(): array
    {
        if (file_exists($this->configFile)) {
            return json_decode(file_get_contents($this->configFile), true) ?: [];
        }
        return [];
    }

    public function saveConfig(array $config): bool
    {
        // Ensure db directory exists
        $dbDir = dirname($this->configFile);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }
        return file_put_contents($this->configFile, json_encode($config, JSON_PRETTY_PRINT)) !== false;
    }

    public function loadDictionary(): array
    {
        if (file_exists($this->dictFile)) {
            return json_decode(file_get_contents($this->dictFile), true) ?: [];
        }
        return [];
    }

    public function saveDictionary(array $dictionary): bool
    {
        return file_put_contents($this->dictFile, json_encode($dictionary, JSON_PRETTY_PRINT)) !== false;
    }
}
