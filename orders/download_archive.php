<?php
/**
 * Raw Archive Photo Downloader
 * Authenticated streaming download for raw original photo assets.
 */
require_once __DIR__ . '/core/database.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/Storage.php';

$photo_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$photo_id) {
    http_response_code(400);
    die("Invalid photo ID.");
}

$db = Database::warehouse();
$stmt = $db->prepare("SELECT * FROM location_photos WHERE id = ? LIMIT 1");
$stmt->execute([$photo_id]);
$photo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$photo || empty($photo['archive_path'])) {
    http_response_code(404);
    die("Photo asset not found.");
}

StorageManager::initialize();
$driverName = $photo['archive_driver'] ?: 'spinning_disk';
$driver = StorageManager::getDriver($driverName);
$fullPath = $driver->getFullPath($photo['archive_path']);

if (!file_exists($fullPath) || !is_readable($fullPath)) {
    // Fallback: check optimized_path if raw archive file is missing
    $ssdDriver = StorageManager::getDriver('ssd_local');
    $fallbackPath = $ssdDriver->getFullPath($photo['optimized_path']);
    if (file_exists($fallbackPath) && is_readable($fallbackPath)) {
        $fullPath = $fallbackPath;
    } else {
        http_response_code(404);
        die("Physical image file is missing from archive storage.");
    }
}

$filename = !empty($photo['original_filename']) ? basename($photo['original_filename']) : basename($fullPath);
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $fullPath) : 'application/octet-stream';
if ($finfo) {
    finfo_close($finfo);
}

// Clean output buffer before streaming binary
if (ob_get_level()) {
    ob_end_clean();
}

// Stream file for download
header('Content-Description: File Transfer');
header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit();
