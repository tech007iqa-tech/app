<?php
require_once '../core/database.php';
require_once '../core/auth.php';
require_once __DIR__ . '/../../orders/core/Security.php';
require_once __DIR__ . '/../../orders/core/UI.php';

Security::init();

$conn_u = Database::getConnection('users');
$username = $_SESSION['username'];
$error = null;
$message = null;

if (isset($_SESSION['settings_success_message'])) {
    $message = $_SESSION['settings_success_message'];
    unset($_SESSION['settings_success_message']);
}

// Helper to generate PPP passcodes
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

// AJAX handler for settings page key verification
if (isset($_GET['action']) && $_GET['action'] === 'ajax_generate_ppp') {
    header('Content-Type: application/json');
    $seq_key = preg_replace('/[^a-fA-F0-9]/', '', trim($_GET['seq_key'] ?? ''));
    $length = (int)($_GET['length'] ?? 30);
    if (strlen($seq_key) < 16 || strlen($seq_key) > 64) {
        echo json_encode(['success' => false, 'error' => 'Invalid sequence key']);
        exit();
    }

    $cell_len = (int)ceil($length / 5.0);
    $passcodes = generate_ppp_passcodes($seq_key, $cell_len);
    echo json_encode(['success' => true, 'passcodes' => $passcodes]);
    exit();
}

// Fetch current user details
try {
    $stmt_ppp = $conn_u->prepare("SELECT ppp_sequence_key, ppp_row_index, ppp_password_len FROM users WHERE username = ?");
    $stmt_ppp->execute([$username]);
    $user_row = $stmt_ppp->fetch(PDO::FETCH_ASSOC);
    $seq_key = $user_row['ppp_sequence_key'] ?? '';
    $saved_row_index = (int)($user_row['ppp_row_index'] ?? 0);
    $saved_pass_len = (int)($user_row['ppp_password_len'] ?? ($_SESSION['ppp_password_len'] ?? 30));
    if ($saved_pass_len < 25) {
        $saved_pass_len = 30; // Fallback default
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle Password Change Form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    // Validate CSRF
    if (!Security::validate($_POST['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please try again.";
    } else {
        $old_pass = $_POST['old_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';
        $new_seq_key = trim($_POST['ppp_sequence_key'] ?? '');
        $ppp_row_index = (int)($_POST['ppp_row_index'] ?? 0);
        $user_id = $username;

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
                        header("Location: settings.php");
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
}

$is_forced = (isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true);
$user_display = htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username']);
$user_role = htmlspecialchars($_SESSION['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings | Technician Dashboard</title>
    <link rel="stylesheet" href="../../orders/assets/styles/components.css">
    <link rel="stylesheet" href="../../orders/assets/styles/style.css">
    <link rel="stylesheet" href="../../orders/assets/styles/dialogs.css">
    <link rel="stylesheet" href="../assets/styles/settings.css">
    <link rel="icon" type="image/png" href="../../orders/assets/icon/smart-home-sensor-wifi-black-outline-25276_1024.png">
    <script src="../../orders/assets/js/dialogEngine.js"></script>
</head>
<body class="modern-theme">

    <div class="breadcrumb-container" role="banner" style="max-width: 1200px; margin: 20px auto; width: 95%; display: flex; justify-content: space-between; align-items: center;">
        <nav class="breadcrumbs">
            <a href="../index.php" class="crumb">
                <span class="step-num">🔧</span> Technician Dashboard
            </a>
            <span class="separator">/</span>
            <a href="settings.php" class="crumb active">
                <span class="step-num">⚙️</span> Settings
            </a>
        </nav>
        <div>
            <span class="user-info" style="font-size: 0.9rem; color: #64748b; font-weight: 600; margin-right: 15px;">👤 <?= $user_display ?> (<?= $user_role ?>)</span>
            <a href="../../orders/core/logout.php" class="btn-signout">Sign Out</a>
        </div>
    </div>

    <main class="container settings-page-wrapper">

        <?php if ($is_forced): ?>
            <div class="status-msg msg-error" style="background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5;">
                ⚠️ <strong>Security Warning:</strong> You are using default/weak credentials. You must update your password to continue using the system.
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="status-msg msg-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="status-msg msg-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- UNIFIED PASSWORD UPDATE FORM -->
        <form method="POST" id="password-update-form" style="width: 100%; display: flex; flex-direction: column; align-items: center; gap: 30px;">
            <?= UI::csrf_field() ?>
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="ppp_sequence_key" id="ppp_sequence_key_input" value="<?= htmlspecialchars($seq_key) ?>">
            <input type="hidden" name="ppp_row_index" id="ppp_row_index_input" value="<?= $saved_row_index ?>">

            <!-- Perfect Paper Passwords (PPP) Card -->
            <div class="settings-card" id="ppp-card">
                <div class="settings-header multi-link-container" style="position: relative;">
                    <h1>🔑 Perfect Paper Passwords (PPP)</h1>
                    <p class="subtitle">Your offline, ultra-secure one-time passcode system.
                        <span class="linked-text-info" style="color: #4f46e5; text-decoration: underline; font-weight: bold; cursor: pointer;">Show Info</span>.
                    </p>

                    <!-- PPP Information Dialog -->
                    <div class="info-dialog" style="max-width: 500px; width: 90%;">
                        <button type="button" class="btn-close-dialog" aria-label="Close dialog">&times;</button>
                        <div style="padding: 10px; text-align: left; line-height: 1.6; font-family: system-ui, -apple-system, sans-serif; color: #1e293b;">
                            <h2 style="font-size: 1.3rem; font-weight: 800; color: #1e293b; margin-top: 0; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">🔑 How PPP Works</h2>
                            <p style="font-size: 0.9rem; color: #475569; margin-bottom: 12px;">
                                <strong>Perfect Paper Passwords (PPP)</strong> is an offline, paper-based authentication system.
                            </p>
                            <h3 style="font-size: 1rem; font-weight: 700; color: #1e293b; margin-bottom: 8px;">Security Instructions:</h3>
                            <ul style="font-size: 0.85rem; color: #475569; padding-left: 20px; margin-bottom: 16px;">
                                <li style="margin-bottom: 6px;"><strong>Print the Card:</strong> Click "Print Passcard" to print a physical copy.</li>
                                <li style="margin-bottom: 6px;"><strong>Passcode Grid:</strong> The card contains 125 unique passcodes indexed from Row 01 to 25 and Columns A to E.</li>
                                <li style="margin-bottom: 6px;"><strong>Authentication:</strong> On login, enter the passcode from the specified cells.</li>
                                <li style="margin-bottom: 6px;"><strong>No Secrets Stored Online:</strong> Only the Sequence Key is stored.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 16px; align-items: center;">
                    <div style="flex: 1; min-width: 150px;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px;">Password Length Range</label>
                        <input type="number" id="ppp_length_input" name="ppp_length" value="<?= $saved_pass_len ?>" min="25" max="80" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: bold; text-align: center;" onchange="onLengthChange()">
                    </div>
                </div>

                <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 16px;">
                    <div style="flex: 1; min-width: 200px;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px;">Sequence Key</label>
                        <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                            <input type="text" value="<?= htmlspecialchars($seq_key) ?>" placeholder="Generate a key to start..." readonly style="font-family: monospace; font-size: 0.75rem; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px; flex: 1; text-align: center;" id="ppp_display_key">
                            <button type="button" onclick="copySequenceKey()" style="background: #e2e8f0; color: #475569; border: none; padding: 0 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; cursor: pointer; height: 34px;">📋</button>
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <button type="button" class="btn-main" onclick="triggerGenKey()" style="background:#64748b; color:white; white-space:nowrap; padding: 0 16px; font-size:0.85rem; border-radius:10px; border:none; cursor:pointer; height:38px; font-weight:800; display: flex; align-items: center; gap: 6px;">🎲 Gen Key</button>

                            <div class="multi-link-container" style="position: relative;">
                                <button type="button" class="btn-main linked-text-info" style="background:#4f46e5; color:white; white-space:nowrap; padding: 0 16px; font-size:0.85rem; border-radius:10px; border:none; cursor:pointer; height:38px; font-weight:800; display: flex; align-items: center; gap: 6px;">🔍 Load Key</button>

                                <div class="info-dialog" id="dialog_seq_key" style="max-width: 500px; width: 90%;">
                                    <button type="button" class="btn-close-dialog" aria-label="Close dialog">&times;</button>
                                    <div style="padding: 10px; text-align: left; line-height: 1.6; font-family: system-ui, -apple-system, sans-serif; color: #1e293b;">
                                        <h2 style="font-size: 1.3rem; font-weight: 800; color: #1e293b; margin-top: 0; margin-bottom: 12px;">🔑 Load Sequence Key</h2>
                                        <p style="font-size: 0.9rem; color: #475569; margin-bottom: 12px;">
                                            Enter an existing 64-character hexadecimal Sequence Key to generate and preview its passcard grid.
                                        </p>
                                        <input type="text" id="manual_seq_input" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: monospace; font-size: 0.75rem; text-align: center; box-sizing: border-box; margin-bottom: 15px;" placeholder="ENTER 64-CHAR HEX KEY...">
                                        <button type="button" onclick="loadManualKey()" style="width: 100%; padding: 12px; background: #4f46e5; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer;">Verify & Load Grid</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="qr-wrapper-settings" style="display: <?= empty($seq_key) ? 'none' : 'flex' ?>; flex-direction: column; align-items: center; justify-content: center; background: white; padding: 10px; border-radius: 12px; border: 1px solid #cbd5e1;">
                        <img id="ppp_qr_img" src="<?= !empty($seq_key) ? 'https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=' . urlencode($seq_key) : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' ?>" alt="PPP QR Code" style="width: 110px; height: 110px; border-radius: 8px; display: block;">
                        <span style="font-size: 0.6rem; color: #64748b; font-weight: 800; margin-top: 4px; text-transform: uppercase;">Sequence QR Code</span>
                    </div>
                </div>

                <!-- Preview Grid Section -->
                <div id="ppp-grid-section" style="border-top: 1px dashed var(--border-color); padding-top: 20px; margin-top: 20px; display: <?= empty($seq_key) ? 'none' : 'block' ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h2 style="font-size: 1rem; font-weight: 800; color: var(--text-main); margin: 0;">Live Preview Grid</h2>
                    </div>

                    <div style="max-height: 250px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 12px; background: white; margin-bottom: 20px; box-sizing: border-box;">
                        <table style="width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.75rem; text-align: center; font-family: monospace; table-layout: fixed;">
                            <thead>
                                <tr style="background: #f8fafc; position: sticky; top: 0; z-index: 1;">
                                    <th style="color: var(--text-secondary); font-weight: 800; padding: 10px 4px; border-bottom: 2px solid #e2e8f0; border-right: 1px solid #f1f5f9; width: 50px; background: #f8fafc;">Row</th>
                                    <th style="color: var(--text-secondary); font-weight: 800; padding: 10px 4px; border-bottom: 2px solid #e2e8f0; border-right: 1px solid #f1f5f9; background: #f8fafc;">A</th>
                                    <th style="color: var(--text-secondary); font-weight: 800; padding: 10px 4px; border-bottom: 2px solid #e2e8f0; border-right: 1px solid #f1f5f9; background: #f8fafc;">B</th>
                                    <th style="color: var(--text-secondary); font-weight: 800; padding: 10px 4px; border-bottom: 2px solid #e2e8f0; border-right: 1px solid #f1f5f9; background: #f8fafc;">C</th>
                                    <th style="color: var(--text-secondary); font-weight: 800; padding: 10px 4px; border-bottom: 2px solid #e2e8f0; border-right: 1px solid #f1f5f9; background: #f8fafc;">D</th>
                                    <th style="color: var(--text-secondary); font-weight: 800; padding: 10px 4px; border-bottom: 2px solid #e2e8f0; background: #f8fafc;">E</th>
                                </tr>
                            </thead>
                            <tbody id="ppp-grid-tbody">
                                <?php
                                $actual_codes = !empty($seq_key) ? generate_ppp_passcodes($seq_key, (int)ceil($saved_pass_len / 5.0)) : [];
                                if (!empty($actual_codes)) {
                                    for ($r = 0; $r < 25; $r++) {
                                        $row_label = str_pad($r + 1, 2, '0', STR_PAD_LEFT);
                                        echo "<tr>";
                                        echo "<td style='padding: 8px 4px; font-weight: 800; color: var(--text-secondary); border-bottom: 1px solid #f1f5f9; border-right: 1px solid #f1f5f9; width: 50px; background: #f8fafc;'>" . str_pad($r + 1, 2, '0', STR_PAD_LEFT) . "</td>";
                                        for ($c = 0; $c < 5; $c++) {
                                            $code_val = $actual_codes[$r * 5 + $c] ?? '';
                                            $border_right = $c < 4 ? "border-right: 1px solid #f1f5f9;" : "";
                                            echo "<td class='ppp-cell' style='padding: 8px 4px; font-weight: bold; letter-spacing: 0.5px; border-bottom: 1px solid #f1f5f9; {$border_right} word-break: break-all;'>" . htmlspecialchars($code_val) . "</td>";
                                        }
                                        echo "</tr>";
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button type="button" onclick="printPPPCard()" style="flex: 1; padding: 12px; border-radius: 8px; background: linear-gradient(135deg, #7c3aed, #4f46e5); color: white; border: none; font-weight: 800; cursor: pointer; font-size: 0.8rem;">🖨️ Print Passcard</button>
                        <button type="button" onclick="viewPPPCard()" style="flex: 1; padding: 12px; border-radius: 8px; background: #64748b; color: white; border: none; font-weight: 800; cursor: pointer; font-size: 0.8rem;">📄 View Passcard</button>
                    </div>
                </div>
            </div>

            <!-- Custom / Custom Password Card -->
            <div class="settings-card">
                <div class="settings-header">
                    <h1>🔐 Update Security Password</h1>
                    <p class="subtitle">Change your password credentials here.</p>
                </div>

                <!-- Bypass PPP Toggle -->
                <div style="display: flex; align-items: center; justify-content: space-between; background: #f8fafc; padding: 16px 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px;">
                    <div>
                        <strong style="display: block; color: var(--text-main); margin-bottom: 4px;">Bypass PPP (Use Custom Password)</strong>
                        <span style="font-size: 0.8rem; color: var(--text-secondary);">Disable Perfect Paper Passwords and set a traditional text password.</span>
                    </div>
                    <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 24px;">
                        <input type="checkbox" name="bypass_ppp" value="1" id="bypass_ppp_checkbox" onchange="toggleBypassPPP()" style="opacity: 0; width: 0; height: 0;">
                        <span class="slider round" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 24px;"></span>
                    </label>
                </div>

                <?php if (!$is_forced): ?>
                    <div class="form-group">
                        <label for="old_password">Current Password</label>
                        <input type="password" id="old_password" name="old_password" placeholder="ENTER CURRENT PASSWORD">
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required placeholder="MIN 25 CHARS (OR 12 IF BYPASSED)">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="CONFIRM NEW PASSWORD">
                </div>

                <button type="submit" class="btn-main" style="width: 100%; padding: 14px; border-radius: 10px; background: var(--accent-color); color: white; font-weight: bold; border: none; cursor: pointer;">Save Password Settings</button>
            </div>
        </form>

    </main>

    <!-- PRINTABLE PASSCARD SOURCE -->
    <div id="ppp-printable-card-source" style="display: none;"></div>

    <script>
    let activeSeqKey = "<?= htmlspecialchars($seq_key) ?>";

    // Switch slider style helper
    const slider = document.querySelector('.slider');
    const checkbox = document.querySelector('#bypass_ppp_checkbox');
    function updateSliderStyle() {
        if (checkbox.checked) {
            slider.style.backgroundColor = '#10b981';
        } else {
            slider.style.backgroundColor = '#cbd5e1';
        }
    }
    checkbox.addEventListener('change', updateSliderStyle);
    updateSliderStyle();

    // Helper functions to mimic php helper String functions
    function String(val) {
        let s = val.toString();
        return {
            padStart: function(targetLength, padString) {
                return s.padStart(targetLength, padString);
            }
        };
    }

    function toggleBypassPPP() {
        const isBypassed = document.getElementById('bypass_ppp_checkbox').checked;
        const pppCard = document.getElementById('ppp-card');
        const newPassInput = document.getElementById('new_password');

        if (isBypassed) {
            pppCard.style.opacity = '0.5';
            pppCard.style.pointerEvents = 'none';
            newPassInput.placeholder = "MIN 12 CHARACTERS WITH COMPLEXITY";
            document.getElementById('ppp_sequence_key_input').value = "";
        } else {
            pppCard.style.opacity = '1';
            pppCard.style.pointerEvents = 'auto';
            newPassInput.placeholder = "MIN 25 CHARACTERS (OR CLICK ANY ROW)";
            document.getElementById('ppp_sequence_key_input').value = activeSeqKey;
        }
    }

    // Call on load
    toggleBypassPPP();

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
        document.getElementById('ppp_sequence_key_input').value = activeSeqKey;

        // Show QR and grid
        document.getElementById('qr-wrapper-settings').style.display = 'flex';
        document.getElementById('ppp-grid-section').style.display = 'block';

        const encodedKey = encodeURIComponent(activeSeqKey);
        const qrImg = document.getElementById('ppp_qr_img');
        if (qrImg) {
            qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=${encodedKey}`;
        }

        fetchGridPreview(activeSeqKey);
    }

    function loadManualKey() {
        const input = document.getElementById('manual_seq_input');
        if (!input) return;

        const val = input.value.trim();
        if (!/^[a-fA-F0-9]{64}$/.test(val)) {
            alert("Sequence key must be exactly 64 hexadecimal characters!");
            return;
        }

        activeSeqKey = val.toUpperCase();
        document.getElementById('ppp_display_key').value = activeSeqKey;
        document.getElementById('ppp_sequence_key_input').value = activeSeqKey;

        document.getElementById('qr-wrapper-settings').style.display = 'flex';
        document.getElementById('ppp-grid-section').style.display = 'block';

        const encodedKey = encodeURIComponent(activeSeqKey);
        const qrImg = document.getElementById('ppp_qr_img');
        if (qrImg) {
            qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=${encodedKey}`;
        }

        fetchGridPreview(activeSeqKey);

        // Close dialog
        if (window.dialogEngine) {
            window.dialogEngine.closeAnyOpenDialogs();
        }
    }

    async function fetchGridPreview(seqKey) {
        const lengthInput = document.getElementById('ppp_length_input');
        const length = lengthInput ? (parseInt(lengthInput.value) || 30) : 30;

        try {
            const response = await fetch(`settings.php?action=ajax_generate_ppp&seq_key=${seqKey}&length=${length}`);
            const data = await response.json();
            if (data.success) {
                renderGrid(data.passcodes, seqKey);
            }
        } catch(e) {
            console.error("Failed to load passcodes preview:", e);
        }
    }

    function renderGrid(passcodes, seqKey) {
        const tbody = document.getElementById('ppp-grid-tbody');
        tbody.innerHTML = '';

        for (let r = 0; r < 25; r++) {
            const rowNum = r + 1;
            const rowLabel = String(rowNum).padStart(2, '0');

            const tr = document.createElement('tr');
            tr.setAttribute('data-row-num', rowNum);
            tr.style.cursor = 'pointer';
            tr.style.background = ((r % 2 === 0) ? '#f8fafc' : '#ffffff');
            tr.onclick = function() { onRowClick(this, rowNum); };

            let tdRow = document.createElement('td');
            tdRow.style.padding = '8px 4px';
            tdRow.style.fontWeight = 'bold';
            tdRow.style.color = '#64748b';
            tdRow.style.borderRight = '1px solid #f1f5f9';
            tdRow.style.borderBottom = '1px solid #f1f5f9';
            tdRow.style.width = '50px';
            tdRow.innerText = rowLabel;
            tr.appendChild(tdRow);

            for (let c = 0; c < 5; c++) {
                let tdCell = document.createElement('td');
                tdCell.className = 'ppp-cell';
                tdCell.style.padding = '8px 4px';
                tdCell.style.fontWeight = 'bold';
                tdCell.style.letterSpacing = '0.5px';
                tdCell.style.borderBottom = '1px solid #f1f5f9';
                if (c < 4) tdCell.style.borderRight = '1px solid #f1f5f9';
                tdCell.style.wordBreak = 'break-all';

                tdCell.innerText = passcodes[r * 5 + c];
                tr.appendChild(tdCell);
            }
            tbody.appendChild(tr);
        }

        updatePrintCardSource(passcodes, seqKey);
    }

    function onRowClick(rowElement, rowNum) {
        const isBypassed = document.getElementById('bypass_ppp_checkbox').checked;
        if (isBypassed) return;

        // Highlight selected row
        const tbody = document.getElementById('ppp-grid-tbody');
        const rows = tbody.querySelectorAll('tr');
        rows.forEach((r, idx) => {
            const rNum = parseInt(r.getAttribute('data-row-num'));
            if (rNum === rowNum) {
                r.style.background = '#e0f2fe';
            } else {
                r.style.background = (idx % 2 === 0) ? '#f8fafc' : '#ffffff';
            }
        });

        // Set row index hidden input
        document.getElementById('ppp_row_index_input').value = rowNum;

        // Set password fields
        const cells = rowElement.querySelectorAll('.ppp-cell');
        let passcodeStr = '';
        cells.forEach(c => passcodeStr += c.innerText);

        document.getElementById('new_password').value = passcodeStr;
        document.getElementById('confirm_password').value = passcodeStr;
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

    // Initialize layout print source on load
    <?php if (!empty($seq_key)): ?>
        fetchGridPreview("<?= htmlspecialchars($seq_key) ?>");
    <?php endif; ?>
    </script>
</body>
</html>
