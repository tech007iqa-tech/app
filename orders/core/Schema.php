<?php
/**
 * IQA System Schema Registry
 * Centralized blueprint for all system databases.
 * Maintains the "Self-Healing" nature of the application.
 */

class Schema {
    /**
     * Blueprints for all system databases.
     */
    private static $blueprints = [
        'customers' => [
            'customers' => "CREATE TABLE IF NOT EXISTS customers (
                customer_id TEXT PRIMARY KEY,
                company_name TEXT NOT NULL,
                contact_person TEXT,
                website TEXT,
                email TEXT,
                phone TEXT,
                address TEXT,
                shipping_address TEXT,
                internal_notes TEXT,
                callback_date TEXT DEFAULT '',
                message_date TEXT DEFAULT '',
                account_status TEXT DEFAULT 'Customer',
                lead_source TEXT DEFAULT 'Manual',
                interest TEXT DEFAULT '',
                contact_method TEXT DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ],
        'orders' => [
            'orders' => "CREATE TABLE IF NOT EXISTS orders (
                order_id TEXT PRIMARY KEY,
                customer_id TEXT,
                status TEXT DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            'items' => "CREATE TABLE IF NOT EXISTS items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id TEXT NOT NULL DEFAULT 'ORD-DEFAULT',
                customer_id TEXT NOT NULL,
                brand TEXT NOT NULL,
                model TEXT NOT NULL,
                series TEXT NOT NULL,
                cpu TEXT DEFAULT '',
                ram TEXT DEFAULT '',
                storage TEXT DEFAULT '',
                battery TEXT DEFAULT '',
                description TEXT NOT NULL,
                notes TEXT DEFAULT '',
                quantity INTEGER NOT NULL,
                unit_price REAL DEFAULT 0.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ],
        'warehouse' => [
            'sectors' => "CREATE TABLE IF NOT EXISTS sectors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                description TEXT,
                icon TEXT,
                color_theme TEXT
            )",
            'inventory' => "CREATE TABLE IF NOT EXISTS inventory (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_owner TEXT NOT NULL,
                sector TEXT NOT NULL,
                location_code TEXT DEFAULT 'ZONE-0',
                brand TEXT NOT NULL,
                model TEXT NOT NULL,
                specs_json TEXT,
                quantity INTEGER DEFAULT 0,
                status TEXT DEFAULT '',
                last_updated_by TEXT,
                price REAL DEFAULT 0.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            'locations' => "CREATE TABLE IF NOT EXISTS locations (
                location_code TEXT PRIMARY KEY,
                status TEXT DEFAULT 'Idle',
                working_zone_name TEXT DEFAULT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            'location_statuses' => "CREATE TABLE IF NOT EXISTS location_statuses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                color TEXT NOT NULL,
                is_default INTEGER DEFAULT 1,
                location_code TEXT DEFAULT NULL
            )",
            'working_zones' => "CREATE TABLE IF NOT EXISTS working_zones (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            'pricing_rules' => "CREATE TABLE IF NOT EXISTS pricing_rules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category TEXT NOT NULL,
                cpu_gen TEXT NOT NULL,
                grade TEXT NOT NULL,
                price REAL DEFAULT 0.00,
                UNIQUE(category, cpu_gen, grade)
            )",
            'location_photos' => "CREATE TABLE IF NOT EXISTS location_photos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                location_code TEXT NOT NULL,
                original_filename TEXT NOT NULL,
                archive_driver TEXT NOT NULL,
                archive_path TEXT NOT NULL,
                optimized_path TEXT NOT NULL,
                thumbnail_path TEXT NOT NULL,
                uploaded_by TEXT NOT NULL,
                category TEXT DEFAULT 'General',
                sector TEXT NOT NULL DEFAULT 'Laptops',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (location_code) REFERENCES locations(location_code) ON DELETE CASCADE
            )",
            'settings' => "CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT
            )",
            'tested_market_categories' => "CREATE TABLE IF NOT EXISTS tested_market_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                display_order INTEGER DEFAULT 0,
                layout_type TEXT DEFAULT 'laptop',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            'tested_market_rules' => "CREATE TABLE IF NOT EXISTS tested_market_rules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL,
                brand_series TEXT DEFAULT '',
                model_number TEXT DEFAULT '',
                is_2in1 INTEGER DEFAULT 0,
                cpu TEXT DEFAULT '',
                price REAL DEFAULT 0.00,
                sale_through REAL DEFAULT 0.00,
                sold_count INTEGER DEFAULT 0,
                effective_date TEXT DEFAULT '',
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES tested_market_categories(id) ON DELETE CASCADE
            )",
            'sold_items' => "CREATE TABLE IF NOT EXISTS sold_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                location_code TEXT,
                sector TEXT NOT NULL DEFAULT 'Laptops',
                brand TEXT NOT NULL,
                model TEXT NOT NULL,
                specs_json TEXT,
                quantity INTEGER DEFAULT 1,
                sold_price REAL DEFAULT 0.00,
                sold_by TEXT,
                reason TEXT DEFAULT 'Reconciliation Sale',
                sold_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ],
        'users' => [
            'users' => "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE,
                password TEXT,
                role TEXT,
                display_name TEXT DEFAULT '',
                ppp_sequence_key TEXT DEFAULT '',
                ppp_row_index INTEGER DEFAULT 0,
                ppp_password_len INTEGER DEFAULT 55
            )",
            'audit_log' => "CREATE TABLE IF NOT EXISTS audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                user_id TEXT,
                user_name TEXT,
                module TEXT,
                action TEXT,
                target_id TEXT,
                details TEXT,
                ip_address TEXT
            )",
            'login_attempts' => "CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address TEXT NOT NULL,
                device_id TEXT NOT NULL,
                username TEXT NOT NULL,
                attempt_count INTEGER DEFAULT 0,
                last_attempt_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ],
        'calendar' => [
            'events' => "CREATE TABLE IF NOT EXISTS events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT,
                event_date DATE NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                color TEXT DEFAULT '#38bdf8',
                customer_id TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        ]
    ];

    /**
     * Ensures all tables for a specific database exist and are up to date.
     * Migrations always run (they are idempotent), bypassing the session cache
     * so a stale session never silently skips a column addition.
     *
     * @param PDO $conn The connection to the database
     * @param string $db_name The name of the database (e.g., 'orders')
     */
    private static $memory_verified = [];

    /**
     * Ensures all tables for a specific database exist and are up to date.
     * Caches verification in memory per-process/request to avoid repetitive PRAGMA queries.
     *
     * @param PDO $conn The connection to the database
     * @param string $db_name The name of the database (e.g., 'orders')
     */
    public static function ensure($conn, $db_name) {
        if (!isset(self::$blueprints[$db_name])) return;

        foreach (self::$blueprints[$db_name] as $table => $sql) {
            // 1. Fast path: already verified in memory during this request/process
            if (isset(self::$memory_verified[$db_name][$table])) {
                continue;
            }

            // 2. Session verification check
            if (Database::isSchemaVerified($db_name, $table)) {
                self::$memory_verified[$db_name][$table] = true;
                continue;
            }

            // Always CREATE TABLE IF NOT EXISTS (safe no-op when table exists)
            $conn->exec($sql);

            // Always run migrations — idempotent PRAGMA checks mean no harm.
            self::runMigrations($conn, $db_name, $table);

            // --- Initial Data Seeding (once per session) ---
            self::seed($conn, $db_name, $table);
            Database::markSchemaVerified($db_name, $table);
            self::$memory_verified[$db_name][$table] = true;
        }
    }

    /**
     * Forces a full schema repair across every database.
     * Called by the Settings integrity button. Clears the session schema cache
     * so every table is re-checked on the next request.
     *
     * @return array  ['fixed' => [...], 'errors' => [...]]
     */
    public static function repairAll() {
        // Wipe session cache so ensure() re-inspects every table
        if (session_status() !== PHP_SESSION_NONE) {
            unset($_SESSION['verified_schemas']);
        }

        $report = ['fixed' => [], 'errors' => []];
        $db_names = array_keys(self::$blueprints);

        foreach ($db_names as $db_name) {
            try {
                $conn = Database::getConnection($db_name);
                foreach (self::$blueprints[$db_name] as $table => $sql) {
                    try {
                        $conn->exec($sql);
                        self::runMigrations($conn, $db_name, $table);
                        $report['fixed'][] = "{$db_name}.{$table}";
                    } catch (Exception $e) {
                        $report['errors'][] = "{$db_name}.{$table}: " . $e->getMessage();
                    }
                }
            } catch (Exception $e) {
                $report['errors'][] = "{$db_name}: " . $e->getMessage();
            }
        }
        return $report;
    }

    /**
     * Seeds initial data into empty tables.
     */
    private static function seed($conn, $db_name, $table) {
        if ($db_name === 'warehouse' && $table === 'sectors') {
            $count = $conn->query("SELECT COUNT(*) FROM sectors")->fetchColumn();
            if ($count == 0) {
                $sectors = [
                    ['Laptops', 'Standard portable computing hardware', '💻', '#3b82f6'],
                    ['Gaming', 'High-performance GPUs and gaming rigs', '🎮', '#8b5cf6'],
                    ['Desktops', 'Workstations and office towers', '🖥️', '#6366f1'],
                    ['Electronics', 'Consumer electronics and peripherals', '🔌', '#f59e0b']
                ];
                $stmt = $conn->prepare("INSERT INTO sectors (name, description, icon, color_theme) VALUES (?, ?, ?, ?)");
                foreach ($sectors as $s) $stmt->execute($s);
            }
        }
        if ($db_name === 'warehouse' && $table === 'settings') {
            $count = $conn->query("SELECT COUNT(*) FROM settings")->fetchColumn();
            if ($count == 0) {
                $stmt = $conn->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
                $stmt->execute(['archive_photos_path', dirname(__DIR__) . '/assets/location_photos/archive/']);
            }
        }
        if ($db_name === 'warehouse' && $table === 'location_statuses') {
            $count = $conn->query("SELECT COUNT(*) FROM location_statuses")->fetchColumn();
            if ($count == 0) {
                $statuses = [
                    ['Working', '#10b981'],
                    ['Audit', '#f59e0b'],
                    ['Shipping', '#3b82f6'],
                    ['In-Review', '#8b5cf6'],
                    ['Warehoused', '#6366f1'],
                    ['Idle', '#64748b']
                ];
                $stmt = $conn->prepare("INSERT INTO location_statuses (name, color, is_default, location_code) VALUES (?, ?, 1, NULL)");
                foreach ($statuses as $s) {
                    $stmt->execute($s);
                }
            }
        }
        if ($db_name === 'warehouse' && $table === 'working_zones') {
            $count = $conn->query("SELECT COUNT(*) FROM working_zones")->fetchColumn();
            if ($count == 0) {
                // Populate default working zones based on existing prefixes or common tags
                $default_zones = ['Zone A', 'Zone B', 'Inbound', 'General'];
                $stmt = $conn->prepare("INSERT OR IGNORE INTO working_zones (name) VALUES (?)");
                foreach ($default_zones as $z) {
                    $stmt->execute([$z]);
                }

                // Associate existing locations with zones
                $locs = $conn->query("SELECT location_code FROM locations")->fetchAll(PDO::FETCH_ASSOC);
                $stmt_up = $conn->prepare("UPDATE locations SET working_zone_name = ? WHERE location_code = ?");
                foreach ($locs as $loc) {
                    $name = $loc['location_code'];
                    $zone = 'General';
                    if (preg_match('/^([a-zA-Z]+)([-‑]?\d+)?/u', $name, $matches)) {
                        $p_zone = 'Zone ' . strtoupper($matches[1]);
                        // Insert zone dynamically if not exists
                        $conn->prepare("INSERT OR IGNORE INTO working_zones (name) VALUES (?)")->execute([$p_zone]);
                        $zone = $p_zone;
                    } elseif (preg_match('/^([a-zA-Z0-9]+)/u', $name, $matches)) {
                        $p_zone = 'Zone ' . strtoupper($matches[1]);
                        $conn->prepare("INSERT OR IGNORE INTO working_zones (name) VALUES (?)")->execute([$p_zone]);
                        $zone = $p_zone;
                    }
                    if (strlen($name) > 10 || stripos($name, 'inbound') !== false || stripos($name, 'desktop') !== false) {
                        $zone = 'General';
                    }
                    $stmt_up->execute([$zone, $name]);
                }
            }
        }
        if ($db_name === 'warehouse' && $table === 'pricing_rules') {
            $count = $conn->query("SELECT COUNT(*) FROM pricing_rules")->fetchColumn();
            if ($count == 0) {
                // Seed Regular laptops cpu/gen pricing
                $regular_prices = [
                    ['4th-5th', 35.00, 30.00, 30.00],
                    ['6th-7th', 55.00, 45.00, 45.00],
                    ['i5-8th', 60.00, 50.00, 55.00],
                    ['i7-8th', 65.00, 60.00, 0.00],
                    ['i5-9th', 85.00, 75.00, 78.00],
                    ['i7-9th', 90.00, 80.00, 0.00],
                    ['i5-10th', 95.00, 85.00, 88.00],
                    ['i7-10th', 100.00, 90.00, 0.00],
                    ['i5-11th', 100.00, 90.00, 95.00],
                    ['i7-11th', 110.00, 100.00, 0.00],
                    ['i5-12th', 115.00, 105.00, 108.00],
                    ['i7-12th', 120.00, 110.00, 0.00]
                ];

                $stmt = $conn->prepare("INSERT OR IGNORE INTO pricing_rules (category, cpu_gen, grade, price) VALUES (?, ?, ?, ?)");

                foreach ($regular_prices as $rp) {
                    $cpu_gen = $rp[0];
                    $stmt->execute(['Regular', $cpu_gen, 'Untested', $rp[1]]);
                    $stmt->execute(['Regular', $cpu_gen, 'Parts', $rp[2]]);
                    $stmt->execute(['Regular', $cpu_gen, 'C Grade', $rp[3]]);
                }

                // Seed empty templates for Gaming
                $other_categories = ['Gaming'];
                $grades = ['Untested', 'Parts', 'C Grade'];
                foreach ($other_categories as $cat) {
                    foreach ($grades as $g) {
                        $stmt->execute([$cat, 'Default', $g, 0.00]);
                    }
                }

                // Seed Chromebook pricing rules (grades: Untested Lot, Tested - Clean (A/B))
                $chromebook_prices = [
                    ['Dell Chromebook 3180 / HP G5 EE', 18.00, 30.00],
                    ['HP Chromebook 11 G6 EE', 18.00, 30.00],
                    ['HP Chromebook 11A G6 EE', 18.00, 30.00],
                    ['HP Chromebook 11 G7 EE', 18.00, 30.00],
                    ['Lenovo 100e / 300e 2nd Gen (MTK)', 18.00, 30.00],
                    ['Samsung Chromebook 4 (11")', 18.00, 30.00],
                    ['Dell 3100 / 3100 2-in-1', 27.00, 35.00],
                    ['HP Chromebook 11 G8 EE', 19.00, 30.00],
                    ['HP Chromebook 11A G8 EE', 19.00, 30.00],
                    ['HP x360 11 G3 EE (Convertible)', 27.00, 35.00],
                    ['Lenovo 100e / 300e 2nd Gen (Intel)', 19.00, 30.00],
                    ['Lenovo 500e 2nd Gen (Convertible)', 27.00, 35.00],
                    ['HP x360 11 G4 EE (Convertible)', 27.00, 35.00],
                    ['Dell Chromebook 3110 / 2-in-1', 27.00, 38.00],
                    ['HP Chromebook 11 G9 EE', 19.00, 35.00],
                    ['Lenovo 100e / 300e 3rd Gen', 19.00, 35.00],
                    ['HP Chromebook 11 G10 EE', 19.00, 45.00],
                    ['Dell Chromebook 3120', 19.00, 50.00]
                ];
                foreach ($chromebook_prices as $cp) {
                    $model_name = $cp[0];
                    $stmt->execute(['Chromebook', $model_name, 'Untested Lot', $cp[1]]);
                    $stmt->execute(['Chromebook', $model_name, 'Tested - Clean (A/B)', $cp[2]]);
                }

                // Seed Microsoft pricing rules
                $microsoft_prices = [
                    ['Surface Laptop 1 (1769)', 81.60, 51.00, 25.50],
                    ['Surface Laptop 2 (1769)', 76.50, 45.90, 30.60],
                    ['Surface Laptop 2 (1782)', 107.10, 66.30, 30.60],
                    ['Surface Laptop 3 (1867/1868)', 158.10, 96.90, 45.90],
                    ['Surface Laptop 4 (1950/1951)', 193.80, 117.30, 56.10],
                    ['Surface Laptop 5 (1950/1951)', 265.20, 163.20, 81.60],
                    ['Surface Laptop 6 (2033/2035)', 428.40, 265.20, 132.60],
                    ['Surface Laptop Go (1943)', 132.60, 81.60, 40.80],
                    ['Surface Book 1 (1703)', 71.40, 45.90, 20.40],
                    ['Surface Book 2 (1823)', 127.50, 81.60, 40.80],
                    ['Surface Book 2 (1834/1835)', 163.20, 102.00, 51.00],
                    ['Surface Book 3 (1899)', 209.10, 132.60, 66.30],
                    ['Surface Book 3 (1900)', 290.70, 178.50, 86.70],
                    ['15" Surface Book 3 (1899)', 346.80, 214.20, 102.00],
                    ['Surface Pro 1 (1514)', 40.80, 25.50, 15.30],
                    ['Surface Pro 2 (1601)', 51.00, 30.60, 15.30],
                    ['Surface Pro 3 (1631)', 51.00, 30.60, 15.30],
                    ['Surface Pro 4 (1724)', 66.30, 40.80, 20.40],
                    ['Surface Pro 5 (1796)', 76.50, 45.90, 20.40],
                    ['Surface Pro 5 (1807)', 76.50, 45.90, 20.40],
                    ['Surface Pro 6 (1796)', 112.20, 71.40, 35.70],
                    ['Surface Pro 7 (1866)', 178.50, 112.20, 56.10],
                    ['Surface Pro 7+ (1960)', 224.40, 137.70, 66.30],
                    ['Surface Pro 8 (1983)', 326.40, 204.00, 102.00],
                    ['Surface Pro 9 (2038)', 453.90, 280.50, 142.80],
                    ['Surface Pro 10 (2079)', 678.30, 423.30, 209.10],
                    ['Surface Pro 8 (Default)', 326.40, 204.00, 102.00],
                    ['Surface Pro 9 (Default)', 453.90, 280.50, 142.80],
                    ['Surface Pro 10 (Default)', 678.30, 423.30, 209.10]
                ];
                foreach ($microsoft_prices as $mp) {
                    $stmt->execute(['Microsoft', $mp[0], 'Tested', $mp[1]]);
                    $stmt->execute(['Microsoft', $mp[0], 'Untested', $mp[2]]);
                    $stmt->execute(['Microsoft', $mp[0], 'For Parts', $mp[3]]);
                }

                // Seed Apple pricing rules (grades: Tested, Untested, For Parts)
                $apple_prices = [
                    ['A1261', 0.00, 20.00, 0.00],
                    ['A1278', 0.00, 20.00, 16.00],
                    ['A1286', 0.00, 35.00, 16.00],
                    ['A1342', 0.00, 20.00, 0.00],
                    ['A1398', 60.00, 40.00, 16.00],
                    ['A1425', 0.00, 30.00, 0.00],
                    ['A1465', 0.00, 20.00, 16.00],
                    ['A1466', 45.00, 20.00, 16.00],
                    ['A1502', 60.00, 40.00, 16.00],
                    ['A1534', 0.00, 27.00, 16.00],
                    ['A1706', 0.00, 70.00, 50.00],
                    ['A1707', 0.00, 70.00, 45.00],
                    ['A1708', 0.00, 70.00, 45.00],
                    ['A1932', 0.00, 75.00, 40.00],
                    ['A2179', 0.00, 135.00, 0.00]
                ];
                foreach ($apple_prices as $ap) {
                    $model = $ap[0];
                    $stmt->execute(['Apple', $model, 'Tested', $ap[1]]);
                    $stmt->execute(['Apple', $model, 'Untested', $ap[2]]);
                    $stmt->execute(['Apple', $model, 'For Parts', $ap[3]]);
                }

                // Seed Rugged pricing rules (grades: Untested Complete, Untested Parts, Tested Complete, Tested No Battery)
                $rugged_prices = [
                    ['4th-5th', 50.00, 40.00, 85.00, 65.00],
                    ['6th-7th', 60.00, 55.00, 107.00, 75.00],
                    ['i5-8th', 80.00, 60.00, 117.00, 97.00],
                    ['i7-8th', 90.00, 95.00, 125.00, 105.00],
                    ['i5-9th', 95.00, 70.00, 0.00, 0.00],
                    ['i7-9th', 100.00, 73.00, 0.00, 0.00],
                    ['i5-10th', 105.00, 75.00, 0.00, 0.00],
                    ['i7-10th', 110.00, 80.00, 0.00, 0.00]
                ];
                foreach ($rugged_prices as $rp) {
                    $cpu_gen = $rp[0];
                    $stmt->execute(['Rugged', $cpu_gen, 'Untested Complete', $rp[1]]);
                    $stmt->execute(['Rugged', $cpu_gen, 'Untested Parts', $rp[2]]);
                    $stmt->execute(['Rugged', $cpu_gen, 'Tested Complete', $rp[3]]);
                    $stmt->execute(['Rugged', $cpu_gen, 'Tested No Battery', $rp[4]]);
                }

                // Seed pricing rules for RAM (DDR3 & DDR4 options from 2GB up to 32GB)
                $ram_prices = [
                    ['2GB DDR3', 0.00, 0.25, 0.00],
                    ['4GB DDR3', 0.25, 1.25, 0.10],
                    ['8GB DDR3', 2.00, 4.50, 0.15],
                    ['16GB DDR3', 6.00, 16.00, 0.20],
                    ['32GB DDR3', 0.00, 0.00, 0.00],
                    ['2GB DDR4', 0.00, 0.00, 0.00],
                    ['4GB DDR4', 0.50, 2.25, 0.10],
                    ['8GB DDR4', 3.50, 8.50, 0.15],
                    ['16GB DDR4', 9.50, 20.00, 0.25],
                    ['32GB DDR4', 22.00, 48.00, 0.40]
                ];
                foreach ($ram_prices as $rp) {
                    $spec = $rp[0];
                    $stmt->execute(['RAM', $spec, 'Untested', $rp[1]]);
                    $stmt->execute(['RAM', $spec, 'Tested', $rp[2]]);
                    $stmt->execute(['RAM', $spec, 'C Grade', $rp[3]]);
                }

                // Seed pricing rules for Storage (SSD M.2)
                $storage_prices = [
                    ['128GB M.2', 10.00, 20.00, 0.10],
                    ['256GB M.2', 16.00, 32.00, 0.15],
                    ['512GB M.2', 26.00, 55.00, 0.25],
                    ['1TB M.2', 50.00, 105.00, 0.40],
                    ['2TB M.2', 100.00, 215.00, 0.75]
                ];
                foreach ($storage_prices as $sp) {
                    $spec = $sp[0];
                    $stmt->execute(['Storage', $spec, 'Untested', $sp[1]]);
                    $stmt->execute(['Storage', $spec, 'Tested', $sp[2]]);
                    $stmt->execute(['Storage', $spec, 'C Grade', $sp[3]]);
                }
            }
        }
        if ($db_name === 'warehouse' && $table === 'tested_market_categories') {
            // Ensure child table tested_market_rules exists prior to seeding rules
            if (isset(self::$blueprints['warehouse']['tested_market_rules'])) {
                $conn->exec(self::$blueprints['warehouse']['tested_market_rules']);
            }
            $count = $conn->query("SELECT COUNT(*) FROM tested_market_categories")->fetchColumn();
            if ($count == 0) {
                $seed_file = __DIR__ . '/tested_market_seed.json';
                if (file_exists($seed_file)) {
                    $seed_data = json_decode(file_get_contents($seed_file), true);
                    if (is_array($seed_data)) {
                        $stmt_cat = $conn->prepare("INSERT INTO tested_market_categories (name, display_order, layout_type) VALUES (?, ?, ?)");
                        $stmt_rule = $conn->prepare("INSERT INTO tested_market_rules (category_id, brand_series, model_number, is_2in1, cpu, price, sale_through, sold_count, effective_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        foreach ($seed_data as $cat) {
                            $stmt_cat->execute([$cat['name'], $cat['display_order'], $cat['layout_type']]);
                            $cat_id = $conn->lastInsertId();
                            if (!empty($cat['rules']) && is_array($cat['rules'])) {
                                foreach ($cat['rules'] as $rule) {
                                    $stmt_rule->execute([
                                        $cat_id,
                                        $rule['brand_series'] ?? '',
                                        $rule['model_number'] ?? '',
                                        $rule['is_2in1'] ?? 0,
                                        $rule['cpu'] ?? '',
                                        $rule['price'] ?? 0.00,
                                        $rule['sale_through'] ?? 0.00,
                                        $rule['sold_count'] ?? 0,
                                        $rule['effective_date'] ?? ''
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Handles specific column additions and index creation for existing tables.
     */
    private static function runMigrations($conn, $db_name, $table) {
        // --- Customers Schema Evolution & Indexes ---
        // Handles DBs created from the old stale blueprint that lacked several columns.
        if ($db_name === 'customers' && $table === 'customers') {
            $cols = array_column(
                $conn->query("PRAGMA table_info(customers)")->fetchAll(PDO::FETCH_ASSOC),
                'name'
            );
            $migrations = [
                'website'          => "ALTER TABLE customers ADD COLUMN website TEXT DEFAULT ''",
                'contact_person'   => "ALTER TABLE customers ADD COLUMN contact_person TEXT DEFAULT ''",
                'shipping_address' => "ALTER TABLE customers ADD COLUMN shipping_address TEXT DEFAULT ''",
                'internal_notes'   => "ALTER TABLE customers ADD COLUMN internal_notes TEXT DEFAULT ''",
                'account_status'   => "ALTER TABLE customers ADD COLUMN account_status TEXT DEFAULT 'Customer'",
                'lead_source'      => "ALTER TABLE customers ADD COLUMN lead_source TEXT DEFAULT 'Manual'",
                'interest'         => "ALTER TABLE customers ADD COLUMN interest TEXT DEFAULT ''",
                'contact_method'   => "ALTER TABLE customers ADD COLUMN contact_method TEXT DEFAULT ''",
            ];
            foreach ($migrations as $col => $sql) {
                if (!in_array($col, $cols)) {
                    $conn->exec($sql);
                }
            }
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_customers_company ON customers(company_name ASC)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_customers_cust_id ON customers(customer_id)");
        }

        // --- Order & Item Schema Evolution & Indexes ---
        if ($db_name === 'orders' && $table === 'items') {
            $cols = array_column(
                $conn->query("PRAGMA table_info(items)")->fetchAll(PDO::FETCH_ASSOC),
                'name'
            );
            $migrations = [
                'ram' => "ALTER TABLE items ADD COLUMN ram TEXT DEFAULT ''",
                'storage' => "ALTER TABLE items ADD COLUMN storage TEXT DEFAULT ''",
                'battery' => "ALTER TABLE items ADD COLUMN battery TEXT DEFAULT ''",
                'notes' => "ALTER TABLE items ADD COLUMN notes TEXT DEFAULT ''"
            ];
            foreach ($migrations as $col => $sql) {
                if (!in_array($col, $cols)) {
                    $conn->exec($sql);
                }
            }

            $conn->exec("CREATE INDEX IF NOT EXISTS idx_items_order ON items(order_id)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_items_customer ON items(customer_id)");
        }

        // --- Warehouse Indexes ---
        if ($db_name === 'warehouse' && $table === 'inventory') {
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_inv_sector ON inventory(sector)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_inv_brand ON inventory(brand)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_inv_location ON inventory(location_code)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_inv_sector_loc ON inventory(sector, location_code)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_inv_loc_sector ON inventory(location_code, sector)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_inv_brand_model ON inventory(brand, model)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_inv_updated ON inventory(updated_at DESC)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_inv_price ON inventory(price)");

            $cols = $conn->query("PRAGMA table_info(inventory)")->fetchAll(PDO::FETCH_ASSOC);
            if (!in_array('price', array_column($cols, 'name'))) {
                $conn->exec("ALTER TABLE inventory ADD COLUMN price REAL DEFAULT 0");
            }
        }

        if ($db_name === 'warehouse' && $table === 'locations') {
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_locations_zone ON locations(working_zone_name, location_code)");
            $cols = $conn->query("PRAGMA table_info(locations)")->fetchAll(PDO::FETCH_ASSOC);
            if (!in_array('working_zone_name', array_column($cols, 'name'))) {
                $conn->exec("ALTER TABLE locations ADD COLUMN working_zone_name TEXT DEFAULT NULL");
            }
        }

        if ($db_name === 'warehouse' && $table === 'location_statuses') {
            $cols = array_column(
                $conn->query("PRAGMA table_info(location_statuses)")->fetchAll(PDO::FETCH_ASSOC),
                'name'
            );
            if (!in_array('is_default', $cols)) {
                $conn->exec("ALTER TABLE location_statuses ADD COLUMN is_default INTEGER DEFAULT 1");
                $conn->exec("UPDATE location_statuses SET is_default = 1 WHERE is_default IS NULL");
            }
            if (!in_array('location_code', $cols)) {
                $conn->exec("ALTER TABLE location_statuses ADD COLUMN location_code TEXT DEFAULT NULL");
            }
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_loc_statuses_name ON location_statuses(name)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_loc_statuses_code ON location_statuses(location_code)");
        }

        if ($db_name === 'warehouse' && $table === 'sold_items') {
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_sold_loc_sec ON sold_items(location_code, sector)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_sold_brand_model ON sold_items(brand, model)");
        }

        // --- Audit & User Indexes ---
        if ($db_name === 'users' && $table === 'audit_log') {
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_audit_timestamp ON audit_log(timestamp)");
        }

        if ($db_name === 'orders' && $table === 'orders') {
            $cols = $conn->query("PRAGMA table_info(orders)")->fetchAll(PDO::FETCH_ASSOC);
            if (!in_array('updated_at', array_column($cols, 'name'))) {
                $conn->exec("ALTER TABLE orders ADD COLUMN updated_at DATETIME");
                $conn->exec("UPDATE orders SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL");
            }
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_orders_status_created ON orders(status, created_at DESC)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_orders_customer_id ON orders(customer_id)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_orders_created_at ON orders(created_at DESC)");
        }

        if ($db_name === 'users' && $table === 'users') {
            $cols = $conn->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
            $col_names = array_column($cols, 'name');
            if (!in_array('display_name', $col_names)) {
                $conn->exec("ALTER TABLE users ADD COLUMN display_name TEXT DEFAULT ''");
            }
            if (!in_array('ppp_sequence_key', $col_names)) {
                $conn->exec("ALTER TABLE users ADD COLUMN ppp_sequence_key TEXT DEFAULT ''");
            }
            if (!in_array('ppp_row_index', $col_names)) {
                $conn->exec("ALTER TABLE users ADD COLUMN ppp_row_index INTEGER DEFAULT 0");
            }
            if (!in_array('ppp_password_len', $col_names)) {
                $conn->exec("ALTER TABLE users ADD COLUMN ppp_password_len INTEGER DEFAULT 55");
            }
        }

        // --- Calendar Schema Evolution ---
        if ($db_name === 'calendar' && $table === 'events') {
            $cols = array_column(
                $conn->query("PRAGMA table_info(events)")->fetchAll(PDO::FETCH_ASSOC),
                'name'
            );
            $migrations = [
                'event_date'  => "ALTER TABLE events ADD COLUMN event_date DATE DEFAULT ''",
                'color'        => "ALTER TABLE events ADD COLUMN color TEXT DEFAULT '#38bdf8'",
                'customer_id'  => "ALTER TABLE events ADD COLUMN customer_id TEXT DEFAULT ''",
            ];
            foreach ($migrations as $col => $sql) {
                if (!in_array($col, $cols)) {
                    $conn->exec($sql);
                }
            }
        }
    }
}
