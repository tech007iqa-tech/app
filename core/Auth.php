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
        if (preg_match('#^(.*(?:/serverWarehouse|/wh\.latinospc))/#', $script_name, $m)) {
            $login_path = $m[1] . '/orders/core/login.php';
        } else {
            $pos_orders = strpos($script_name, '/orders/');
            if ($pos_orders !== false) {
                $login_path = substr($script_name, 0, $pos_orders) . '/orders/core/login.php';
            } else {
                $pos_labels = strpos($script_name, '/labels/');
                if ($pos_labels !== false) {
                    $login_path = substr($script_name, 0, $pos_labels) . '/orders/core/login.php';
                } else {
                    $pos_mkt = strpos($script_name, '/marketing/');
                    if ($pos_mkt !== false) {
                        $login_path = substr($script_name, 0, $pos_mkt) . '/orders/core/login.php';
                    } else {
                        $pos_tech = strpos($script_name, '/tech/');
                        if ($pos_tech !== false) {
                            $login_path = substr($script_name, 0, $pos_tech) . '/orders/core/login.php';
                        } else {
                            $login_path = '/orders/core/login.php';
                        }
                    }
                }
            }
        }

        $current_uri = $_SERVER['REQUEST_URI'] ?? '';
        $redirect_target = $login_path;
        if (!empty($current_uri)) {
            $redirect_target .= (strpos($login_path, '?') !== false ? '&' : '?') . 'return_url=' . urlencode($current_uri);
        }

        header("Location: " . $redirect_target);
        exit();
    }
}
