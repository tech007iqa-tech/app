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
            )",
            'interaction_logs' => "CREATE TABLE IF NOT EXISTS interaction_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id TEXT NOT NULL,
                contact_date TEXT,
                method TEXT,
                note TEXT,
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
                aisle_shelf TEXT DEFAULT NULL,
                is_archived INTEGER DEFAULT 0,
                archived_at DATETIME DEFAULT NULL,
                archived_reason TEXT DEFAULT NULL,
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
            'inventory_move_logs' => "CREATE TABLE IF NOT EXISTS inventory_move_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                inventory_id INTEGER NOT NULL,
                source_location TEXT NOT NULL,
                target_location TEXT NOT NULL,
                source_zone TEXT DEFAULT NULL,
                target_zone TEXT DEFAULT NULL,
                quantity_moved INTEGER NOT NULL,
                remaining_source_qty INTEGER NOT NULL DEFAULT 0,
                moved_by TEXT NOT NULL,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
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
        ],
        'tech' => [
            'logs' => "CREATE TABLE IF NOT EXISTS logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tech_id TEXT NOT NULL,
                status TEXT NOT NULL,
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
            )",
            'daily_status_changes' => "CREATE TABLE IF NOT EXISTS daily_status_changes (
                tech_id TEXT NOT NULL,
                change_date DATE DEFAULT (date('now', 'localtime')),
                change_count INTEGER DEFAULT 0,
                PRIMARY KEY (tech_id, change_date)
            )",
            'parts_inventory' => "CREATE TABLE IF NOT EXISTS parts_inventory (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                part_name TEXT NOT NULL,
                category TEXT,
                quantity INTEGER DEFAULT 0,
                low_stock_threshold INTEGER DEFAULT 5,
                notes TEXT,
                last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ],
        'labels' => [
            'items' => "CREATE TABLE IF NOT EXISTS items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT DEFAULT 'Laptop',
                brand TEXT NOT NULL,
                model TEXT NOT NULL,
                series TEXT,
                cpu_gen TEXT,
                cpu_specs TEXT,
                cpu_cores TEXT,
                cpu_speed TEXT,
                cpu_details TEXT,
                ram TEXT,
                storage TEXT,
                battery BOOLEAN,
                battery_specs TEXT,
                gpu TEXT,
                screen_res TEXT,
                webcam TEXT,
                backlit_kb TEXT,
                os_version TEXT,
                cosmetic_grade TEXT,
                work_notes TEXT,
                bios_state TEXT,
                description TEXT,
                status TEXT DEFAULT 'In Warehouse',
                warehouse_location TEXT,
                serial_number TEXT,
                order_id INTEGER,
                buyer_name TEXT,
                buyer_order_num TEXT,
                sale_price NUMERIC,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            'batteries' => "CREATE TABLE IF NOT EXISTS batteries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                brand TEXT NOT NULL,
                part_number TEXT NOT NULL,
                model_name TEXT,
                aliases TEXT,
                voltage TEXT,
                capacity_wh TEXT,
                capacity_mah TEXT,
                cell_count TEXT,
                chemistry TEXT DEFAULT 'Li-ion',
                compatible_models TEXT NOT NULL,
                warehouse_location TEXT DEFAULT 'Unassigned',
                qty_in_stock INTEGER DEFAULT 0,
                condition TEXT DEFAULT 'Tested OEM 80%+',
                connector_type TEXT,
                notes TEXT,
                status TEXT DEFAULT 'Available',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ],
        'marketing' => [
            'leads' => "CREATE TABLE IF NOT EXISTS leads (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                company TEXT,
                email TEXT,
                phone TEXT,
                source TEXT,
                status TEXT DEFAULT 'New',
                notes TEXT,
                last_contacted DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            'campaigns' => "CREATE TABLE IF NOT EXISTS campaigns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                type TEXT,
                status TEXT DEFAULT 'Draft',
                start_date DATE,
                end_date DATE,
                budget NUMERIC,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            'model_templates' => "CREATE TABLE IF NOT EXISTS model_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                model_name TEXT UNIQUE NOT NULL,
                category TEXT,
                base_specs TEXT,
                marketing_copy TEXT,
                hero_image_path TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            'photos' => "CREATE TABLE IF NOT EXISTS photos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                filename TEXT NOT NULL,
                original_name TEXT,
                model_name TEXT,
                category TEXT,
                file_path TEXT NOT NULL,
                thumbnail_path TEXT,
                optimized_path TEXT,
                file_size INTEGER,
                mime_type TEXT,
                status TEXT DEFAULT 'Ready',
                source TEXT DEFAULT 'upload',
                location_code TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            'audit_logs' => "CREATE TABLE IF NOT EXISTS audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_type TEXT NOT NULL,
                entity_id TEXT NOT NULL,
                action TEXT NOT NULL,
                summary TEXT,
                old_value TEXT,
                new_value TEXT,
                user_name TEXT DEFAULT 'System',
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ],
        'intake' => [
            'committed_intakes' => "CREATE TABLE IF NOT EXISTS committed_intakes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                date TEXT,
                qty INTEGER,
                item TEXT,
                serial TEXT,
                location TEXT,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
        if ($db_name === 'tech' && $table === 'parts_inventory') {
            $count = $conn->query("SELECT COUNT(*) FROM parts_inventory")->fetchColumn();
            if ($count == 0) {
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
        }
        if ($db_name === 'labels' && $table === 'batteries') {
            $count = $conn->query("SELECT COUNT(*) FROM batteries")->fetchColumn();
            if ($count == 0) {
                self::seedEnterpriseBatteries($conn);
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
            $cols = array_column($conn->query("PRAGMA table_info(locations)")->fetchAll(PDO::FETCH_ASSOC), 'name');
            if (!in_array('working_zone_name', $cols)) {
                $conn->exec("ALTER TABLE locations ADD COLUMN working_zone_name TEXT DEFAULT NULL");
            }
            if (!in_array('aisle_shelf', $cols)) {
                $conn->exec("ALTER TABLE locations ADD COLUMN aisle_shelf TEXT DEFAULT NULL");
            }
            if (!in_array('is_archived', $cols)) {
                $conn->exec("ALTER TABLE locations ADD COLUMN is_archived INTEGER DEFAULT 0");
            }
            if (!in_array('archived_at', $cols)) {
                $conn->exec("ALTER TABLE locations ADD COLUMN archived_at DATETIME DEFAULT NULL");
            }
            if (!in_array('archived_reason', $cols)) {
                $conn->exec("ALTER TABLE locations ADD COLUMN archived_reason TEXT DEFAULT NULL");
            }
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_locations_zone ON locations(working_zone_name, location_code)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_locations_archived ON locations(is_archived, working_zone_name)");
        }

        if ($db_name === 'warehouse' && $table === 'inventory_move_logs') {
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_move_logs_inv ON inventory_move_logs(inventory_id)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_move_logs_loc ON inventory_move_logs(source_location, target_location)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_move_logs_time ON inventory_move_logs(timestamp DESC)");
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

        // --- Customers Interaction Logs Indexes ---
        if ($db_name === 'customers' && $table === 'interaction_logs') {
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_interactions_cid ON interaction_logs(customer_id)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_interactions_date ON interaction_logs(contact_date)");
        }

        // --- Tech Module Evolution & Indexes ---
        if ($db_name === 'tech' && $table === 'logs') {
            $cols = array_column($conn->query("PRAGMA table_info(logs)")->fetchAll(PDO::FETCH_ASSOC), 'name');
            if (!in_array('edited', $cols)) $conn->exec("ALTER TABLE logs ADD COLUMN edited INTEGER DEFAULT 0");
            if (!in_array('delete_requested', $cols)) $conn->exec("ALTER TABLE logs ADD COLUMN delete_requested INTEGER DEFAULT 0");
            if (!in_array('status_change_requested', $cols)) $conn->exec("ALTER TABLE logs ADD COLUMN status_change_requested TEXT DEFAULT ''");
            if (!in_array('os', $cols)) $conn->exec("ALTER TABLE logs ADD COLUMN os TEXT");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_tech_logs_tech_id ON logs(tech_id)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_tech_logs_status ON logs(status)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_tech_logs_created ON logs(created_at DESC)");
        }

        // --- Labels Module Evolution & Indexes ---
        if ($db_name === 'labels' && $table === 'items') {
            $cols = array_column($conn->query("PRAGMA table_info(items)")->fetchAll(PDO::FETCH_ASSOC), 'name');
            if (!in_array('serial_number', $cols)) $conn->exec("ALTER TABLE items ADD COLUMN serial_number TEXT");
            if (!in_array('updated_at', $cols)) $conn->exec("ALTER TABLE items ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP");
            if (!in_array('buyer_name', $cols)) $conn->exec("ALTER TABLE items ADD COLUMN buyer_name TEXT");
            if (!in_array('buyer_order_num', $cols)) $conn->exec("ALTER TABLE items ADD COLUMN buyer_order_num TEXT");
            if (!in_array('sale_price', $cols)) $conn->exec("ALTER TABLE items ADD COLUMN sale_price NUMERIC");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_labels_brand_model ON items(brand, model)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_labels_status ON items(status)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_labels_location ON items(warehouse_location)");
        }

        if ($db_name === 'labels' && $table === 'batteries') {
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_batteries_part_number ON batteries(part_number)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_batteries_brand ON batteries(brand)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_batteries_location ON batteries(warehouse_location)");
        }

        // --- Marketing Module Evolution & Indexes ---
        if ($db_name === 'marketing' && $table === 'photos') {
            $cols = array_column($conn->query("PRAGMA table_info(photos)")->fetchAll(PDO::FETCH_ASSOC), 'name');
            if (!in_array('thumbnail_path', $cols)) $conn->exec("ALTER TABLE photos ADD COLUMN thumbnail_path TEXT");
            if (!in_array('optimized_path', $cols)) $conn->exec("ALTER TABLE photos ADD COLUMN optimized_path TEXT");
            if (!in_array('status', $cols)) $conn->exec("ALTER TABLE photos ADD COLUMN status TEXT DEFAULT 'Ready'");
            if (!in_array('source', $cols)) $conn->exec("ALTER TABLE photos ADD COLUMN source TEXT DEFAULT 'upload'");
            if (!in_array('location_code', $cols)) $conn->exec("ALTER TABLE photos ADD COLUMN location_code TEXT");
        }
        if ($db_name === 'marketing' && $table === 'leads') {
            $cols = array_column($conn->query("PRAGMA table_info(leads)")->fetchAll(PDO::FETCH_ASSOC), 'name');
            if (!in_array('last_contacted', $cols)) $conn->exec("ALTER TABLE leads ADD COLUMN last_contacted DATETIME");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_marketing_leads_status ON leads(status)");
        }

        // --- Intake Module Indexes ---
        if ($db_name === 'intake' && $table === 'committed_intakes') {
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_intake_date ON committed_intakes(date)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_intake_loc ON committed_intakes(location)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_intake_item ON committed_intakes(item)");
        }
    }

    /**
     * Seeds initial enterprise laptop battery catalog.
     */
    public static function seedEnterpriseBatteries($conn) {
        $batteries = [
            [
                'Dell', 'WDX0R', 'Dell Type WDX0R 42Wh 3-Cell Battery',
                '3CRH3, T2JX4, FC92N, CYMGM, FW8KR, 0WDX0R, Y3F7Y, P69G001',
                '11.4V', '42Wh', '3500mAh', '3-Cell', 'Li-ion',
                'Dell Inspiron 13 5368, Dell Inspiron 13 5378, Dell Inspiron 13 7368, Dell Inspiron 13 7378, Dell Inspiron 14 5468, Dell Inspiron 15 5567, Dell Inspiron 15 5568, Dell Inspiron 15 5570, Dell Inspiron 15 5578, Dell Inspiron 15 7560, Dell Inspiron 15 7569, Dell Inspiron 15 7570, Dell Inspiron 15 7579, Dell Inspiron 17 5767, Dell Inspiron 17 5770, Dell Latitude 13 3379, Dell Latitude 3180, Dell Latitude 3189, Dell Vostro 14 5468, Dell Vostro 15 5568',
                'Bin BAT-D01', 8, 'Tested OEM 80%+', 'Internal Ribbon Cable',
                'Fits Dell Inspiron 5000/7000 & Latitude 3000 series.', 'Available'
            ],
            [
                'Dell', '3HWPP', 'Dell Type 3HWPP 68Wh 4-Cell Battery',
                '4GVGH, 1WND8, 01WND8, JY8D6, 0JY8D6, 03HWPP',
                '15.2V', '68Wh', '4250mAh', '4-Cell', 'Li-Polymer',
                'Dell Latitude 5400, Dell Latitude 5410, Dell Latitude 5500, Dell Latitude 5510, Dell Inspiron 7590 2-in-1, Dell Inspiron 7791 2-in-1',
                'Bin BAT-D02', 12, 'Tested OEM 80%+', 'Internal Ribbon Cable',
                'Extended capacity pack. Occupies 2.5" drive bay area in Latitude 5400/5500.', 'Available'
            ],
            [
                'Dell', '1VX1I', 'Dell Type 1VX1I 42Wh 3-Cell Battery',
                'DJ1J0, F3YGT, 01VX1I, 0DJ1J0, PGFX4, ONFOH',
                '11.4V', '42Wh', '3500mAh', '3-Cell', 'Li-ion',
                'Dell Latitude 5280, Dell Latitude 5290, Dell Latitude 5480, Dell Latitude 5490, Dell Latitude 5580, Dell Latitude 5590',
                'Bin BAT-D03', 15, 'Tested OEM 80%+', 'Internal Ribbon Cable',
                'Standard 3-cell pack allowing 2.5" SATA HDD/SSD installation.', 'Available'
            ],
            [
                'Dell', '93FTF', 'Dell Type 93FTF 51Wh 3-Cell Battery',
                'GD1JP, D4CMT, 093FTF, 0GD1JP, 83XPC',
                '11.4V', '51Wh', '4250mAh', '3-Cell', 'Li-Polymer',
                'Dell Latitude 5280, Dell Latitude 5290, Dell Latitude 5480, Dell Latitude 5490, Dell Latitude 5580, Dell Latitude 5590, Dell Precision 3520, Dell Precision 3530',
                'Bin BAT-D04', 9, 'Tested OEM 80%+', 'Internal Ribbon Cable',
                'Fits Latitude 5480/5490 and Precision 3520/3530.', 'Available'
            ],
            [
                'Dell', '6GTPY', 'Dell Type 6GTPY 97Wh 6-Cell Extended Battery',
                '5XJ28, 5046J, 06GTPY, 05XJ28, H5H20',
                '11.4V', '97Wh', '8333mAh', '6-Cell', 'Li-Polymer',
                'Dell XPS 15 9560, Dell XPS 15 9570, Dell XPS 15 7590, Dell Precision 5510, Dell Precision 5520, Dell Precision 5530, Dell Precision 5540, Dell Vostro 7590',
                'Bin BAT-D05', 6, 'Tested OEM 80%+', 'Internal Ribbon Cable',
                'Extended capacity. Replaces 2.5" drive caddy in XPS 15 and Precision 5520/5530/5540.', 'Available'
            ],
            [
                'Dell', 'J60J5', 'Dell Type J60J5 55Wh 4-Cell Battery',
                '0J60J5, R1V85, 0R1V85, 242WD, GG4FM, MC34Y',
                '7.6V', '55Wh', '7000mAh', '4-Cell', 'Li-ion',
                'Dell Latitude E7270, Dell Latitude E7470',
                'Bin BAT-D06', 14, 'Tested OEM 80%+', 'Internal Flat Cable',
                'Specifically designed for 6th Gen Dell Latitude E7270 and E7470 ultrabooks.', 'Available'
            ],
            [
                'Dell', 'F3YGT', 'Dell Type F3YGT 60Wh 4-Cell Battery',
                '2X39G, 02X39G, DM3WV, 0DM3WV, 451-BBYE',
                '7.6V', '60Wh', '7500mAh', '4-Cell', 'Li-Polymer',
                'Dell Latitude 7280, Dell Latitude 7290, Dell Latitude 7380, Dell Latitude 7390, Dell Latitude 7480, Dell Latitude 7490',
                'Bin BAT-D07', 18, 'Tested OEM 80%+', 'Internal Ribbon Cable',
                'Primary battery for 7th & 8th Gen Dell Latitude 7000 series ultrabooks.', 'Available'
            ],
            [
                'Dell', '6MT4T', 'Dell Type 6MT4T 62Wh 4-Cell Battery',
                '7V69Y, TXF9M, 79VRK, 06MT4T, 07V69Y',
                '7.6V', '62Wh', '8160mAh', '4-Cell', 'Li-ion',
                'Dell Latitude E5250, Dell Latitude E5270, Dell Latitude E5450, Dell Latitude E5470, Dell Latitude E5550, Dell Latitude E5570, Dell Precision 3510',
                'Bin BAT-D08', 11, 'Tested OEM 80%+', 'Internal Flat Ribbon Cable',
                'Fits Latitude E5450, E5470, E5550, E5570.', 'Available'
            ],
            [
                'Dell', 'MXV9V', 'Dell Type MXV9V 52Wh 4-Cell Battery',
                '0MXV9V, 5VC2M, 05VC2M, 829MX',
                '7.6V', '52Wh', '6500mAh', '4-Cell', 'Li-Polymer',
                'Dell Latitude 5300, Dell Latitude 5300 2-in-1, Dell Latitude 5310, Dell Latitude 5310 2-in-1, Dell Latitude 7300, Dell Latitude 7400, Dell Inspiron 7391 2-in-1',
                'Bin BAT-D09', 7, 'Tested OEM 80%+', 'Internal Ribbon Cable',
                'Compact pack for Latitude 5300/7300/7400.', 'Available'
            ],
            [
                'HP', 'CS03XL', 'HP CS03XL Long Life Notebook Battery',
                'HSTNN-DB6U, HSTNN-UB6S, HSTNN-I33C-4, 800231-141, 800513-001, T7B32AA',
                '11.4V', '46.5Wh', '3910mAh', '3-Cell', 'Li-ion',
                'HP EliteBook 745 G3, HP EliteBook 745 G4, HP EliteBook 755 G3, HP EliteBook 755 G4, HP EliteBook 840 G3, HP EliteBook 840 G4, HP EliteBook 850 G3, HP EliteBook 850 G4, HP ZBook 15u G3, HP ZBook 15u G4, HP mt42 Mobile Thin Client, HP mt43 Mobile Thin Client',
                'Bin BAT-H01', 22, 'Tested OEM 80%+', 'Internal Drop-in Connector',
                'High-volume warehouse battery. Fits HP EliteBook 840 G3 and G4.', 'Available'
            ],
            [
                'HP', 'SS03XL', 'HP SS03XL Long Life Rechargeable Battery',
                'HSTNN-IB8C, HSTNN-LB8G, HSTNN-DB8J, 932823-1C1, 933321-855, 933321-852',
                '11.55V', '50Wh', '4110mAh', '3-Cell', 'Li-ion',
                'HP EliteBook 735 G5, HP EliteBook 735 G6, HP EliteBook 745 G5, HP EliteBook 745 G6, HP EliteBook 830 G5, HP EliteBook 830 G6, HP EliteBook 840 G5, HP EliteBook 840 G6, HP EliteBook 846 G5, HP EliteBook 846 G6, HP ZBook 14u G5, HP ZBook 14u G6, HP mt44 Mobile Thin Client, HP mt45 Mobile Thin Client',
                'Bin BAT-H02', 26, 'Brand New OEM', 'Internal Drop-in Connector',
                'Primary battery for 8th Gen HP EliteBook 840 G5 and G6.', 'Available'
            ],
            [
                'HP', 'CC03XL', 'HP CC03XL 53Wh Notebook Battery',
                'HSTNN-IB9F, HSTNN-DB9Q, L77608-1C1, L77608-2C1, L78553-005',
                '11.55V', '53Wh', '4330mAh', '3-Cell', 'Li-Polymer',
                'HP EliteBook 830 G7, HP EliteBook 830 G8, HP EliteBook 835 G7, HP EliteBook 835 G8, HP EliteBook 840 G7, HP EliteBook 840 G8, HP EliteBook 845 G7, HP EliteBook 845 G8, HP ZBook Firefly 14 G7, HP ZBook Firefly 14 G8',
                'Bin BAT-H03', 14, 'Tested OEM 80%+', 'Internal Connector Cable',
                'Standard battery for 10th & 11th Gen HP EliteBook 840 G7 and G8.', 'Available'
            ],
            [
                'HP', 'TT03XL', 'HP TT03XL 56Wh Notebook Battery',
                'HSTNN-DB8K, HSTNN-LB8H, HSTNN-UB7A, 932824-1C1, 933322-855',
                '11.55V', '56Wh', '4550mAh', '3-Cell', 'Li-Polymer',
                'HP EliteBook 850 G5, HP EliteBook 850 G6, HP EliteBook 755 G5, HP ZBook 15u G5, HP ZBook 15u G6',
                'Bin BAT-H04', 8, 'Tested OEM 80%+', 'Internal Drop-in Connector',
                'Fits 15.6" EliteBook 850 G5/G6 and ZBook 15u G5/G6.', 'Available'
            ],
            [
                'HP', 'RI04', 'HP RI04 Notebook Battery',
                'HSTNN-DB7B, HSTNN-PB6Q, 805047-851, 805294-001',
                '14.8V', '44Wh', '2850mAh', '4-Cell', 'Li-ion',
                'HP ProBook 450 G3, HP ProBook 455 G3, HP ProBook 470 G3',
                'Bin BAT-H05', 10, 'Tested OEM 80%+', 'External Snap-in Slot',
                'External clip-in battery for ProBook 450 G3.', 'Available'
            ],
            [
                'HP', 'RR03XL', 'HP RR03XL 48Wh Battery',
                'HSTNN-PB6W, HSTNN-LB7I, HSTNN-UB7C, 851477-421, 851610-850',
                '11.4V', '48Wh', '3890mAh', '3-Cell', 'Li-ion',
                'HP ProBook 430 G4, HP ProBook 440 G4, HP ProBook 450 G4, HP ProBook 455 G4, HP ProBook 470 G4',
                'Bin BAT-H06', 9, 'Tested OEM 80%+', 'Internal Ribbon Cable',
                'Internal battery for HP ProBook 400 G4 generation.', 'Available'
            ],
            [
                'HP', 'JC04', 'HP JC04 4-Cell External Battery',
                'JC03, HSTNN-DB8E, HSTNN-PB6Y, HSTNN-LB7V, 919700-850, 919701-850',
                '14.6V', '41.6Wh', '2850mAh', '4-Cell', 'Li-ion',
                'HP 240 G6, HP 245 G6, HP 250 G6, HP 255 G6, HP 15-bs000, HP 15-bw000, HP 17-bs000',
                'Bin BAT-H07', 16, 'Tested OEM 80%+', 'External Snap-in Slot',
                'Common external battery for entry-level HP 250 G6 laptops.', 'Available'
            ],
            [
                'HP', 'HT03XL', 'HP HT03XL Long Life Battery',
                'HSTNN-UB7J, HSTNN-DB8R, HSTNN-LB8M, L11119-855, L11421-2C2',
                '11.55V', '41.04Wh', '3470mAh', '3-Cell', 'Li-ion',
                'HP 240 G7, HP 245 G7, HP 250 G7, HP 255 G7, HP Pavilion 14-ce, HP Pavilion 14-cf, HP Pavilion 15-cs, HP Pavilion 15-cw, HP Pavilion 15-da, HP Pavilion 15-db',
                'Bin BAT-H08', 19, 'Brand New OEM', 'Internal Drop-in Connector',
                'High-frequency replacement pack for HP 250 G7 and Pavilion 15.', 'Available'
            ],
            [
                'Lenovo', '01AV421', 'Lenovo ThinkPad Internal Battery 01AV421',
                '01AV419, 01AV420, 01AV489, SB10K97576, SB10K97577, SB10K97578',
                '11.4V', '24Wh', '2090mAh', '3-Cell', 'Li-Polymer',
                'Lenovo ThinkPad T470, Lenovo ThinkPad T480, Lenovo ThinkPad A475, Lenovo ThinkPad A485',
                'Bin BAT-L01', 24, 'Tested OEM 80%+', 'Internal Flat Ribbon Cable',
                'Internal front battery in PowerBridge dual-battery system.', 'Available'
            ],
            [
                'Lenovo', '01AV423', 'Lenovo ThinkPad Battery 61+ (Rear External)',
                '61, 61+, 61++, 01AV422, 01AV424, 01AV425, 01AV426, 01AV427, SB10K97580',
                '10.8V', '48Wh', '4400mAh', '6-Cell', 'Li-ion',
                'Lenovo ThinkPad T470, Lenovo ThinkPad T480, Lenovo ThinkPad T570, Lenovo ThinkPad T580, Lenovo ThinkPad P51s, Lenovo ThinkPad P52s, Lenovo ThinkPad A475, Lenovo ThinkPad A485',
                'Bin BAT-L02', 17, 'Tested OEM 80%+', 'External Snap-in Slot',
                'Rear clip-in cylindrical pack for ThinkPad T470 / T480. High warehouse demand.', 'Available'
            ],
            [
                'Lenovo', '45N1127', 'Lenovo ThinkPad Battery 68+ (Extended Rear)',
                '68, 68+, 45N1124, 45N1125, 45N1126, 45N1128, 45N1738, 45N1775, 0C52862',
                '10.8V', '72Wh', '6600mAh', '6-Cell', 'Li-ion',
                'Lenovo ThinkPad T440, Lenovo ThinkPad T440s, Lenovo ThinkPad T450, Lenovo ThinkPad T450s, Lenovo ThinkPad T460, Lenovo ThinkPad T460p, Lenovo ThinkPad X240, Lenovo ThinkPad X250, Lenovo ThinkPad X260, Lenovo ThinkPad L450, Lenovo ThinkPad L460, Lenovo ThinkPad W550s',
                'Bin BAT-L03', 28, 'Tested OEM 80%+', 'External Snap-in Slot',
                'Classic ThinkPad 6-cell external extended battery. Fits T440/T450/T460 & X240/X250/X260.', 'Available'
            ],
            [
                'Lenovo', 'L17M3P51', 'Lenovo ThinkPad L17M3P51 57Wh Battery',
                '01AV463, 01AV464, 01AV465, 01AV466, SB10K97610, SB10K97611, L17C3P51',
                '11.52V', '57Wh', '4950mAh', '3-Cell', 'Li-Polymer',
                'Lenovo ThinkPad T490, Lenovo ThinkPad T590, Lenovo ThinkPad P43s, Lenovo ThinkPad P53s',
                'Bin BAT-L04', 13, 'Tested OEM 80%+', 'Internal Ribbon Cable',
                'Single internal battery in ThinkPad T490. Replaced dual-battery PowerBridge.', 'Available'
            ],
            [
                'Lenovo', 'L18M3P73', 'Lenovo ThinkPad L18M3P73 50Wh Battery',
                '02DL007, 02DL008, 02DL009, 02DL010, SB10K97652, SB10K97653, L18C3P73',
                '11.52V', '50Wh', '4345mAh', '3-Cell', 'Li-Polymer',
                'Lenovo ThinkPad T14 Gen 1, Lenovo ThinkPad T14 Gen 2, Lenovo ThinkPad P14s Gen 1, Lenovo ThinkPad P14s Gen 2',
                'Bin BAT-L05', 15, 'Brand New OEM', 'Internal Ribbon Cable',
                'High-demand modern fleet pack for ThinkPad T14 Gen 1 and Gen 2.', 'Available'
            ],
            [
                'Lenovo', '00HW022', 'Lenovo ThinkPad 00HW022 Front Internal Battery',
                '00HW023, 00HW024, 00HW025, SB10F46460, SB10F46461, SB10F46462',
                '11.25V', '24Wh', '2100mAh', '3-Cell', 'Li-ion',
                'Lenovo ThinkPad T460s, Lenovo ThinkPad T470s',
                'Bin BAT-L06', 12, 'Tested OEM 80%+', 'Internal Flat Ribbon Cable',
                'Dual internal battery architecture for T460s/T470s (Battery 1 Front).', 'Available'
            ],
            [
                'Lenovo', '01AV477', 'Lenovo ThinkPad Battery 77+ (P50 / P51 / P52)',
                '77, 77+, 01AV477, SB10H45007, SB10H45008, SB10K97634, 00NY493, 00NY492, 00NY491, 00NY490, SB10H45071, SB10H45073',
                '11.25V', '90Wh', '8000mAh', '6-Cell', 'Li-ion',
                'Lenovo ThinkPad P50, Lenovo ThinkPad P51, Lenovo ThinkPad P52',
                'Bin BAT-L07', 7, 'Tested OEM 80%+', 'External Snap-in Slot',
                'High-capacity 90Wh external pack for ThinkPad P50/P51/P52 workstations (ASM P/N: SB10H45007 / FRU: 01AV477).', 'Available'
            ],
            [
                'Apple', 'A1819', 'Apple MacBook Pro 13" Touch Bar Battery A1819',
                '020-01705, 080-333-4000, A1706',
                '11.41V', '49.2Wh', '4314mAh', '3-Cell', 'Li-Polymer',
                'Apple MacBook Pro 13" Touch Bar A1706 (Late 2016 MLH12LL/A), Apple MacBook Pro 13" Touch Bar A1706 (Mid 2017 MPXV2LL/A)',
                'Bin BAT-A01', 6, 'Brand New OEM', 'Internal Ribbon Connector Board',
                'Adhesive-backed 3-cell pouch pack for MacBook Pro 13 Touch Bar A1706.', 'Available'
            ],
            [
                'Apple', 'A1713', 'Apple MacBook Pro 13" Non-Touch Bar Battery A1713',
                '020-00814, 020-00816, A1708',
                '11.4V', '54.5Wh', '4781mAh', '3-Cell', 'Li-Polymer',
                'Apple MacBook Pro 13" Function Keys A1708 (Late 2016 MLL42LL/A), Apple MacBook Pro 13" Function Keys A1708 (Mid 2017 MPXQ2LL/A)',
                'Bin BAT-A02', 8, 'Brand New OEM', 'Internal Ribbon Connector Board',
                'For non-Touch Bar A1708 MacBook Pro. Different form factor from A1819.', 'Available'
            ],
            [
                'Apple', 'A1496', 'Apple MacBook Air 13" Battery A1496',
                'A1405, A1377, 020-8143-A, 020-8145-A',
                '7.6V', '54.4Wh', '7150mAh', '4-Cell', 'Li-Polymer',
                'Apple MacBook Air 13" A1466 (Mid 2013 to 2017 MD760LL/A, MQD32LL/A), Apple MacBook Air 13" A1369 (Late 2010, Mid 2011 MC503LL/A)',
                'Bin BAT-A03', 14, 'Tested OEM 80%+', 'Internal Drop-in Connector',
                'Classic wedge MacBook Air 13" screw-in battery pack.', 'Available'
            ],
            [
                'Microsoft', 'G3HTA027H', 'Microsoft Surface Pro Battery G3HTA027H / DYNM02',
                'DYNM02, G3HTA036H, G3HTA044H',
                '7.57V', '45Wh', '5940mAh', '2-Cell', 'Li-ion',
                'Microsoft Surface Pro 4 1724, Microsoft Surface Pro 5 (2017) 1796, Microsoft Surface Pro 6 1796',
                'Bin BAT-M01', 5, 'Brand New OEM', 'Internal Multi-Pin Ribbon',
                'For Surface Pro 4 / 5 / 6 tablet enclosures.', 'Available'
            ],
            [
                'Microsoft', 'G3HTA038H', 'Microsoft Surface Laptop Battery G3HTA038H',
                'DYNM03, 1769-BAT',
                '7.57V', '45.2Wh', '5970mAh', '2-Cell', 'Li-Polymer',
                'Microsoft Surface Laptop 1 1769, Microsoft Surface Laptop 2 1769, Microsoft Surface Laptop 3 13.5" 1867 1868',
                'Bin BAT-M02', 4, 'Brand New OEM', 'Internal Flat Flex',
                'Fits Surface Laptop 1 / 2 / 3 with Alcantara / Metal palmrest.', 'Available'
            ]
        ];

        $stmt = $conn->prepare("
            INSERT INTO batteries (
                brand, part_number, model_name, aliases, voltage, capacity_wh,
                capacity_mah, cell_count, chemistry, compatible_models,
                warehouse_location, qty_in_stock, condition, connector_type,
                notes, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($batteries as $bat) {
            $stmt->execute($bat);
        }
    }
}
