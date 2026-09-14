<?php

require_once __DIR__ . '/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 0. Device ID setting
if (!isset($_COOKIE['device_id'])) {
    $device_id = bin2hex(random_bytes(16));
    setcookie('device_id', $device_id, time() + (86400 * 365 * 5), "/", "", true, true);
    $_COOKIE['device_id'] = $device_id;
} else {
    $device_id = $_COOKIE['device_id'];
}

// 1. Auto-Redirect if already logged in
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    $return_url = $_GET['return_url'] ?? '';
    if (!empty($return_url) && preg_match('#^(/[a-zA-Z0-9_\-\./\?=&%#]+|\.\./[a-zA-Z0-9_\-\./\?=&%#]+)$#', $return_url) && !str_starts_with($return_url, '//')) {
        header("Location: " . $return_url);
        exit();
    }
    if ($_SESSION['role'] === 'Admin') {
        $redirect = "../index.php";
    } elseif ($_SESSION['role'] === 'Front Desk') {
        $redirect = "../index.php?view=calendar";
    } else {
        $redirect = "../index.php?view=warehouse";
    }
    header("Location: $redirect");
    exit();
}

// AJAX handler for generating PPP passcodes
if (isset($_GET['action']) && $_GET['action'] === 'ajax_generate_ppp') {
    header('Content-Type: application/json');
    $seq_key = preg_replace('/[^a-fA-F0-9]/', '', trim($_GET['seq_key'] ?? ''));
    $length = (int) ($_GET['length'] ?? 30);
    if (strlen($seq_key) < 16 || strlen($seq_key) > 64) {
        echo json_encode(['success' => false, 'error' => 'Invalid sequence key']);
        exit();
    }

    function generate_ppp_passcodes($sequence_key, $cell_len = 4)
    {
        $clean_key = preg_replace('/[^a-fA-F0-9]/', '', (string) $sequence_key);
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
            $blocks_needed = (int) ceil(($cell_len * 6) / 128.0);
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

    $cell_len = (int) ceil($length / 5.0);
    $passcodes = generate_ppp_passcodes($seq_key, $cell_len);
    echo json_encode(['success' => true, 'passcodes' => $passcodes]);
    exit();
}

$error = null;

try {
    $conn_auth = Database::users();

    // Consolidated Initial Schema (Role included)
    $conn_auth->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        display_name TEXT DEFAULT '',
        role TEXT DEFAULT 'Operator',
        ppp_sequence_key TEXT DEFAULT '',
        ppp_row_index INTEGER DEFAULT 0,
        ppp_password_len INTEGER DEFAULT 55,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $conn_auth->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip_address TEXT NOT NULL,
        device_id TEXT NOT NULL,
        username TEXT NOT NULL,
        attempt_count INTEGER DEFAULT 0,
        last_attempt_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migration: add columns if older DB
    $cols = $conn_auth->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
    $col_names = array_column($cols, 'name');

    if (!in_array('display_name', $col_names)) {
        $conn_auth->exec("ALTER TABLE users ADD COLUMN display_name TEXT DEFAULT ''");
    }
    if (!in_array('role', $col_names)) {
        $conn_auth->exec("ALTER TABLE users ADD COLUMN role TEXT DEFAULT 'Operator'");
    }
    if (!in_array('ppp_sequence_key', $col_names)) {
        $conn_auth->exec("ALTER TABLE users ADD COLUMN ppp_sequence_key TEXT DEFAULT ''");
    }
    if (!in_array('ppp_row_index', $col_names)) {
        $conn_auth->exec("ALTER TABLE users ADD COLUMN ppp_row_index INTEGER DEFAULT 0");
    }
    if (!in_array('ppp_password_len', $col_names)) {
        $conn_auth->exec("ALTER TABLE users ADD COLUMN ppp_password_len INTEGER DEFAULT 55");
    }

    // Seed default user if empty (admin / 123)
    $stmt = $conn_auth->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('123', PASSWORD_BCRYPT);
        $stmt_s = $conn_auth->prepare("INSERT INTO users (username, password, display_name, role) VALUES (?, ?, ?, ?)");
        $stmt_s->execute(['admin', $hash, 'Administrator', 'Admin']);
    }

    // Helper function for Fibonacci sequence
    if (!function_exists('get_fibonacci')) {
        function get_fibonacci($n)
        {
            if ($n <= 0)
                return 0;
            if ($n === 1)
                return 1;
            $prev = 0;
            $curr = 1;
            for ($i = 2; $i <= $n; $i++) {
                $temp = $curr;
                $curr = $prev + $curr;
                $prev = $temp;
            }
            return $curr;
        }
    }

    // AJAX handler for Step 1 (username check)
    if (isset($_GET['action']) && $_GET['action'] === 'check_username') {
        header('Content-Type: application/json');
        $username = trim($_GET['username'] ?? '');
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $device_id = $_COOKIE['device_id'] ?? '';

        // Check attempts
        $stmt_att = $conn_auth->prepare("
            SELECT MAX(attempt_count) as max_count, MAX(last_attempt_at) as last_time
            FROM login_attempts
            WHERE ip_address = ? OR device_id = ? OR username = ?
        ");
        $stmt_att->execute([$ip_address, $device_id, $username]);
        $attempt_data = $stmt_att->fetch(PDO::FETCH_ASSOC);

        $attempts = (int) ($attempt_data['max_count'] ?? 0);
        $last_time = $attempt_data['last_time'] ?? null;

        // Check for 77th attempt honeypot
        if ($attempts >= 77) {
            echo json_encode([
                'success' => true,
                'honeypot' => true
            ]);
            exit();
        }

        if ($attempts >= 2 && $last_time) {
            $delay = 5 * get_fibonacci($attempts - 1);
            $elapsed = time() - strtotime($last_time . ' UTC');
            if ($elapsed < $delay) {
                echo json_encode([
                    'success' => false,
                    'lockout' => true,
                    'remaining' => $delay - $elapsed
                ]);
                exit();
            }
        }

        // Check user existence
        $stmt_u = $conn_auth->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_u->execute([$username]);
        $user_exists = (bool) $stmt_u->fetchColumn();

        // Simulated time delay to protect username validation from timing attacks
        usleep(rand(50000, 100000));

        echo json_encode([
            'success' => true,
            'exists' => $user_exists
        ]);
        exit();
    }

    // POST Login Handler
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username']) && isset($_POST['password'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $device_id = $_COOKIE['device_id'] ?? '';

        // Check lockout and honeypot again
        $stmt_att = $conn_auth->prepare("
            SELECT MAX(attempt_count) as max_count, MAX(last_attempt_at) as last_time
            FROM login_attempts
            WHERE ip_address = ? OR device_id = ? OR username = ?
        ");
        $stmt_att->execute([$ip_address, $device_id, $username]);
        $attempt_data = $stmt_att->fetch(PDO::FETCH_ASSOC);

        $attempts = (int) ($attempt_data['max_count'] ?? 0);
        $last_time = $attempt_data['last_time'] ?? null;

        if ($attempts >= 77) {
            $_SESSION['honeypot'] = true;
            header("Location: ../index.php?view=settings");
            exit();
        }

        if ($attempts >= 2 && $last_time) {
            $delay = 5 * get_fibonacci($attempts - 1);
            $elapsed = time() - strtotime($last_time . ' UTC');
            if ($elapsed < $delay) {
                $error = "Too many failed login attempts. Please try again in " . ($delay - $elapsed) . " seconds.";
            }
        }

        if (empty($error)) {
            $stmt_l = $conn_auth->prepare("SELECT * FROM users WHERE username = ?");
            $stmt_l->execute([$username]);
            $user = $stmt_l->fetch(PDO::FETCH_ASSOC);

            $log_attempt = false;
            if ($user) {
                $verified = false;
                $clean_password = preg_replace('/\s+/', '', $password);
                if (!empty($user['ppp_sequence_key'])) {
                    $verified = password_verify($password . $user['ppp_sequence_key'], $user['password'])
                        || password_verify($clean_password . $user['ppp_sequence_key'], $user['password']);
                }
                if (!$verified) {
                    $verified = password_verify($password, $user['password'])
                        || password_verify($clean_password, $user['password']);
                }
                if ($verified && $clean_password !== $password) {
                    $password = $clean_password;
                }

                if ($verified) {
                    // Reset attempt counter
                    $stmt_clear = $conn_auth->prepare("DELETE FROM login_attempts WHERE ip_address = ? OR device_id = ? OR username = ?");
                    $stmt_clear->execute([$ip_address, $device_id, $username]);

                    // Session Fixation Protection
                    session_regenerate_id(true);

                    $_SESSION['authenticated'] = true;
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['display_name'] = $user['display_name'] ?: $user['username'];
                    $_SESSION['role'] = $user['role'] ?? 'Operator';
                    $_SESSION['ppp_password_len'] = strlen($password);

                    $is_ppp_user = !empty($user['ppp_sequence_key']);
                    $min_len = $is_ppp_user ? 25 : 12;

                    if (($user['username'] === 'admin' && password_verify('123', $user['password'])) || strlen($password) < $min_len) {
                        $_SESSION['force_password_change'] = true;
                    }

                    // Redirect based on role or return_url
                    if (isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true) {
                        header("Location: ../index.php?view=settings");
                        exit();
                    }
                    $return_url = $_POST['return_url'] ?? ($_GET['return_url'] ?? '');
                    if (!empty($return_url) && preg_match('#^(/[a-zA-Z0-9_\-\./\?=&%#]+|\.\./[a-zA-Z0-9_\-\./\?=&%#]+)$#', $return_url) && !str_starts_with($return_url, '//')) {
                        header("Location: " . $return_url);
                        exit();
                    }
                    if ($_SESSION['role'] === 'Admin') {
                        header("Location: ../index.php");
                    } elseif ($_SESSION['role'] === 'Front Desk') {
                        header("Location: ../index.php?view=calendar");
                    } else {
                        header("Location: ../index.php?view=warehouse");
                    }
                    exit();
                } else {
                    $error = "Invalid username or password";
                    $log_attempt = true;
                }
            } else {
                $error = "Invalid username or password";
            }

            if (!empty($error) && $log_attempt) {
                // Log failed attempt for user
                $stmt_find = $conn_auth->prepare("SELECT attempt_count FROM login_attempts WHERE ip_address = ? AND username = ?");
                $stmt_find->execute([$ip_address, $username]);
                $existing_count = $stmt_find->fetchColumn();

                if ($existing_count !== false) {
                    $stmt_up = $conn_auth->prepare("
                        UPDATE login_attempts
                        SET attempt_count = attempt_count + 1, last_attempt_at = CURRENT_TIMESTAMP
                        WHERE ip_address = ? AND username = ?
                    ");
                    $stmt_up->execute([$ip_address, $username]);
                } else {
                    $stmt_ins = $conn_auth->prepare("
                        INSERT INTO login_attempts (ip_address, device_id, username, attempt_count)
                        VALUES (?, ?, ?, 1)
                    ");
                    $stmt_ins->execute([$ip_address, $device_id, $username]);
                }
            }
        }
    }

    // Check if honeypot active
    $show_honeypot = false;
    if (isset($_SESSION['honeypot']) && $_SESSION['honeypot'] === true) {
        $show_honeypot = true;
    } else {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $device_id = $_COOKIE['device_id'] ?? '';
        $stmt_hp = $conn_auth->prepare("
            SELECT MAX(attempt_count) FROM login_attempts
            WHERE ip_address = ? OR device_id = ?
        ");
        $stmt_hp->execute([$ip_address, $device_id]);
        $max_att = (int) $stmt_hp->fetchColumn();
        if ($max_att >= 77) {
            $show_honeypot = true;
        }
    }

    if ($show_honeypot) {
        ?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Settings | System Portal</title>
            <link rel="stylesheet" href="../assets/styles/style.css?v=1">
            <style>
                body {
                    background: #0f172a;
                    color: #f1f5f9;
                    font-family: system-ui, -apple-system, sans-serif;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0;
                }

                .container {
                    width: 100%;
                    max-width: 800px;
                    padding: 40px 20px;
                }

                .card {
                    background: #1e293b;
                    border: 1px solid #334155;
                    border-radius: 16px;
                    padding: 30px;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
                }

                .warning-banner {
                    background: #7f1d1d;
                    border: 1px solid #b91c1c;
                    color: #fca5a5;
                    padding: 20px;
                    border-radius: 12px;
                    margin-bottom: 30px;
                    font-weight: bold;
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    font-size: 1.1rem;
                }

                .form-group {
                    margin-bottom: 20px;
                }

                label {
                    display: block;
                    font-size: 0.85rem;
                    font-weight: 600;
                    color: #94a3b8;
                    margin-bottom: 8px;
                    text-transform: uppercase;
                }

                input {
                    width: 100%;
                    padding: 12px;
                    background: #0f172a;
                    border: 1px solid #334155;
                    border-radius: 8px;
                    color: white;
                    font-size: 1rem;
                    box-sizing: border-box;
                }

                button {
                    width: 100%;
                    padding: 14px;
                    background: #4f46e5;
                    color: white;
                    border: none;
                    border-radius: 8px;
                    font-weight: bold;
                    cursor: pointer;
                    font-size: 1rem;
                    margin-top: 10px;
                }

                button:hover {
                    background: #4338ca;
                }
            </style>
        </head>

        <body>
            <div class="container">
                <div class="card">
                    <div class="warning-banner">
                        <span style="font-size: 2rem;">⚠️</span>
                        <div>
                            <strong>Security Warning:</strong><br>
                            You are using default credentials. You must change your password to secure the system.
                        </div>
                    </div>
                    <h2 style="margin-top: 0; margin-bottom: 20px; font-size: 1.5rem;">Account Security</h2>
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" value="••••••••" readonly>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" placeholder="Enter new complex password">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" placeholder="Confirm new complex password">
                    </div>
                    <button type="button" onclick="alert('Password updated successfully! Redirecting...')">Update
                        Password</button>
                </div>
            </div>
            <script>
                try {
                    history.pushState(null, '', '../index.php?view=settings');
                } catch (e) { }
            </script>
        </body>

        </html>
        <?php
        exit();
    }
} catch (Exception $e) {
    $error = "System Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | System Portal</title>
    <link rel="stylesheet" href="../assets/styles/style.css?v=<?= filemtime('../assets/styles/style.css') ?>">
    <link rel="stylesheet" href="../assets/styles/login.css?v=<?= filemtime('../assets/styles/login.css') ?>">
    <link rel="stylesheet" href="../assets/styles/dialogs.css?v=<?= filemtime('../assets/styles/dialogs.css') ?>">
    <link rel="icon" type="image/png" href="../assets/icon/smart-home-sensor-wifi-black-outline-25276_1024.png">
    <script src="../assets/js/dialogEngine.js?v=<?= filemtime('../assets/js/dialogEngine.js') ?>"></script>
    <script src="https://unpkg.com/html5-qrcode" defer></script>
</head>

<body class="login-body">

    <div class="login-card">
        <div class="login-logo">
            <a href="../../index.php">
                <img src="../assets/icon/smart-home-sensor-wifi-black-outline-25276_1024.png" alt="IQA Metal Logo">
            </a>
        </div>

        <div class="login-header">
            <h1>System Portal</h1>
            <p>Enter your credentials to access order management.</p>
        </div>

        <?php if ($error): ?>
            <div class="login-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="login-form">
            <input type="hidden" name="return_url" value="<?= htmlspecialchars($_REQUEST['return_url'] ?? '') ?>">
            <div id="username-step">
                <div class="login-form-group">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label for="username" style="margin-bottom: 0;">Username</label>
                        <button type="button" id="btn-edit-username" onclick="enableUsernameEdit()"
                            style="display: none; background: none; border: none; color: #4f46e5; cursor: pointer; font-size: 0.85rem; font-weight: 600; padding: 0; text-transform: none;">Change</button>
                    </div>
                    <input type="text" id="username" name="username" class="login-input" placeholder="admin" required
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <button type="button" id="btn-next" onclick="handleUsernameSubmit()" class="btn-login">Next ➔</button>
            </div>

            <div id="password-step" style="display: none;">
                <div class="login-form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="login-input" placeholder="••••••••"
                        required>
                </div>
                <button type="submit" class="btn-login">🔒 Sign In Safely</button>
            </div>
        </form>

        <div class="login-footer multi-link-container" style="position: relative;">
            <small>&copy; <?= date('M, Y') ?> <span class="linked-text-info"
                    style="cursor: pointer; text-decoration: underline; font-weight: bold;">System</span> | Secured
                Batch fulfillment</small>

            <!-- Hidden Dialog Container containing the PPP Card -->
            <div class="info-dialog" id="dialog_ppp_card"
                style="max-width: 600px; width: 95%; text-align: left; color: #1e293b; background: white; padding: 25px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.25);">
                <button type="button" class="btn-close-dialog" aria-label="Close dialog">&times;</button>
                <div style="font-family: system-ui, -apple-system, sans-serif;">
                    <h2
                        style="font-size: 1.25rem; font-weight: 800; margin-top: 0; margin-bottom: 15px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                        🔑 Perfect Paper Passwords (PPP)</h2>

                    <div
                        style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 15px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 12px; align-items: center;">
                        <div style="flex: 1; min-width: 120px;">
                            <label
                                style="display: block; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 6px;">Password
                                Length Range</label>
                            <input type="number" id="ppp_length_input" value="30" min="25" max="80"
                                style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: bold; text-align: center;"
                                onchange="onLengthChange()">
                        </div>
                        <div style="flex: 2; min-width: 200px;">
                            <label
                                style="display: block; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 6px;">Sequence
                                Key</label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" id="ppp_display_key" placeholder="Generate or enter 64-hex key..."
                                    style="font-family: monospace; font-size: 0.75rem; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px; flex: 1; text-align: center;">
                                <button type="button" onclick="copySequenceKey()"
                                    style="background: #e2e8f0; color: #475569; border: none; padding: 0 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; cursor: pointer;"
                                    title="Copy to clipboard">📋</button>
                                <button type="button" onclick="startQRScanner()"
                                    style="background: #e2e8f0; color: #475569; border: none; padding: 0 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; cursor: pointer;"
                                    title="Scan QR Code via Camera">📷</button>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
                        <button type="button" onclick="triggerGenKey()"
                            style="background: #64748b; color: white; padding: 10px 16px; font-size: 0.85rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 800;">🎲
                            Gen Key</button>
                        <button type="button" onclick="applyManualKey()"
                            style="background: #4f46e5; color: white; padding: 10px 16px; font-size: 0.85rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 800; flex: 1;">🔍
                            Load Grid</button>
                    </div>

                    <!-- Scanner Container -->
                    <div id="reader"
                        style="width: 100%; max-width: 450px; margin: 15px auto; display: none; border: 1px solid #cbd5e1; border-radius: 12px; overflow: hidden; background: #000;">
                    </div>
                    <button type="button" id="btn_stop_scanner" onclick="stopQRScanner()"
                        style="display: none; background: #ef4444; color: white; padding: 10px 16px; font-size: 0.85rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 800; width: 100%; margin-bottom: 15px;">🛑
                        Stop Scanning</button>

                    <div id="qr-container-wrapper"
                        style="display: none; flex-direction: column; align-items: center; justify-content: center; background: white; padding: 10px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 15px;">
                        <a id="ppp_qr_link" href="#" target="_blank" title="Click to open QR Code in new tab"
                            style="cursor: pointer; display: block;">
                            <img id="ppp_qr_img" src="" alt="PPP QR Code"
                                style="width: 100px; height: 100px; border-radius: 8px; display: block;">
                        </a>
                        <span
                            style="font-size: 0.6rem; color: #64748b; font-weight: 800; margin-top: 4px; text-transform: uppercase;">Sequence
                            QR Code</span>
                    </div>

                    <!-- Table and Grid Preview -->
                    <div id="ppp-grid-section"
                        style="display: none; border-top: 1px dashed #e2e8f0; padding-top: 15px;">
                        <h3
                            style="font-size: 0.9rem; font-weight: 800; color: #1e293b; margin-top: 0; margin-bottom: 10px;">
                            Live Passcard Grid Preview</h3>
                        <p
                            style="font-size: 0.75rem; color: #64748b; margin-top: 0; margin-bottom: 10px; font-style: italic;">
                            Tip: Click any row in the preview grid to auto-fill its passcode into the login password
                            field.
                        </p>
                        <div
                            style="max-height: 200px; overflow: auto; border: 1px solid #e2e8f0; border-radius: 8px; background: white; margin-bottom: 15px;">
                            <table
                                style="width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.75rem; text-align: center; font-family: monospace; table-layout: fixed;">
                                <thead>
                                    <tr style="background: #f1f5f9;">
                                        <th
                                            style="color: #475569; font-weight: bold; padding: 8px 4px; border-bottom: 2px solid #cbd5e1; border-right: 1px solid #e2e8f0; width: 50px; position: sticky; top: 0; background: #f1f5f9;">
                                            Row</th>
                                        <th
                                            style="color: #475569; font-weight: bold; padding: 8px 4px; border-bottom: 2px solid #cbd5e1; border-right: 1px solid #e2e8f0; position: sticky; top: 0; background: #f1f5f9;">
                                            A</th>
                                        <th
                                            style="color: #475569; font-weight: bold; padding: 8px 4px; border-bottom: 2px solid #cbd5e1; border-right: 1px solid #e2e8f0; position: sticky; top: 0; background: #f1f5f9;">
                                            B</th>
                                        <th
                                            style="color: #475569; font-weight: bold; padding: 8px 4px; border-bottom: 2px solid #cbd5e1; border-right: 1px solid #e2e8f0; position: sticky; top: 0; background: #f1f5f9;">
                                            C</th>
                                        <th
                                            style="color: #475569; font-weight: bold; padding: 8px 4px; border-bottom: 2px solid #cbd5e1; border-right: 1px solid #e2e8f0; position: sticky; top: 0; background: #f1f5f9;">
                                            D</th>
                                        <th
                                            style="color: #475569; font-weight: bold; padding: 8px 4px; border-bottom: 2px solid #cbd5e1; position: sticky; top: 0; background: #f1f5f9;">
                                            E</th>
                                    </tr>
                                </thead>
                                <tbody id="ppp-grid-tbody">
                                    <!-- Populated dynamically -->
                                </tbody>
                            </table>
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <button type="button" onclick="printPPPCard()"
                                style="flex: 1; padding: 12px; border-radius: 8px; background: linear-gradient(135deg, #7c3aed, #4f46e5); color: white; border: none; font-weight: 800; cursor: pointer; font-size: 0.8rem;">🖨️
                                Print Passcard</button>
                            <button type="button" onclick="viewPPPCard()"
                                style="flex: 1; padding: 12px; border-radius: 8px; background: #64748b; color: white; border: none; font-weight: 800; cursor: pointer; font-size: 0.8rem;">📄
                                View Passcard</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PRINTABLE PASSCARD SOURCE -->
    <div id="ppp-printable-card-source" style="display: none;"></div>

    <script>
        let activeSeqKey = "";
        let pendingSeqKey = "";
        let selectedRowIdx = 0;

        function onManualKeyInput(val) {
            // No error label to toggle, just validation if needed
        }

        function applyManualKey() {
            const inputEl = document.getElementById('ppp_display_key');
            if (!inputEl) return;

            const rawVal = (inputEl.value || '').replace(/[^0-9a-fA-F]/g, '');
            if (rawVal.length < 16 || rawVal.length > 64) {
                alert("Please enter a valid hexadecimal Sequence Key (32-hex 128-bit or 64-hex 256-bit).");
                return;
            }

            inputEl.value = rawVal.toUpperCase();
            pendingSeqKey = rawVal.toUpperCase();
            activeSeqKey = pendingSeqKey;

            // Show QR and grid preview if they are hidden
            const qrWrapper = document.getElementById('qr-container-wrapper');
            if (qrWrapper) qrWrapper.style.display = 'flex';
            const gridSection = document.getElementById('ppp-grid-section');
            if (gridSection) gridSection.style.display = 'block';

            // Update QR image
            const encodedKey = encodeURIComponent(pendingSeqKey);
            const qrImg = document.getElementById('ppp_qr_img');
            if (qrImg) {
                qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=${encodedKey}`;
            }
            const qrLink = document.getElementById('ppp_qr_link');
            if (qrLink) {
                qrLink.href = `https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=${encodedKey}`;
            }

            // Fetch grid preview
            fetchGridPreview(activeSeqKey);
        }

        function generateRandomHexKey() {
            const chars = '0123456789ABCDEF';
            let result = '';
            for (let i = 0; i < 64; i++) {
                result += chars[Math.floor(Math.random() * 16)];
            }
            return result;
        }

        function triggerGenKey() {
            activeSeqKey = generateRandomHexKey();
            document.getElementById('ppp_display_key').value = activeSeqKey;

            // Show QR container and grid if hidden
            document.getElementById('qr-container-wrapper').style.display = 'flex';
            document.getElementById('ppp-grid-section').style.display = 'block';

            // Update QR image
            const encodedKey = encodeURIComponent(activeSeqKey);
            const qrImg = document.getElementById('ppp_qr_img');
            if (qrImg) {
                qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=${encodedKey}`;
            }
            const qrLink = document.getElementById('ppp_qr_link');
            if (qrLink) {
                qrLink.href = `https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=${encodedKey}`;
            }

            selectedRowIdx = 0;
            fetchGridPreview(activeSeqKey);
        }

        async function fetchGridPreview(seqKey) {
            const lengthInput = document.getElementById('ppp_length_input');
            const length = lengthInput ? (parseInt(lengthInput.value) || 30) : 30;

            try {
                const response = await fetch(`login.php?action=ajax_generate_ppp&seq_key=${seqKey}&length=${length}`);
                const data = await response.json();
                if (data.success) {
                    renderGrid(data.passcodes, seqKey);
                }
            } catch (e) {
                console.error("Failed to load passcodes preview:", e);
            }
        }

        function renderGrid(passcodes, seqKey) {
            const tbody = document.getElementById('ppp-grid-tbody');
            tbody.innerHTML = '';

            for (let r = 0; r < 25; r++) {
                const rowNum = r + 1;
                const isSelected = (selectedRowIdx === rowNum);
                const rowLabel = String(rowNum).padStart(2, '0');

                const tr = document.createElement('tr');
                tr.setAttribute('data-row-num', rowNum);
                tr.style.cursor = 'pointer';
                tr.style.background = isSelected ? '#e0f2fe' : ((r % 2 === 0) ? '#f8fafc' : '#ffffff');
                tr.onclick = function () { onRowClick(this, rowNum); };

                let tdRow = document.createElement('td');
                tdRow.style.padding = (r === 0) ? '10px 4px 8px 4px' : '8px 4px 8px 4px';
                tdRow.style.fontWeight = 'bold';
                tdRow.style.color = '#64748b';
                tdRow.style.borderRight = '1px solid #e2e8f0';
                tdRow.style.borderBottom = '1px solid #e2e8f0';
                tdRow.style.width = '50px';
                tdRow.innerText = rowLabel;
                tr.appendChild(tdRow);

                for (let c = 0; c < 5; c++) {
                    let tdCell = document.createElement('td');
                    tdCell.className = 'ppp-cell';
                    tdCell.style.padding = (r === 0) ? '10px 4px 8px 4px' : '8px 4px 8px 4px';
                    tdCell.style.fontWeight = 'bold';
                    tdCell.style.letterSpacing = '0.5px';
                    tdCell.style.borderBottom = '1px solid #e2e8f0';
                    if (c < 4) tdCell.style.borderRight = '1px solid #e2e8f0';
                    tdCell.style.wordBreak = 'break-all';

                    tdCell.innerText = passcodes[r * 5 + c];
                    tr.appendChild(tdCell);
                }
                tbody.appendChild(tr);
            }

            // Update QR image and link dynamically on grid load
            const encodedKey = encodeURIComponent(seqKey);
            const qrImg = document.getElementById('ppp_qr_img');
            if (qrImg) {
                qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=${encodedKey}`;
            }
            const qrLink = document.getElementById('ppp_qr_link');
            if (qrLink) {
                qrLink.href = `https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=${encodedKey}`;
            }

            updatePrintCardSource(passcodes, seqKey);
        }

        function updatePrintCardSource(passcodes, seqKey) {
            const source = document.getElementById('ppp-printable-card-source');
            if (!source) return;

            const lengthInput = document.getElementById('ppp_length_input');
            const length = lengthInput ? (parseInt(lengthInput.value) || 30) : 30;

            let tableRowsHtml = '';
            for (let r = 0; r < 25; r++) {
                const rowLabel = String(r + 1).padStart(2, '0');
                let cellsHtml = '';
                for (let c = 0; c < 5; c++) {
                    cellsHtml += `<td style='padding: 5px 3px; border: 1px solid #ccc; font-weight: bold; letter-spacing: 0.5px; white-space: nowrap;'>${passcodes[r * 5 + c]}</td>`;
                }
                tableRowsHtml += `<tr>
                <td style='padding: 5px 3px; border: 1px solid #ccc; font-weight: bold; background: #fafafa; white-space: nowrap;'>${rowLabel}</td>
                ${cellsHtml}
            </tr>`;
            }

            source.innerHTML = `
            <div style="border: 2px dashed #333; border-radius: 12px; padding: 20px; max-width: 100%; width: 100%; box-sizing: border-box; background: white; color: black; font-family: 'Courier New', Courier, monospace; box-shadow: 0 4px 10px rgba(0,0,0,0.15); margin: 20px auto;">
                <div style="display: flex; justify-content: space-between; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 15px;">
                    <strong style="font-size: 16px; letter-spacing: 1px;">PERFECT PAPER PASSCARD</strong>
                </div>
                <div style="font-size: 10px; margin-bottom: 15px; word-break: break-all; border: 1px solid #ddd; padding: 8px; background: #f9f9f9; border-radius: 6px;">
                    <strong>SEQUENCE KEY:</strong><br>${seqKey}
                </div>
                <table style="width: 100%; border-collapse: collapse; font-size: 11px; text-align: center; table-layout: auto;">
                    <thead>
                        <tr style="border-bottom: 2px solid #000; background: #eee;">
                            <th style="padding: 5px 3px; border: 1px solid #ccc; width: 50px;">Row</th>
                            <th style="padding: 5px 3px; border: 1px solid #ccc; font-weight: bold;">A</th>
                            <th style="padding: 5px 3px; border: 1px solid #ccc; font-weight: bold;">B</th>
                            <th style="padding: 5px 3px; border: 1px solid #ccc; font-weight: bold;">C</th>
                            <th style="padding: 5px 3px; border: 1px solid #ccc; font-weight: bold;">D</th>
                            <th style="padding: 5px 3px; border: 1px solid #ccc; font-weight: bold;">E</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${tableRowsHtml}
                    </tbody>
                </table>
                <div style="margin-top: 15px; text-align: center; font-size: 9px; color: #666; border-top: 1px solid #eee; padding-top: 8px;">
                    GRC Perfect Paper Passwords &bull; Password Length: ${length} &bull; Keep this card secure and offline.
                </div>
            </div>
        `;
        }

        function printPPPCard() {
            const source = document.getElementById('ppp-printable-card-source');
            if (!source) return;
            const printWindow = window.open('', '_blank');
            printWindow.document.write('<html><head><title>Print PPP Passcard</title></head><body style="margin:20px;">' + source.innerHTML + '</body></html>');
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 250);
        }

        function viewPPPCard() {
            const source = document.getElementById('ppp-printable-card-source');
            if (!source) return;
            const viewWindow = window.open('', '_blank');
            viewWindow.document.write('<html><head><title>PPP Passcard</title></head><body style="margin:20px;">' + source.innerHTML + '</body></html>');
            viewWindow.document.close();
            viewWindow.focus();
        }

        function onRowClick(rowElement, rowNum) {
            selectedRowIdx = rowNum;

            // Highlight selected row
            const tbody = document.getElementById('ppp-grid-tbody');
            const rows = tbody.querySelectorAll('tr');
            rows.forEach((r, idx) => {
                const rNum = parseInt(r.getAttribute('data-row-num'));
                if (rNum === selectedRowIdx) {
                    r.style.background = '#e0f2fe';
                } else {
                    r.style.background = (idx % 2 === 0) ? '#f8fafc' : '#ffffff';
                }
            });

            // Set password input value
            const cells = rowElement.querySelectorAll('.ppp-cell');
            let passcodeStr = '';
            cells.forEach(c => passcodeStr += c.innerText);

            const passwordInput = document.getElementById('password');
            if (passwordInput) {
                passwordInput.value = passcodeStr;
            }
        }

        function onLengthChange() {
            const input = document.getElementById('ppp_length_input');
            let val = parseInt(input.value) || 30;
            if (val < 25) val = 25;
            if (val > 80) val = 80;
            input.value = val;

            if (activeSeqKey) {
                fetchGridPreview(activeSeqKey);
            }
        }

        function copySequenceKey() {
            const input = document.getElementById('ppp_display_key');
            if (!input || !input.value) {
                alert("No sequence key generated yet!");
                return;
            }
            const key = input.value;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(key).then(() => {
                    alert('Sequence Key copied!');
                }).catch(err => {
                    fallbackCopyText(input);
                });
            } else {
                fallbackCopyText(input);
            }
        }

        function fallbackCopyText(input) {
            try {
                const wasReadOnly = input.readOnly;
                input.readOnly = false;
                input.select();
                input.setSelectionRange(0, 99999);
                const successful = document.execCommand('copy');
                input.readOnly = wasReadOnly;
                if (successful) {
                    alert('Sequence Key copied!');
                } else {
                    alert('Failed to copy. Please manually copy the text.');
                }
            } catch (err) {
                alert('Failed to copy. Please manually copy the text.');
            }
        }

        let html5QrCode = null;

        function startQRScanner() {
            const readerDiv = document.getElementById('reader');
            const stopBtn = document.getElementById('btn_stop_scanner');

            readerDiv.style.display = 'block';
            stopBtn.style.display = 'block';

            // Check for secure context
            if (!window.isSecureContext) {
                alert("Camera access requires a secure context (HTTPS or localhost). If you are accessing this site via HTTP, your mobile browser will block camera requests.");
            }

            html5QrCode = new Html5Qrcode("reader");
            const qrCodeSuccessCallback = (decodedText, decodedResult) => {
                let scannedText = decodedText.trim();
                // If it's a URL, extract the query parameters (e.g. data=HEX)
                if (scannedText.includes('data=')) {
                    try {
                        const urlParams = new URLSearchParams(scannedText.split('?')[1]);
                        scannedText = urlParams.get('data') || scannedText;
                    } catch (e) { }
                }

                // Remove non-hex characters and convert to uppercase
                scannedText = scannedText.replace(/[^a-fA-F0-9]/g, '').toUpperCase();
                if (scannedText.length === 64) {
                    document.getElementById('ppp_display_key').value = scannedText;
                    stopQRScanner();
                    applyManualKey(); // Immediately load the grid
                } else {
                    alert("Scanned text is not a valid 64-hex sequence key! Code: " + scannedText);
                }
            };
            const config = { fps: 10, qrbox: { width: 250, height: 250 } };

            // Query available cameras to pick the back/rear one explicitly (often fixes mobile camera selection bugs)
            Html5Qrcode.getCameras().then(devices => {
                if (devices && devices.length > 0) {
                    let cameraId = devices[0].id;
                    // Seek environment/rear/back camera
                    for (const device of devices) {
                        const label = device.label.toLowerCase();
                        if (label.includes('back') || label.includes('environment') || label.includes('rear') || label.includes('out')) {
                            cameraId = device.id;
                            break;
                        }
                    }
                    return html5QrCode.start(cameraId, config, qrCodeSuccessCallback);
                } else {
                    return html5QrCode.start({ facingMode: "environment" }, config, qrCodeSuccessCallback);
                }
            }).catch(err => {
                console.warn("getCameras failed or rejected, falling back to facingMode constraints:", err);
                return html5QrCode.start({ facingMode: "environment" }, config, qrCodeSuccessCallback);
            }).catch(err => {
                console.error("Camera access failed:", err);
                alert("Could not start camera: " + err + "\n\nMake sure the app is hosted on HTTPS and camera permissions are allowed.");
                stopQRScanner();
            });
        }

        function stopQRScanner() {
            if (html5QrCode) {
                html5QrCode.stop().then(() => {
                    document.getElementById('reader').style.display = 'none';
                    document.getElementById('btn_stop_scanner').style.display = 'none';
                    html5QrCode = null;
                }).catch(err => {
                    console.error("Failed to stop scanner:", err);
                    document.getElementById('reader').style.display = 'none';
                    document.getElementById('btn_stop_scanner').style.display = 'none';
                    html5QrCode = null;
                });
            } else {
                document.getElementById('reader').style.display = 'none';
                document.getElementById('btn_stop_scanner').style.display = 'none';
            }
        }

        function enableUsernameEdit() {
            const usernameInput = document.getElementById('username');
            usernameInput.readOnly = false;
            usernameInput.focus();

            document.getElementById('btn-edit-username').style.display = 'none';
            document.getElementById('btn-next').style.display = 'block';
            document.getElementById('password-step').style.display = 'none';
            document.getElementById('password').value = '';
        }

        async function handleUsernameSubmit() {
            const usernameInput = document.getElementById('username');
            let username = usernameInput.value.trim();
            if (!username) {
                alert("Please enter a username.");
                return;
            }

            const nextBtn = document.getElementById('btn-next');
            const origBtnText = nextBtn.innerText;
            nextBtn.disabled = true;
            nextBtn.innerText = "Checking...";

            try {
                const response = await fetch(`login.php?action=check_username&username=${encodeURIComponent(username)}`);
                const data = await response.json();

                if (data.lockout) {
                    // Show lockout error
                    let errorDiv = document.querySelector('.login-error');
                    if (!errorDiv) {
                        errorDiv = document.createElement('div');
                        errorDiv.className = 'login-error';
                        const card = document.querySelector('.login-card');
                        card.insertBefore(errorDiv, document.getElementById('login-form'));
                    }
                    errorDiv.innerText = `Too many failed login attempts. Please try again in ${data.remaining} seconds.`;
                    nextBtn.disabled = false;
                    nextBtn.innerText = origBtnText;
                    return;
                }

                if (data.honeypot) {
                    // Trigger honeypot view by reloading the page which will render the warning
                    window.location.reload();
                    return;
                }

                if (data.success) {
                    // Hide username next button, show password step, lock username, show edit option
                    usernameInput.readOnly = true;
                    document.getElementById('btn-edit-username').style.display = 'inline-block';
                    nextBtn.style.display = 'none';
                    document.getElementById('password-step').style.display = 'block';
                    document.getElementById('password').focus();
                }
            } catch (e) {
                console.error("Username check failed:", e);
                // Fallback: allow them to proceed anyway to avoid locking them out on minor script errors
                usernameInput.readOnly = true;
                document.getElementById('btn-edit-username').style.display = 'inline-block';
                nextBtn.style.display = 'none';
                document.getElementById('password-step').style.display = 'block';
                document.getElementById('password').focus();
            } finally {
                nextBtn.disabled = false;
                nextBtn.innerText = origBtnText;
            }
        }
    </script>

</body>

</html>