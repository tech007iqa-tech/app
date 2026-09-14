<?php
/**
 * Settings Actions & Backend Controller Partial
 * Handles authentication security, Steve Gibson GRC PPP algorithm, AJAX directory browsing, and system maintenance tasks.
 */

$db_file = Database::getDbDir() . '/users.db';
$message = $_SESSION['settings_success_message'] ?? '';
unset($_SESSION['settings_success_message']);
$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!Security::validate($_POST['csrf_token'] ?? '')) {
        die("Security Error: CSRF token validation failed.");
    }
}

try {
    $conn_u = Database::users();

    $username = $_SESSION['username'];
    $stmt_ppp = $conn_u->prepare("SELECT ppp_sequence_key, ppp_row_index, ppp_password_len FROM users WHERE username = ?");
    $stmt_ppp->execute([$username]);
    $user_row = $stmt_ppp->fetch(PDO::FETCH_ASSOC);
    $seq_key = $user_row['ppp_sequence_key'] ?? '';
    $saved_row_index = (int)($user_row['ppp_row_index'] ?? 0);
    $saved_pass_len = (int)($user_row['ppp_password_len'] ?? ($_SESSION['ppp_password_len'] ?? 30));
    if ($saved_pass_len < 25) {
        $saved_pass_len = 30;
    }

    // Helper to generate PPP passcodes
    // Algorithm designed by Steve Gibson (Gibson Research Corporation)
    // Reference: https://www.grc.com/ppp.htm
    if (!function_exists('generate_ppp_passcodes')) {
        function generate_ppp_passcodes($sequence_key, $cell_len = 4) {
            $clean_key = preg_replace('/[^a-fA-F0-9]/', '', (string)$sequence_key);
            $hex_len = strlen($clean_key);
            if ($hex_len < 16 || $hex_len > 64) {
                return [];
            }

            if ($hex_len === 32) {
                $cipher = 'aes-128-ecb';
            } elseif ($hex_len === 48) {
                $cipher = 'aes-192-ecb';
            } elseif ($hex_len === 64) {
                $cipher = 'aes-256-ecb';
            } elseif ($hex_len < 32) {
                $clean_key = str_pad($clean_key, 32, '0');
                $cipher = 'aes-128-ecb';
            } elseif ($hex_len < 48) {
                $clean_key = str_pad($clean_key, 48, '0');
                $cipher = 'aes-192-ecb';
            } else {
                $clean_key = str_pad($clean_key, 64, '0');
                $cipher = 'aes-256-ecb';
            }

            $alphabet = '!#%+23456789:=?@ABCDEFGHJKLMNPRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
            $key_bin = hex2bin($clean_key);
            $passcodes = [];

            for ($i = 0; $i < 125; $i++) {
                $ciphertext = "";
                $blocks_needed = (int)ceil(($cell_len * 6) / 128.0);
                for ($b = 0; $b < $blocks_needed; $b++) {
                    $counter_bin = pack('P', $i) . pack('P', $b);
                    $ciphertext .= openssl_encrypt($counter_bin, $cipher, $key_bin, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
                }

                $passcode = "";
                $bit_buffer = 0;
                $bit_count = 0;
                $byte_index = 0;
                $cipher_len = strlen($ciphertext);

                for ($char_idx = 0; $char_idx < $cell_len; $char_idx++) {
                    while ($bit_count < 6 && $byte_index < $cipher_len) {
                        $bit_buffer = ($bit_buffer << 8) | ord($ciphertext[$byte_index]);
                        $byte_index++;
                        $bit_count += 8;
                    }
                    if ($bit_count >= 6) {
                        $shift = $bit_count - 6;
                        $idx = ($bit_buffer >> $shift) & 0x3F;
                        $bit_count = $shift;
                        $passcode .= $alphabet[$idx];
                    } else {
                        $passcode .= $alphabet[0];
                    }
                }
                $passcodes[] = $passcode;
            }
            return $passcodes;
        }
    }

    // AJAX Endpoint: Generate PPP Passcodes
    if (isset($_GET['action']) && $_GET['action'] === 'ajax_generate_ppp') {
        header('Content-Type: application/json');
        $seq_key_param = preg_replace('/[^a-fA-F0-9]/', '', trim($_GET['seq_key'] ?? ''));
        $length = (int)($_GET['length'] ?? 30);
        if (strlen($seq_key_param) < 16 || strlen($seq_key_param) > 64) {
            echo json_encode(['success' => false, 'error' => 'Invalid sequence key']);
            exit();
        }
        $cell_len = (int)ceil($length / 5.0);
        $passcodes = generate_ppp_passcodes($seq_key_param, $cell_len);
        echo json_encode(['success' => true, 'passcodes' => $passcodes]);
        exit();
    }

    // AJAX Endpoint: List Directories for Archive Path Picker
    if (isset($_GET['action']) && $_GET['action'] === 'list_dirs') {
        header('Content-Type: application/json');
        if ($_SESSION['username'] !== 'admin') {
            echo json_encode(['error' => 'Unauthorized']);
            exit();
        }

        $path = $_GET['path'] ?? '';
        if (empty($path)) {
            $drives = [];
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                foreach (range('C', 'Z') as $letter) {
                    if (@is_dir($letter . ':\\')) {
                        $drives[] = $letter . ':\\';
                    }
                }
            } else {
                $drives[] = '/';
            }
            echo json_encode(['current' => '', 'drives' => $drives, 'dirs' => []]);
            exit();
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && preg_match('#^[A-Z]:#i', $path)) {
            if (substr($path, 2, 1) !== '/') {
                $path = substr($path, 0, 2) . '/' . substr($path, 2);
            }
        }

        $real = @realpath($path);
        if ($real) {
            $path = str_replace('\\', '/', $real);
        }
        $path = rtrim($path, '/') . '/';

        $dirs = [];
        try {
            if (@is_dir($path)) {
                $files = @scandir($path);
                if ($files) {
                    foreach ($files as $file) {
                        if ($file === '.' || $file === '..') continue;
                        $full = $path . $file;
                        if (@is_dir($full)) {
                            $dirs[] = $file;
                        }
                    }
                }
            }
        } catch (Exception $e) {}

        $parent = dirname($path);
        $parent = str_replace('\\', '/', $parent);
        if ($parent === $path || $parent === '.' || $parent === '/' || preg_match('#^[A-Z]:/$#i', $path)) {
            $parent = '';
        } else {
            $parent = rtrim($parent, '/') . '/';
        }

        echo json_encode([
            'current' => $path,
            'parent' => $parent,
            'drives' => [],
            'dirs' => $dirs
        ]);
        exit();
    }

    // 1. Handle Password Change (All Users)
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $old_pass = $_POST['old_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';
        $new_seq_key = trim($_POST['ppp_sequence_key'] ?? '');
        $ppp_row_index = (int)($_POST['ppp_row_index'] ?? 0);
        $user_id = $_SESSION['username'];

        $is_forced = (isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true);
        $verified_old = false;

        if ($is_forced) {
            $verified_old = true;
        } else {
            $stmt = $conn_u->prepare("SELECT password, ppp_sequence_key FROM users WHERE username = ?");
            $stmt->execute([$user_id]);
            $user_row = $stmt->fetch(PDO::FETCH_ASSOC);
            $current_hash = $user_row['password'] ?? '';
            $current_seq = $user_row['ppp_sequence_key'] ?? '';

            if ($current_hash) {
                if (!empty($current_seq)) {
                    $verified_old = password_verify($old_pass . $current_seq, $current_hash);
                }
                if (!$verified_old) {
                    $verified_old = password_verify($old_pass, $current_hash);
                }
            }
        }

        if ($verified_old) {
            if ($new_pass === $confirm_pass) {
                $bypass_ppp = isset($_POST['bypass_ppp']) && $_POST['bypass_ppp'] === '1';
                $min_len = $bypass_ppp ? 12 : 25;

                if (Security::validatePassword($new_pass, $error, $min_len)) {
                    if (!$bypass_ppp && !empty($new_seq_key)) {
                        $clean_new_seq = preg_replace('/[^a-fA-F0-9]/', '', $new_seq_key);
                        if (strlen($clean_new_seq) < 16 || strlen($clean_new_seq) > 64) {
                            $error = "Sequence key must be a valid hexadecimal key (32-hex 128-bit or 64-hex 256-bit).";
                        } else {
                            $new_seq_key = strtoupper($clean_new_seq);
                        }
                    }

                    if (!$error) {
                        $hash_password = $new_pass;
                        if ($bypass_ppp) {
                            $stmt_u = $conn_u->prepare("UPDATE users SET password = ?, ppp_sequence_key = '', ppp_row_index = 0, ppp_password_len = 0 WHERE username = ?");
                            $stmt_u->execute([password_hash($hash_password, PASSWORD_BCRYPT), $user_id]);
                        } else {
                            if (!empty($new_seq_key)) {
                                $seq_key = strtoupper($new_seq_key);
                                $hash_password .= $seq_key;
                                $stmt_u = $conn_u->prepare("UPDATE users SET password = ?, ppp_sequence_key = ?, ppp_row_index = ?, ppp_password_len = ? WHERE username = ?");
                                $stmt_u->execute([password_hash($hash_password, PASSWORD_BCRYPT), $seq_key, $ppp_row_index, strlen($new_pass), $user_id]);
                            } else {
                                $stmt_key = $conn_u->prepare("SELECT ppp_sequence_key FROM users WHERE username = ?");
                                $stmt_key->execute([$user_id]);
                                $existing_key = $stmt_key->fetchColumn();
                                if (!empty($existing_key)) {
                                    $hash_password .= $existing_key;
                                }
                                $stmt_u = $conn_u->prepare("UPDATE users SET password = ?, ppp_row_index = ?, ppp_password_len = ? WHERE username = ?");
                                $stmt_u->execute([password_hash($hash_password, PASSWORD_BCRYPT), $ppp_row_index, strlen($new_pass), $user_id]);
                            }
                        }
                        $_SESSION['settings_success_message'] = "Password and security settings updated successfully!";
                        $_SESSION['ppp_password_len'] = $bypass_ppp ? 0 : strlen($new_pass);
                        if (isset($_SESSION['force_password_change'])) {
                            unset($_SESSION['force_password_change']);
                        }
                        header("Location: index.php?view=settings");
                        exit();
                    }
                }
            } else {
                $error = "New passwords do not match.";
            }
        } else {
            $error = "Incorrect current password.";
        }
    }

    // 1b. Handle Signature / Display Name Update (All Users)
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'update_signature') {
        $display_name = trim($_POST['display_name'] ?? '');
        if ($display_name !== '') {
            $stmt_sig = $conn_u->prepare("UPDATE users SET display_name = ? WHERE username = ?");
            $stmt_sig->execute([$display_name, $_SESSION['username']]);
            $_SESSION['display_name'] = $display_name;
            $message = "Signature updated! Your name will appear on future invoices.";
        } else {
            $error = "Signature name cannot be empty.";
        }
    }

    // 2. Handle User Management (Admin Only)
    if ($_SESSION['username'] === 'admin') {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add_user' && !empty($_POST['new_username'])) {
                $nu = trim($_POST['new_username']);
                $np = $_POST['new_password'];
                $nr = $_POST['new_role'] ?? 'Operator';

                if (empty($np) || strlen($np) < 3) {
                    $error = "Password must be at least 3 characters.";
                } else {
                    $hash = password_hash($np, PASSWORD_BCRYPT);
                    try {
                        $auth_add = $conn_u->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                        $auth_add->execute([$nu, $hash, $nr]);
                        $message = "New user '{$nu}' ({$nr}) added successfully!";
                    } catch(Exception $e) { $error = "Error: Username might already exist."; }
                }
            }

            if ($_POST['action'] === 'change_role' && !empty($_POST['target_user'])) {
                $tu = $_POST['target_user'];
                $tr = $_POST['target_role'];
                if ($tu !== 'admin') {
                    $stmt_role = $conn_u->prepare("UPDATE users SET role = ? WHERE username = ?");
                    $stmt_role->execute([$tr, $tu]);
                    $message = "User '{$tu}' permissions updated to {$tr}.";
                }
            }

            if ($_POST['action'] === 'delete_user' && !empty($_POST['del_username'])) {
                $du = $_POST['del_username'];
                if ($du !== 'admin') {
                    $auth_del = $conn_u->prepare("DELETE FROM users WHERE username = ?");
                    $auth_del->execute([$du]);
                    $message = "User '{$du}' removed.";
                }
            }
        }
    }

    // 3. Handle System Maintenance (Admin Only)
    if ($_SESSION['username'] === 'admin' && isset($_POST['action'])) {
        if ($_POST['action'] === 'cleanup_customers') {
            $db_cust_file = Database::getDbDir() . '/customers.db';
            $db_orders_file = Database::getDbDir() . '/orders.db';
            try {
                if (!$db_cust_file || !$db_orders_file) throw new Exception("Database files not found.");

                $conn_m = new PDO("sqlite:" . $db_cust_file);
                $conn_m->exec("ATTACH DATABASE '" . $db_orders_file . "' AS db_o");

                // Delete customers with no orders
                $sql_clean = "DELETE FROM customers WHERE customer_id NOT IN (SELECT DISTINCT customer_id FROM db_o.orders)";
                $stmt_clean = $conn_m->prepare($sql_clean);
                $stmt_clean->execute();
                $removed = $stmt_clean->rowCount();

                $message = "Cleanup complete! Removed {$removed} customer(s) with 0 orders.";
            } catch (Exception $e) { $error = "Cleanup failed: " . $e->getMessage(); }
        }

        if ($_POST['action'] === 'optimize_db') {
            try {
                $dbs = ['customers', 'orders', 'warehouse', 'users', 'calendar'];
                $optimized = 0;
                foreach ($dbs as $db) {
                    $pdo = Database::getConnection($db);
                    $pdo->exec("VACUUM");
                    $pdo->exec("ANALYZE");
                    $optimized++;
                }
                $message = "System performance optimized! Re-indexed {$optimized} core databases.";
            } catch (Exception $e) { $error = "Optimization failed: " . $e->getMessage(); }
        }

        if ($_POST['action'] === 'integrity_check') {
            require_once __DIR__ . '/../../core/Schema.php';
            $report = Schema::repairAll();
            $fixed_count = count($report['fixed']);
            $err_count   = count($report['errors']);
            if ($err_count === 0) {
                $message = "✅ Integrity check complete. {$fixed_count} table(s) verified/repaired. No errors.";
            } else {
                $message = "⚠️ Integrity check done. {$fixed_count} table(s) OK. {$err_count} error(s): " . implode(' | ', $report['errors']);
            }
            $_SESSION['integrity_report'] = $report;
        }

        if ($_POST['action'] === 'update_archive_path') {
            $newPath = trim($_POST['archive_photos_path'] ?? '');
            if (!empty($newPath)) {
                $newPath = rtrim(str_replace('\\', '/', $newPath), '/') . '/';
                try {
                    $conn_w = Database::warehouse();
                    $stmt = $conn_w->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
                    $stmt->execute(['archive_photos_path', $newPath]);
                    $message = "Archive storage path updated successfully to: " . htmlspecialchars($newPath);
                } catch (Exception $e) {
                    $error = "Failed to update storage path: " . $e->getMessage();
                }
            } else {
                $error = "Storage path cannot be empty.";
            }
        }

        if ($_POST['action'] === 'export_photos_backup') {
            require_once __DIR__ . '/../../core/BackupManager.php';
            try {
                $backupDir = dirname(__DIR__, 4) . '/db';
                if (!is_dir($backupDir)) {
                    @mkdir($backupDir, 0755, true);
                }
                $tarPath = $backupDir . '/photos_backup_' . date('Y-m-d') . '.tar';
                $conn_w = Database::warehouse();
                $backupManager = new BackupManager($conn_w);

                if ($backupManager->export($tarPath)) {
                    if (ob_get_level() > 0) ob_clean();
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/x-tar');
                    header('Content-Disposition: attachment; filename="' . basename($tarPath) . '"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($tarPath));
                    readfile($tarPath);
                    @unlink($tarPath);
                    exit();
                } else {
                    $error = "Failed to generate backup archive.";
                }
            } catch (Exception $e) {
                $error = "Backup failed: " . $e->getMessage();
            }
        }

        if ($_POST['action'] === 'import_photos_backup') {
            if (isset($_FILES['backup_tar']) && $_FILES['backup_tar']['error'] === UPLOAD_ERR_OK) {
                require_once __DIR__ . '/../../core/BackupManager.php';
                try {
                    $conn_w = Database::warehouse();
                    $backupManager = new BackupManager($conn_w);
                    $backupManager->import($_FILES['backup_tar']['tmp_name']);
                    $message = "Backup archive restored successfully! All location photos are imported and populated.";
                } catch (Exception $e) {
                    $error = "Restore failed: " . $e->getMessage();
                }
            } else {
                $error = "Upload error or no tar backup file selected.";
            }
        }
    }
    $is_forced = (isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true);
} catch (PDOException $e) { $error = "Database error: " . $e->getMessage(); }
