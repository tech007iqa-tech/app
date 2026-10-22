<?php
/**
 * tech/api/lookup_label.php
 * Real-time Smart Label Lookup, Catalog & Spec Collision Engine against labels.db
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

try {
    $conn = Database::labels();

    $action = trim($_GET['action'] ?? $_POST['action'] ?? '');
    $sn     = trim($_GET['sn'] ?? $_POST['sn'] ?? '');
    $id     = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    $q      = trim($_GET['q'] ?? $_POST['q'] ?? '');

    // ─────────────────────────────────────────────────────────────────────────
    // ACTION 1: Smart Catalog of Unique Labels (for Quick Preselect in Logs UI)
    // ─────────────────────────────────────────────────────────────────────────
    if ($action === 'catalog') {
        $stmt = $conn->query("
            SELECT 
                id, brand, model, series, cpu_gen, cpu_specs, cpu_cores, cpu_speed,
                ram, storage, gpu, screen_res, battery, battery_specs, os_version,
                cosmetic_grade, bios_state, description, status, warehouse_location,
                created_at, updated_at
            FROM items
            WHERE brand != '' AND model != ''
            ORDER BY id DESC
        ");
        $rawLabels = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $catalog = [];
        foreach ($rawLabels as $lbl) {
            // Parse locations into clean distinct array
            $locs = [];
            if (!empty($lbl['warehouse_location'])) {
                $parts = array_map('trim', explode(',', $lbl['warehouse_location']));
                foreach ($parts as $p) {
                    if ($p !== '' && !in_array($p, $locs, true)) {
                        $locs[] = $p;
                    }
                }
            }

            // Human-readable title
            $title = trim($lbl['brand'] . ' ' . $lbl['model'] . ($lbl['series'] ? ' ' . $lbl['series'] : ''));
            $specsSummary = implode(' · ', array_filter([
                $lbl['cpu_specs'] ?: ($lbl['cpu_gen'] ?: null),
                $lbl['ram'] ?: null,
                $lbl['storage'] ?: null
            ]));

            $catalog[] = array_merge($lbl, [
                'display_title' => $title,
                'specs_summary' => $specsSummary,
                'locations_list' => $locs
            ]);
        }

        echo json_encode([
            'success' => true,
            'catalog' => $catalog,
            'total'   => count($catalog)
        ]);
        exit();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ACTION 2: Spec Collision Check ("One label per specs")
    // ─────────────────────────────────────────────────────────────────────────
    if ($action === 'check_specs') {
        $brand     = trim($_GET['brand'] ?? $_POST['brand'] ?? '');
        $model     = trim($_GET['model'] ?? $_POST['model'] ?? '');
        $series    = trim($_GET['series'] ?? $_POST['series'] ?? '');
        $cpu_specs = trim($_GET['cpu_specs'] ?? $_POST['cpu_specs'] ?? '');
        $ram       = trim($_GET['ram'] ?? $_POST['ram'] ?? '');
        $storage   = trim($_GET['storage'] ?? $_POST['storage'] ?? '');

        if ($brand === '' || $model === '') {
            echo json_encode(['success' => true, 'found' => false, 'message' => 'Brand and Model required for collision check.']);
            exit();
        }

        // Query for matching specs
        $stmt_check = $conn->prepare("
            SELECT * FROM items
            WHERE LOWER(TRIM(brand)) = LOWER(TRIM(:brand))
              AND LOWER(TRIM(model)) = LOWER(TRIM(:model))
              AND (
                    LOWER(TRIM(COALESCE(series, ''))) = LOWER(TRIM(:series))
                    OR (:series = '' AND (series IS NULL OR series = ''))
                  )
              AND (
                    LOWER(TRIM(COALESCE(cpu_specs, ''))) = LOWER(TRIM(:cpu_specs))
                    OR (:cpu_specs = '' AND (cpu_specs IS NULL OR cpu_specs = ''))
                  )
              AND (
                    LOWER(TRIM(COALESCE(ram, ''))) = LOWER(TRIM(:ram))
                    OR (:ram = '' AND (ram IS NULL OR ram = ''))
                  )
              AND (
                    LOWER(TRIM(COALESCE(storage, ''))) = LOWER(TRIM(:storage))
                    OR (:storage = '' AND (storage IS NULL OR storage = ''))
                  )
            ORDER BY id ASC
            LIMIT 1
        ");

        $stmt_check->execute([
            ':brand'     => $brand,
            ':model'     => $model,
            ':series'    => $series,
            ':cpu_specs' => $cpu_specs,
            ':ram'       => $ram,
            ':storage'   => $storage
        ]);

        $matched = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($matched) {
            $locs = [];
            if (!empty($matched['warehouse_location'])) {
                $parts = array_map('trim', explode(',', $matched['warehouse_location']));
                foreach ($parts as $p) {
                    if ($p !== '' && !in_array($p, $locs, true)) {
                        $locs[] = $p;
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'found'   => true,
                'label'   => $matched,
                'locations_list' => $locs
            ]);
            exit();
        } else {
            echo json_encode([
                'success' => true,
                'found'   => false
            ]);
            exit();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ACTION 3: Direct Lookup by Label ID
    // ─────────────────────────────────────────────────────────────────────────
    if ($id > 0) {
        $stmt = $conn->prepare("SELECT * FROM items WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($item) {
            $locs = [];
            if (!empty($item['warehouse_location'])) {
                $parts = array_map('trim', explode(',', $item['warehouse_location']));
                foreach ($parts as $p) {
                    if ($p !== '' && !in_array($p, $locs, true)) {
                        $locs[] = $p;
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'found' => true,
                'match_type' => 'id',
                'data' => $item,
                'locations_list' => $locs
            ]);
            exit();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ACTION 4: Exact Lookup by Serial Number (S/N) / Asset Tag
    // ─────────────────────────────────────────────────────────────────────────
    if ($sn !== '') {
        $stmt = $conn->prepare("SELECT * FROM items WHERE serial_number = ? AND serial_number != '' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$sn]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($item) {
            $locs = [];
            if (!empty($item['warehouse_location'])) {
                $parts = array_map('trim', explode(',', $item['warehouse_location']));
                foreach ($parts as $p) {
                    if ($p !== '' && !in_array($p, $locs, true)) {
                        $locs[] = $p;
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'found' => true,
                'match_type' => 'serial_number',
                'data' => $item,
                'locations_list' => $locs
            ]);
            exit();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ACTION 5: Predictive Autocomplete / Search Query
    // ─────────────────────────────────────────────────────────────────────────
    if ($q !== '' && strlen($q) >= 2) {
        $searchTerm = '%' . $q . '%';
        $stmt = $conn->prepare("
            SELECT DISTINCT id, brand, model, series, cpu_gen, cpu_specs, cpu_cores, cpu_speed, ram, storage, gpu, screen_res, os_version, warehouse_location
            FROM items
            WHERE model LIKE ? OR series LIKE ? OR (brand || ' ' || model) LIKE ? OR cpu_specs LIKE ?
            ORDER BY id DESC
            LIMIT 10
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'found' => count($suggestions) > 0,
            'suggestions' => $suggestions
        ]);
        exit();
    }

    // Default Not Found
    echo json_encode([
        'success' => true,
        'found' => false,
        'message' => 'No matching hardware profile found.'
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database query error: ' . $e->getMessage()
    ]);
}
