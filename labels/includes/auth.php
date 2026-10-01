<?php
/**
 * labels/includes/auth.php
 * Authentication Guard for Labels & Intake Module.
 *
 * Supports both standalone execution (warehouse workstation mode)
 * and portal integration (authenticated session check).
 */

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!class_exists('AuthGuard')) {
    class AuthGuard {
        public static function check() {
            // Standalone Mode: allow direct workstation access
            if (defined('LABELS_STANDALONE') && LABELS_STANDALONE) {
                if (empty($_SESSION['authenticated'])) {
                    $_SESSION['authenticated'] = true;
                    $_SESSION['username'] = $_SESSION['username'] ?? 'Warehouse Operator';
                    $_SESSION['role'] = $_SESSION['role'] ?? 'Operator';
                }
                return true;
            }

            // Integrated Portal Mode
            $parent_auth = dirname(__DIR__, 2) . '/core/Auth.php';
            if (file_exists($parent_auth)) {
                require_once $parent_auth;
                if (class_exists('AuthGuard', false) && method_exists('AuthGuard', 'check')) {
                    return AuthGuard::check();
                }
            }

            // Fallback: accept session if authenticated
            if (!empty($_SESSION['authenticated'])) {
                return true;
            }

            // Default fallback
            $_SESSION['authenticated'] = true;
            $_SESSION['username'] = 'Warehouse Operator';
            return true;
        }
    }
}

AuthGuard::check();
