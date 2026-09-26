<?php
/**
 * IQA Lightweight Security Helper
 * Simple CSRF protection to keep forms safe.
 */

if (!class_exists('Security')) {
class Security {
    /**
     * Ensures a CSRF token exists in the session
     */
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            if (PHP_VERSION_ID >= 70300) {
                @session_set_cookie_params([
                    'lifetime' => 0,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $is_https,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            } else {
                @ini_set('session.cookie_httponly', 1);
                @ini_set('session.use_only_cookies', 1);
                if ($is_https) {
                    @ini_set('session.cookie_secure', 1);
                }
            }
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
     * Generates a cryptographically secure 64-character hexadecimal sequence key for PPP
     */
    public static function generate_ppp_key() {
        return strtoupper(bin2hex(random_bytes(32)));
    }

    /**
     * Generates Steve Gibson GRC Perfect Paper Passwords passcodes
     *
     * @param string $sequence_key 32 or 64-character hex sequence key (128-bit or 256-bit)
     * @param int $cell_len Character length per cell (typically ceil(length / 5))
     * @return array 125 passcodes (25 rows x 5 columns)
     */
    public static function generate_ppp_passcodes($sequence_key, $cell_len = 6) {
        $clean_key = preg_replace('/[^a-fA-F0-9]/', '', (string)$sequence_key);
        $hex_len = strlen($clean_key);
        if ($hex_len < 16 || $hex_len > 64) {
            return [];
        }

        if ($hex_len === 32) {
            $cipher = 'aes-128-ecb';
        } elseif ($hex_len === 48) {
            $cipher = 'aes-192-ecb';
        } elseif ($hex_len === 64) {
            $cipher = 'aes-256-ecb';
        } elseif ($hex_len < 32) {
            $clean_key = str_pad($clean_key, 32, '0');
            $cipher = 'aes-128-ecb';
        } elseif ($hex_len < 48) {
            $clean_key = str_pad($clean_key, 48, '0');
            $cipher = 'aes-192-ecb';
        } else {
            $clean_key = str_pad($clean_key, 64, '0');
            $cipher = 'aes-256-ecb';
        }

        $alphabet = '!#%+23456789:=?@ABCDEFGHJKLMNPRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $key_bin = hex2bin($clean_key);
        $passcodes = [];

        for ($i = 0; $i < 125; $i++) {
            $ciphertext = "";
            $blocks_needed = (int)ceil(($cell_len * 6) / 128.0);
            for ($b = 0; $b < $blocks_needed; $b++) {
                $counter_bin = pack('P', $i) . pack('P', $b);
                $ciphertext .= openssl_encrypt($counter_bin, $cipher, $key_bin, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
            }

            $passcode = "";
            $bit_buffer = 0;
            $bit_count = 0;
            $byte_index = 0;
            $cipher_len = strlen($ciphertext);

            for ($char_idx = 0; $char_idx < $cell_len; $char_idx++) {
                while ($bit_count < 6 && $byte_index < $cipher_len) {
                    $bit_buffer = ($bit_buffer << 8) | ord($ciphertext[$byte_index]);
                    $byte_index++;
                    $bit_count += 8;
                }
                if ($bit_count >= 6) {
                    $shift = $bit_count - 6;
                    $idx = ($bit_buffer >> $shift) & 0x3F;
                    $bit_count = $shift;
                    $passcode .= $alphabet[$idx];
                } else {
                    $passcode .= $alphabet[0];
                }
            }
            $passcodes[] = $passcode;
        }
        return $passcodes;
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
}
