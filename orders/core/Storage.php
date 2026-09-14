<?php
/**
 * Storage Abstraction layer for Location Photos.
 * Supports multiple disk drivers (e.g. SSD for web optimized views, Spinning Disk for raw archives).
 */
interface StorageDriver {
    public function put($filename, $sourcePath);
    public function getFullPath($filename);
    public function getUrl($filename);
    public function delete($filename);
}

class LocalDiskDriver implements StorageDriver {
    protected $basePath;
    protected $urlPrefix;

    public function __construct($basePath, $urlPrefix = '') {
        $this->basePath = rtrim(str_replace('\\', '/', $basePath), '/') . '/';
        $this->urlPrefix = $urlPrefix;
    }

    public function put($filename, $sourcePath) {
        if (!is_dir($this->basePath)) {
            if (!mkdir($this->basePath, 0755, true)) {
                throw new Exception("Unable to create storage directory: " . $this->basePath);
            }
        }
        $target = $this->basePath . basename($filename);
        if (!copy($sourcePath, $target)) {
            throw new Exception("Failed to write file to: " . $target);
        }
        return true;
    }

    public function getFullPath($filename) {
        return $this->basePath . basename($filename);
    }

    public function getUrl($filename) {
        if (empty($this->urlPrefix)) {
            return 'assets/location_photos/' . basename($filename);
        }
        return $this->urlPrefix . basename($filename);
    }

    public function delete($filename) {
        $target = $this->getFullPath($filename);
        if (file_exists($target)) {
            @unlink($target);
        }
        return true;
    }
}

class StorageManager {
    private static $drivers = [];

    public static function initialize() {
        if (!empty(self::$drivers)) {
            return;
        }

        // SSD Preview local storage
        $ssdPath = dirname(__DIR__) . '/assets/location_photos/';
        self::$drivers['ssd_local'] = new LocalDiskDriver($ssdPath, 'assets/location_photos/');

        // Archive storage (spinning disk) - Fetch path from settings db
        $archivePath = '';
        try {
            // Get DB connection helper from app context
            require_once __DIR__ . '/Database.php';
            $db = Database::warehouse();
            $stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
            $stmt->execute(['archive_photos_path']);
            $archivePath = $stmt->fetchColumn();
        } catch (Exception $e) {}

        if (empty($archivePath)) {
            $archivePath = dirname(__DIR__) . '/assets/location_photos/archive/';
        }

        self::$drivers['spinning_disk'] = new LocalDiskDriver($archivePath, '');
    }

    /**
     * @return StorageDriver
     */
    public static function getDriver($name) {
        self::initialize();
        if (!isset(self::$drivers[$name])) {
            throw new Exception("Storage driver '{$name}' is not configured.");
        }
        return self::$drivers[$name];
    }
}
