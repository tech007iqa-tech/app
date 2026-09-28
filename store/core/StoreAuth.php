<?php
// core/StoreAuth.php
/**
 * IQA Metal Store - Central Authentication & User Account Service
 * Supports 3 distinct roles:
 *  1. Guest: Unregistered / anonymous visitor browsing and acquiring hardware.
 *  2. Customer: Registered shopper with order history, address book, and profile.
 *  3. Tender: Privileged staff operator / admin with warehouse publishing and catalog management rights.
 *
 * Isolated store database: data/db/store_users.db
 */

class StoreAuth {
    private static ?PDO $db = null;

    /**
     * Start session safely if not already active
     */
    public static function initSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Resolve database path and return PDO connection for store_users.db
     */
    public static function getDb(): PDO {
        if (self::$db !== null) {
            return self::$db;
        }

        $candidates = [];

        // Check if centralized Database manager is available
        $databaseCandidates = [
            __DIR__ . '/../../core/Database.php',
            dirname(__DIR__, 2) . '/core/Database.php',
            __DIR__ . '/../../serverWarehouse/core/Database.php',
            dirname(__DIR__, 3) . '/serverWarehouse/core/Database.php',
            dirname(__DIR__, 3) . '/core/Database.php',
        ];
        foreach ($databaseCandidates as $dcand) {
            if (file_exists($dcand)) {
                require_once $dcand;
                break;
            }
        }

        if (class_exists('Database') && method_exists('Database', 'getDbDir')) {
            $candidates[] = Database::getDbDir() . '/store_users.db';
        }

        $candidates[] = dirname(__DIR__, 3) . '/data/db/store_users.db';
        $candidates[] = dirname(__DIR__, 2) . '/data/db/store_users.db';
        $candidates[] = __DIR__ . '/../../data/db/store_users.db';
        $candidates[] = __DIR__ . '/../data/store_users.db';

        $dbPath = null;
        foreach ($candidates as $cand) {
            $dir = dirname($cand);
            if (is_dir($dir)) {
                $dbPath = $cand;
                break;
            }
        }

        if (!$dbPath) {
            $targetDir = (class_exists('Database') && method_exists('Database', 'getDbDir'))
                ? Database::getDbDir()
                : (is_dir(dirname(__DIR__, 3) . '/data/db') ? dirname(__DIR__, 3) . '/data/db' : __DIR__ . '/../../data/db');
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }
            $dbPath = $targetDir . '/store_users.db';
        }

        try {
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("PRAGMA journal_mode = WAL;");
            $pdo->exec("PRAGMA busy_timeout = 5000;");
            $pdo->exec("PRAGMA foreign_keys = ON;");
            self::$db = $pdo;
            self::ensureSchema();
            return self::$db;
        } catch (PDOException $e) {
            die("Store users database connection failed: " . htmlspecialchars($e->getMessage()));
        }
    }

    /**
     * Self-healing schema check & default account seeding
     */
    private static function ensureSchema(): void {
        $db = self::$db;
        if (!$db) return;

        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE,
                password TEXT NOT NULL,
                display_name TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'Customer', -- 'Tender', 'Admin', 'Customer'
                phone TEXT DEFAULT '',
                address_line1 TEXT DEFAULT '',
                address_line2 TEXT DEFAULT '',
                city TEXT DEFAULT '',
                state TEXT DEFAULT '',
                zip TEXT DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address TEXT NOT NULL,
                username TEXT NOT NULL,
                attempt_count INTEGER DEFAULT 1,
                last_attempt_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Seed default accounts if database is empty
        $count = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($count === 0) {
            // 1. Default Tender Account
            $stmt = $db->prepare("
                INSERT INTO users (username, email, password, display_name, role) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                'tender',
                'tender@iqametal.com',
                password_hash('iqametal123', PASSWORD_DEFAULT),
                'Store Tender',
                'Tender'
            ]);

            // 2. Default Store Admin Account
            $stmt->execute([
                'admin',
                'admin@iqametal.com',
                password_hash('admin123', PASSWORD_DEFAULT),
                'Store Administrator',
                'Admin'
            ]);

            // 3. Default Demo Customer Account
            $stmt->execute([
                'customer',
                'customer@iqametal.com',
                password_hash('customer123', PASSWORD_DEFAULT),
                'Valued Customer',
                'Customer'
            ]);
        }
    }

    /**
     * Authenticate user with rate limiting
     *
     * @param string $usernameOrEmail
     * @param string $password
     * @param string|null $requiredRole Optional restriction: 'Tender' or 'Customer'
     * @return array [success => bool, error => string|null, user => array|null]
     */
    public static function login(string $usernameOrEmail, string $password, ?string $requiredRole = null): array {
        self::initSession();
        $db = self::getDb();

        $identifier = trim($usernameOrEmail);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if (empty($identifier) || empty($password)) {
            return ['success' => false, 'error' => 'Please provide both username/email and password.'];
        }

        // 1. Rate Limiting Check: max 6 failed attempts within 10 minutes
        $stmtAttempts = $db->prepare("
            SELECT attempt_count, last_attempt_at 
            FROM login_attempts 
            WHERE (ip_address = ? OR username = ?) 
            ORDER BY last_attempt_at DESC LIMIT 1
        ");
        $stmtAttempts->execute([$ip, $identifier]);
        $attempt = $stmtAttempts->fetch(PDO::FETCH_ASSOC);

        if ($attempt && (int)$attempt['attempt_count'] >= 6) {
            $elapsed = time() - strtotime($attempt['last_attempt_at'] . ' UTC');
            if ($elapsed < 600) {
                $wait = 600 - $elapsed;
                return [
                    'success' => false, 
                    'error' => "Too many failed attempts. Please wait {$wait} seconds before trying again."
                ];
            }
        }

        // 2. Find user by username OR email
        $stmt = $db->prepare("
            SELECT * FROM users 
            WHERE LOWER(username) = LOWER(?) OR (LOWER(email) = LOWER(?) AND email != '') 
            LIMIT 1
        ");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 3. Verify password
        if (!$user || !password_verify($password, $user['password'])) {
            // Log failed attempt
            $stmtLog = $db->prepare("
                INSERT INTO login_attempts (ip_address, username, attempt_count, last_attempt_at) 
                VALUES (?, ?, 1, CURRENT_TIMESTAMP)
            ");
            $stmtLog->execute([$ip, $identifier]);

            return ['success' => false, 'error' => 'Invalid credentials. Please verify your login details and try again.'];
        }

        // 4. Role restriction check
        if ($requiredRole === 'Tender' && !in_array($user['role'], ['Tender', 'Admin'])) {
            return [
                'success' => false, 
                'error' => 'Access Denied: This portal requires Store Tender privileges. Shoppers please use the customer login.'
            ];
        }

        // 5. Successful login: Clear failed attempts
        $stmtClear = $db->prepare("DELETE FROM login_attempts WHERE ip_address = ? OR username = ?");
        $stmtClear->execute([$ip, $identifier]);

        // 6. Regenerate session to prevent fixation
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['display_name'] = $user['display_name'] ?: $user['username'];
        $_SESSION['email'] = $user['email'] ?? '';
        $_SESSION['role'] = $user['role'];

        if (in_array($user['role'], ['Tender', 'Admin'])) {
            // Enable tender mode & backwards-compatible flag
            $_SESSION['authenticated'] = true;
            unset($_SESSION['live_preview']);
        } else {
            // Customer: Ensure tender privileges are explicitly unset
            unset($_SESSION['authenticated']);
            unset($_SESSION['live_preview']);
        }

        return ['success' => true, 'user' => $user];
    }

    /**
     * Register a new Customer account
     *
     * @param array $data Form fields [username, email, password, display_name, phone, address...]
     * @return array [success => bool, error => string|null, user => array|null]
     */
    public static function register(array $data): array {
        self::initSession();
        $db = self::getDb();

        $username = trim($data['username'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $displayName = trim($data['display_name'] ?? '');
        $phone = trim($data['phone'] ?? '');

        // Validation
        if (strlen($username) < 3 || strlen($username) > 30 || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $username)) {
            return ['success' => false, 'error' => 'Username must be 3-30 characters long and contain only letters, numbers, hyphens, and underscores.'];
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Please provide a valid email address.'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'error' => 'Password must be at least 6 characters long.'];
        }

        if (empty($displayName)) {
            $displayName = $username;
        }

        // Check uniqueness
        $stmtCheck = $db->prepare("SELECT id, username, email FROM users WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?) LIMIT 1");
        $stmtCheck->execute([$username, $email]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if (strtolower($existing['username']) === strtolower($username)) {
                return ['success' => false, 'error' => 'That username is already taken. Please choose another.'];
            }
            if (!empty($existing['email']) && strtolower($existing['email']) === strtolower($email)) {
                return ['success' => false, 'error' => 'An account with that email address already exists.'];
            }
        }

        // Insert new customer
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password, display_name, role, phone, address_line1, address_line2, city, state, zip) 
            VALUES (?, ?, ?, ?, 'Customer', ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $username,
            $email,
            $hashed,
            $displayName,
            $phone,
            trim($data['address_line1'] ?? ''),
            trim($data['address_line2'] ?? ''),
            trim($data['city'] ?? ''),
            trim($data['state'] ?? ''),
            trim($data['zip'] ?? '')
        ]);

        $newId = (int)$db->lastInsertId();

        // Auto-login newly registered customer
        session_regenerate_id(true);
        $_SESSION['user_id'] = $newId;
        $_SESSION['username'] = $username;
        $_SESSION['display_name'] = $displayName;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = 'Customer';
        unset($_SESSION['authenticated']);

        return [
            'success' => true, 
            'user' => [
                'id' => $newId,
                'username' => $username,
                'email' => $email,
                'display_name' => $displayName,
                'role' => 'Customer'
            ]
        ];
    }

    /**
     * Get current logged-in user profile
     */
    public static function current(): ?array {
        self::initSession();
        if (empty($_SESSION['username'])) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'],
            'display_name' => $_SESSION['display_name'] ?? $_SESSION['username'],
            'email' => $_SESSION['email'] ?? '',
            'role' => $_SESSION['role'] ?? (empty($_SESSION['authenticated']) ? 'Customer' : 'Tender')
        ];
    }

    /**
     * Fetch complete user record from database
     */
    public static function getCurrentUserDetails(): ?array {
        self::initSession();
        $curr = self::current();
        if (!$curr) return null;

        $db = self::getDb();
        if (!empty($curr['id'])) {
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$curr['id']]);
        } else {
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$curr['username']]);
        }
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Check if visitor is an unauthenticated Guest
     */
    public static function isGuest(): bool {
        self::initSession();
        return empty($_SESSION['username']);
    }

    /**
     * Check if visitor is a logged-in Customer
     */
    public static function isCustomer(): bool {
        self::initSession();
        return !empty($_SESSION['username']) && (($_SESSION['role'] ?? '') === 'Customer');
    }

    /**
     * Check if visitor is an active Store Tender
     */
    public static function isTender(): bool {
        self::initSession();
        return !empty($_SESSION['authenticated']) && in_array(($_SESSION['role'] ?? ''), ['Tender', 'Admin', 'Operator']);
    }

    /**
     * Update customer profile details
     */
    public static function updateProfile(int $id, array $data): bool {
        $db = self::getDb();
        $stmt = $db->prepare("
            UPDATE users 
            SET display_name = ?, phone = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, zip = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $res = $stmt->execute([
            trim($data['display_name'] ?? ''),
            trim($data['phone'] ?? ''),
            trim($data['address_line1'] ?? ''),
            trim($data['address_line2'] ?? ''),
            trim($data['city'] ?? ''),
            trim($data['state'] ?? ''),
            trim($data['zip'] ?? ''),
            $id
        ]);

        if ($res && isset($_SESSION['user_id']) && $_SESSION['user_id'] === $id) {
            $_SESSION['display_name'] = trim($data['display_name'] ?? $_SESSION['username']);
        }

        return $res;
    }

    /**
     * Change user password
     */
    public static function changePassword(int $id, string $oldPassword, string $newPassword): array {
        $db = self::getDb();
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $currentHash = $stmt->fetchColumn();

        if (!$currentHash || !password_verify($oldPassword, $currentHash)) {
            return ['success' => false, 'error' => 'Current password is incorrect.'];
        }

        if (strlen($newPassword) < 6) {
            return ['success' => false, 'error' => 'New password must be at least 6 characters long.'];
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $db->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $update->execute([$newHash, $id]);

        return ['success' => true];
    }

    /**
     * Universal log out
     */
    public static function logout(): void {
        self::initSession();
        unset($_SESSION['user_id']);
        unset($_SESSION['username']);
        unset($_SESSION['display_name']);
        unset($_SESSION['email']);
        unset($_SESSION['role']);
        unset($_SESSION['authenticated']);
        unset($_SESSION['live_preview']);
        session_destroy();
    }
}
