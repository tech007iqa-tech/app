<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$is_subpage = (strpos($script_name, '/pages/') !== false);

/**
 * Technician Access Control Layer
 * Checks if a session is authenticated and has the Technician role.
 */
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    $login_url = $is_subpage ? '../core/login.php' : 'core/login.php';
    header("Location: " . $login_url);
    exit();
}

$user_role = $_SESSION['role'] ?? '';
$has_access = false;

// Admins and Technicians have access.
if ($user_role === 'Admin' || strpos($user_role, 'Technician') !== false) {
    $has_access = true;
}

if (!$has_access) {
    die("Access Denied: You do not have Technician privileges. Please contact an Administrator.");
}

// Enforce password change if force_password_change is active
if (isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true) {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $is_api = (strpos($request_uri, '/api/') !== false);

    if ($is_api) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Password change required.']);
        exit();
    } else {
        $is_settings = (basename($script_name) === 'settings.php');
        if (!$is_settings) {
            $redirect_url = $is_subpage ? 'settings.php' : 'pages/settings.php';
            header("Location: " . $redirect_url);
            exit();
        }
    }
}
?>
