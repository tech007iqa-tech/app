<?php
/**
 * Bulk Sales Import from Excel
 * Scans root directory for B2B Sales .xlsx files, parses all worksheets/tabs,
 * maps them to customers/orders/items with correct dates, and imports them sheet-by-sheet.
 */
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/Audit.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/UI.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_role = $_SESSION['role'] ?? 'Operator';
if ($user_role !== 'Admin') {
    die("Access denied. Admin privileges required.");
}

$message = '';
$error = '';

// Excel serial date converter helper
function excelDateToPhp($serial) {
    $serial = (float)$serial;
    if ($serial > 60) {
        $days = $serial - 25569;
    } else {
        $days = $serial - 25568;
    }
    $timestamp = round($days * 86400);
    return date('Y-m-d H:i:s', $timestamp);
}

// Directory deletion helper
function deleteImportTempDir($dirPath) {
    if (!is_dir($dirPath)) {
        return;
    }
    $files = array_diff(scandir($dirPath), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dirPath/$file")) ? deleteImportTempDir("$dirPath/$file") : unlink("$dirPath/$file");
    }
    rmdir($dirPath);
}

// Function to extract and parse metadata/items from all sheets in an .xlsx file
function parseXlsxData($filePath) {
    $random = bin2hex(random_bytes(4));
    $tempDir = __DIR__ . '/../../temp_import_' . $random;
    $zipFile = __DIR__ . '/../../temp_import_' . $random . '.zip';

    // Copy to .zip and extract using PowerShell
    if (!copy($filePath, $zipFile)) {
        return ['error' => "Failed to copy file to temp zip."];
    }

    $cmd = "powershell -Command \"Expand-Archive -Path '" . str_replace('/', '\\', $zipFile) . "' -DestinationPath '" . str_replace('/', '\\', $tempDir) . "' -Force\"";
    shell_exec($cmd);

    if (file_exists($zipFile)) {
        unlink($zipFile);
    }

    if (!is_dir($tempDir)) {
        return ['error' => "PowerShell extraction failed."];
    }

    // 1. Read sheet relationships and metadata
    $workbookPath = $tempDir . '/xl/workbook.xml';
    $relsPath = $tempDir . '/xl/_rels/workbook.xml.rels';
    if (!file_exists($workbookPath) || !file_exists($relsPath)) {
        deleteImportTempDir($tempDir);
        return ['error' => "Excel file is missing workbook schemas."];
    }

    // Read sheets definitions
    $workbookXml = file_get_contents($workbookPath);
    $wb = new SimpleXMLElement($workbookXml);
    $sheetsMap = [];
    foreach ($wb->sheets->sheet as $sh) {
        $sheetName = (string)$sh['name'];
        $rId = '';
        foreach ($sh->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships') as $key => $val) {
            if ($key === 'id') $rId = (string)$val;
        }
        if (empty($rId)) {
            foreach ($sh->attributes('r', true) as $key => $val) {
                if ($key === 'id') $rId = (string)$val;
            }
        }
        $sheetsMap[$rId] = [
            'name' => $sheetName,
            'target' => ''
        ];
    }

    // Read relationships to get sheet XML paths
    $relsXml = file_get_contents($relsPath);
    $rels = new SimpleXMLElement($relsXml);
    foreach ($rels->Relationship as $rel) {
        $id = (string)$rel['Id'];
        $target = (string)$rel['Target'];
        if (isset($sheetsMap[$id])) {
            $sheetsMap[$id]['target'] = $target;
        }
    }

    // 2. Read shared strings
    $sharedStrings = [];
    $stringsPath = $tempDir . '/xl/sharedStrings.xml';
    if (file_exists($stringsPath)) {
        $stringsXml = file_get_contents($stringsPath);
        $xml = new SimpleXMLElement($stringsXml);
        foreach ($xml->si as $si) {
            if (isset($si->t)) {
                $sharedStrings[] = (string)$si->t;
            } else if (isset($si->r)) {
                $text = '';
                foreach ($si->r as $r) {
                    $text .= (string)$r->t;
                }
                $sharedStrings[] = $text;
            } else {
                $sharedStrings[] = '';
            }
        }
    }

    // 3. Parse each sheet
    $parsedSheets = [];
    foreach ($sheetsMap as $sh) {
        $sheetFile = $tempDir . '/xl/' . $sh['target'];
        if (!file_exists($sheetFile)) {
            continue;
        }

        $sheetXml = file_get_contents($sheetFile);
        $xml = new SimpleXMLElement($sheetXml);
        $rows = [];

        foreach ($xml->sheetData->row as $row) {
            $rowIndex = (int)$row['r'];
            $rowData = [];
            foreach ($row->c as $c) {
                $cellRef = (string)$c['r'];
                preg_match('/^[A-Z]+/', $cellRef, $matches);
                if (empty($matches)) continue;
                $col = $matches[0];

                $val = '';
                if (isset($c->v)) {
                    $val = (string)$c->v;
                    if (isset($c['t']) && (string)$c['t'] === 's') {
                        $val = $sharedStrings[(int)$val] ?? '';
                    }
                }
                $rowData[$col] = $val;
            }
            $rows[$rowIndex] = $rowData;
        }

        // Extract metadata from this sheet
        $customerName = trim($rows[3]['B'] ?? '');
        $excelDateVal = trim($rows[4]['B'] ?? '');
        $orderNoVal = trim($rows[5]['B'] ?? '');

        // 1. Dynamically locate the header row (between row 6 and 10) by checking for keywords
        $headerRowIndex = 8; // default
        $maxHeaderMatches = 0;
        $headerKeywords = ['type', 'brand', 'model', 'cpu', 'status', 'price', 'qty', 'total', 'summary', 'note', 'check again'];

        for ($r = 6; $r <= 10; $r++) {
            if (!isset($rows[$r])) continue;
            $matches = 0;
            foreach ($rows[$r] as $col => $val) {
                $valClean = trim(strtolower($val));
                foreach ($headerKeywords as $kw) {
                    if (strpos($valClean, $kw) !== false) {
                        $matches++;
                    }
                }
            }
            if ($matches > $maxHeaderMatches) {
                $maxHeaderMatches = $matches;
                $headerRowIndex = $r;
            }
        }

        $headerRow = $rows[$headerRowIndex] ?? [];
        $colMapping = [
            'type' => null,
            'brand' => null,
            'model' => null,
            'cpu' => null,
            'status' => null,
            'price' => null,
            'qty' => null,
            'summary' => null,
            'notes' => null
        ];

        foreach ($headerRow as $colLetter => $cellValue) {
            $cleanedVal = trim(strtolower($cellValue));
            if ($cleanedVal === 'type' || $cleanedVal === 'sector' || $cleanedVal === 'category') {
                $colMapping['type'] = $colLetter;
            } else if ($cleanedVal === 'brand' || $cleanedVal === 'make' || $cleanedVal === 'manufacturer') {
                $colMapping['brand'] = $colLetter;
            } else if ($cleanedVal === 'model' || $cleanedVal === 'model name' || $cleanedVal === 'model number') {
                $colMapping['model'] = $colLetter;
            } else if (strpos($cleanedVal, 'cpu') !== false || strpos($cleanedVal, 'processor') !== false || strpos($cleanedVal, 'gen') !== false) {
                $colMapping['cpu'] = $colLetter;
            } else if ($cleanedVal === 'status' || $cleanedVal === 'condition' || $cleanedVal === 'state') {
                $colMapping['status'] = $colLetter;
            } else if ($cleanedVal === 'price' || $cleanedVal === 'rate' || $cleanedVal === 'cost' || strpos($cleanedVal, 'unit price') !== false || strpos($cleanedVal, 'price') !== false) {
                $colMapping['price'] = $colLetter;
            } else if ($cleanedVal === 'qty' || $cleanedVal === 'quantity' || $cleanedVal === 'count' || $cleanedVal === 'pcs' || $cleanedVal === 'pieces') {
                $colMapping['qty'] = $colLetter;
            } else if (strpos($cleanedVal, 'summar') !== false || strpos($cleanedVal, 'desc') !== false || $cleanedVal === 'item' || $cleanedVal === 'check again') {
                $colMapping['summary'] = $colLetter;
            } else if (strpos($cleanedVal, 'note') !== false || strpos($cleanedVal, 'comment') !== false || strpos($cleanedVal, 'remark') !== false) {
                $colMapping['notes'] = $colLetter;
            }
        }

        // Apply standard defaults for missing headers
        if (!$colMapping['type']) $colMapping['type'] = 'A';
        if (!$colMapping['brand']) $colMapping['brand'] = 'B';
        if (!$colMapping['model']) $colMapping['model'] = 'C';
        if (!$colMapping['cpu']) $colMapping['cpu'] = 'D';
        if (!$colMapping['status']) $colMapping['status'] = 'E';
        if (!$colMapping['price']) $colMapping['price'] = 'F';
        if (!$colMapping['qty']) $colMapping['qty'] = 'G';
        if (!$colMapping['summary']) $colMapping['summary'] = 'I';
        if (!$colMapping['notes']) $colMapping['notes'] = 'J';

        // 2. Sample data rows to check for shifted columns/zeros or correct unmapped columns
        $sampleRows = [];
        $headerEndIdx = $headerRowIndex + 1;
        for ($i = $headerEndIdx; $i < $headerEndIdx + 15; $i++) {
            if (isset($rows[$i])) {
                $sampleRows[] = $rows[$i];
            }
        }

        $allCols = [];
        foreach ($sampleRows as $sRow) {
            foreach ($sRow as $col => $val) {
                $allCols[$col][] = trim($val);
            }
        }

        // Validate Price column has numeric data, otherwise search other columns
        $priceCol = $colMapping['price'];
        $priceHasData = false;
        if ($priceCol && isset($allCols[$priceCol])) {
            foreach ($allCols[$priceCol] as $v) {
                if ($v !== '' && (float)preg_replace('/[^-0-9.]/', '', $v) > 0) {
                    $priceHasData = true;
                    break;
                }
            }
        }

        if (!$priceCol || !$priceHasData) {
            foreach ($allCols as $col => $vals) {
                $decimalCount = 0;
                foreach ($vals as $v) {
                    if (empty($v)) continue;
                    $cleanV = preg_replace('/[^-0-9.]/', '', $v);
                    if (str_starts_with($v, '$') || (is_numeric($cleanV) && strpos($v, '.') !== false && (float)$cleanV > 0)) {
                        $decimalCount++;
                    }
                }
                if ($decimalCount >= 1) {
                    $colMapping['price'] = $col;
                    break;
                }
            }
        }

        // Validate QTY column, otherwise classify using small whole integers
        $qtyCol = $colMapping['qty'];
        $qtyHasData = false;
        if ($qtyCol && isset($allCols[$qtyCol])) {
            foreach ($allCols[$qtyCol] as $v) {
                if ($v !== '' && (int)preg_replace('/[^-0-9.]/', '', $v) > 0) {
                    $qtyHasData = true;
                    break;
                }
            }
        }

        if (!$qtyCol || !$qtyHasData) {
            foreach ($allCols as $col => $vals) {
                if ($col === $colMapping['price']) continue;
                $intCount = 0;
                $totalSum = 0;
                foreach ($vals as $v) {
                    if (empty($v)) continue;
                    $cleanV = preg_replace('/[^-0-9.]/', '', $v);
                    if (is_numeric($cleanV) && (int)$cleanV == (float)$cleanV && (int)$cleanV > 0 && (int)$cleanV < 1000) {
                        $intCount++;
                        $totalSum += (int)$cleanV;
                    }
                }
                if ($intCount >= 1) {
                    $avg = $totalSum / $intCount;
                    if ($avg < 150) { // QTY averages are typically small numbers
                        $colMapping['qty'] = $col;
                        break;
                    }
                }
            }
        }

        // Count items first
        $items = [];
        $itemCount = 0;
        foreach ($rows as $idx => $row) {
            if ($idx <= $headerRowIndex) continue; // Loop starts relative to header row index
            $type = trim($row[$colMapping['type']] ?? '');
            $brand = trim($row[$colMapping['brand']] ?? '');
            $model = trim($row[$colMapping['model']] ?? '');
            $cpu = trim($row[$colMapping['cpu']] ?? '');
            $status = trim($row[$colMapping['status']] ?? '');
            $price = trim($row[$colMapping['price']] ?? '');
            $qty = trim($row[$colMapping['qty']] ?? '');
            $summary = trim($row[$colMapping['summary']] ?? '');
            $notes = trim($row[$colMapping['notes']] ?? '');

            // Skip empty rows
            if ($type === '' && $brand === '' && $model === '') {
                continue;
            }

            // Skip header rows or total/summary rows that might be left in the sheet
            $brandLower = strtolower($brand);
            $typeLower = strtolower($type);
            $modelLower = strtolower($model);
            if ($brandLower === 'brand' || $typeLower === 'type' || $modelLower === 'model' ||
                $brandLower === 'total' || $typeLower === 'total' || $modelLower === 'total' ||
                strpos($brandLower, 'total') !== false || strpos($typeLower, 'total') !== false) {
                continue;
            }

            $items[] = [
                'type' => $type,
                'brand' => $brand ?: 'Generic',
                'model' => $model ?: 'Bulk Item',
                'cpu' => $cpu,
                'status' => $status,
                'price' => (float)preg_replace('/[^-0-9.]/', '', $price),
                'qty' => (int)$qty ?: 1,
                'summary' => $summary,
                'notes' => $notes
            ];
            $itemCount++;
        }

        // Skip sheet if it's completely empty / not a sales sheet
        if (empty($customerName) && empty($excelDateVal) && empty($orderNoVal) && $itemCount === 0) {
            continue;
        }

        // Validate fields strictly
        $errors = [];
        if (empty($customerName) || $customerName === 'Name') {
            $errors[] = "Missing Customer/Company Name (Cell B3)";
        }

        $orderDate = null;
        if (empty($excelDateVal) || $excelDateVal === 'Date') {
            $errors[] = "Missing Order Date (Cell B4)";
        } else if (!is_numeric($excelDateVal)) {
            $errors[] = "Invalid Date Format (Cell B4 must be an Excel serial number)";
        } else {
            $orderDate = excelDateToPhp($excelDateVal);
            // Check for unreasonable Excel date (e.g. 1900 leap year date serial zero-ish value)
            if (str_starts_with($orderDate, '1899') || str_starts_with($orderDate, '1900')) {
                $errors[] = "Invalid Order Date (Cell B4: dates before year 2000 are not allowed)";
            }
        }

        if (empty($orderNoVal) || $orderNoVal === 'Order #') {
            $errors[] = "Missing Order Number (Cell B5)";
        }

        if ($itemCount === 0) {
            $errors[] = "No valid inventory items found starting on Row 11";
        }

        $parsedSheets[] = [
            'sheet_name' => $sh['name'],
            'customer' => $customerName ?: 'Unknown',
            'date' => $orderDate ?: date('Y-m-d H:i:s'),
            'order_no' => $orderNoVal ? preg_replace('/[^0-9]/', '', $orderNoVal) : null,
            'items' => $items,
            'item_count' => $itemCount,
            'errors' => $errors
        ];
    }

    deleteImportTempDir($tempDir);
    return $parsedSheets;
}

// Scan root directory for excel files starting with 'B2B Sales'
$rootPath = __DIR__ . '/../../';
$xlsxFiles = [];
$dir = new DirectoryIterator($rootPath);
foreach ($dir as $fileinfo) {
    if (!$fileinfo->isDot() && $fileinfo->isFile() && $fileinfo->getExtension() === 'xlsx') {
        $filename = $fileinfo->getFilename();
        if (str_starts_with($filename, 'B2B Sales')) {
            $xlsxFiles[] = [
                'name' => $filename,
                'path' => $fileinfo->getPathname(),
                'mtime' => $fileinfo->getMTime()
            ];
        }
    }
}

// Cache file metadata in session for fast display
if (!isset($_SESSION['import_metadata_cache_multi'])) {
    $_SESSION['import_metadata_cache_multi'] = [];
}

foreach ($xlsxFiles as &$file) {
    $cacheKey = $file['name'] . '_' . $file['mtime'];
    if (isset($_SESSION['import_metadata_cache_multi'][$cacheKey])) {
        $file['sheets'] = $_SESSION['import_metadata_cache_multi'][$cacheKey];
    } else {
        $sheetsData = parseXlsxData($file['path']);
        if (!isset($sheetsData['error'])) {
            $_SESSION['import_metadata_cache_multi'][$cacheKey] = $sheetsData;
            $file['sheets'] = $sheetsData;
        } else {
            $file['sheets'] = null;
            $file['error'] = $sheetsData['error'];
        }
    }
}
unset($file);

// POST processor for single import
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_file') {
    if (!Security::validate($_POST['csrf_token'] ?? '')) {
        $error = "Security Error: Invalid CSRF token.";
    } else {
        $targetFile = $_POST['filename'] ?? '';
        $targetSheet = $_POST['sheet_name'] ?? '';
        $foundFile = null;
        foreach ($xlsxFiles as $f) {
            if ($f['name'] === $targetFile) {
                $foundFile = $f;
                break;
            }
        }

        if (!$foundFile) {
            $error = "Specified file not found.";
        } else {
            // Find sheet
            $sheetMeta = null;
            if (isset($foundFile['sheets']) && is_array($foundFile['sheets'])) {
                foreach ($foundFile['sheets'] as $sh) {
                    if ($sh['sheet_name'] === $targetSheet) {
                        $sheetMeta = $sh;
                        break;
                    }
                }
            }

            if (!$sheetMeta) {
                $error = "Specified tab/sheet not found.";
            } else {
                $conn_c = Database::customers();
                $conn_o = Database::orders();

                $conn_c->beginTransaction();
                $conn_o->beginTransaction();

                try {
                    // 1. Resolve customer ID (check by company name)
                    $company = $sheetMeta['customer'];
                    $stmt = $conn_c->prepare("SELECT customer_id FROM customers WHERE company_name = ?");
                    $stmt->execute([$company]);
                    $customer_id = $stmt->fetchColumn();

                    if (!$customer_id) {
                        // Create customer
                        $customer_id = 'CUST-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
                        $stmt_ins = $conn_c->prepare("INSERT INTO customers (customer_id, company_name, created_at) VALUES (?, ?, ?)");
                        $stmt_ins->execute([$customer_id, $company, $sheetMeta['date']]);
                        Audit::log('CREATE_CUSTOMER', $customer_id, "Auto-created during Excel tab import of " . $foundFile['name'], 'crm');
                    }

                    // 2. Resolve order ID
                    $order_no = $sheetMeta['order_no'] ?: strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
                    $order_id = 'ORD-' . $order_no;

                    // Delete existing order details if we are re-importing (to prevent item duplication)
                    $stmt_del_items = $conn_o->prepare("DELETE FROM items WHERE order_id = ?");
                    $stmt_del_items->execute([$order_id]);
                    $stmt_del_ord = $conn_o->prepare("DELETE FROM orders WHERE order_id = ?");
                    $stmt_del_ord->execute([$order_id]);

                    // Insert order
                    $stmt_ord = $conn_o->prepare("INSERT INTO orders (order_id, customer_id, status, created_at, updated_at) VALUES (?, ?, 'finalized', ?, ?)");
                    $stmt_ord->execute([$order_id, $customer_id, $sheetMeta['date'], $sheetMeta['date']]);

                    // Insert items
                    $stmt_item = $conn_o->prepare("INSERT INTO items (order_id, customer_id, brand, model, series, cpu, description, quantity, unit_price, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    foreach ($sheetMeta['items'] as $item) {
                        $desc = $item['summary'] ?: ($item['type'] . " " . $item['brand'] . " " . $item['model'] . " " . $item['cpu'] . " " . $item['status']);
                        if ($item['notes']) {
                            $desc .= " (" . $item['notes'] . ")";
                        }

                        $stmt_item->execute([
                            $order_id,
                            $customer_id,
                            $item['brand'],
                            $item['model'],
                            'N/A',
                            $item['cpu'],
                            $desc,
                            $item['qty'],
                            $item['price'],
                            $sheetMeta['date']
                        ]);
                    }

                    $conn_c->commit();
                    $conn_o->commit();

                    Audit::log('IMPORT_SALES_ORDER', $order_id, "Successfully imported tab '{$targetSheet}' with {$sheetMeta['item_count']} items from " . $foundFile['name'], 'orders');

                    $_SESSION['import_success_msg'] = "Successfully imported order <strong>$order_id</strong> for <strong>$company</strong> from tab <strong>$targetSheet</strong>!";
                    header("Location: index.php?view=import_sales");
                    exit();

                } catch (Exception $e) {
                    $conn_c->rollBack();
                    $conn_o->rollBack();
                    $error = "Database transaction failed: " . $e->getMessage();
                }
            }
        }
    }
}

// POST processor for auto import all valid orders
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'auto_import_all') {
    if (!Security::validate($_POST['csrf_token'] ?? '')) {
        $error = "Security Error: Invalid CSRF token.";
    } else {
        // Fetch existing orders to check duplicates
        $conn_orders = Database::orders();
        $existingOrders = [];
        try {
            $stmt = $conn_orders->query("SELECT order_id FROM orders");
            $existingOrders = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {}

        $conn_c = Database::customers();
        $conn_o = Database::orders();

        $importedCount = 0;
        $errorsList = [];

        foreach ($xlsxFiles as $file) {
            if (!is_array($file['sheets'])) continue;

            foreach ($file['sheets'] as $sheetMeta) {
                // Skip sheets with errors
                if (!empty($sheetMeta['errors'])) continue;

                $order_id = 'ORD-' . $sheetMeta['order_no'];
                // Skip already imported orders
                if (in_array($order_id, $existingOrders)) continue;

                $conn_c->beginTransaction();
                $conn_o->beginTransaction();

                try {
                    // Resolve Customer
                    $company = $sheetMeta['customer'];
                    $stmt = $conn_c->prepare("SELECT customer_id FROM customers WHERE company_name = ?");
                    $stmt->execute([$company]);
                    $customer_id = $stmt->fetchColumn();

                    if (!$customer_id) {
                        $customer_id = 'CUST-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
                        $stmt_ins = $conn_c->prepare("INSERT INTO customers (customer_id, company_name, created_at) VALUES (?, ?, ?)");
                        $stmt_ins->execute([$customer_id, $company, $sheetMeta['date']]);
                        Audit::log('CREATE_CUSTOMER', $customer_id, "Auto-created during bulk Excel auto-import of " . $file['name'], 'crm');
                    }

                    // Insert Order
                    $stmt_ord = $conn_o->prepare("INSERT INTO orders (order_id, customer_id, status, created_at, updated_at) VALUES (?, ?, 'finalized', ?, ?)");
                    $stmt_ord->execute([$order_id, $customer_id, $sheetMeta['date'], $sheetMeta['date']]);

                    // Insert items
                    $stmt_item = $conn_o->prepare("INSERT INTO items (order_id, customer_id, brand, model, series, cpu, description, quantity, unit_price, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    foreach ($sheetMeta['items'] as $item) {
                        $desc = $item['summary'] ?: ($item['type'] . " " . $item['brand'] . " " . $item['model'] . " " . $item['cpu'] . " " . $item['status']);
                        if ($item['notes']) {
                            $desc .= " (" . $item['notes'] . ")";
                        }

                        $stmt_item->execute([
                            $order_id,
                            $customer_id,
                            $item['brand'],
                            $item['model'],
                            'N/A',
                            $item['cpu'],
                            $desc,
                            $item['qty'],
                            $item['price'],
                            $sheetMeta['date']
                        ]);
                    }

                    $conn_c->commit();
                    $conn_o->commit();

                    Audit::log('IMPORT_SALES_ORDER', $order_id, "Auto-imported tab '{$sheetMeta['sheet_name']}' from " . $file['name'], 'orders');
                    $importedCount++;

                } catch (Exception $e) {
                    $conn_c->rollBack();
                    $conn_o->rollBack();
                    $errorsList[] = "Failed to import sheet '{$sheetMeta['sheet_name']}' from file '{$file['name']}': " . $e->getMessage();
                }
            }
        }

        if ($importedCount > 0) {
            $_SESSION['import_success_msg'] = "Successfully auto-imported <strong>$importedCount</strong> new B2B sales orders!";
            if (!empty($errorsList)) {
                $_SESSION['import_success_msg'] .= "<br>Note: Some sheets failed to import:<br>" . implode('<br>', $errorsList);
            }
        } else {
            if (!empty($errorsList)) {
                $error = "Auto-import failed:<br>" . implode('<br>', $errorsList);
            } else {
                $_SESSION['import_success_msg'] = "No new valid B2B sales orders found to import.";
            }
        }
        header("Location: index.php?view=import_sales");
        exit();
    }
}

// Fetch already existing orders to mark status
$conn_orders = Database::orders();
$existingOrders = [];
try {
    $stmt = $conn_orders->query("SELECT order_id FROM orders");
    $existingOrders = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$success_msg = $_SESSION['import_success_msg'] ?? '';
unset($_SESSION['import_success_msg']);
?>

<div class="orders-container" style="animation: fadeInDown 0.4s ease-out;">
    <header class="orders-header" style="margin-bottom: 40px; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-main); margin-bottom: 5px;">📥 Import Sales Data</h1>
            <p style="color: var(--text-secondary); font-size: 1rem;">Migrate sales records from root .xlsx spreadsheets directly into customers and completed orders.</p>
        </div>
        <div style="display: flex; gap: 15px; align-items: center;">
            <form action="" method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="auto_import_all">
                <?= UI::csrf_field() ?>
                <button type="submit" class="btn-main" style="background: var(--accent-gradient); color: white; font-weight: 800; border: none; padding: 12px 24px; border-radius: 12px; height: 44px; display: flex; align-items: center; cursor: pointer; box-shadow: var(--shadow-sm);">
                    🤖 Auto-Import All New Tabs
                </button>
            </form>
            <a href="index.php?view=orders&type=completed" class="btn-main" style="background: #f1f5f9; color: #475569; box-shadow: none; border: 1px solid #e2e8f0; font-weight: 700; height: 44px; display: flex; align-items: center; text-decoration: none;">
                ← View Completed Orders
            </a>
        </div>
    </header>

    <?php if ($success_msg): ?>
        <div style="background: #ecfdf5; color: #065f46; padding: 20px; border-radius: 16px; margin-bottom: 30px; font-weight: 700; border: 1px solid #d1fae5; display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 1.5rem;">✅</span> <?= $success_msg ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background: #fef2f2; color: #991b1b; padding: 20px; border-radius: 16px; margin-bottom: 30px; font-weight: 700; border: 1px solid #fecaca; display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 1.5rem;">⚠️</span> <?= $error ?>
        </div>
    <?php endif; ?>

    <!-- Excel files table -->
    <div style="background: white; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: var(--shadow-sm); overflow: hidden; padding: 20px;">
        <h3 style="font-weight: 900; font-size: 1.25rem; color: var(--text-main); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            📁 Available B2B Sales Sheets in Root Directory
        </h3>

        <?php if (empty($xlsxFiles)): ?>
            <div style="padding: 60px; text-align: center; color: #94a3b8; font-weight: 600;">
                No matching B2B Sales Excel spreadsheets found in the root directory.
            </div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 800; font-size: 0.85rem;">
                        <th style="padding: 16px 20px;">Excel File</th>
                        <th style="padding: 16px 20px;">Tab Name</th>
                        <th style="padding: 16px 20px;">Customer / Company</th>
                        <th style="padding: 16px 20px;">Order Date</th>
                        <th style="padding: 16px 20px;">Order ID</th>
                        <th style="padding: 16px 20px; text-align: center;">Items</th>
                        <th style="padding: 16px 20px; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($xlsxFiles as $file): ?>
                        <?php if (is_array($file['sheets'])): ?>
                             <?php foreach ($file['sheets'] as $meta):
                                $hasErrors = !empty($meta['errors']);
                                $order_id = $meta['order_no'] ? 'ORD-' . $meta['order_no'] : 'N/A';
                                $isImported = !$hasErrors && in_array($order_id, $existingOrders);
                            ?>
                                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s; background: <?= $hasErrors ? '#fffafb' : 'white' ?>;">
                                    <td style="padding: 20px; font-weight: 700; color: var(--text-main);">
                                        📊 <?= htmlspecialchars($file['name']) ?>
                                    </td>
                                    <td style="padding: 20px; font-weight: 800; color: var(--accent-color);">
                                        📁 <?= htmlspecialchars($meta['sheet_name']) ?>
                                    </td>
                                    <td style="padding: 20px; color: var(--text-main); font-weight: 600;">
                                        <?= htmlspecialchars($meta['customer']) ?>
                                        <?php if ($hasErrors): ?>
                                            <div style="margin-top: 6px; padding: 10px; background: #fdf2f2; border: 1px solid #fde2e2; border-radius: 8px; font-size: 0.75rem; color: #991b1b; font-weight: 600;">
                                                <div style="font-weight: 800; margin-bottom: 4px; display: flex; align-items: center; gap: 4px;">⚠️ Layout Errors:</div>
                                                <?php foreach ($meta['errors'] as $e): ?>
                                                    <div>• <?= htmlspecialchars($e) ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 20px; color: #475569; font-size: 0.9rem;">
                                        <?= $hasErrors && !$meta['order_no'] ? 'N/A' : date('M d, Y', strtotime($meta['date'])) ?>
                                    </td>
                                    <td style="padding: 20px; font-family: monospace; color: #0f172a; font-weight: 700;">
                                        <?= htmlspecialchars($order_id) ?>
                                    </td>
                                    <td style="padding: 20px; text-align: center; font-weight: 800; color: var(--text-secondary);">
                                        <?= htmlspecialchars($meta['item_count']) ?>
                                    </td>
                                    <td style="padding: 20px; text-align: right;">
                                        <?php if ($hasErrors): ?>
                                            <button type="button" class="btn-main" disabled style="background: #f8fafc; color: #cbd5e1; border: 1px solid #e2e8f0; cursor: not-allowed; box-shadow: none; font-weight: 800; white-space: nowrap;">
                                                ❌ Blocked
                                            </button>
                                        <?php else: ?>
                                            <form action="" method="POST" style="margin: 0; display: inline-block;">
                                                <input type="hidden" name="action" value="import_file">
                                                <input type="hidden" name="filename" value="<?= htmlspecialchars($file['name']) ?>">
                                                <input type="hidden" name="sheet_name" value="<?= htmlspecialchars($meta['sheet_name']) ?>">
                                                <?= UI::csrf_field() ?>

                                                <?php if ($isImported): ?>
                                                    <button type="submit" class="btn-main" style="background: #ecfdf5; color: #047857; font-weight: 800; border: 1px solid #a7f3d0; box-shadow: none;">
                                                        🔄 Re-Import
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn-main" style="background: var(--accent-color); color: white; font-weight: 800;">
                                                        🚀 Import Tab
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                                <td style="padding: 20px; font-weight: 700; color: var(--text-main);">
                                    📊 <?= htmlspecialchars($file['name']) ?>
                                </td>
                                <td colspan="5" style="padding: 20px; color: #ef4444; font-weight: 600; font-size: 0.9rem;">
                                    Error: <?= htmlspecialchars($file['error'] ?? 'Unknown parsing error') ?>
                                </td>
                                <td style="padding: 20px; text-align: right;">
                                    <button class="btn-main" disabled style="background: #f1f5f9; color: #94a3b8; cursor: not-allowed; box-shadow: none;">
                                        Unavailable
                                    </button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
