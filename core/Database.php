<?php
/**
 * IQA Metal Warehouse Systems - Centralized Database Manager
 * Unified PDO connection pool, schema management, and cross-database query engine.
 * Stores all SQLite databases safely outside the HTTP web root in data/db/.
 */

class Database
{
    private static $instances = [];
    private static $db_dir = null;

    /**
     * Resolves the database directory, prioritizing the secure data/db directory
     * located outside the HTTP web scope.
     */
    public static function getDbDir()
    {
        if (self::$db_dir !== null) {
            return self::$db_dir;
        }

        // 1. Explicit Environment Variable
        if (getenv('WH_DATA_DIR') && is_dir(getenv('WH_DATA_DIR'))) {
            return self::$db_dir = rtrim(getenv('WH_DATA_DIR'), '/\\');
        }

        // 2. Candidate paths outside HTTP scope
        $candidates = [
            // Local PC: wh.latinospc/serverWarehouse/core -> wh.latinospc/data/db
            dirname(__DIR__, 2) . '/data/db',

            // cPanel Standard: /home4/latinspc/wh.latinospc.com/serverWarehouse/core -> /home4/latinspc/data/db
            dirname(__DIR__, 3) . '/data/db',

            // Direct cPanel absolute server path
            '/home4/latinspc/data/db',

            // Relative sibling fallback
            dirname(__DIR__, 1) . '/db',
        ];

        foreach ($candidates as $cand) {
            if (is_dir($cand)) {
                return self::$db_dir = realpath($cand) ?: $cand;
            }
        }

        // Default to outside HTTP directory and create if needed
        $target = dirname(__DIR__, 2) . '/data/db';
        if (!is_dir($target)) {
            @mkdir($target, 0755, true);
        }
        return self::$db_dir = $target;
    }

    /**
     * Get a PDO connection to a specific database file.
     *
     * @param string $db_name Name of the database (e.g., 'customers', 'orders', 'warehouse', 'users', 'tech')
     * @return PDO
     */
    public static function getConnection($db_name)
    {
        if (!isset(self::$instances[$db_name])) {
            $dir = self::getDbDir();
            $db_path = $dir . '/' . $db_name . '.db';

            // Ensure directory exists
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            // Ensure .htaccess exists to prevent database downloads if directory is ever web-accessible
            $htaccess_path = $dir . '/.htaccess';
            if (!file_exists($htaccess_path)) {
                @file_put_contents($htaccess_path, "# Prevent direct download of SQLite database files\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n");
            }

            try {
                $conn = new PDO("sqlite:" . $db_path);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Concurrency & integrity optimizations
                $conn->exec("PRAGMA journal_mode = WAL;");
                $conn->exec("PRAGMA busy_timeout = 5000;");
                $conn->exec("PRAGMA synchronous = NORMAL;");
                $conn->exec("PRAGMA foreign_keys = ON;");
                $conn->exec("PRAGMA cache_size = -64000;");
                $conn->exec("PRAGMA temp_store = MEMORY;");

                // Self-Healing Schema Integration for Orders/Warehouse/Customers/Users/Calendar
                $schema_file = __DIR__ . '/../orders/core/Schema.php';
                if (file_exists($schema_file)) {
                    require_once $schema_file;
                    if (class_exists('Schema')) {
                        Schema::ensure($conn, $db_name);
                    }
                }

                // Initialize Schema for Tech module if connecting to tech.db
                if ($db_name === 'tech') {
                    self::initTechSchema($conn);
                }

                self::$instances[$db_name] = $conn;
            } catch (PDOException $e) {
                die("Database Connection Error (" . $db_name . "): " . $e->getMessage());
            }
        }
        return self::$instances[$db_name];
    }

    // Convenience connection getters
    public static function customers()
    {
        return self::getConnection('customers');
    }
    public static function orders()
    {
        return self::getConnection('orders');
    }
    public static function warehouse()
    {
        return self::getConnection('warehouse');
    }
    public static function users()
    {
        return self::getConnection('users');
    }
    public static function calendar()
    {
        return self::getConnection('calendar');
    }
    public static function tech()
    {
        return self::getConnection('tech');
    }
    public static function marketing()
    {
        return self::getConnection('marketing');
    }

    /**
     * Attaches another database to the current connection for cross-database joins.
     *
     * @param PDO $conn The primary connection
     * @param string $db_to_attach The name of the DB to attach (e.g., 'customers')
     * @param string $alias The alias to use for the attached DB (e.g., 'cust')
     */
    public static function attach(PDO $conn, $db_to_attach, $alias)
    {
        $db_path = self::getDbDir() . '/' . $db_to_attach . '.db';
        $conn->exec("ATTACH DATABASE '{$db_path}' AS {$alias}");
    }

    /**
     * Executes a query on a primary database while automatically attaching
     * multiple supporting databases for cross-DB joins.
     *
     * @param string $primary_db The name of the primary DB (e.g., 'orders')
     * @param array $attachments Key-value pairs of [alias => db_name]
     * @param string $sql The SQL query to execute
     * @param array $params Optional positional parameters
     * @return PDOStatement
     */
    public static function queryIntegrated($primary_db, $attachments, $sql, $params = [])
    {
        $conn = self::getConnection($primary_db);
        foreach ($attachments as $alias => $name) {
            try {
                self::attach($conn, $name, $alias);
            } catch (Exception $e) {
            }
        }
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    private static $verified_schemas = [];

    /**
     * Schema Caching: Checks if a table/schema has been verified in this process or session.
     */
    public static function isSchemaVerified($db, $table)
    {
        if (isset(self::$verified_schemas[$db][$table]))
            return true;
        if (session_status() === PHP_SESSION_NONE)
            return false;
        return isset($_SESSION['verified_schemas'][$db][$table]);
    }

    /**
     * Schema Caching: Marks a table/schema as verified.
     */
    public static function markSchemaVerified($db, $table)
    {
        self::$verified_schemas[$db][$table] = true;
        if (session_status() === PHP_SESSION_NONE)
            return;
        $_SESSION['verified_schemas'][$db][$table] = true;
    }

    /**
     * Initializes schema and migration routines for the Tech module
     */
    public static function initTechSchema(PDO $conn)
    {
        if (self::isSchemaVerified('tech', 'all'))
            return;

        // Logs Table (Good and Bad logs distinguished by status)
        $conn->exec("CREATE TABLE IF NOT EXISTS logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tech_id TEXT NOT NULL,
            status TEXT NOT NULL, -- 'Good' or 'Bad'
            qty INTEGER DEFAULT 1,
            make TEXT,
            model TEXT,
            series TEXT,
            cpu TEXT,
            gpu TEXT,
            ram TEXT,
            storage TEXT,
            battery TEXT,
            bios_state TEXT,
            os TEXT,
            notes TEXT,
            ready_for_warehouse INTEGER DEFAULT 0,
            edited INTEGER DEFAULT 0,
            delete_requested INTEGER DEFAULT 0,
            status_change_requested TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Migration: add columns if older DB
        try {
            $cols = $conn->query("PRAGMA table_info(logs)")->fetchAll(PDO::FETCH_ASSOC);
            $col_names = array_column($cols, 'name');

            if (!in_array('edited', $col_names)) {
                $conn->exec("ALTER TABLE logs ADD COLUMN edited INTEGER DEFAULT 0");
            }
            if (!in_array('delete_requested', $col_names)) {
                $conn->exec("ALTER TABLE logs ADD COLUMN delete_requested INTEGER DEFAULT 0");
            }
            if (!in_array('status_change_requested', $col_names)) {
                $conn->exec("ALTER TABLE logs ADD COLUMN status_change_requested TEXT DEFAULT ''");
            }
            if (!in_array('os', $col_names)) {
                $conn->exec("ALTER TABLE logs ADD COLUMN os TEXT");
            }
        } catch (Exception $e) {
        }

        // daily_status_changes table to track Good-to-Bad limit of 5 per day per tech
        $conn->exec("CREATE TABLE IF NOT EXISTS daily_status_changes (
            tech_id TEXT NOT NULL,
            change_date DATE DEFAULT (date('now', 'localtime')),
            change_count INTEGER DEFAULT 0,
            PRIMARY KEY (tech_id, change_date)
        )");

        // Parts Inventory Table
        $conn->exec("CREATE TABLE IF NOT EXISTS parts_inventory (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            part_name TEXT NOT NULL,
            category TEXT,
            quantity INTEGER DEFAULT 0,
            low_stock_threshold INTEGER DEFAULT 5,
            notes TEXT,
            last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Seed default parts if table is empty
        try {
            $stmt = $conn->query("SELECT COUNT(*) FROM parts_inventory");
            if ($stmt && $stmt->fetchColumn() == 0) {
                $default_parts = [
                    ['8GB DDR4 RAM', 'Memory', 20, 5],
                    ['16GB DDR4 RAM', 'Memory', 20, 5],
                    ['256GB SSD', 'Storage', 15, 5],
                    ['512GB SSD', 'Storage', 10, 3],
                    ['Thermal Paste', 'Consumable', 5, 2]
                ];
                $stmt_ins = $conn->prepare("INSERT INTO parts_inventory (part_name, category, quantity, low_stock_threshold) VALUES (?, ?, ?, ?)");
                foreach ($default_parts as $part) {
                    $stmt_ins->execute($part);
                }
            }
        } catch (Exception $e) {
        }

        self::markSchemaVerified('tech', 'all');
    }
}
?>