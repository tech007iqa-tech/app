<?php
/**
 * IQA System Backup Engine
 * Packages all core databases into a secure ZIP for download.
 */
require_once __DIR__ . '/../core/database.php';
include __DIR__ . '/../core/auth.php';

// 1. Admin Only Access
if (($_SESSION['role'] ?? '') !== 'Admin') {
    die("Access Denied: Administrator role required.");
}

// 2. Prepare Backup
$db_dir = Database::getDbDir();
$backup_name = 'LatinosPC_Backup_' . date('Y-m-d_His') . '.zip';
$temp_file = sys_get_temp_dir() . '/' . $backup_name;
$timestamp = date('Y-m-d_His');
$files = glob($db_dir . '/*.{db,sqlite}', GLOB_BRACE) ?: glob($db_dir . '/*.db');

if (extension_loaded('zip')) {
    // PREFERRED: ZIP Archive
    $backup_name = "LatinosPC_Backup_{$timestamp}.zip";
    $temp_file = sys_get_temp_dir() . '/' . $backup_name;

    $zip = new ZipArchive();
    if ($zip->open($temp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        foreach ($files as $file) $zip->addFile($file, basename($file));
        $zip->close();
        stream_backup($temp_file, $backup_name, 'application/zip');
    }
} elseif (class_exists('PharData')) {
    // FALLBACK: TAR Archive (Built-in to PHP Phar extension)
    $backup_name = "LatinosPC_Backup_{$timestamp}.tar";
    $temp_file = sys_get_temp_dir() . '/' . $backup_name;

    try {
        $tar = new PharData($temp_file);
        foreach ($files as $file) $tar->addFile($file, basename($file));
        stream_backup($temp_file, $backup_name, 'application/x-tar');
    } catch (Exception $e) {
        die("Backup failed: " . $e->getMessage());
    }
} else {
    die("Error: Neither ZipArchive nor PharData (Tar) extensions are enabled on this server. Please contact your administrator to enable 'extension=zip' in php.ini.");
}

/**
 * Streams the file to browser and cleans up
 */
function stream_backup($path, $name, $type) {
    Audit::log('SYSTEM_BACKUP', 'ALL_DATABASES', "Backup generated: " . $name, 'system');

    header('Content-Type: ' . $type);
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($path));
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($path);
    unlink($path);
    exit();
}
