<?php
// includes/functions.php
// A collection of global PHP helper functions to format data nicely before sending to the UI
// or before saving into the .sqlite file.
require_once __DIR__ . '/auth.php';

/**
 * Validates and converts an input into a strict integer. Returns `$default` if invalid.
 */
function sanitize_int($val, $default = 0) {
    if (isset($val) && is_numeric($val)) {
        return (int)$val;
    }
    return $default;
}

/**
 * Trims strings, prevents basic XSS, and drops empty strings to NULL for clean DB inserts.
 */
function sanitize_text($val) {
    if (!isset($val) || trim($val) === '') {
        return null; // Don't save empty strings; use SQLite NULL
    }
    // Convert special chars mostly for safety if viewing outside of our controlled JSON layer
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

/**
 * Returns a standardized JSON response format. Use this at the end of every file in /api/
 *
 * @param bool $success - Did the operation succeed?
 * @param array|null $data - The payload to send to JS logic.
 * @param string|null $error_msg - The reason for failure (if success is false)
 */
function send_json_response($success = true, $data = [], $error_msg = null) {
    header('Content-Type: application/json');
    $output = [
        'success' => $success
    ];

    if ($success) {
        $output['data'] = $data;
    } else {
        $output['error'] = $error_msg;
        // Optionally set a 400 status code if it's a solid failure
        http_response_code(400);
    }

    echo json_encode($output);
    exit;
}

/**
 * Formats a raw number into standard US currency layout: "$45.00"
 */
function format_currency($amount) {
    if (!is_numeric($amount)) return "$0.00";
    return "$" . number_format((float)$amount, 2, '.', ',');
}

/**
 * Convert raw timestamps to readable format for the tables
 */
function format_date($timestamp) {
    if (!$timestamp) return "—";
    return date("M j, Y", strtotime($timestamp));
}

/**
 * Ensures critical system folders exist and are writable.
 * Used during 'Deep Integrity Repair' or initialization.
 */
function ensure_system_folders() {
    $base = __DIR__ . '/../';
    $folders = ['exports', 'exports/labels', 'exports/orders', 'db/backups', 'fileBackups'];

    foreach ($folders as $f) {
        $path = $base . $f;
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    // Add a basic .htaccess to the exports folder to prevent directory browsing
    $htaccess = $base . 'exports/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Options -Indexes\n<Files \"*.php\">\n    Order allow,deny\n    Deny from all\n</Files>");
    }
}
