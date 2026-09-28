<?php
// core/Tender.php
/**
 * IQA Metal Store - Store Tender (Privileged Operator) Manager
 *
 * Clean separation of roles:
 * 1. The Tender: Logged-in warehouse/store operator with inventory, pricing & publishing privileges.
 * 2. The User: Public shopper browsing the catalog, adding items to cart, and acquiring hardware.
 */

class Tender {
    /**
     * Start session safely if not already active
     */
    private static function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Check if the current visitor is an authenticated Tender (Staff / Operator / Admin)
     */
    public static function isLoggedIn() {
        self::initSession();
        return !empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }

    /**
     * Check if Tender Mode is active (logged in AND not currently in Customer Preview)
     */
    public static function isTenderMode() {
        return self::isLoggedIn() && !self::isCustomerPreview();
    }

    /**
     * Check if the Tender is viewing the store in "Customer Preview" mode
     */
    public static function isCustomerPreview() {
        self::initSession();
        return !empty($_SESSION['live_preview']) && $_SESSION['live_preview'] === true;
    }

    /**
     * Toggle Customer Preview mode on or off
     */
    public static function setCustomerPreview($enable) {
        self::initSession();
        $_SESSION['live_preview'] = (bool)$enable;
    }

    /**
     * Get tender profile info [username, display_name, role]
     */
    public static function current() {
        self::initSession();
        if (!self::isLoggedIn()) {
            return null;
        }
        return [
            'username' => $_SESSION['username'] ?? 'operator',
            'display_name' => !empty($_SESSION['display_name']) ? $_SESSION['display_name'] : ($_SESSION['username'] ?? 'Store Tender'),
            'role' => $_SESSION['role'] ?? 'Tender'
        ];
    }

    /**
     * Check if tender has admin-level privileges
     */
    public static function isAdmin() {
        $tender = self::current();
        if (!$tender) return false;
        return strtolower($tender['role']) === 'admin';
    }

    /**
     * URL to log in as a Tender
     */
    public static function loginUrl($returnUrl = 'index.php') {
        return 'tender_login.php?return_url=' . urlencode($returnUrl);
    }

    /**
     * URL to log out of Tender privileges
     */
    public static function logoutUrl() {
        return 'logout.php?role=tender';
    }
}
