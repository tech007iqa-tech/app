<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Audit.php';
Security::init();

/**
 * Access Control Layer
 * Checks if a session is authenticated. If not, redirect to login page.
 */
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: core/login.php");
    exit();
}

// Enforce password change if using default credentials
if (isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true) {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $is_api = (strpos($request_uri, '/api/') !== false);

    if ($is_api) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Password change required.']);
        exit();
    } else {
        $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
        $is_index = (basename($script_name) === 'index.php');
        $current_view = $_GET['view'] ?? '';
        $is_settings = ($is_index && $current_view === 'settings');

        if (!$is_settings) {
            $redirect_url = 'index.php?view=settings';
            if (strpos($script_name, '/pages/') !== false) {
                $redirect_url = '../index.php?view=settings';
            }
            header("Location: " . $redirect_url);
            exit();
        }
    }
}
?>
