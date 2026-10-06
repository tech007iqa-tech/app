<?php
/**
 * labels/includes/config.php
 * Central Configuration & Modular Fallback Engine for IQA Labels.
 *
 * Ensures this module can run 100% STANDALONE on any host or server,
 * while maintaining seamless integration with the parent portal when present.
 */

// 1. Session initialization
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Detection of parent portal environment
$parent_core_dir = dirname(__DIR__, 2) . '/core';
$has_parent_core = is_dir($parent_core_dir) && file_exists($parent_core_dir . '/Database.php');

// Define Standalone Mode (Workstation Mode)
if (!defined('LABELS_STANDALONE')) {
    define('LABELS_STANDALONE', true);
}

// 3. Application Metadata
define('LABELS_APP_NAME', 'IQA Label Engine');
define('LABELS_APP_SUBTITLE', 'Warehouse Inventory & Thermal Label Logistics');
define('LABELS_VERSION', '2.5.0 Modular');
define('LABELS_LABEL_WIDTH', '2in');
define('LABELS_LABEL_HEIGHT', '1in');

// 4. Security Class: Use Parent if present, otherwise Standalone Fallback
$core_security_file = $parent_core_dir . '/Security.php';
if (file_exists($core_security_file)) {
    require_once $core_security_file;
} elseif (!class_exists('Security')) {
    class Security {
        public static function init() {
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
        }

        public static function getToken() {
            self::init();
            return $_SESSION['csrf_token'];
        }

        public static function validate($token) {
            self::init();
            return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
        }
    }
}
Security::init();

// 5. Company Class: Use Parent if present, otherwise Standalone Fallback
$core_company_file = $parent_core_dir . '/Company.php';
if (file_exists($core_company_file)) {
    require_once $core_company_file;
} elseif (!class_exists('Company')) {
    class Company {
        public static function getName() {
            return $_SESSION['company_name'] ?? 'IQA Metal';
        }
        public static function getSystemName() {
            return 'IQA Metal Warehouse Systems';
        }
        public static function getTagline() {
            return 'Intelligent inventory management & rapid label logistics.';
        }
        public static function getUrl() {
            return 'https://iqametal.com';
        }
        public static function getCurrency() {
            return '$';
        }
    }
}

// 6. UI Component Library: Use Parent if present, otherwise Standalone Fallback
$core_ui_file = $parent_core_dir . '/UI.php';
if (file_exists($core_ui_file)) {
    require_once $core_ui_file;
} elseif (!class_exists('UI')) {
    class UI {
        public static function csrf_field() {
            $token = Security::getToken();
            return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
        }

        public static function stat_card($title, $value, $class = '', $id = '', $icon = '📦', $subtext = '') {
            $id_attr = $id ? 'id="' . htmlspecialchars($id) . '"' : '';
            $title_esc = htmlspecialchars($title);
            $value_esc = htmlspecialchars($value);
            $sub_html = $subtext ? '<div class="stat-subtext">' . htmlspecialchars($subtext) . '</div>' : '';
            return "
            <div {$id_attr} class='panel stat-card " . htmlspecialchars($class) . "'>
                <div class='stat-icon-wrapper'>{$icon}</div>
                <div class='stat-content'>
                    <span class='stat-title'>{$title_esc}</span>
                    <div class='stat-value'>{$value_esc}</div>
                    {$sub_html}
                </div>
            </div>";
        }

        public static function badge($text, $type = 'default') {
            $type_class = 'badge-' . strtolower(str_replace(' ', '-', $type));
            return "<span class='badge " . htmlspecialchars($type_class) . "'>" . htmlspecialchars($text) . "</span>";
        }

        public static function button($text, $link = '#', $icon = '', $class = 'btn-primary') {
            $icon_html = $icon ? "<span class='btn-icon'>" . $icon . "</span>" : "";
            return "
            <a href='" . htmlspecialchars($link) . "' class='btn " . htmlspecialchars($class) . "'>
                {$icon_html}
                <span class='btn-text'>" . htmlspecialchars($text) . "</span>
            </a>";
        }

        public static function theme_toggle() {
            return '<button type="button" class="theme-toggle-btn" id="themeToggle" aria-label="Toggle Theme" title="Toggle Light/Dark Theme">
                <span class="theme-icon light-icon">☀️</span>
                <span class="theme-icon dark-icon">🌙</span>
            </button>';
        }
    }
}
