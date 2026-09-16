<?php
/**
 * MediaManager - Centralized Photography & Camera Engine
 * Handles image optimization, EXIF rotation correction, WebP generation,
 * date-partitioned storage (YYYY/MM), and database record management.
 */

require_once __DIR__ . '/Storage.php';
require_once __DIR__ . '/database.php';

class MediaManager {
    private $db;

    public function __construct($db = null) {
        $this->db = $db ?: Database::warehouse();
    }

    /**
     * Process an uploaded photo (file path or base64 dataURI).
     *
     * @param string $source Path to temporary file or base64 dataURI
     * @param string $originalName Original filename
     * @param string $locationCode Shelf/Location identifier (e.g. 'C-1')
     * @param string $sector Sector name (e.g. 'Laptops', 'Desktops')
     * @param string $category Layer / Category tag (e.g. 'Layer 1 (Bottom)')
     * @param string $uploadedBy Username
     * @return array Created photo record details
     */
    public function processUpload($source, $originalName, $locationCode, $sector = 'Laptops', $category = 'General', $uploadedBy = 'System') {
        StorageManager::initialize();

        $isBase64 = strpos($source, 'data:image/') === 0;
        $tempCleanup = false;

        if ($isBase64) {
            $tempFile = tempnam(sys_get_temp_dir(), 'cam_snap_');
            $dataParts = explode(',', $source);
            $binaryData = base64_decode($dataParts[1] ?? '');
            if (!$binaryData) {
                throw new Exception("Invalid base64 camera image data.");
            }
            file_put_contents($tempFile, $binaryData);
            $sourcePath = $tempFile;
            $tempCleanup = true;
            if (empty($originalName) || $originalName === 'blob' || $originalName === 'snapshot.jpg') {
                $originalName = 'snapshot_' . date('Ymd_His') . '.jpg';
            }
        } else {
            $sourcePath = $source;
            if (!file_exists($sourcePath)) {
                throw new Exception("Source image file does not exist: " . $sourcePath);
            }
        }

        try {
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $ext = 'jpg';
            }

            // Date partitioning: YYYY/MM/
            $yearMonth = date('Y/m');
            $safeLoc = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $locationCode);
            $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
            $uniqueId = uniqid();

            $baseFilename = "{$safeLoc}_{$safeName}_{$uniqueId}";
            $rawRelPath = "{$yearMonth}/{$baseFilename}.{$ext}";
            $optRelPath = "{$yearMonth}/{$baseFilename}_opt.webp";
            $thumbRelPath = "{$yearMonth}/{$baseFilename}_thumb.webp";

            $archiveDriver = StorageManager::getDriver('spinning_disk');
            $ssdDriver = StorageManager::getDriver('ssd_local');

            // 1. Store Raw File in Archive Driver
            $archiveDriver->put($rawRelPath, $sourcePath);
            $rawFullPath = $archiveDriver->getFullPath($rawRelPath);

            // 2. Generate Optimized WebP Image (Max 1920px) and Micro-Thumbnail (160x160)
            $optFullPath = $ssdDriver->getFullPath($optRelPath);
            $thumbFullPath = $ssdDriver->getFullPath($thumbRelPath);

            $optDir = dirname($optFullPath);
            if (!is_dir($optDir)) {
                @mkdir($optDir, 0755, true);
            }
            $thumbDir = dirname($thumbFullPath);
            if (!is_dir($thumbDir)) {
                @mkdir($thumbDir, 0755, true);
            }

            $optSuccess = $this->resizeAndOptimize($sourcePath, $optFullPath, 1920, 82, false);
            $thumbSuccess = $this->resizeAndOptimize($sourcePath, $thumbFullPath, 160, 75, true);

            if (!$optSuccess) {
                // Fallback copy if GD transformation fails
                $ssdDriver->put($optRelPath, $sourcePath);
                $optUrl = $ssdDriver->getUrl($optRelPath);
            } else {
                $optUrl = $ssdDriver->getUrl($optRelPath);
            }

            if (!$thumbSuccess) {
                $ssdDriver->put($thumbRelPath, $sourcePath);
                $thumbUrl = $ssdDriver->getUrl($thumbRelPath);
            } else {
                $thumbUrl = $ssdDriver->getUrl($thumbRelPath);
            }

            // 3. Save Record in Database
            $stmt = $this->db->prepare("
                INSERT INTO location_photos (
                    location_code, original_filename, archive_driver, archive_path, 
                    optimized_path, thumbnail_path, uploaded_by, category, sector, created_at
                ) VALUES (?, ?, 'spinning_disk', ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");

            $stmt->execute([
                $locationCode,
                $originalName,
                $rawRelPath,
                $optUrl,
                $thumbUrl,
                $uploadedBy,
                $category,
                $sector
            ]);

            $newId = (int)$this->db->lastInsertId();

            if ($tempCleanup && file_exists($sourcePath)) {
                @unlink($sourcePath);
            }

            return [
                'id' => $newId,
                'location_code' => $locationCode,
                'original_filename' => $originalName,
                'optimized_path' => $optUrl,
                'thumbnail_path' => $thumbUrl,
                'archive_path' => $rawRelPath,
                'category' => $category,
                'sector' => $sector,
                'uploaded_by' => $uploadedBy,
                'created_at' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            if ($tempCleanup && file_exists($sourcePath)) {
                @unlink($sourcePath);
            }
            throw $e;
        }
    }

    /**
     * Delete a photo record and remove its files from disk.
     */
    public function deletePhoto($photoId) {
        $stmt = $this->db->prepare("SELECT * FROM location_photos WHERE id = ?");
        $stmt->execute([(int)$photoId]);
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$photo) {
            return false;
        }

        StorageManager::initialize();
        $archiveDriver = StorageManager::getDriver('spinning_disk');
        $ssdDriver = StorageManager::getDriver('ssd_local');

        // Delete raw archive
        if (!empty($photo['archive_path'])) {
            $archiveDriver->delete($photo['archive_path']);
        }

        // Delete optimized webp & thumbnail
        if (!empty($photo['optimized_path'])) {
            $ssdDriver->delete($photo['optimized_path']);
        }
        if (!empty($photo['thumbnail_path'])) {
            $ssdDriver->delete($photo['thumbnail_path']);
        }

        $stmtDel = $this->db->prepare("DELETE FROM location_photos WHERE id = ?");
        return $stmtDel->execute([(int)$photoId]);
    }

    /**
     * Fetch all photos for a specific location.
     */
    public function getPhotosForLocation($locationCode, $sector = null) {
        if ($sector) {
            $stmt = $this->db->prepare("SELECT * FROM location_photos WHERE location_code = ? AND sector = ? ORDER BY category ASC, created_at DESC");
            $stmt->execute([$locationCode, $sector]);
        } else {
            $stmt = $this->db->prepare("SELECT * FROM location_photos WHERE location_code = ? ORDER BY category ASC, created_at DESC");
            $stmt->execute([$locationCode]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch all photos for a working zone.
     */
    public function getPhotosForZone($zoneName) {
        $stmt = $this->db->prepare("
            SELECT lp.* FROM location_photos lp
            INNER JOIN locations l ON lp.location_code = l.location_code
            WHERE l.working_zone_name = ?
            ORDER BY lp.location_code ASC, lp.category ASC, lp.created_at DESC
        ");
        $stmt->execute([$zoneName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resize, orient, and convert an image to WebP format using GD.
     */
    private function resizeAndOptimize($source, $target, $maxDimension, $quality = 80, $squareCrop = false) {
        if (!extension_loaded('gd')) {
            return false;
        }

        $info = @getimagesize($source);
        if (!$info) {
            return false;
        }

        $mime = $info['mime'];
        $srcImg = null;

        switch ($mime) {
            case 'image/jpeg':
                $srcImg = @imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $srcImg = @imagecreatefrompng($source);
                break;
            case 'image/gif':
                $srcImg = @imagecreatefromgif($source);
                break;
            case 'image/webp':
                $srcImg = @imagecreatefromwebp($source);
                break;
        }

        if (!$srcImg) {
            return false;
        }

        // Correct EXIF Orientation if available (for smartphone uploads)
        if (function_exists('exif_read_data') && ($mime === 'image/jpeg')) {
            $exif = @exif_read_data($source);
            if (!empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3:
                        $srcImg = imagerotate($srcImg, 180, 0);
                        break;
                    case 6:
                        $srcImg = imagerotate($srcImg, -90, 0);
                        break;
                    case 8:
                        $srcImg = imagerotate($srcImg, 90, 0);
                        break;
                }
            }
        }

        $width = imagesx($srcImg);
        $height = imagesy($srcImg);

        if ($squareCrop) {
            $newWidth = $newHeight = $maxDimension;
            if ($width > $height) {
                $srcX = (int)(($width - $height) / 2);
                $srcY = 0;
                $srcW = $height;
                $srcH = $height;
            } else {
                $srcX = 0;
                $srcY = (int)(($height - $width) / 2);
                $srcW = $width;
                $srcH = $width;
            }
        } else {
            $srcX = 0;
            $srcY = 0;
            $srcW = $width;
            $srcH = $height;

            if ($width > $maxDimension || $height > $maxDimension) {
                if ($width >= $height) {
                    $newWidth = $maxDimension;
                    $newHeight = (int)floor($height * ($newWidth / $width));
                } else {
                    $newHeight = $maxDimension;
                    $newWidth = (int)floor($width * ($newHeight / $height));
                }
            } else {
                $newWidth = $width;
                $newHeight = $height;
            }
        }

        $dstImg = imagecreatetruecolor($newWidth, $newHeight);

        // Alpha channel handling
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);

        imagecopyresampled($dstImg, $srcImg, 0, 0, $srcX, $srcY, $newWidth, $newHeight, $srcW, $srcH);

        $success = false;
        if (function_exists('imagewebp')) {
            $success = imagewebp($dstImg, $target, $quality);
        } else {
            $success = imagejpeg($dstImg, $target, $quality);
        }

        imagedestroy($srcImg);
        imagedestroy($dstImg);

        return $success;
    }
}
