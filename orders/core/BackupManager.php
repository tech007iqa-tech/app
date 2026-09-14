<?php
/**
 * Backup Manager for Location Photography
 * Creates and restores standard .tar archives containing raw photos and SQLite database metadata.
 */
require_once __DIR__ . '/Storage.php';

class BackupManager {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Exports all location photos to a .tar archive.
     */
    public function export($outputTarPath) {
        if (file_exists($outputTarPath)) {
            @unlink($outputTarPath);
        }

        // 1. Fetch metadata from DB
        $stmt = $this->db->query("SELECT * FROM location_photos");
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Create a temporary folder inside the workspace
        $tempDir = sys_get_temp_dir() . '/wh_photo_backup_' . uniqid();
        if (!mkdir($tempDir, 0755, true)) {
            throw new Exception("Failed to create temporary export directory.");
        }

        try {
            // 3. Write metadata.json
            file_put_contents($tempDir . '/metadata.json', json_encode($photos, JSON_PRETTY_PRINT));

            // 4. Copy raw photos into the temp folder
            $archiveDriver = StorageManager::getDriver('spinning_disk');
            foreach ($photos as $photo) {
                $sourceFullPath = $archiveDriver->getFullPath($photo['archive_path']);
                if (file_exists($sourceFullPath)) {
                    copy($sourceFullPath, $tempDir . '/' . $photo['archive_path']);
                }
            }

            // 5. Pack into .tar archive using PharData
            $tar = new PharData($outputTarPath);
            $tar->buildFromDirectory($tempDir);

        } finally {
            // Clean up temporary files
            $this->recursiveRemoveDir($tempDir);
        }

        return file_exists($outputTarPath);
    }

    /**
     * Restores location photos from a .tar archive.
     */
    public function import($inputTarPath) {
        if (!file_exists($inputTarPath)) {
            throw new Exception("Tar backup file does not exist.");
        }

        $tempDir = sys_get_temp_dir() . '/wh_photo_restore_' . uniqid();
        if (!mkdir($tempDir, 0755, true)) {
            throw new Exception("Failed to create temporary import directory.");
        }

        try {
            // 1. Extract tar using PharData
            $tar = new PharData($inputTarPath);
            $tar->extractTo($tempDir);

            $metaFile = $tempDir . '/metadata.json';
            if (!file_exists($metaFile)) {
                throw new Exception("Invalid backup package: metadata.json is missing.");
            }

            $photos = json_decode(file_get_contents($metaFile), true);
            if (!is_array($photos)) {
                throw new Exception("Invalid metadata format in backup package.");
            }

            $archiveDriver = StorageManager::getDriver('spinning_disk');
            $ssdDriver = StorageManager::getDriver('ssd_local');

            require_once __DIR__ . '/LocationPhotoProcessor.php';
            $processor = new LocationPhotoProcessor($this->db);

            // Reflecting the helper method in GD
            $gdEnabled = extension_loaded('gd');

            foreach ($photos as $photo) {
                $originalFilename = $photo['original_filename'];
                $archivePath = $photo['archive_path'];
                $tempFilePath = $tempDir . '/' . $archivePath;

                if (!file_exists($tempFilePath)) {
                    continue; // Skip if raw file is missing from archive package
                }

                // 2. Conflict Resolution (Windows OS Style: Auto-Rename)
                $finalArchivePath = $archivePath;
                $counter = 1;
                $pathInfo = pathinfo($archivePath);

                while (file_exists($archiveDriver->getFullPath($finalArchivePath))) {
                    $finalArchivePath = $pathInfo['filename'] . ' (' . $counter . ').' . $pathInfo['extension'];
                    $counter++;
                }

                // 3. Move original raw photo to current configured archive storage
                $archiveDriver->put($finalArchivePath, $tempFilePath);

                // 4. Re-generate optimized previews on SSD
                $baseFilename = pathinfo($finalArchivePath, PATHINFO_FILENAME);
                $optimizedFilename = $baseFilename . '_opt.webp';
                $thumbnailFilename = $baseFilename . '_thumb.webp';

                $rawFullPath = $archiveDriver->getFullPath($finalArchivePath);
                $optimizedFullPath = $ssdDriver->getFullPath($optimizedFilename);
                $thumbnailFullPath = $ssdDriver->getFullPath($thumbnailFilename);

                $optSuccess = false;
                $thumbSuccess = false;

                if ($gdEnabled) {
                    // Call GD resizing helper
                    $optSuccess = $this->resizeImageFallback($rawFullPath, $optimizedFullPath, 1920, 85);
                    $thumbSuccess = $this->resizeImageFallback($rawFullPath, $thumbnailFullPath, 150, 75, true);
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

                // 5. Insert or Update DB record
                $stmt = $this->db->prepare("
                    INSERT INTO location_photos
                    (location_code, original_filename, archive_driver, archive_path, optimized_path, thumbnail_path, uploaded_by, category, sector)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $photo['location_code'],
                    $originalFilename,
                    'spinning_disk',
                    $finalArchivePath,
                    $optimizedPath,
                    $thumbnailPath,
                    $photo['uploaded_by'] ?? 'System',
                    $photo['category'] ?? 'General',
                    $photo['sector'] ?? 'Laptops'
                ]);
            }
        } finally {
            // Clean up temporary files
            $this->recursiveRemoveDir($tempDir);
        }

        return true;
    }

    private function recursiveRemoveDir($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveRemoveDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private function resizeImageFallback($source, $target, $maxWidth, $quality, $square = false) {
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
