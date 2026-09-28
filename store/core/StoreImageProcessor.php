<?php
/**
 * StoreImageProcessor.php
 * Handles image upload validation, WebP optimization, and square thumbnail generation
 * specifically for the store application, keeping assets strictly in store/images/store/.
 */

class StoreImageProcessor {
    private static $defaultDir = __DIR__ . '/../images/store';

    /**
     * Process an uploaded photo file
     * @param array $file $_FILES['...'] element
     * @param string|null $targetDir Directory to save into (defaults to store/images/store)
     * @return array Result containing paths relative to store/
     */
    public static function processUpload($file, $targetDir = null) {
        if (!$file || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'No valid file uploaded'];
        }

        $dir = $targetDir ?: self::$defaultDir;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $originalName = basename($file['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (!in_array($ext, $allowed, true)) {
            return ['success' => false, 'error' => 'Unsupported format. Allowed: JPG, PNG, WEBP, GIF.'];
        }

        // Verify MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $validMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $validMimes, true)) {
            return ['success' => false, 'error' => 'Invalid image content.'];
        }

        $baseId = 'store_' . bin2hex(random_bytes(8));
        $rawFilename = 'raw_' . $baseId . '.' . $ext;
        $rawFullPath = $dir . DIRECTORY_SEPARATOR . $rawFilename;

        $saved = false;
        if (is_uploaded_file($file['tmp_name'])) {
            $saved = move_uploaded_file($file['tmp_name'], $rawFullPath);
        } else {
            $saved = @copy($file['tmp_name'], $rawFullPath);
        }

        if (!$saved) {
            return ['success' => false, 'error' => 'Failed to save uploaded file.'];
        }

        return self::generateWebPVersions($rawFullPath, $baseId, $originalName, $dir);
    }

    /**
     * Process an existing file on disk into optimized and thumbnail WebP
     */
    public static function processExistingFile($sourcePath, $targetDir = null) {
        if (!file_exists($sourcePath)) {
            return ['success' => false, 'error' => 'File not found'];
        }

        $dir = $targetDir ?: self::$defaultDir;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $originalName = basename($sourcePath);
        $baseId = 'store_' . bin2hex(random_bytes(8));

        return self::generateWebPVersions($sourcePath, $baseId, $originalName, $dir);
    }

    /**
     * Generate optimized WebP and square thumbnail WebP versions
     */
    private static function generateWebPVersions($sourcePath, $baseId, $originalName, $dir) {
        $rawRelative = 'images/store/' . basename($sourcePath);

        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            // Fallback: return raw image for all tiers if GD or WebP is unavailable
            return [
                'success' => true,
                'raw' => $rawRelative,
                'opt' => $rawRelative,
                'thumb' => $rawRelative,
                'original_name' => $originalName
            ];
        }

        $optFilename = 'opt_' . $baseId . '.webp';
        $thumbFilename = 'thumb_' . $baseId . '.webp';

        $optFullPath = $dir . DIRECTORY_SEPARATOR . $optFilename;
        $thumbFullPath = $dir . DIRECTORY_SEPARATOR . $thumbFilename;

        $optSuccess = self::resizeAndSaveWebP($sourcePath, $optFullPath, 1200, 85, false);
        $thumbSuccess = self::resizeAndSaveWebP($sourcePath, $thumbFullPath, 250, 80, true);

        $optRelative = $optSuccess ? ('images/store/' . $optFilename) : $rawRelative;
        $thumbRelative = $thumbSuccess ? ('images/store/' . $thumbFilename) : $optRelative;

        return [
            'success' => true,
            'raw' => $rawRelative,
            'opt' => $optRelative,
            'thumb' => $thumbRelative,
            'original_name' => $originalName
        ];
    }

    /**
     * Resize and encode to WebP with optional square crop
     */
    private static function resizeAndSaveWebP($source, $target, $maxDim, $quality, $square = false) {
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
            $newW = $newH = $maxDim;
            $srcX = 0;
            $srcY = 0;
            if ($width > $height) {
                $srcX = (int)(($width - $height) / 2);
                $cropW = $cropH = $height;
            } else {
                $srcY = (int)(($height - $width) / 2);
                $cropW = $cropH = $width;
            }
            $dstImg = imagecreatetruecolor($newW, $newH);
            imagealphablending($dstImg, false);
            imagesavealpha($dstImg, true);
            imagecopyresampled($dstImg, $srcImg, 0, 0, $srcX, $srcY, $newW, $newH, $cropW, $cropH);
        } else {
            // Scale proportionally to max dimension
            if ($width <= $maxDim && $height <= $maxDim) {
                $newW = $width;
                $newH = $height;
            } elseif ($width > $height) {
                $newW = $maxDim;
                $newH = (int)round(($height / $width) * $maxDim);
            } else {
                $newH = $maxDim;
                $newW = (int)round(($width / $height) * $maxDim);
            }

            $dstImg = imagecreatetruecolor($newW, $newH);
            imagealphablending($dstImg, false);
            imagesavealpha($dstImg, true);
            imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $width, $height);
        }

        $result = imagewebp($dstImg, $target, $quality);

        imagedestroy($srcImg);
        imagedestroy($dstImg);

        return $result;
    }
}
