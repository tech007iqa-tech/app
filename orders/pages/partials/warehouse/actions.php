<?php
/**
 * Warehouse Action Controllers
 * Handles all POST requests for inventory, zones, statuses, and location photos.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    if (!Security::validate($_POST['csrf_token'] ?? '')) {
        die("Security Error: CSRF Token Invalid.");
    }

    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    if ($_POST['action'] === 'delete_inventory' && isset($_POST['item_id'])) {
        $item_id = (int)$_POST['item_id'];
        $stmt_sel = $conn_wh->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt_sel->execute([$item_id]);
        $item = $stmt_sel->fetch(PDO::FETCH_ASSOC);

        if ($item) {
            $stmt_sold = $conn_wh->prepare("
                INSERT INTO sold_items (location_code, sector, brand, model, specs_json, quantity, sold_price, sold_by, reason) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $reason = $_POST['reason'] ?? 'Depletion / Shelf Removal';
            $stmt_sold->execute([
                $item['location_code'],
                $item['sector'],
                $item['brand'],
                $item['model'],
                $item['specs_json'],
                (int)$item['quantity'],
                (float)($item['price'] ?? 0.00),
                $current_user,
                $reason
            ]);
        }

        $stmt = $conn_wh->prepare("DELETE FROM inventory WHERE id=?");
        $stmt->execute([$item_id]);

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'deleted_id' => $item_id, 'recorded_sold' => true]);
            exit();
        }

        $sector = $_GET['sector'] ?? $_POST['sector'] ?? 'Laptops';
        $loc = $_GET['loc'] ?? $_POST['location_code'] ?? '';
        header("Location: index.php?view=warehouse&sector=" . urlencode($sector) . "&loc=" . urlencode($loc) . "&msg=deleted#wh-form-title");
        exit();
    }

    if ($_POST['action'] === 'deplete_inventory_item' && isset($_POST['item_id'])) {
        $item_id = (int)$_POST['item_id'];
        $delta = (int)($_POST['delta'] ?? -1);

        $stmt = $conn_wh->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt->execute([$item_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Item not found.']);
                exit();
            }
        } else {
            $curr_qty = (int)$row['quantity'];
            $new_qty = $curr_qty + $delta;

            // If decremented, record the decremented quantity as sold
            if ($delta < 0) {
                $sold_qty = abs($delta);
                $stmt_sold = $conn_wh->prepare("
                    INSERT INTO sold_items (location_code, sector, brand, model, specs_json, quantity, sold_price, sold_by, reason) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Step Depletion')
                ");
                $stmt_sold->execute([
                    $row['location_code'],
                    $row['sector'],
                    $row['brand'],
                    $row['model'],
                    $row['specs_json'],
                    $sold_qty,
                    (float)($row['price'] ?? 0.00),
                    $current_user
                ]);
            }

            if ($new_qty <= 0) {
                $stmt_del = $conn_wh->prepare("DELETE FROM inventory WHERE id = ?");
                $stmt_del->execute([$item_id]);
                $deleted = true;
            } else {
                $stmt_up = $conn_wh->prepare("UPDATE inventory SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt_up->execute([$new_qty, $item_id]);
                $deleted = false;
            }

            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'deleted' => $deleted, 'new_quantity' => max(0, $new_qty)]);
                exit();
            }
        }
    }

    if ($_POST['action'] === 'purge_inventory_items' && isset($_POST['item_ids'])) {
        $item_ids = json_decode($_POST['item_ids'], true);
        if (is_array($item_ids) && !empty($item_ids)) {
            $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
            
            // Record as sold before deleting
            $stmt_sel = $conn_wh->prepare("SELECT * FROM inventory WHERE id IN ($placeholders)");
            $stmt_sel->execute(array_map('intval', $item_ids));
            $items_to_purge = $stmt_sel->fetchAll(PDO::FETCH_ASSOC);

            $stmt_sold = $conn_wh->prepare("
                INSERT INTO sold_items (location_code, sector, brand, model, specs_json, quantity, sold_price, sold_by, reason) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Batch Purge')
            ");
            foreach ($items_to_purge as $it) {
                $stmt_sold->execute([
                    $it['location_code'],
                    $it['sector'],
                    $it['brand'],
                    $it['model'],
                    $it['specs_json'],
                    (int)($it['quantity'] ?? 1),
                    (float)($it['price'] ?? 0.00),
                    $current_user
                ]);
            }

            $stmt = $conn_wh->prepare("DELETE FROM inventory WHERE id IN ($placeholders)");
            $stmt->execute(array_map('intval', $item_ids));
        }

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'count' => is_array($item_ids) ? count($item_ids) : 0]);
            exit();
        }
    }

    if ($_POST['action'] === 'reconcile_location_sync' && isset($_POST['location_code'])) {
        $loc = trim($_POST['location_code']);
        $sector = $_POST['sector'] ?? 'Laptops';
        
        // Can receive verified_items as JSON array of {id, qty} or object {id: qty}, or fallback to kept_item_ids
        $verified_items_raw = $_POST['verified_items'] ?? '';
        $verified_map = []; // [ id => verified_qty ]
        
        if (!empty($verified_items_raw)) {
            $decoded = json_decode($verified_items_raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $k => $v) {
                    if (is_array($v) && isset($v['id'])) {
                        $verified_map[(int)$v['id']] = max(1, (int)($v['qty'] ?? 1));
                    } elseif (is_numeric($k)) {
                        $verified_map[(int)$k] = max(1, (int)$v);
                    }
                }
            }
        } elseif (isset($_POST['kept_item_ids'])) {
            $kept_ids = json_decode($_POST['kept_item_ids'], true) ?: [];
            foreach ($kept_ids as $kid) {
                $verified_map[(int)$kid] = null; // keep current db qty
            }
        }

        $kept_ids = array_keys($verified_map);

        $conn_wh->beginTransaction();
        try {
            // 1. Fetch all items currently on this shelf/sector
            $stmt_all = $conn_wh->prepare("SELECT * FROM inventory WHERE location_code = ? AND sector = ?");
            $stmt_all->execute([$loc, $sector]);
            $all_shelf_items = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

            $sold_count = 0;
            $kept_count = 0;
            $deleted_ids = [];
            $updated_ids = [];

            $stmt_sold = $conn_wh->prepare("
                INSERT INTO sold_items (location_code, sector, brand, model, specs_json, quantity, sold_price, sold_by, reason)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt_update_qty = $conn_wh->prepare("UPDATE inventory SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");

            foreach ($all_shelf_items as $item) {
                $item_id = (int)$item['id'];
                $db_qty = (int)($item['quantity'] ?? 1);
                $price = (float)($item['price'] ?? 0.00);

                if (isset($verified_map[$item_id])) {
                    // Item was verified on shelf!
                    $target_qty = $verified_map[$item_id] !== null ? (int)$verified_map[$item_id] : $db_qty;
                    if ($target_qty <= 0) $target_qty = 1;

                    if ($target_qty < $db_qty) {
                        // Some units were missing/sold
                        $diff_sold = $db_qty - $target_qty;
                        $stmt_sold->execute([
                            $loc,
                            $item['sector'] ?? $sector,
                            $item['brand'],
                            $item['model'],
                            $item['specs_json'],
                            $diff_sold,
                            $price,
                            $current_user,
                            'Reconcile Audit Count Adjustment'
                        ]);
                        $sold_count += $diff_sold;
                    }

                    if ($target_qty !== $db_qty) {
                        $stmt_update_qty->execute([$target_qty, $item_id]);
                        $updated_ids[] = $item_id;
                    }
                    $kept_count += $target_qty;
                } else {
                    // Item was NOT checked off -> Purge and record as SOLD
                    $stmt_sold->execute([
                        $loc,
                        $item['sector'] ?? $sector,
                        $item['brand'],
                        $item['model'],
                        $item['specs_json'],
                        $db_qty,
                        $price,
                        $current_user,
                        'Full Location Sync Audit'
                    ]);
                    $deleted_ids[] = $item_id;
                    $sold_count += $db_qty;
                }
            }

            // Delete missing items from inventory
            if (!empty($deleted_ids)) {
                $del_ph = implode(',', array_fill(0, count($deleted_ids), '?'));
                $stmt_del = $conn_wh->prepare("DELETE FROM inventory WHERE id IN ($del_ph)");
                $stmt_del->execute($deleted_ids);
            }

            $conn_wh->commit();

            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'kept_count' => $kept_count,
                    'sold_count' => $sold_count,
                    'deleted_record_count' => count($deleted_ids),
                    'deleted_ids' => $deleted_ids,
                    'message' => "Shelf {$loc} reconciled: {$kept_count} unit(s) verified & retained, {$sold_count} unit(s) recorded as SOLD & removed."
                ]);
                exit();
            }

            header("Location: index.php?view=warehouse&sector=" . urlencode($sector) . "&loc=" . urlencode($loc) . "&msg=synced");
            exit();
        } catch (Exception $e) {
            $conn_wh->rollBack();
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Sync error: ' . $e->getMessage()]);
                exit();
            }
            die("Sync failed: " . $e->getMessage());
        }
    }

    if ($_POST['action'] === 'quick_add_inventory') {
        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $loc = trim($_POST['location_code'] ?? '');
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        $price = (float)($_POST['price'] ?? 0.00);
        $sector = $_POST['sector'] ?? 'Laptops';
        $auto_consolidate = !empty($_POST['auto_consolidate']);

        if (empty($brand) || empty($model)) {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Brand and Model are required.']);
                exit();
            }
        }

        // Dynamic Specs mapping based on sector
        $specs = [];
        if ($sector === 'Laptops') {
            $specs = [
                'cpu' => $_POST['cpu'] ?? '',
                'gpu' => $_POST['gpu'] ?? '',
                'ram' => $_POST['ram'] ?? '',
                'storage' => $_POST['storage'] ?? '',
                'battery' => $_POST['battery'] ?? '',
                'series' => $_POST['series'] ?? '',
                'gen' => $_POST['gen'] ?? '',
                'condition' => $_POST['condition'] ?? 'Used',
                'notes' => $_POST['notes'] ?? ''
            ];
        } elseif ($sector === 'Gaming') {
            $specs = [
                'category' => $_POST['gaming_category'] ?? 'Consoles',
                'series' => $_POST['series'] ?? '',
                'condition' => $_POST['condition'] ?? 'Used',
                'notes' => $_POST['notes'] ?? '',
                'ram' => $_POST['ram'] ?? '',
                'storage' => $_POST['storage'] ?? '',
                'cpu' => $_POST['cpu'] ?? '',
                'gpu' => $_POST['gpu'] ?? ''
            ];
        } elseif ($sector === 'Desktops') {
            $specs = [
                'cpu_gen' => $_POST['cpu_gen'] ?? '',
                'ram' => $_POST['ram'] ?? '',
                'storage' => $_POST['storage'] ?? '',
                'condition' => $_POST['condition'] ?? 'Used',
                'notes' => $_POST['notes'] ?? ''
            ];
        } else {
            $specs = ['condition' => $_POST['condition'] ?? 'Used', 'notes' => $_POST['notes'] ?? ''];
        }

        $specs_json = json_encode($specs);
        $last_id = null;
        $consolidated = false;

        if ($auto_consolidate && !empty($loc)) {
            // Check for identical item on the same location
            $stmt_find = $conn_wh->prepare("
                SELECT id, quantity, price FROM inventory 
                WHERE sector = ? AND location_code = ? AND brand = ? AND model = ? AND specs_json = ? 
                LIMIT 1
            ");
            $stmt_find->execute([$sector, $loc, $brand, $model, $specs_json]);
            $existing = $stmt_find->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $new_qty = (int)$existing['quantity'] + $qty;
                $stmt_up = $conn_wh->prepare("
                    UPDATE inventory 
                    SET quantity = ?, last_updated_by = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt_up->execute([$new_qty, $current_user, $existing['id']]);
                $last_id = $existing['id'];
                $consolidated = true;
            }
        }

        if (!$consolidated) {
            $stmt = $conn_wh->prepare("
                INSERT INTO inventory (user_owner, sector, location_code, brand, model, specs_json, quantity, price) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$current_user, $sector, $loc, $brand, $model, $specs_json, $qty, $price]);
            $last_id = $conn_wh->lastInsertId();
        }

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'id' => $last_id,
                'consolidated' => $consolidated,
                'brand' => $brand,
                'model' => $model,
                'quantity' => $qty
            ]);
            exit();
        }

        header("Location: index.php?view=warehouse&sector=" . urlencode($sector) . "&loc=" . urlencode($loc) . "&msg=added&last_id=" . $last_id);
        exit();
    }


    if ($_POST['action'] === 'rename_zone' && isset($_POST['old_loc']) && isset($_POST['new_loc'])) {
        $old_loc = $_POST['old_loc'];
        $new_loc = trim($_POST['new_loc']);
        $new_status = $_POST['location_status'] ?? 'Idle';

        if (!empty($new_loc)) {
            $conn_wh->beginTransaction();
            try {
                // Check if the new location code already exists
                $stmt_check = $conn_wh->prepare("SELECT COUNT(*) FROM locations WHERE location_code = ?");
                $stmt_check->execute([$new_loc]);
                $exists = $stmt_check->fetchColumn() > 0;

                // Update items in inventory from old to new location code
                $stmt = $conn_wh->prepare("UPDATE inventory SET location_code = ? WHERE location_code = ?");
                $stmt->execute([$new_loc, $old_loc]);

                if ($exists) {
                    // Merge: update status and timestamp of the existing target location, then delete old location entry
                    $stmt_loc = $conn_wh->prepare("UPDATE locations SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE location_code = ?");
                    $stmt_loc->execute([$new_status, $new_loc]);

                    if ($new_loc !== $old_loc) {
                        $stmt_del = $conn_wh->prepare("DELETE FROM locations WHERE location_code = ?");
                        $stmt_del->execute([$old_loc]);
                    }
                    $msg = "zone_merged";
                } else {
                    // Rename: target location doesn't exist, we can just update the existing location row
                    $stmt_loc = $conn_wh->prepare("UPDATE locations SET location_code = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE location_code = ?");
                    $stmt_loc->execute([$new_loc, $new_status, $old_loc]);
                    $msg = "zone_updated";
                }

                $conn_wh->commit();
                $redir_zone = $_POST['active_zone'] ?? $_GET['zone'] ?? '';
                $redirect_url = "index.php?view=warehouse&sector=" . urlencode($selected_sector) . "&msg=" . $msg;
                if (!empty($redir_zone)) {
                    $redirect_url .= "&zone=" . urlencode($redir_zone);
                }
                header("Location: " . $redirect_url);
                exit();
            } catch (Exception $e) {
                $conn_wh->rollBack();
                die("Failed to update zone: " . $e->getMessage());
            }
        }
    }

    if ($_POST['action'] === 'rename_working_zone' && isset($_POST['old_zone_name']) && isset($_POST['new_zone_name'])) {
        $old_zone = $_POST['old_zone_name'];
        $new_zone = trim($_POST['new_zone_name']);

        if (!empty($new_zone)) {
            $conn_wh->beginTransaction();
            try {
                // Check if the new working zone name already exists
                $stmt_check = $conn_wh->prepare("SELECT COUNT(*) FROM working_zones WHERE name = ?");
                $stmt_check->execute([$new_zone]);
                $exists = $stmt_check->fetchColumn() > 0;

                if ($exists) {
                    // Merge: If target zone exists, we update locations' working_zone_name to the new zone
                    $stmt_loc = $conn_wh->prepare("UPDATE locations SET working_zone_name = ? WHERE working_zone_name = ?");
                    $stmt_loc->execute([$new_zone, $old_zone]);

                    // Delete the old working zone as it's now empty/merged
                    if ($new_zone !== $old_zone) {
                        $stmt_del = $conn_wh->prepare("DELETE FROM working_zones WHERE name = ?");
                        $stmt_del->execute([$old_zone]);
                    }
                    $msg = "working_zone_merged";
                } else {
                    // Rename: normal update of the working zone name
                    $stmt = $conn_wh->prepare("UPDATE working_zones SET name = ? WHERE name = ?");
                    $stmt->execute([$new_zone, $old_zone]);

                    // Update locations pointing to the old zone name
                    $stmt_loc = $conn_wh->prepare("UPDATE locations SET working_zone_name = ? WHERE working_zone_name = ?");
                    $stmt_loc->execute([$new_zone, $old_zone]);
                    $msg = "working_zone_updated";
                }

                $conn_wh->commit();
                header("Location: index.php?view=warehouse&sector=" . urlencode($selected_sector) . "&msg=" . $msg);
                exit();
            } catch (Exception $e) {
                $conn_wh->rollBack();
                die("Failed to update working zone: " . $e->getMessage());
            }
        }
    }

    if ($_POST['action'] === 'delete_working_zone' && isset($_POST['zone_name'])) {
        $zone_name = $_POST['zone_name'];
        $conn_wh->beginTransaction();
        try {
            $stmt_inv = $conn_wh->prepare("
                DELETE FROM inventory
                WHERE location_code IN (
                    SELECT location_code FROM locations WHERE working_zone_name = ?
                )
            ");
            $stmt_inv->execute([$zone_name]);

            $stmt_loc = $conn_wh->prepare("DELETE FROM locations WHERE working_zone_name = ?");
            $stmt_loc->execute([$zone_name]);

            $stmt_wz = $conn_wh->prepare("DELETE FROM working_zones WHERE name = ?");
            $stmt_wz->execute([$zone_name]);

            $conn_wh->commit();
            header("Location: index.php?view=warehouse&sector=" . urlencode($selected_sector) . "&msg=working_zone_deleted");
            exit();
        } catch (Exception $e) {
            $conn_wh->rollBack();
            die("Delete working zone failed: " . $e->getMessage());
        }
    }

    if ($_POST['action'] === 'add_working_zone' && isset($_POST['zone_name'])) {
        $zone_name = trim($_POST['zone_name']);
        if (!empty($zone_name)) {
            $stmt = $conn_wh->prepare("INSERT OR IGNORE INTO working_zones (name) VALUES (?)");
            $stmt->execute([$zone_name]);
        }
        header("Location: index.php?view=warehouse&sector=" . urlencode($selected_sector) . "&msg=zone_added");
        exit();
    }

    if ($_POST['action'] === 'add_sub_zone' && isset($_POST['shelf_name'])) {
        $shelf_name = trim($_POST['shelf_name']);
        $parent_zone = $_POST['parent_zone'] ?? 'General';
        if (!empty($shelf_name)) {
            $stmt_check = $conn_wh->prepare("SELECT COUNT(*) FROM locations WHERE location_code = ?");
            $stmt_check->execute([$shelf_name]);
            if ($stmt_check->fetchColumn() > 0) {
                $stmt = $conn_wh->prepare("UPDATE locations SET working_zone_name = ?, updated_at = CURRENT_TIMESTAMP WHERE location_code = ?");
                $stmt->execute([$parent_zone, $shelf_name]);
            } else {
                $stmt = $conn_wh->prepare("INSERT INTO locations (location_code, status, working_zone_name) VALUES (?, 'Idle', ?)");
                $stmt->execute([$shelf_name, $parent_zone]);
            }
        }
        header("Location: index.php?view=warehouse&sector=" . urlencode($selected_sector) . "&zone=" . urlencode($parent_zone) . "&msg=shelf_added");
        exit();
    }

    if ($_POST['action'] === 'add_location_status' && isset($_POST['status_name'])) {
        $name = trim($_POST['status_name']);
        $color = $_POST['status_color'] ?? '#64748b';
        if (!empty($name)) {
            $stmt = $conn_wh->prepare("INSERT OR IGNORE INTO location_statuses (name, color, is_default, location_code) VALUES (?, ?, 0, NULL)");
            $stmt->execute([$name, $color]);
        }
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'name' => $name, 'color' => $color]);
            exit();
        }
        header("Location: index.php?view=warehouse&sector=" . urlencode($selected_sector) . "&msg=status_added");
        exit();
    }

    if ($_POST['action'] === 'delete_zone' && isset($_POST['old_loc'])) {
        $old_loc = $_POST['old_loc'];
        $conn_wh->beginTransaction();
        try {
            // Bulk delete items
            $stmt = $conn_wh->prepare("DELETE FROM inventory WHERE location_code = ?");
            $stmt->execute([$old_loc]);

            // Delete location tracking
            $stmt_loc = $conn_wh->prepare("DELETE FROM locations WHERE location_code = ?");
            $stmt_loc->execute([$old_loc]);

            $conn_wh->commit();
            $redir_zone = $_POST['active_zone'] ?? $_GET['zone'] ?? '';
            $redirect_url = "index.php?view=warehouse&sector=" . urlencode($selected_sector) . "&msg=zone_deleted";
            if (!empty($redir_zone)) {
                $redirect_url .= "&zone=" . urlencode($redir_zone);
            }
            header("Location: " . $redirect_url);
            exit();
        } catch (Exception $e) {
            $conn_wh->rollBack();
            die("Delete failed: " . $e->getMessage());
        }
    }

    if ($_POST['action'] === 'add_inventory' || $_POST['action'] === 'edit_inventory') {
        $brand = $_POST['brand'];
        $model = $_POST['model'];
        $loc = $_POST['location_code'];
        $qty = (int) $_POST['quantity'];
        $price = (float) ($_POST['price'] ?? 0.00);
        $sector = $_POST['sector'];

        // Dynamic Specs mapping based on sector
        $specs = [];
        if ($sector === 'Laptops') {
            $specs = [
                'cpu' => $_POST['cpu'] ?? '',
                'gpu' => $_POST['gpu'] ?? '',
                'ram' => $_POST['ram'] ?? '',
                'storage' => $_POST['storage'] ?? '',
                'battery' => $_POST['battery'] ?? '',
                'windows' => $_POST['windows'] ?? '',
                'series' => $_POST['series'] ?? '',
                'gen' => $_POST['gen'] ?? '',
                'bios' => $_POST['bios'] ?? '',
                'condition' => $_POST['condition'] ?? '',
                'notes' => $_POST['notes'] ?? ''
            ];
        } elseif ($sector === 'Gaming') {
            $specs = [
                'category' => $_POST['gaming_category'] ?? 'Consoles',
                'series' => $_POST['series'] ?? '',
                'condition' => $_POST['condition'] ?? '',
                'notes' => $_POST['notes'] ?? '',
                'ram' => $_POST['ram'] ?? '',
                'storage' => $_POST['storage'] ?? '',
                'cpu' => $_POST['cpu'] ?? '',
                'gpu' => $_POST['gpu'] ?? ''
            ];
        } elseif ($sector === 'Desktops') {
            $specs = [
                'cpu_gen' => $_POST['cpu_gen'] ?? '',
                'condition' => $_POST['condition'] ?? '',
                'notes' => $_POST['notes'] ?? ''
            ];
        } else {
            $specs = ['condition' => $_POST['condition'] ?? '', 'notes' => $_POST['notes'] ?? ''];
        }

        $specs_json = json_encode($specs);

        if ($_POST['action'] === 'edit_inventory' && isset($_POST['item_id'])) {
            // Concurrency Check: Verify if the record was updated by someone else
            $last_known = $_POST['last_updated_at'] ?? '';
            $stmt_check = $conn_wh->prepare("SELECT updated_at FROM inventory WHERE id = ?");
            $stmt_check->execute([$_POST['item_id']]);
            $current_ts = $stmt_check->fetchColumn();

            if ($last_known && $current_ts && $last_known !== $current_ts) {
                $error_msg = "CONCURRENCY_ERROR";
                header("Location: index.php?view=warehouse&sector=" . urlencode($sector) . "&loc=" . urlencode($loc) . "&msg=" . $error_msg . "#wh-form-title");
                exit();
            }

            $stmt = $conn_wh->prepare("UPDATE inventory SET brand=?, model=?, specs_json=?, quantity=?, price=?, last_updated_by=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
            $stmt->execute([$brand, $model, $specs_json, $qty, $price, $current_user, $_POST['item_id']]);
            $last_id = $_POST['item_id'];
        } else {
            $stmt = $conn_wh->prepare("INSERT INTO inventory (user_owner, sector, location_code, brand, model, specs_json, quantity, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$current_user, $sector, $loc, $brand, $model, $specs_json, $qty, $price]);
            $last_id = $conn_wh->lastInsertId();
        }
        $msg = ($_POST['action'] === 'edit_inventory') ? 'updated' : 'added';
        $hash = ($msg === 'added') ? '#wh-main-form' : '#inventory-list';
        header("Location: index.php?view=warehouse&sector=" . urlencode($sector) . "&loc=" . urlencode($loc) . "&msg=" . $msg . "&last_id=" . $last_id . $hash);
        exit();
    }

    if ($_POST['action'] === 'upload_location_photo') {
        $loc = $_POST['location_code'] ?? '';
        $sector = $_POST['sector'] ?? 'Laptops';
        $category = $_POST['category'] ?? 'General';
        $redirect_to = $_POST['redirect_to'] ?? 'location';
        $active_zone = $_POST['active_zone'] ?? '';

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            require_once __DIR__ . '/../../core/MediaManager.php';
            try {
                $mediaManager = new MediaManager($conn_wh);
                $mediaManager->processUpload(
                    $_FILES['photo']['tmp_name'],
                    $_FILES['photo']['name'],
                    $loc,
                    $sector,
                    $category,
                    $current_user
                );
                $msg = 'photo_uploaded';
            } catch (Exception $e) {
                $msg = 'photo_error&err=' . urlencode($e->getMessage());
            }
        } else {
            $msg = 'photo_error&err=No+file+selected';
        }

        if ($redirect_to === 'zone' && !empty($active_zone)) {
            header("Location: index.php?view=warehouse&sector=" . urlencode($sector) . "&zone=" . urlencode($active_zone) . "&msg=" . $msg);
        } else {
            header("Location: index.php?view=warehouse&sector=" . urlencode($sector) . "&loc=" . urlencode($loc) . "&msg=" . $msg);
        }
        exit();
    }

    if ($_POST['action'] === 'delete_location_photo') {
        $photo_id = (int)($_POST['photo_id'] ?? 0);
        $loc = $_POST['location_code'] ?? '';
        $sector = $_POST['sector'] ?? 'Laptops';
        $redirect_to = $_POST['redirect_to'] ?? 'location';
        $active_zone = $_POST['active_zone'] ?? '';

        if ($photo_id > 0) {
            try {
                require_once __DIR__ . '/../../core/MediaManager.php';
                $mediaManager = new MediaManager($conn_wh);
                $deleted = $mediaManager->deletePhoto($photo_id);
                $msg = $deleted ? 'photo_deleted' : 'photo_not_found';
            } catch (Exception $e) {
                $msg = 'photo_error&err=' . urlencode($e->getMessage());
            }
        } else {
            $msg = 'photo_error&err=Invalid+photo+ID';
        }

        if ($redirect_to === 'zone' && !empty($active_zone)) {
            header("Location: index.php?view=warehouse&sector=" . urlencode($sector) . "&zone=" . urlencode($active_zone) . "&msg=" . $msg);
        } else {
            header("Location: index.php?view=warehouse&sector=" . urlencode($sector) . "&loc=" . urlencode($loc) . "&msg=" . $msg);
        }
        exit();
    }
}
