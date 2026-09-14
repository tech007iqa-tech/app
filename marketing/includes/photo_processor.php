<?php
/**
 * Photo Processor Utility - Marketing Hub
 * Handles thumbnail generation and image optimization.
 */

class PhotoProcessor {
    private $pdo;
    private $basePath;
    private $thumbWidth = 400;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->basePath = __DIR__ . '/../';
    }

    /**
     * Processes a photo: generates thumbnail, optimized version, and updates DB.
     */
    public function process($photoId) {
        // Check for GD library before processing
        if (!extension_loaded('gd')) {
            $this->updateStatus($photoId, 'Ready');
            return true;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM photos WHERE id = ?");
        $stmt->execute([$photoId]);
        $photo = $stmt->fetch();

        if (!$photo) return false;

        $sourcePath = $this->basePath . $photo['file_path'];
        if (!file_exists($sourcePath)) {
            $this->updateStatus($photoId, 'Error');
            return false;
        }

        // 1. Generate Thumbnail (150x150 Square)
        $thumbFilename = 'thumb_' . pathinfo($photo['filename'], PATHINFO_FILENAME) . '.webp';
        $thumbRelativePath = 'assets/photo_bucket/' . $thumbFilename;
        $thumbFullPath = $this->basePath . $thumbRelativePath;

        // 2. Generate Optimized Full Version (Max 1920px)
        $optFilename = 'opt_' . pathinfo($photo['filename'], PATHINFO_FILENAME) . '.webp';
        $optRelativePath = 'assets/photo_bucket/' . $optFilename;
        $optFullPath = $this->basePath . $optRelativePath;

        $tSuccess = $this->resizeImage($sourcePath, $thumbFullPath, 150, 75, true); // True for square crop
        $oSuccess = $this->resizeImage($sourcePath, $optFullPath, 1920, 85);

        if ($tSuccess || $oSuccess) {
            $stmt = $this->pdo->prepare("UPDATE photos SET thumbnail_path = ?, optimized_path = ?, status = 'Ready' WHERE id = ?");
            $stmt->execute([
                $tSuccess ? $thumbRelativePath : null,
                $oSuccess ? $optRelativePath : null,
                $photoId
            ]);
            return true;
        } else {
            $this->updateStatus($photoId, 'Ready');
            return false;
        }
    }

    private function updateStatus($id, $status) {
        $stmt = $this->pdo->prepare("UPDATE photos SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
    }

    private function resizeImage($source, $target, $maxWidth, $quality, $square = false) {
        if (!extension_loaded('gd')) return false;

        $info = getimagesize($source);
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
            // Only resize if wider than maxWidth
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

        // Preserve transparency
        imagealphablending($tmpImg, false);
        imagesavealpha($tmpImg, true);

        imagecopyresampled($tmpImg, $srcImg, 0, 0, $srcX, $srcY, $newWidth, $newHeight, $width, $height);

        // Always save as WebP for the processed versions
        $success = false;
        if (function_exists('imagewebp')) {
            $success = imagewebp($tmpImg, $target, $quality);
        } else {
            // Fallback to JPEG if WebP not supported
            $success = imagejpeg($tmpImg, $target, $quality);
        }

        imagedestroy($srcImg);
        imagedestroy($tmpImg);

        return $success;
    }
}
