<?php
/**
 * Self-Healing Schema Guard for Marketing Module
 */

function marketing_schema_guard($pdo) {
    try {
        // 1. Leads Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS leads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            company TEXT,
            email TEXT,
            phone TEXT,
            source TEXT,
            status TEXT DEFAULT 'New',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // 2. Campaigns Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS campaigns (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            type TEXT, -- Email, Social, Cold Call
            status TEXT DEFAULT 'Draft',
            start_date DATE,
            end_date DATE,
            budget NUMERIC,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // 3. Model Templates Table (Specs Library)
        $pdo->exec("CREATE TABLE IF NOT EXISTS model_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            model_name TEXT UNIQUE NOT NULL,
            category TEXT,
            base_specs TEXT,
            marketing_copy TEXT,
            hero_image_path TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // 4. Audit Logs Table (Project Requirement)
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
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

        // 5. Photos Table (Photo Bucket)
        $pdo->exec("CREATE TABLE IF NOT EXISTS photos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL,
            original_name TEXT,
            model_name TEXT,
            category TEXT,
            file_path TEXT NOT NULL,
            thumbnail_path TEXT,
            file_size INTEGER,
            mime_type TEXT,
            status TEXT DEFAULT 'Ready', -- Ready, Processing, Error
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // --- Automated Migrations ---
        $stmt_info = $pdo->query("PRAGMA table_info(photos)");
        $photo_cols = array_column($stmt_info->fetchAll(PDO::FETCH_ASSOC), 'name');

        if (!in_array('thumbnail_path', $photo_cols)) {
            $pdo->exec("ALTER TABLE photos ADD COLUMN thumbnail_path TEXT");
        }
        if (!in_array('optimized_path', $photo_cols)) {
            $pdo->exec("ALTER TABLE photos ADD COLUMN optimized_path TEXT");
        }
        if (!in_array('status', $photo_cols)) {
            $pdo->exec("ALTER TABLE photos ADD COLUMN status TEXT DEFAULT 'Ready'");
        }
        if (!in_array('source', $photo_cols)) {
            $pdo->exec("ALTER TABLE photos ADD COLUMN source TEXT DEFAULT 'upload'");
        }
        if (!in_array('location_code', $photo_cols)) {
            $pdo->exec("ALTER TABLE photos ADD COLUMN location_code TEXT");
        }

        $stmt_info = $pdo->query("PRAGMA table_info(leads)");
        $col_names = array_column($stmt_info->fetchAll(PDO::FETCH_ASSOC), 'name');

        // Example migration: if we need to add a 'last_contacted' column
        if (!in_array('last_contacted', $col_names)) {
            $pdo->exec("ALTER TABLE leads ADD COLUMN last_contacted DATETIME");
        }

        // Add customer_id to track CRM sync
        if (!in_array('customer_id', $col_names)) {
            $pdo->exec("ALTER TABLE leads ADD COLUMN customer_id TEXT");
        }

        // 5. Performance Indexes (Optimization)
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_leads_email ON leads(email)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_leads_customer_id ON leads(customer_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_templates_model ON model_templates(model_name)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_timestamp ON audit_logs(timestamp)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_photos_model ON photos(model_name)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_photos_category ON photos(category)");

        return true;
    } catch (Exception $e) {
        error_log("Marketing Schema Guard Error: " . $e->getMessage());
        return false;
    }
}

/**
 * CRM Schema Guard
 * Ensures the Master CRM (customers.db) is compatible with Marketing leads.
 */
function crm_schema_guard($pdo) {
    try {
        // Create customers table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
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
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $stmt_info = $pdo->query("PRAGMA table_info(customers)");
        $col_names = array_column($stmt_info->fetchAll(PDO::FETCH_ASSOC), 'name');

        // Add lead-specific columns if they don't exist
        if (!in_array('lead_source', $col_names)) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN lead_source TEXT DEFAULT 'Manual'");
        }
        if (!in_array('account_status', $col_names)) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN account_status TEXT DEFAULT 'Customer'");
        }

        return true;
    } catch (Exception $e) {
        error_log("CRM Schema Guard Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Labels Schema Guard
 * Ensures the Labels database (labels.sqlite) is compatible.
 */
function labels_schema_guard($pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS items (
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

        $stmt_info = $pdo->query("PRAGMA table_info(items)");
        $col_names = array_column($stmt_info->fetchAll(PDO::FETCH_ASSOC), 'name');

        if (!in_array('serial_number', $col_names)) $pdo->exec("ALTER TABLE items ADD COLUMN serial_number TEXT");
        if (!in_array('updated_at',    $col_names)) $pdo->exec("ALTER TABLE items ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP");
        if (!in_array('buyer_name',    $col_names)) $pdo->exec("ALTER TABLE items ADD COLUMN buyer_name TEXT");
        if (!in_array('buyer_order_num', $col_names)) $pdo->exec("ALTER TABLE items ADD COLUMN buyer_order_num TEXT");
        if (!in_array('sale_price',    $col_names)) $pdo->exec("ALTER TABLE items ADD COLUMN sale_price NUMERIC");

        // Indexes
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_items_serial ON items(serial_number)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_items_brand_model ON items(brand, model)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_items_status ON items(status)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_items_location ON items(warehouse_location)");

        return true;
    } catch (Exception $e) {
        error_log("Labels Schema Guard Error: " . $e->getMessage());
        return false;
    }
}
?>
