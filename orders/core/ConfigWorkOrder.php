<?php
// orders/core/ConfigWorkOrder.php

class ConfigWorkOrder
{
    private string $configFile;
    private string $mainConfigFile;

    public function __construct()
    {
        $this->configFile = dirname(__DIR__, 2) . '/db/config_work_order.json';
        $this->mainConfigFile = dirname(__DIR__, 2) . '/db/config.json';
    }

    /**
     * Load B2B Work Order config and merge it with API key from main config.
     */
    public function loadConfig(): array
    {
        $config = [];
        if (file_exists($this->configFile)) {
            $config = json_decode(file_get_contents($this->configFile), true) ?: [];
        }

        // Inherit gemini_api_key from db/config.json
        $config['gemini_api_key'] = $this->getApiKey();

        return $config;
    }

    /**
     * Fetch the API Key from main configuration file.
     */
    public function getApiKey(): string
    {
        if (file_exists($this->mainConfigFile)) {
            $mainConfig = json_decode(file_get_contents($this->mainConfigFile), true) ?: [];
            return $mainConfig['gemini_api_key'] ?? '';
        }
        return '';
    }

    /**
     * Check if Gemini API key exists.
     */
    public function hasApiKey(): bool
    {
        return !empty($this->getApiKey());
    }

    /**
     * Save configuration changes back to db/config_work_order.json.
     */
    public function saveConfig(array $config): bool
    {
        // Don't save api key in the B2B config, keep it only in the main config
        unset($config['gemini_api_key']);

        $dbDir = dirname($this->configFile);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        return file_put_contents($this->configFile, json_encode($config, JSON_PRETTY_PRINT)) !== false;
    }
}
