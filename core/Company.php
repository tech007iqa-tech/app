<?php
/**
 * IQA Metal Warehouse Systems - Company & Brand Configuration Helper
 * Provides centralized, dynamic configuration stored safely outside HTTP in warehouse.db
 */

require_once __DIR__ . '/Database.php';

class Company
{
    private static $cache = [];
    private static $loaded = false;

    /**
     * Loads all company settings into memory cache
     */
    private static function load()
    {
        if (self::$loaded) {
            return;
        }

        try {
            $conn = Database::warehouse();
            // Ensure settings table exists
            $conn->exec("CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT
            )");

            $stmt = $conn->query("SELECT key, value FROM settings");
            if ($stmt) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    self::$cache[$row['key']] = $row['value'];
                }
            }
        } catch (Exception $e) {
            // Graceful fallback if database connection is pending setup
        }

        self::$loaded = true;
    }

    /**
     * Retrieves a setting value with a default fallback
     */
    public static function get($key, $default = '')
    {
        self::load();
        return self::$cache[$key] ?? $default;
    }

    /**
     * Updates or creates a setting
     */
    public static function set($key, $value)
    {
        try {
            $conn = Database::warehouse();
            $conn->exec("CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT
            )");
            $stmt = $conn->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value");
            $stmt->execute([$key, (string) $value]);
            self::$cache[$key] = (string) $value;
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Saves multiple settings at once
     */
    public static function setMultiple(array $data)
    {
        try {
            $conn = Database::warehouse();
            $conn->beginTransaction();
            $stmt = $conn->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value");
            foreach ($data as $k => $v) {
                $stmt->execute([$k, is_array($v) ? json_encode($v) : (string) $v]);
                self::$cache[$k] = is_array($v) ? json_encode($v) : (string) $v;
            }
            $conn->commit();
            return true;
        } catch (Exception $e) {
            if (isset($conn) && $conn->inTransaction()) {
                $conn->rollBack();
            }
            return false;
        }
    }

    /**
     * Checks if initial system setup has been completed
     */
    public static function isSetupComplete()
    {
        return self::get('setup_completed', '0') === '1';
    }

    // Convenience brand helpers with default values
    public static function getName()
    {
        return self::get('company_name', 'IQA Metal');
    }

    public static function getSystemName()
    {
        return self::get('system_name', 'IQA Metal Warehouse Systems');
    }

    public static function getUrl()
    {
        return self::get('company_url', 'https://latinospc.com');
    }

    public static function getTagline()
    {
        return self::get('tagline', 'Intelligent inventory management & rapid label logistics.');
    }

    public static function getEmail()
    {
        return self::get('support_email', 'contact@latinospc.com');
    }

    public static function getCurrency()
    {
        return self::get('currency_symbol', '$');
    }

    public static function getTrade()
    {
        return self::get('trade_description', 'Used Computer, Laptop & Electronics Refurbishing');
    }
}
