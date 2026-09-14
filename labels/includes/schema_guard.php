<?php
// includes/schema_guard.php
// This utility ensures that if a database file is missing or corrupted,
// the system can automatically rebuild the tables to prevent a total crash.

function check_and_rebuild_schemas($pdo_labels, $pdo_orders, $pdo_rolodex, $pdo_audit) {
    try {
        // 1. Check Labels
        $pdo_labels->exec("CREATE TABLE IF NOT EXISTS items (
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
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Automated Migration for labels
        $stmt_info = $pdo_labels->query("PRAGMA table_info(items)");
        $columns = $stmt_info->fetchAll(PDO::FETCH_ASSOC);
        $col_names = array_column($columns, 'name');

        if (!in_array('serial_number', $col_names)) $pdo_labels->exec("ALTER TABLE items ADD COLUMN serial_number TEXT");
        if (!in_array('updated_at',    $col_names)) $pdo_labels->exec("ALTER TABLE items ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP");
        if (!in_array('buyer_name',    $col_names)) $pdo_labels->exec("ALTER TABLE items ADD COLUMN buyer_name TEXT");
        if (!in_array('buyer_order_num', $col_names)) $pdo_labels->exec("ALTER TABLE items ADD COLUMN buyer_order_num TEXT");
        if (!in_array('sale_price',    $col_names)) $pdo_labels->exec("ALTER TABLE items ADD COLUMN sale_price NUMERIC");

        // 2. Check Orders
        $pdo_orders->exec("CREATE TABLE IF NOT EXISTS purchase_orders (
            order_number INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL,
            order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            total_qty INTEGER,
            total_price NUMERIC,
            document_path TEXT,
            invoice_status TEXT DEFAULT 'Pending'
        )");

        // Order Migrations
        $stmt_oinfo = $pdo_orders->query("PRAGMA table_info(purchase_orders)");
        $orow_names = array_column($stmt_oinfo->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('invoice_status', $orow_names)) $pdo_orders->exec("ALTER TABLE purchase_orders ADD COLUMN invoice_status TEXT DEFAULT 'Pending'");
        if (!in_array('document_path', $orow_names))  $pdo_orders->exec("ALTER TABLE purchase_orders ADD COLUMN document_path TEXT");
        $pdo_orders->exec("CREATE TABLE IF NOT EXISTS order_items (
            line_id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_number INTEGER NOT NULL,
            item_id INTEGER NOT NULL,
            brand TEXT,
            model TEXT,
            specs_blob TEXT,
            qty INTEGER DEFAULT 1,
            unit_price NUMERIC,
            total_price NUMERIC
        )");
        $pdo_orders->exec("CREATE TABLE IF NOT EXISTS sold_history (
            history_id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_number INTEGER NOT NULL,
            item_id INTEGER NOT NULL,
            serial_number TEXT,
            customer_id INTEGER NOT NULL,
            brand_model TEXT,
            sale_price NUMERIC,
            sale_date DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // 3. Check Rolodex
        $pdo_rolodex->exec("CREATE TABLE IF NOT EXISTS customers (
            customer_id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_name TEXT,
            contact_person TEXT NOT NULL,
            email TEXT,
            phone TEXT,
            lead_status TEXT DEFAULT 'New Lead',
            tier TEXT DEFAULT 'Bronze',
            address TEXT,
            tax_id TEXT,
            website TEXT,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Migration for Rolodex
        $stmt_cinfo = $pdo_rolodex->query("PRAGMA table_info(customers)");
        $crow_names = array_column($stmt_cinfo->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('tier', $crow_names)) $pdo_rolodex->exec("ALTER TABLE customers ADD COLUMN tier TEXT DEFAULT 'Bronze'");

        // 4. Check Audit
        $pdo_audit->exec("CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entity_type TEXT NOT NULL,
            entity_id TEXT NOT NULL,
            action TEXT NOT NULL,
            summary TEXT,
            old_value TEXT,
            new_value TEXT,
            user_name TEXT DEFAULT 'System',
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // 5. High-Performance Indexing (Phase 1 Optimization)
        // These ensure searches remain instant even with tens of thousands of items
        $pdo_labels->exec("CREATE INDEX IF NOT EXISTS idx_items_serial ON items(serial_number)");
        $pdo_labels->exec("CREATE INDEX IF NOT EXISTS idx_items_brand_model ON items(brand, model)");
        $pdo_labels->exec("CREATE INDEX IF NOT EXISTS idx_items_status ON items(status)");
        $pdo_labels->exec("CREATE INDEX IF NOT EXISTS idx_items_location ON items(warehouse_location)");

        return true;
    } catch (Exception $e) {
        error_log("Schema Guard Error: " . $e->getMessage());
        return false;
    }
}
?>
