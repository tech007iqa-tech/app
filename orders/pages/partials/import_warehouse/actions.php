<?php
/**
 * Warehouse Import Controllers & Actions Partial
 * Handles AJAX inline edits, CSV upload validation, bulk database insertion, and cancel requests.
 */

// Phase 0: Handle AJAX cell updates
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_GET['action']) && $_GET['action'] === 'update_import_cell') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $rowIndex = isset($input['row_index']) ? (int)$input['row_index'] : -1;
    $field = $input['field'] ?? '';
    $val = $input['val'] ?? '';

    if ($rowIndex >= 0 && isset($_SESSION['import_rows'][$rowIndex])) {
        if (in_array($field, ['date', 'qty', 'location'])) {
            if ($field === 'qty') {
                $_SESSION['import_rows'][$rowIndex]['qty'] = $val;
            } else {
                $_SESSION['import_rows'][$rowIndex][$field] = $val;
            }
        } else {
            $_SESSION['import_rows'][$rowIndex]['parsed'][$field] = $val;
        }

        $row = $_SESSION['import_rows'][$rowIndex];
        $rowErrors = [];
        if (empty(trim($row['item']))) {
            $rowErrors[] = "Item is empty";
        }
        if (empty(trim($row['location']))) {
            $rowErrors[] = "Location is empty";
        }
        $qtyVal = filter_var($row['qty'], FILTER_VALIDATE_INT);
        if ($qtyVal === false || $qtyVal <= 0) {
            $rowErrors[] = "QTY must be a positive integer";
        }
        if (empty(trim($row['date']))) {
            $rowErrors[] = "Date is empty";
        }

        $_SESSION['import_rows'][$rowIndex]['errors'] = $rowErrors;
        $_SESSION['import_rows'][$rowIndex]['status'] = empty($rowErrors) ? 'Accept' : 'Reject';

        $total = count($_SESSION['import_rows']);
        $accepted = 0;
        $rejected = 0;
        foreach ($_SESSION['import_rows'] as $r) {
            if ($r['status'] === 'Accept') $accepted++;
            else $rejected++;
        }

        echo json_encode([
            'success' => true,
            'status' => $_SESSION['import_rows'][$rowIndex]['status'],
            'errors' => $rowErrors,
            'accepted' => $accepted,
            'rejected' => $rejected,
            'total' => $total
        ]);
        exit();
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid row index or session expired']);
        exit();
    }
}

$current_user = $_SESSION['username'] ?? 'Admin';
$message = '';
$error = '';
$preview_mode = false;
$rows = [];
$acceptedCount = 0;
$rejectedCount = 0;
$zone_locations_map = [];
$working_zones = [];
$all_locations = [];
$available_sectors = [];
$suggested_zone = '';

// Read URL parameters for sector, location, and zone pre-selection
$active_param_sector = $_GET['sector'] ?? ($_SESSION['import_sector'] ?? 'Gaming');
$active_param_loc = $_GET['loc'] ?? '';
$active_param_zone = $_GET['zone'] ?? '';

// Unconditionally pre-fetch available sectors, working zones, and existing locations
try {
    $stmt_sec = $conn_wh->query("SELECT name FROM sectors ORDER BY name ASC");
    $available_sectors = $stmt_sec->fetchAll(PDO::FETCH_COLUMN);
    if (empty($available_sectors)) {
        $available_sectors = ['Gaming', 'Laptops', 'Desktops', 'Electronics'];
    }

    $stmt_zones = $conn_wh->query("SELECT name FROM working_zones ORDER BY name ASC");
    $working_zones = $stmt_zones->fetchAll(PDO::FETCH_COLUMN);

    $stmt_locs = $conn_wh->query("SELECT location_code, working_zone_name, status FROM locations ORDER BY location_code ASC");
    $all_locations = $stmt_locs->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all_locations as $row_loc) {
        $z = $row_loc['working_zone_name'] ?: 'General';
        $zone_locations_map[$z][] = $row_loc['location_code'];
    }

    // Auto-resolve working zone for active URL location if not provided
    if (!empty($active_param_loc) && empty($active_param_zone)) {
        foreach ($all_locations as $loc_entry) {
            if (strcasecmp($loc_entry['location_code'], $active_param_loc) === 0) {
                $active_param_zone = $loc_entry['working_zone_name'];
                break;
            }
        }
        if (empty($active_param_zone)) {
            if (preg_match('/^([a-zA-Z]+)/u', $active_param_loc, $m)) {
                $active_param_zone = 'Zone ' . strtoupper($m[1]);
            } else {
                $active_param_zone = 'General';
            }
        }
    }
} catch (Exception $e) {
    $available_sectors = ['Gaming', 'Laptops', 'Desktops', 'Electronics'];
}

// Phase 1b: Handle Clipboard Paste Submission for Verification Preview
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'preview_clipboard') {
    $rawText = $_POST['clipboard_text'] ?? '';
    $selected_sector = trim($_POST['sector'] ?? 'Gaming');
    $defaultLoc = strtoupper(trim($_POST['location'] ?? 'Inbound'));
    if (empty($defaultLoc)) $defaultLoc = 'Inbound';

    $_SESSION['import_sector'] = $selected_sector;

    if (!empty(trim($rawText))) {
        $lines = preg_split('/\r?\n/', trim($rawText));
        $rows = [];
        $preview_mode = true;
        $acceptedCount = 0;
        $rejectedCount = 0;

        // Detect delimiter
        $sample = implode("\n", array_slice($lines, 0, 5));
        $tabCount = substr_count($sample, "\t");
        $commaCount = substr_count($sample, ",");
        $delim = ($commaCount > $tabCount) ? ',' : "\t";

        $parsedLines = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            if ($delim === "\t") {
                $parsedLines[] = array_map(function($v) {
                    $s = trim($v);
                    if (str_starts_with($s, '"') && str_ends_with($s, '"')) $s = substr($s, 1, -1);
                    return trim($s);
                }, explode("\t", $line));
            } else {
                $parsedLines[] = str_getcsv($line);
            }
        }

        if (!empty($parsedLines)) {
            $first = $parsedLines[0];
            $brandIdx = -1; $modelIdx = -1; $seriesIdx = -1; $cpuIdx = -1; $descIdx = -1; $priceIdx = -1; $qtyIdx = -1;
            $hasHeader = false;

            foreach ($first as $idx => $col) {
                $c = strtolower(trim($col));
                if (in_array($c, ['brand', 'make'])) { $brandIdx = $idx; $hasHeader = true; }
                elseif ($c === 'model') { $modelIdx = $idx; $hasHeader = true; }
                elseif ($c === 'series') { $seriesIdx = $idx; $hasHeader = true; }
                elseif (strpos($c, 'cpu') !== false || strpos($c, 'gen') !== false) { $cpuIdx = $idx; $hasHeader = true; }
                elseif (strpos($c, 'desc') !== false) { $descIdx = $idx; $hasHeader = true; }
                elseif (strpos($c, 'price') !== false || strpos($c, 'cost') !== false) { $priceIdx = $idx; $hasHeader = true; }
                elseif (strpos($c, 'qty') !== false || strpos($c, 'quantity') !== false) { $qtyIdx = $idx; $hasHeader = true; }
            }

            $dataRows = $hasHeader ? array_slice($parsedLines, 1) : $parsedLines;
            if (!$hasHeader) {
                $colCount = count($first);
                if ($colCount >= 7) {
                    $brandIdx = 0; $modelIdx = 1; $seriesIdx = 2; $cpuIdx = 3; $descIdx = 4; $priceIdx = 5; $qtyIdx = 6;
                } elseif ($colCount === 6) {
                    $brandIdx = 0; $modelIdx = 1; $seriesIdx = 2; $descIdx = 3; $priceIdx = 4; $qtyIdx = 5;
                }
            }

            foreach ($dataRows as $cols) {
                if (count($cols) < 2) continue;
                $brand = $brandIdx !== -1 ? trim($cols[$brandIdx] ?? '') : '';
                $model = $modelIdx !== -1 ? trim($cols[$modelIdx] ?? '') : '';
                $series = $seriesIdx !== -1 ? trim($cols[$seriesIdx] ?? '') : '';
                $cpu = $cpuIdx !== -1 ? trim($cols[$cpuIdx] ?? '') : '';
                $desc = $descIdx !== -1 ? trim($cols[$descIdx] ?? '') : '';
                $rawPrice = $priceIdx !== -1 ? ($cols[$priceIdx] ?? '0') : '0';
                $rawQty = $qtyIdx !== -1 ? ($cols[$qtyIdx] ?? '1') : '1';

                if (empty($brand) && empty($model)) continue;
                if (empty($model)) $model = $series ?: 'Console';
                if (empty($brand)) $brand = 'Generic';

                $price = Security::sanitize_float($rawPrice);
                $qty = Security::sanitize_int($rawQty);
                if ($qty <= 0) $qty = 1;

                $itemDesc = trim("$brand $model $series $desc");
                $loc = $defaultLoc;

                $condition = 'Untested';
                if (stripos($desc, 'not working') !== false || stripos($desc, 'for parts') !== false) {
                    $condition = 'For parts';
                }

                $parsed = [
                    'brand' => $brand,
                    'model' => $model,
                    'series' => $series,
                    'cpu' => $cpu,
                    'gen' => '',
                    'ram' => '',
                    'storage' => '',
                    'battery' => '',
                    'condition' => $condition,
                    'price' => $price
                ];

                $acceptedCount++;
                $rows[] = [
                    'status' => 'Accept',
                    'errors' => [],
                    'date' => date('Y-m-d'),
                    'qty' => $qty,
                    'item' => $itemDesc,
                    'serial' => '',
                    'location' => $loc,
                    'notes' => $desc,
                    'parsed' => $parsed
                ];
            }

            $_SESSION['import_rows'] = $rows;
        } else {
            $error = "No readable rows found in pasted text.";
        }
    } else {
        $error = "Pasted text was empty.";
    }
}

// Phase 1: Handle File Upload & Validation Preview
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_FILES['inventory_csv'])) {
    $file = $_FILES['inventory_csv'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($file['tmp_name'], 'r');

        if ($handle !== false) {
            $header = fgetcsv($handle);
            if ($header) {
                // Map headers (case-insensitive)
                $mapping = [];
                foreach ($header as $index => $col) {
                    $mapping[trim(strtolower($col))] = $index;
                }

                // Required headers Date| QTY| Item| Serial| location | notes
                $required = ['date', 'qty', 'item', 'serial', 'location', 'notes'];
                $missing = [];
                foreach ($required as $req) {
                    if (!isset($mapping[$req])) {
                        $missing[] = ucfirst($req);
                    }
                }

                if (!empty($missing)) {
                    $error = "Missing required columns in CSV: " . implode(', ', $missing) . ". Header must contain: Date, QTY, Item, Serial, location, notes.";
                } else {
                    $preview_mode = true;
                    while (($data = fgetcsv($handle)) !== false) {
                        if (count($data) < count($required)) continue;

                        $rawDate = $data[$mapping['date']] ?? '';
                        $rawQty = $data[$mapping['qty']] ?? '';
                        $rawItem = $data[$mapping['item']] ?? '';
                        $rawSerial = $data[$mapping['serial']] ?? '';
                        $rawLoc = $data[$mapping['location']] ?? '';
                        $rawNotes = $data[$mapping['notes']] ?? '';

                        $rowErrors = [];
                        if (empty(trim($rawItem))) {
                            $rowErrors[] = "Item is empty";
                        }
                        if (empty(trim($rawLoc))) {
                            $rowErrors[] = "Location is empty";
                        }
                        $qtyVal = filter_var($rawQty, FILTER_VALIDATE_INT);
                        if ($qtyVal === false || $qtyVal <= 0) {
                            $rowErrors[] = "QTY must be a positive integer";
                        }
                        if (empty(trim($rawDate))) {
                            $rowErrors[] = "Date is empty";
                        }

                        $parsed = parseItemString($rawItem, $rawNotes, $rawSerial);

                        $finalNotes = trim($rawNotes);
                        if (!empty(trim($rawSerial))) {
                            $finalNotes = "SN: " . trim($rawSerial) . ($finalNotes ? " - " . $finalNotes : "");
                        }

                        $status = empty($rowErrors) ? 'Accept' : 'Reject';
                        if ($status === 'Accept') {
                            $acceptedCount++;
                        } else {
                            $rejectedCount++;
                        }

                        $rows[] = [
                            'status' => $status,
                            'errors' => $rowErrors,
                            'date' => $rawDate,
                            'qty' => $qtyVal !== false ? $qtyVal : $rawQty,
                            'item' => $rawItem,
                            'serial' => $rawSerial,
                            'location' => $rawLoc,
                            'notes' => $finalNotes,
                            'parsed' => $parsed
                        ];
                    }
                    $_SESSION['import_rows'] = $rows;
                }
            } else {
                $error = "The uploaded file is empty.";
            }
            fclose($handle);
        }
    } else {
        $error = "File upload error code: " . $file['error'];
    }
}

// Phase 2: Confirm and Save to Database
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_import') {
    if (!empty($_SESSION['import_rows'])) {
        $override_zone = null;
        if (!empty($_POST['override_zone_select'])) {
            if ($_POST['override_zone_select'] === '__NEW_ZONE__' && !empty($_POST['override_zone_custom'])) {
                $override_zone = trim($_POST['override_zone_custom']);
            } else if ($_POST['override_zone_select'] !== '__NEW_ZONE__') {
                $override_zone = trim($_POST['override_zone_select']);
            }
        }

        $override_loc = null;
        if (!empty($_POST['override_location_select'])) {
            if ($_POST['override_location_select'] === '__NEW_LOC__' && !empty($_POST['override_location_custom'])) {
                $override_loc = trim(strtoupper($_POST['override_location_custom']));
            } else if ($_POST['override_location_select'] !== '__NEW_LOC__') {
                $override_loc = trim($_POST['override_location_select']);
            }
        } else if (!empty($_POST['override_location_custom'])) {
            $override_loc = trim(strtoupper($_POST['override_location_custom']));
        }

        $conn_wh->beginTransaction();
        try {
            $count = 0;
            foreach ($_SESSION['import_rows'] as $row) {
                if ($row['status'] === 'Accept') {
                    $loc = ($override_loc !== null) ? $override_loc : strtoupper(trim($row['location']));
                    getOrCreateLocation($conn_wh, $loc, $override_zone);

                    $brand = $row['parsed']['brand'];
                    $model = $row['parsed']['model'];
                    $sector = $_SESSION['import_sector'] ?? 'Gaming';
                    $qty = (int)$row['qty'];
                    $price = (float)$row['parsed']['price'];

                    $specs = [
                        'series' => $row['parsed']['series'] ?? '',
                        'cpu' => $row['parsed']['cpu'],
                        'gen' => $row['parsed']['gen'],
                        'ram' => $row['parsed']['ram'],
                        'storage' => $row['parsed']['storage'],
                        'battery' => $row['parsed']['battery'],
                        'condition' => $row['parsed']['condition'],
                        'notes' => $row['notes']
                    ];
                    $specs_json = json_encode($specs);

                    $stmt = $conn_wh->prepare("INSERT INTO inventory (user_owner, sector, location_code, brand, model, specs_json, quantity, price, last_updated_by)
                                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$current_user, $sector, $loc, $brand, $model, $specs_json, $qty, $price, $current_user]);
                    $count++;
                }
            }
            $conn_wh->commit();
            $message = "Successfully imported $count inventory items into the warehouse. New zones/locations were registered automatically.";
            unset($_SESSION['import_rows']);
        } catch (Exception $e) {
            $conn_wh->rollBack();
            $error = "Import failed: " . $e->getMessage();
        }
    } else {
        $error = "No valid data to import.";
    }
}

// Phase 3: Cancel Import
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_import') {
    unset($_SESSION['import_rows']);
    header("Location: index.php?view=import_warehouse");
    exit();
}

// Pre-fetch working zones and locations map for preview mode
$display_rows = $_SESSION['import_rows'] ?? $rows;
$total = count($display_rows);
$accepted = 0;
$rejected = 0;
foreach ($display_rows as $r) {
    if ($r['status'] === 'Accept') $accepted++;
    else $rejected++;
}

if ($preview_mode || !empty($_SESSION['import_rows'])) {
    try {
        $sample_location = '';
        foreach ($display_rows as $row) {
            if ($row['status'] === 'Accept' && !empty($row['location'])) {
                $sample_location = trim($row['location']);
                break;
            }
        }
        if ($sample_location !== '') {
            $stmt_suggest = $conn_wh->prepare("SELECT working_zone_name FROM locations WHERE location_code = ?");
            $stmt_suggest->execute([$sample_location]);
            $suggested_zone = $stmt_suggest->fetchColumn();

            if (!$suggested_zone) {
                if (preg_match('/^([a-zA-Z]+)/u', $sample_location, $matches)) {
                    $prefix = strtoupper($matches[1]);
                    foreach ($working_zones as $wz) {
                        if (strcasecmp($wz, $prefix) === 0 || strcasecmp($wz, 'Zone ' . $prefix) === 0) {
                            $suggested_zone = $wz;
                            break;
                        }
                    }
                    if (!$suggested_zone) {
                        $suggested_zone = 'Zone ' . $prefix;
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Fallback silently
    }
}

