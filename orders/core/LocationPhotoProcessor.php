<?php
/**
 * Processes location photography uploads.
 * Resizes, optimizes, generates thumbnails, and saves original raw photos to the archive.
 */
require_once __DIR__ . '/Storage.php';

class LocationPhotoProcessor {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Processes an uploaded photo file.
     */
    public function processUpload($tmpPath, $originalName, $locationCode, $sector, $category, $uploadedBy) {
        // Ensure storage directory is initialized
        StorageManager::initialize();

        $archiveDriver = StorageManager::getDriver('spinning_disk');
        $ssdDriver = StorageManager::getDriver('ssd_local');

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            throw new Exception("Unsupported image format. Allowed formats: jpg, jpeg, png, gif, webp.");
        }

        // Generate unique base filename
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $uniqueId = uniqid();

        $rawFilename = $locationCode . '_' . $safeName . '_' . $uniqueId . '.' . $ext;
        $optimizedFilename = $locationCode . '_' . $safeName . '_' . $uniqueId . '_opt.webp';
        $thumbnailFilename = $locationCode . '_' . $safeName . '_' . $uniqueId . '_thumb.webp';

        // 1. Save original raw photo to configured archive location
        $archiveDriver->put($rawFilename, $tmpPath);

        // 2. Generate optimized image and thumbnail on local SSD
        $rawFullPath = $archiveDriver->getFullPath($rawFilename);
        $optimizedFullPath = $ssdDriver->getFullPath($optimizedFilename);
        $thumbnailFullPath = $ssdDriver->getFullPath($thumbnailFilename);

        $gdEnabled = extension_loaded('gd');
        $optSuccess = false;
        $thumbSuccess = false;

        if ($gdEnabled) {
            $optSuccess = $this->resizeImage($rawFullPath, $optimizedFullPath, 1920, 85);
            $thumbSuccess = $this->resizeImage($rawFullPath, $thumbnailFullPath, 150, 75, true);
        }

        if (!$optSuccess) {
            $ssdDriver->put($optimizedFilename, $rawFullPath);
            $optimizedPath = $ssdDriver->getUrl($optimizedFilename);
        } else {
            $optimizedPath = $ssdDriver->getUrl($optimizedFilename);
        }

        if (!$thumbSuccess) {
            $ssdDriver->put($thumbnailFilename, $rawFullPath);
            $thumbnailPath = $ssdDriver->getUrl($thumbnailFilename);
        } else {
            $thumbnailPath = $ssdDriver->getUrl($thumbnailFilename);
        }

        // 3. Save reference in SQLite DB
        $stmt = $this->db->prepare("
            INSERT INTO location_photos (location_code, original_filename, archive_driver, archive_path, optimized_path, thumbnail_path, uploaded_by, category, sector)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $locationCode,
            $originalName,
            'spinning_disk',
            $rawFilename,
            $optimizedPath,
            $thumbnailPath,
            $uploadedBy,
            $category,
            $sector
        ]);

        return true;
    }

    /**
     * Resizes and converts images to WebP fallback JPEG.
     */
    private function resizeImage($source, $target, $maxWidth, $quality, $square = false) {
        $info = @getimagesize($source);
        if (!$info) return false;

        $mime = $info['mime'];
        $srcImg = null;
        switch ($mime) {
            case 'image/jpeg': $srcImg = @imagecreatefromjpeg($source); break;
            case 'image/png':  $srcImg = @imagecreatefrompng($source);  break;
            case 'image/gif':  $srcImg = @imagecreatefromgif($source);  break;
            case 'image/webp': $srcImg = @imagecreatefromwebp($source); break;
        }

        if (!$srcImg) return false;

        $width = $info[0];
        $height = $info[1];

        if ($square) {
            $newWidth = $newHeight = $maxWidth;
            $srcX = 0; $srcY = 0;
            if ($width > $height) {
                $srcX = ($width - $height) / 2;
                $width = $height;
            } else {
                $srcY = ($height - $width) / 2;
                $height = $width;
            }
        } else {
            if ($width > $maxWidth) {
                $newWidth = $maxWidth;
                $newHeight = floor($height * ($newWidth / $width));
            } else {
                $newWidth = $width;
                $newHeight = $height;
            }
            $srcX = 0; $srcY = 0;
        }

        $tmpImg = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve alpha channels
        imagealphablending($tmpImg, false);
        imagesavealpha($tmpImg, true);

        imagecopyresampled($tmpImg, $srcImg, 0, 0, $srcX, $srcY, $newWidth, $newHeight, $width, $height);

        $success = false;
        if (function_exists('imagewebp')) {
            $success = imagewebp($tmpImg, $target, $quality);
        } else {
            $success = imagejpeg($tmpImg, $target, $quality);
        }

        imagedestroy($srcImg);
        imagedestroy($tmpImg);

        return $success;
    }
}
