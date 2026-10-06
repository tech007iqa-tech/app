<?php
/**
 * Global Portal Authentication Guard
 * Enforces that any signed user has access, and unauthenticated users are redirected or rejected.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class AuthGuard {
    /**
     * Enforces that the current session is authenticated.
     * Any signed user (Admin, Manager, Operator, Technician, Sales, Marketing, Front Desk) is granted access.
     * Unauthenticated page requests are redirected to the portal login screen with a return URL.
     * Unauthenticated API/AJAX requests receive a 401 Unauthorized JSON response.
     *
     * @return bool True if authenticated or running via CLI.
     */
    public static function check() {
        if (php_sapi_name() === 'cli') {
            return true;
        }

        if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
            return true;
        }

        $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || (strpos($script_name, '/api/') !== false);

        if ($is_ajax) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Authentication required. Please log in.']);
            exit();
        }

        // Determine login path dynamically based on current web path
        if (preg_match('#^(.*?)/(?:orders|labels|marketing|tech|sampleWHdata|setup|store|core|assets|db|DOCS)/#i', $script_name, $m)) {
            $base_path = $m[1];
        } else {
            $base_path = rtrim(str_replace('\\', '/', dirname($script_name)), '/\\');
            if ($base_path === '.' || $base_path === '/' || $base_path === '\\') {
                $base_path = '';
            }
        }
        $login_path = ($base_path !== '' ? $base_path : '') . '/orders/core/login.php';

        $current_uri = $_SERVER['REQUEST_URI'] ?? '';
        $redirect_target = $login_path;
        if (!empty($current_uri)) {
            $redirect_target .= (strpos($login_path, '?') !== false ? '&' : '?') . 'return_url=' . urlencode($current_uri);
        }

        header("Location: " . $redirect_target);
        exit();
    }
}
