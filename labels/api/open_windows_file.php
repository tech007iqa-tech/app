<?php
// api/open_windows_file.php
// A secure bridge to launch local files in their default Windows application.
// This allows bypassing the "Download" sandbox of the browser since the server is the workstation.

require_once '../includes/functions.php';

header('Content-Type: application/json');

try {
    // 1. Validate Input
    $path = $_POST['path'] ?? $_GET['path'] ?? null;

    if (!$path) {
        throw new Exception("No file path provided.");
    }

    // 2. Security Check: Only allow files inside the /exports/ directory
    // This prevents a malicious call from opening system files like C:\Windows\System32\...
    $base_dir = realpath(__DIR__ . '/../exports/');
    $target_file = realpath(__DIR__ . '/../' . $path);

    if (!$target_file || strpos($target_file, $base_dir) !== 0) {
        throw new Exception("Access Denied: You can only open files within the exports directory.");
    }

    if (!file_exists($target_file)) {
        throw new Exception("File not found on workstation: " . htmlspecialchars($path));
    }

    // 3. Trigger Windows to open the file
    // 'Start-Process' is the PowerShell equivalent of double-clicking the file.
    $cmd = 'powershell.exe -Command "Start-Process \'' . $target_file . '\'"';
    shell_exec($cmd);

    send_json_response(true, [
        'message' => "Successfully requested Windows to launch the file.",
        'file' => basename($target_file)
    ]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>
