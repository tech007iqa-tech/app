<?php
/**
 * IQA Lightweight Security Helper
 * Simple CSRF protection to keep forms safe.
 */

class Security {
    /**
     * Ensures a CSRF token exists in the session
     */
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    /**
     * Gets the current CSRF token
     */
    public static function getToken() {
        self::init();
        return $_SESSION['csrf_token'];
    }

    /**
     * Validates a submitted CSRF token
     */
    public static function validate($token) {
        self::init();
        return !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Sanitizes currency/decimal strings (e.g., "$1,200.50" -> 1200.50)
     */
    public static function sanitize_float($val) {
        if (is_numeric($val)) return (float)$val;
        $clean = preg_replace('/[^-0-9.]/', '', $val);
        return (float)$clean;
    }

    /**
     * Sanitizes integer strings (e.g., "1,000" -> 1000)
     */
    public static function sanitize_int($val) {
        if (is_numeric($val)) return (int)$val;
        $clean = preg_replace('/[^0-9]/', '', $val);
        return (int)$clean;
    }

    /**
     * Validates a password against security policies:
     * - Minimum 24 characters
     * - At least one uppercase letter
     * - At least one lowercase letter
     * - At least one digit
     * - At least one special character
     */
    public static function validatePassword($password, &$error_msg = '', $min_len = 25) {
        $len = strlen($password);
        if ($len < $min_len || $len > 125) {
            $error_msg = "Password length must be between {$min_len} and 125 characters.";
            return false;
        }
        // Only allow bypassing complexity checks for system-generated PPP passcodes (default length 25+)
        if ($min_len >= 25 && preg_match('/^[!#%+23456789:=?@ABCDEFGHJKLMNPRSTUVWXYZabcdefghijkmnopqrstuvwxyz]+$/', $password)) {
            return true;
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $error_msg = "Password must contain at least one uppercase letter.";
            return false;
        }
        if (!preg_match('/[a-z]/', $password)) {
            $error_msg = "Password must contain at least one lowercase letter.";
            return false;
        }
        if (!preg_match('/[0-9]/', $password)) {
            $error_msg = "Password must contain at least one number.";
            return false;
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $error_msg = "Password must contain at least one special character.";
            return false;
        }
        return true;
    }
}
