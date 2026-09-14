<?php
// api/check_file_exists.php
// Checks if a specific file exists in the exports directory.
header('Content-Type: application/json');
require_once '../includes/functions.php';

try {
    $path = $_POST['path'] ?? $_GET['path'] ?? null;

    if (!$path) {
        throw new Exception("No path provided.");
    }

    // Security check: only allow files in exports
    $base_dir = realpath(__DIR__ . '/../exports/');
    $target_file = realpath(__DIR__ . '/../' . $path);

    $exists = false;
    if ($target_file && strpos($target_file, $base_dir) === 0 && file_exists($target_file)) {
        $exists = true;
    }

    send_json_response(true, ['exists' => $exists]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
