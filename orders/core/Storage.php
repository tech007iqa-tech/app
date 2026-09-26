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
        $this->urlPrefix = !empty($urlPrefix) ? rtrim(str_replace('\\', '/', $urlPrefix), '/') . '/' : '';
    }

    private function sanitizeRelPath($filename) {
        $clean = str_replace('\\', '/', $filename);
        $clean = preg_replace('#/+#', '/', $clean);
        // Strip out leading slash or dangerous relative parent traversal
        $clean = ltrim($clean, '/');
        $clean = str_replace('../', '', $clean);
        if (!empty($this->urlPrefix) && strpos($clean, $this->urlPrefix) === 0) {
            $clean = substr($clean, strlen($this->urlPrefix));
        }
        return $clean;
    }

    public function put($filename, $sourcePath) {
        $relPath = $this->sanitizeRelPath($filename);
        $target = $this->basePath . $relPath;
        $dir = dirname($target);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                throw new Exception("Unable to create storage directory: " . $dir);
            }
        }
        if (!@copy($sourcePath, $target)) {
            throw new Exception("Failed to write file to: " . $target);
        }
        return true;
    }

    public function getFullPath($filename) {
        $relPath = $this->sanitizeRelPath($filename);
        return $this->basePath . $relPath;
    }

    public function getUrl($filename) {
        $relPath = $this->sanitizeRelPath($filename);
        if (empty($this->urlPrefix)) {
            return 'assets/location_photos/' . $relPath;
        }
        return $this->urlPrefix . $relPath;
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
            require_once __DIR__ . '/database.php';
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
