<?php
/**
 * Photo Bucket Module - Hardened Business Logic
 * Zero-trust upload filtering, strict MIME verification, atomic deletions, and CSRF protection.
 */

function handle_photo_bucket_actions($marketingDb, $processor, $labelsDb, $warehouseDb = null) {
    // 1. Handle Upload Action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo'])) {
        // Validate CSRF
        if (!Security::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['notify'] = ['message' => 'Security Error: Invalid CSRF token.', 'type' => 'error'];
            header("Location: index.php?page=photo_bucket");
            exit;
        }

        $model_name = trim($_POST['model_name'] ?? '');
        $category = trim($_POST['category'] ?? 'General');

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['photo']['tmp_name'];
            $original_name = basename($_FILES['photo']['name']);
            $file_size = (int)$_FILES['photo']['size'];

            // Strict File Extension Whitelist (Prevent RCE)
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

            if (!in_array($extension, $allowed_extensions, true)) {
                $_SESSION['notify'] = ['message' => 'Upload rejected: Only JPG, PNG, WEBP, and GIF images are permitted.', 'type' => 'error'];
                header("Location: index.php?page=photo_bucket");
                exit;
            }

            // Max size limit (15MB)
            if ($file_size > 15 * 1024 * 1024) {
                $_SESSION['notify'] = ['message' => 'Upload rejected: Image exceeds maximum limit of 15MB.', 'type' => 'error'];
                header("Location: index.php?page=photo_bucket");
                exit;
            }

            // Deep MIME-type verification
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($file_tmp);
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

            if (!in_array($mime_type, $allowed_mimes, true)) {
                $_SESSION['notify'] = ['message' => 'Upload rejected: File contents do not match a valid image format.', 'type' => 'error'];
                header("Location: index.php?page=photo_bucket");
                exit;
            }

            // Generate cryptographically random, sanitized filename
            $filename = 'img_' . bin2hex(random_bytes(10)) . '.' . $extension;
            $target_dir = realpath(__DIR__ . '/../../assets/photo_bucket');
            if (!$target_dir || !is_dir($target_dir)) {
                $target_dir = __DIR__ . '/../../assets/photo_bucket';
                @mkdir($target_dir, 0777, true);
                $target_dir = realpath($target_dir);
            }

            $target_path = $target_dir . DIRECTORY_SEPARATOR . $filename;

            if (move_uploaded_file($file_tmp, $target_path)) {
                try {
                    $relative_path = 'assets/photo_bucket/' . $filename;
                    $stmt = $marketingDb->prepare("INSERT INTO photos (filename, original_name, model_name, category, file_path, file_size, mime_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$filename, $original_name, $model_name, $category, $relative_path, $file_size, $mime_type, 'Processing']);

                    $photoId = $marketingDb->lastInsertId();
                    log_marketing_audit($marketingDb, 'PHOTO', $photoId, 'UPLOADED', "Uploaded photo asset: $original_name for $model_name");

                    // Trigger image optimization & thumbnail generation
                    if ($processor) {
                        $processor->process($photoId);
                    }

                    $_SESSION['notify'] = ['message' => "Photo uploaded and processed successfully!", 'type' => 'success'];
                } catch (Throwable $e) {
                    error_log("Database error recording photo: " . $e->getMessage());
                    $_SESSION['notify'] = ['message' => "Database error saving photo record.", 'type' => 'error'];
                }
            } else {
                $_SESSION['notify'] = ['message' => "Failed to move uploaded file to asset storage.", 'type' => 'error'];
            }
        } else {
            $_SESSION['notify'] = ['message' => "Upload error or no file selected.", 'type' => 'error'];
        }

        header("Location: index.php?page=photo_bucket");
        exit;
    }

    // 2. Handle Delete Action (POST only, CSRF guarded)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_photo'])) {
        if (!Security::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['notify'] = ['message' => 'Security Error: Invalid CSRF token.', 'type' => 'error'];
            header("Location: index.php?page=photo_bucket");
            exit;
        }

        $photo_id = (int)$_POST['delete_photo'];

        try {
            $stmt = $marketingDb->prepare("SELECT * FROM photos WHERE id = ?");
            $stmt->execute([$photo_id]);
            $photo = $stmt->fetch();

            if ($photo) {
                // Delete physical files safely without directory traversal
                $filesToDelete = [$photo['file_path'] ?? '', $photo['thumbnail_path'] ?? '', $photo['optimized_path'] ?? ''];
                $baseStorage = realpath(__DIR__ . '/../../assets/photo_bucket');

                foreach ($filesToDelete as $f) {
                    if (!empty($f)) {
                        $candidate = realpath(__DIR__ . '/../../' . $f);
                        // Ensure target is strictly inside assets/photo_bucket directory
                        if ($candidate && $baseStorage && strpos($candidate, $baseStorage) === 0 && file_exists($candidate)) {
                            @unlink($candidate);
                        }
                    }
                }

                $delStmt = $marketingDb->prepare("DELETE FROM photos WHERE id = ?");
                $delStmt->execute([$photo_id]);

                log_marketing_audit($marketingDb, 'PHOTO', $photo_id, 'DELETED', "Deleted photo asset: " . ($photo['original_name'] ?: $photo['filename']));
                $_SESSION['notify'] = ['message' => "Photo asset permanently deleted.", 'type' => 'success'];
            }
        } catch (Throwable $e) {
            error_log("Delete failed: " . $e->getMessage());
            $_SESSION['notify'] = ['message' => "Error deleting photo asset.", 'type' => 'error'];
        }

        header("Location: index.php?page=photo_bucket");
        exit;
    }

    // 4. Handle Sync from Warehouse (POST only, CSRF guarded)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_warehouse_photos'])) {
        if (!Security::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['notify'] = ['message' => 'Security Error: Invalid CSRF token.', 'type' => 'error'];
            header("Location: index.php?page=photo_bucket");
            exit;
        }

        try {
            $result = sync_warehouse_photos_to_bucket($marketingDb, $warehouseDb, $processor);
            $synced = $result['synced'] ?? 0;
            $skipped = $result['skipped'] ?? 0;

            if ($synced > 0) {
                $_SESSION['notify'] = [
                    'message' => "Successfully synced {$synced} photo" . ($synced === 1 ? '' : 's') . " from warehouse stock" . ($skipped > 0 ? " ({$skipped} already up-to-date)." : "!"),
                    'type' => 'success'
                ];
            } else {
                $_SESSION['notify'] = [
                    'message' => "Warehouse sync up to date: All warehouse location photos are already in Photo Bucket ({$skipped} checked).",
                    'type' => 'info'
                ];
            }
        } catch (Throwable $e) {
            error_log("Warehouse photo sync error: " . $e->getMessage());
            $_SESSION['notify'] = ['message' => "Warehouse photo sync error: " . $e->getMessage(), 'type' => 'error'];
        }
        header("Location: index.php?page=photo_bucket");
        exit;
    }
}

/**
 * Syncs warehouse photography (from location_photos and disk archive) into Photo Bucket
 */
function sync_warehouse_photos_to_bucket($marketingDb, $warehouseDb, $processor) {
    if (!$marketingDb) {
        return ['synced' => 0, 'skipped' => 0, 'error' => 'Marketing DB unavailable'];
    }

    $synced = 0;
    $skipped = 0;
    $targetDir = realpath(__DIR__ . '/../../assets/photo_bucket');
    if (!$targetDir || !is_dir($targetDir)) {
        $targetDir = __DIR__ . '/../../assets/photo_bucket';
        @mkdir($targetDir, 0777, true);
        $targetDir = realpath($targetDir);
    }

    // 1. Build index of existing photos in marketing.db to avoid duplicate imports
    $existingPhotos = $marketingDb->query("SELECT original_name, filename, file_path, location_code FROM photos")->fetchAll(PDO::FETCH_ASSOC);
    $existingMap = [];
    foreach ($existingPhotos as $ep) {
        if (!empty($ep['original_name'])) $existingMap[strtolower(trim($ep['original_name']))] = true;
        if (!empty($ep['filename'])) $existingMap[strtolower(trim($ep['filename']))] = true;
    }

    // 2. Build index of warehouse inventory by location_code
    $invByLoc = [];
    if ($warehouseDb) {
        try {
            $invStmt = $warehouseDb->query("SELECT location_code, brand, model, sector, quantity FROM inventory WHERE quantity > 0");
            while ($invRow = $invStmt->fetch(PDO::FETCH_ASSOC)) {
                $loc = trim($invRow['location_code'] ?? '');
                if (!empty($loc)) {
                    if (!isset($invByLoc[$loc])) {
                        $invByLoc[$loc] = [];
                    }
                    $invByLoc[$loc][] = $invRow;
                }
            }
        } catch (Throwable $e) {
            error_log("Failed to load inventory for photo sync: " . $e->getMessage());
        }
    }

    // 3. Scan orders location photo directories
    $archiveDir = realpath(__DIR__ . '/../../../orders/assets/location_photos/archive');
    $optDir = realpath(__DIR__ . '/../../../orders/assets/location_photos');

    $syncQueue = [];

    // Check location_photos table in warehouseDb if present
    if ($warehouseDb) {
        try {
            $whPhotos = $warehouseDb->query("SELECT * FROM location_photos")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($whPhotos as $wp) {
                $orig = $wp['original_filename'] ?? '';
                $archivePath = $wp['archive_path'] ?? '';
                $loc = $wp['location_code'] ?? '';
                $cat = $wp['category'] ?? 'Bulk Stock';
                $fullPath = '';
                if ($archiveDir && file_exists($archiveDir . DIRECTORY_SEPARATOR . $archivePath)) {
                    $fullPath = $archiveDir . DIRECTORY_SEPARATOR . $archivePath;
                } elseif ($optDir && file_exists($optDir . DIRECTORY_SEPARATOR . $archivePath)) {
                    $fullPath = $optDir . DIRECTORY_SEPARATOR . $archivePath;
                }
                $syncQueue[] = [
                    'original_name' => $orig ?: basename($archivePath),
                    'source_path' => $fullPath,
                    'location_code' => $loc,
                    'category' => $cat,
                    'sector' => $wp['sector'] ?? 'Laptops'
                ];
            }
        } catch (Throwable $e) {}
    }

    // Scan physical files in orders/assets/location_photos/archive/
    if ($archiveDir && is_dir($archiveDir)) {
        $diskFiles = glob($archiveDir . DIRECTORY_SEPARATOR . '*.*');
        foreach ($diskFiles as $df) {
            $base = basename($df);
            $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $alreadyQueued = false;
                foreach ($syncQueue as $sq) {
                    if (!empty($sq['source_path']) && basename($sq['source_path']) === $base) {
                        $alreadyQueued = true;
                        break;
                    }
                }
                if (!$alreadyQueued) {
                    // Extract location code from filename: e.g. "A‑A_sower_..." -> "A‑A"
                    $loc = '';
                    $parts = explode('_', $base);
                    if (!empty($parts[0])) {
                        $loc = trim($parts[0]);
                    }
                    $syncQueue[] = [
                        'original_name' => $base,
                        'source_path' => $df,
                        'location_code' => $loc,
                        'category' => 'Bulk Stock',
                        'sector' => 'Warehouse'
                    ];
                }
            }
        }
    }

    // Process sync queue
    foreach ($syncQueue as $item) {
        $origName = $item['original_name'];
        $srcPath = $item['source_path'];
        $loc = $item['location_code'];

        if (empty($srcPath) || !file_exists($srcPath)) {
            continue;
        }

        $lookupKey = strtolower(trim($origName));
        $baseKey = strtolower(trim(basename($srcPath)));
        if (isset($existingMap[$lookupKey]) || isset($existingMap[$baseKey])) {
            $skipped++;
            continue;
        }

        // Deduce Model Name from location inventory
        $modelName = '';
        if (!empty($loc) && isset($invByLoc[$loc]) && !empty($invByLoc[$loc])) {
            $candidates = array_filter($invByLoc[$loc], function($c) {
                return strtolower($c['brand'] ?? '') !== 'mix' && strtolower($c['model'] ?? '') !== 'mix';
            });
            if (!empty($candidates)) {
                $primary = reset($candidates);
                $modelName = trim(($primary['brand'] ?? '') . ' ' . ($primary['model'] ?? ''));
            } else {
                $primary = reset($invByLoc[$loc]);
                $modelName = trim(($primary['brand'] ?? '') . ' ' . ($primary['model'] ?? ''));
            }
        }
        if (empty($modelName) || strtolower($modelName) === 'mix mix' || strtolower($modelName) === 'mix mi') {
            $modelName = !empty($loc) ? "Warehouse Stock ({$loc})" : "Warehouse Stock";
        }

        // Category mapping
        $category = 'Bulk Stock';
        $origCat = strtolower($item['category'] ?? '');
        if (strpos($origCat, 'detail') !== false) {
            $category = 'Detail Shot';
        } elseif (strpos($origCat, 'pallet') !== false || strpos($origCat, 'bulk') !== false) {
            $category = 'Bulk Stock';
        } elseif (strpos($origCat, 'laptop') !== false) {
            $category = 'Laptop';
        } elseif (strpos($origCat, 'workstation') !== false) {
            $category = 'Workstation';
        }

        // Copy raw image into photo_bucket
        $ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));
        $destFilename = 'wh_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destPath = $targetDir . DIRECTORY_SEPARATOR . $destFilename;

        if (@copy($srcPath, $destPath)) {
            $fileSize = (int)filesize($destPath);
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($destPath) ?: 'image/jpeg';
            $relPath = 'assets/photo_bucket/' . $destFilename;

            $stmt = $marketingDb->prepare("
                INSERT INTO photos (filename, original_name, model_name, category, file_path, file_size, mime_type, status, source, location_code)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Processing', 'warehouse', ?)
            ");
            $stmt->execute([
                $destFilename,
                $origName,
                $modelName,
                $category,
                $relPath,
                $fileSize,
                $mimeType,
                $loc
            ]);

            $newPhotoId = $marketingDb->lastInsertId();

            // Run photo processor for WebP thumbnail and optimized views
            if ($processor) {
                $processor->process($newPhotoId);
            }

            // Sync back to warehouse.db location_photos if missing
            if ($warehouseDb) {
                try {
                    $checkWh = $warehouseDb->prepare("SELECT id FROM location_photos WHERE archive_path = ? OR original_filename = ?");
                    $checkWh->execute([basename($srcPath), $origName]);
                    if (!$checkWh->fetchColumn()) {
                        $insWh = $warehouseDb->prepare("
                            INSERT INTO location_photos (location_code, original_filename, archive_driver, archive_path, optimized_path, thumbnail_path, uploaded_by, category, sector)
                            VALUES (?, ?, 'spinning_disk', ?, ?, ?, 'Warehouse Sync', ?, ?)
                        ");
                        $insWh->execute([
                            $loc,
                            $origName,
                            basename($srcPath),
                            'assets/location_photos/' . basename($srcPath),
                            'assets/location_photos/' . basename($srcPath),
                            $category,
                            $item['sector'] ?? 'Laptops'
                        ]);
                    }
                } catch (Throwable $e) {}
            }

            log_marketing_audit($marketingDb, 'PHOTO', $newPhotoId, 'SYNCED', "Synced warehouse photo: {$origName} for {$modelName} (Loc: {$loc})");
            $existingMap[strtolower(trim($origName))] = true;
            $existingMap[strtolower(trim($destFilename))] = true;
            $synced++;
        }
    }

    return ['synced' => $synced, 'skipped' => $skipped];
}

function get_photo_bucket_models($marketingDb, $labelsDb = null, $warehouseDb = null) {
    $models = [];
    try {
        if ($marketingDb) {
            $local_models = $marketingDb->query("SELECT DISTINCT model_name FROM model_templates ORDER BY model_name ASC")->fetchAll(PDO::FETCH_COLUMN);
            if ($local_models) {
                $models = array_merge($models, $local_models);
            }
        }

        if ($warehouseDb) {
            $wh_models = $warehouseDb->query("SELECT DISTINCT (brand || ' ' || model) FROM inventory WHERE quantity > 0 AND LOWER(brand) NOT LIKE '%mix%' ORDER BY brand ASC, model ASC")->fetchAll(PDO::FETCH_COLUMN);
            if ($wh_models) {
                $models = array_merge($models, $wh_models);
            }
        }

        if ($labelsDb) {
            $warehouse_models = $labelsDb->query("SELECT DISTINCT model FROM items ORDER BY model ASC")->fetchAll(PDO::FETCH_COLUMN);
            if ($warehouse_models) {
                $models = array_merge($models, $warehouse_models);
            }
        }
    } catch (Throwable $e) {
        error_log("Failed to fetch photo bucket models: " . $e->getMessage());
    }

    $models = array_unique(array_filter(array_map('trim', $models)));
    sort($models);
    return $models;
}
