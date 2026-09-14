<?php
/**
 * IQA Metal Warehouse Systems - System Setup & Trade Configuration Wizard
 * Tailored for Computer Refurbishing, IT Asset Disposition (ITAD), and Electronics Warehouses.
 */

require_once __DIR__ . '/../core/Company.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/UI.php';
require_once __DIR__ . '/../core/Database.php';

Security::init();

/**
 * Verifies an administrator password input against the active database record.
 * Supports Perfect Paper Passwords (PPP) with or without salted key, standard bcrypt hashes,
 * and handles whitespace variations.
 */
function verify_admin_password_input($input_password, $admin_record)
{
    if (!$admin_record || empty($input_password)) {
        return false;
    }
    $clean_password = preg_replace('/\s+/', '', $input_password);
    $verified = false;
    if (!empty($admin_record['ppp_sequence_key'])) {
        $verified = password_verify($input_password . $admin_record['ppp_sequence_key'], $admin_record['password'])
            || password_verify($clean_password . $admin_record['ppp_sequence_key'], $admin_record['password']);
    }
    if (!$verified) {
        $verified = password_verify($input_password, $admin_record['password'])
            || password_verify($clean_password, $admin_record['password']);
    }
    return $verified;
}

// Fetch active administrator account from users.db
$admin_record = null;
$has_password_in_place = false;
try {
    $conn_u = Database::users();
    $stmt_u = $conn_u->query("SELECT * FROM users WHERE username = 'admin' OR role = 'Admin' ORDER BY id ASC LIMIT 1");
    if ($stmt_u && ($row = $stmt_u->fetch(PDO::FETCH_ASSOC))) {
        $admin_record = $row;
        if (!empty($row['password'])) {
            $has_password_in_place = true;
        }
    }
} catch (Exception $e) {
}

$is_already_setup = Company::isSetupComplete();
$system_protected = $is_already_setup || $has_password_in_place;

// Check reconfigure session unlock status (15-minute validity window)
$is_unlocked = false;
if (isset($_SESSION['setup_reconfigure_unlocked']) && (time() - (int) $_SESSION['setup_reconfigure_unlocked'] < 900)) {
    $is_unlocked = true;
}

// Immediate lock action handler
if (isset($_GET['lock']) || (isset($_POST['action']) && $_POST['action'] === 'lock_setup')) {
    unset($_SESSION['setup_reconfigure_unlocked']);
    header("Location: ../index.php");
    exit();
}

// AJAX handler for generating PPP passcodes in setup wizard
if (isset($_GET['action']) && $_GET['action'] === 'ajax_generate_ppp') {
    header('Content-Type: application/json');
    if ($system_protected && !$is_unlocked) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Security challenge required. Setup wizard is locked.']);
        exit();
    }
    $seq_key = preg_replace('/[^a-fA-F0-9]/', '', trim($_GET['seq_key'] ?? ''));
    $length = (int) ($_GET['length'] ?? 30);
    if (strlen($seq_key) < 16 || strlen($seq_key) > 64) {
        echo json_encode(['success' => false, 'error' => 'Invalid sequence key (must be 16 to 64 hex characters)']);
        exit();
    }

    $cell_len = (int) ceil($length / 5.0);
    $passcodes = Security::generate_ppp_passcodes($seq_key, $cell_len);
    echo json_encode(['success' => true, 'passcodes' => $passcodes]);
    exit();
}

$reconfigure = isset($_GET['reconfigure']) && $_GET['reconfigure'] === '1';

// Handle Reconfiguration Unlock Challenge
$unlock_error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unlock_reconfigure') {
    if (!Security::validate($_POST['csrf_token'] ?? '')) {
        $unlock_error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $submitted_pass = $_POST['admin_password'] ?? '';
        if (empty($submitted_pass)) {
            $unlock_error = 'Please enter your current administrator password to proceed.';
        } elseif (verify_admin_password_input($submitted_pass, $admin_record)) {
            // Password verified! Grant 15-minute access window and set session auth
            $_SESSION['setup_reconfigure_unlocked'] = time();
            $_SESSION['authenticated'] = true;
            $_SESSION['username'] = $admin_record['username'] ?? 'admin';
            $_SESSION['role'] = 'Admin';
            $_SESSION['display_name'] = $admin_record['display_name'] ?: 'Administrator';

            header("Location: index.php?reconfigure=1");
            exit();
        } else {
            $unlock_error = 'Access Denied: The administrator password entered is incorrect.';
        }
    }
}

// If already set up and not reconfiguring and no POST action, redirect to portal
if ($is_already_setup && !$reconfigure && !isset($_POST['action'])) {
    header("Location: ../index.php");
    exit();
}

$error = '';
$success = false;

// Handle Form Submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_setup') {
    if (!Security::validate($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        // Enforce password confirmation for changes on protected systems
        if ($system_protected) {
            $confirm_pass = $_POST['current_admin_password'] ?? '';
            if (empty($confirm_pass) || !verify_admin_password_input($confirm_pass, $admin_record)) {
                $error = 'Security Authorization Failed: You must confirm your current administrator password to commit changes. No modifications were saved.';
            }
        }

        if (empty($error)) {
            $company_name = trim($_POST['company_name'] ?? 'IQA Metal');
            $system_name = trim($_POST['system_name'] ?? 'IQA Metal Warehouse Systems');
            $company_url = trim($_POST['company_url'] ?? 'https://latinospc.com');
            $support_email = trim($_POST['support_email'] ?? 'contact@latinospc.com');
            $currency_symbol = trim($_POST['currency_symbol'] ?? '$');
            $tagline = trim($_POST['tagline'] ?? 'Intelligent inventory management & rapid label logistics.');

            $hardware_lines = $_POST['hardware_lines'] ?? ['Laptops', 'Desktops', 'Monitors', 'Parts'];
            $grading_standards = $_POST['grading_standards'] ?? ['A-Grade', 'B-Grade', 'C-Grade', 'Untested', 'Scrap'];
            $diagnostics = $_POST['diagnostics'] ?? ['CPU', 'RAM', 'Storage', 'Battery', 'BIOS', 'OS'];
            $label_preset = trim($_POST['label_preset'] ?? '4x6_thermal');

            $auth_mode = trim($_POST['auth_mode'] ?? ($system_protected ? 'keep_existing' : 'ppp'));
            $admin_user = trim($_POST['admin_user'] ?? 'admin');
            $admin_name = trim($_POST['admin_name'] ?? 'System Administrator');
            $ppp_sequence_key = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', trim($_POST['ppp_sequence_key'] ?? '')));
            $ppp_row_index = (int) ($_POST['ppp_row_index'] ?? 0);
            $ppp_password_len = (int) ($_POST['ppp_password_len'] ?? 30);
            $selected_passcode = trim($_POST['selected_passcode'] ?? '');
            $admin_pass = $_POST['admin_pass'] ?? '';
            $admin_pass_confirm = $_POST['admin_pass_confirm'] ?? '';

            if (empty($company_name)) {
                $error = 'Company name is required.';
            } elseif ($auth_mode === 'custom' && !$is_already_setup && (empty($admin_pass) || strlen($admin_pass) < 4)) {
                $error = 'Please provide a secure administrator password of at least 4 characters.';
            } elseif ($auth_mode === 'custom' && !empty($admin_pass) && ($admin_pass !== $admin_pass_confirm)) {
                $error = 'Administrator passwords do not match.';
            } elseif ($auth_mode === 'ppp' && (empty($ppp_sequence_key) || strlen($ppp_sequence_key) < 16 || strlen($ppp_sequence_key) > 64)) {
                $error = 'Please generate or enter a valid hexadecimal PPP sequence key (32-hex 128-bit or 64-hex 256-bit).';
            } elseif ($auth_mode === 'ppp' && ($ppp_row_index < 1 || $ppp_row_index > 25)) {
                $error = 'Please click to select an authentication row (Row 1-25) from the passcard grid.';
            } else {
                try {
                    // 1. Commit Settings into warehouse.db
                    $settings_data = [
                        'company_name' => $company_name,
                        'system_name' => $system_name,
                        'company_url' => $company_url,
                        'support_email' => $support_email,
                        'currency_symbol' => $currency_symbol,
                        'tagline' => $tagline,
                        'trade_description' => 'Used Computer, Laptop & Electronics Refurbishing',
                        'hardware_lines' => json_encode($hardware_lines),
                        'grading_standards' => json_encode($grading_standards),
                        'diagnostics_checklist' => json_encode($diagnostics),
                        'label_preset' => $label_preset,
                        'setup_completed' => '1',
                        'setup_timestamp' => date('Y-m-d H:i:s')
                    ];
                    Company::setMultiple($settings_data);

                    // 2. Commit Administrator Account into users.db
                    $conn_users = Database::users();
                    $conn_users->exec("CREATE TABLE IF NOT EXISTS users (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        username TEXT NOT NULL UNIQUE,
                        password TEXT NOT NULL,
                        role TEXT NOT NULL DEFAULT 'Admin',
                        display_name TEXT DEFAULT '',
                        ppp_sequence_key TEXT DEFAULT '',
                        ppp_row_index INTEGER DEFAULT 0,
                        ppp_password_len INTEGER DEFAULT 55
                    )");
                    $conn_users->exec("CREATE TABLE IF NOT EXISTS login_attempts (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        ip_address TEXT NOT NULL,
                        device_id TEXT NOT NULL,
                        username TEXT NOT NULL,
                        attempt_count INTEGER DEFAULT 0,
                        last_attempt_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    )");

                    if ($auth_mode === 'keep_existing') {
                        // Preserve current password and authentication parameters
                        $stmt_u = $conn_users->prepare("UPDATE users SET display_name = ? WHERE username = ?");
                        $stmt_u->execute([$admin_name, $admin_user]);
                    } elseif ($auth_mode === 'default_creds') {
                        // Default Credentials: admin / 123
                        $password_hash = password_hash('123', PASSWORD_BCRYPT);
                        $stmt_u = $conn_users->prepare("INSERT INTO users (username, password, display_name, role, ppp_sequence_key, ppp_row_index, ppp_password_len) 
                            VALUES (?, ?, ?, 'Admin', '', 0, 0)
                            ON CONFLICT(username) DO UPDATE SET password = excluded.password, display_name = excluded.display_name, role = 'Admin', ppp_sequence_key = '', ppp_row_index = 0, ppp_password_len = 0");
                        $stmt_u->execute([$admin_user, $password_hash, $admin_name]);
                    } elseif ($auth_mode === 'ppp') {
                        // Perfect Paper Passwords
                        if (empty($selected_passcode)) {
                            $cell_len = (int) ceil($ppp_password_len / 5.0);
                            $all_codes = Security::generate_ppp_passcodes($ppp_sequence_key, $cell_len);
                            $row_offset = ($ppp_row_index - 1) * 5;
                            $selected_passcode = implode('', array_slice($all_codes, $row_offset, 5));
                        }
                        $password_hash = password_hash($selected_passcode . $ppp_sequence_key, PASSWORD_BCRYPT);
                        $stmt_u = $conn_users->prepare("INSERT INTO users (username, password, display_name, role, ppp_sequence_key, ppp_row_index, ppp_password_len) 
                            VALUES (?, ?, ?, 'Admin', ?, ?, ?)
                            ON CONFLICT(username) DO UPDATE SET password = excluded.password, display_name = excluded.display_name, role = 'Admin', ppp_sequence_key = excluded.ppp_sequence_key, ppp_row_index = excluded.ppp_row_index, ppp_password_len = excluded.ppp_password_len");
                        $stmt_u->execute([$admin_user, $password_hash, $admin_name, $ppp_sequence_key, $ppp_row_index, $ppp_password_len]);
                    } elseif ($auth_mode === 'custom' && !empty($admin_pass)) {
                        // Custom Password
                        $password_hash = password_hash($admin_pass, PASSWORD_BCRYPT);
                        $stmt_u = $conn_users->prepare("INSERT INTO users (username, password, display_name, role, ppp_sequence_key, ppp_row_index, ppp_password_len) 
                            VALUES (?, ?, ?, 'Admin', '', 0, 0)
                            ON CONFLICT(username) DO UPDATE SET password = excluded.password, display_name = excluded.display_name, role = 'Admin', ppp_sequence_key = '', ppp_row_index = 0, ppp_password_len = 0");
                        $stmt_u->execute([$admin_user, $password_hash, $admin_name]);
                    }

                    // Clear login attempts to prevent lockouts
                    try {
                        $conn_users->exec("DELETE FROM login_attempts");
                    } catch (Exception $eAttempts) {
                    }

                    // Re-lock the reconfigure session so subsequent visits require authentication
                    unset($_SESSION['setup_reconfigure_unlocked']);
                    $success = true;
                } catch (Exception $e) {
                    $error = 'Failed to save configuration: ' . $e->getMessage();
                }
            }
        }
    }
}

// Prefill current or default values
$curr_company = Company::getName();
$curr_system = Company::getSystemName();
$curr_url = Company::getUrl();
$curr_email = Company::getEmail();
$curr_currency = Company::getCurrency();
$curr_tagline = Company::getTagline();

// Existing admin account PPP defaults
$existing_seq_key = $admin_record['ppp_sequence_key'] ?? '';
$existing_row_index = (int) ($admin_record['ppp_row_index'] ?? 0);
$existing_pass_len = (int) ($admin_record['ppp_password_len'] ?: 30);

if (empty($existing_seq_key)) {
    $existing_seq_key = Security::generate_ppp_key();
}

// IF SYSTEM HAS A PASSWORD IN PLACE AND IS NOT UNLOCKED:
// RENDER SECURITY WARNING AND PASSWORD CHALLENGE SCREEN AND EXIT!
if ($system_protected && !$is_unlocked):
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Security Verification Required | <?= htmlspecialchars($curr_company) ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap"
            rel="stylesheet">
        <link rel="stylesheet" href="../assets/css/components.css">
        <style>
            :root {
                --primary-color: #0056b3;
                --primary-dark: #082d45;
                --secondary-color: #218838;
                --secondary-dark: #155724;
                --bannerAndFooter-bg: #daedfb;
                --bg-base: #041521;
                --card-bg: rgba(8, 45, 69, 0.85);
                --card-border: rgba(218, 237, 251, 0.14);
                --accent-primary: #38bdf8;
                --accent-gradient: linear-gradient(135deg, #0056b3 0%, #38bdf8 50%, #218838 100%);
                --text-main: #f8fafc;
                --text-muted: #94a3b8;
                --input-bg: rgba(4, 21, 33, 0.75);
                --input-border: rgba(218, 237, 251, 0.16);
            }

            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            body {
                background-color: var(--bg-base);
                color: var(--text-main);
                font-family: 'Outfit', sans-serif;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                padding: 2rem 1rem;
                position: relative;
                overflow-x: hidden;
                background: radial-gradient(circle at 50% 0%, rgba(0, 86, 179, 0.25) 0%, transparent 60%),
                    linear-gradient(135deg, #041521 0%, #082d45 100%);
            }

            .glow-blob {
                position: fixed;
                border-radius: 50%;
                filter: blur(120px);
                z-index: 0;
                opacity: 0.35;
                pointer-events: none;
            }

            .blob-1 {
                top: -10%;
                left: -10%;
                width: 500px;
                height: 500px;
                background: #dc2626;
                opacity: 0.2;
            }

            .blob-2 {
                bottom: -10%;
                right: -10%;
                width: 500px;
                height: 500px;
                background: #0056b3;
            }

            .wizard-container {
                position: relative;
                z-index: 1;
                width: 100%;
                max-width: 580px;
                background: var(--card-bg);
                backdrop-filter: blur(20px);
                border: 1px solid rgba(239, 68, 68, 0.35);
                border-radius: 24px;
                box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.8), 0 0 40px rgba(239, 68, 68, 0.15);
                overflow: hidden;
                animation: fadeIn 0.4s ease-out;
            }

            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(16px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .alert-error {
                background: rgba(239, 68, 68, 0.18);
                border: 1px solid rgba(239, 68, 68, 0.45);
                color: #fca5a5;
                padding: 1rem;
                border-radius: 12px;
                margin-bottom: 1.5rem;
                font-size: 0.9rem;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .btn-wizard {
                border: none;
                cursor: pointer;
                font-family: inherit;
                font-weight: 700;
                border-radius: 12px;
                display: inline-flex;
                align-items: center;
                transition: all 0.2s;
            }

            .btn-submit {
                background: linear-gradient(135deg, #dc2626 0%, #ea580c 50%, #f59e0b 100%);
                color: white;
                box-shadow: 0 4px 15px rgba(239, 68, 68, 0.35);
            }

            .btn-submit:hover {
                transform: translateY(-1px);
                box-shadow: 0 6px 20px rgba(239, 68, 68, 0.5);
            }

            .btn-prev {
                background: rgba(255, 255, 255, 0.08);
                color: #cbd5e1;
                border: 1px solid var(--card-border);
            }

            .btn-prev:hover {
                background: rgba(255, 255, 255, 0.14);
                color: white;
            }

            input[type="password"],
            input[type="text"] {
                width: 100%;
                padding: 0.85rem 1rem;
                background: var(--input-bg);
                border: 1px solid var(--input-border);
                border-radius: 10px;
                color: white;
                font-family: inherit;
                font-size: 0.95rem;
                outline: none;
                transition: all 0.2s;
            }

            input:focus {
                border-color: #38bdf8;
                box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
            }
        </style>
    </head>

    <body>
        <div class="glow-blob blob-1"></div>
        <div class="glow-blob blob-2"></div>

        <div class="wizard-container">
            <!-- Prominent Red/Amber System Security Banner -->
            <div
                style="background: linear-gradient(90deg, rgba(220, 38, 38, 0.25) 0%, rgba(245, 158, 11, 0.25) 100%); border-bottom: 1px solid rgba(239, 68, 68, 0.4); padding: 12px 24px; text-align: center; color: #fca5a5; font-size: 0.85rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase;">
                ⚠️ Active Production System &bull; Password Protected
            </div>

            <div
                style="padding: 2.5rem 2.5rem 1.25rem; text-align: center; border-bottom: 1px solid var(--card-border); background: rgba(255, 255, 255, 0.02);">
                <div
                    style="display: inline-flex; align-items: center; justify-content: center; width: 68px; height: 68px; border-radius: 50%; background: rgba(239, 68, 68, 0.15); border: 2px solid rgba(239, 68, 68, 0.4); font-size: 2rem; margin-bottom: 12px; box-shadow: 0 0 30px rgba(239, 68, 68, 0.3);">
                    🛡️
                </div>
                <h1
                    style="font-size: 1.85rem; font-weight: 800; background: linear-gradient(135deg, #fca5a5 0%, #fbbf24 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 8px;">
                    Administrator Verification
                </h1>
                <p
                    style="font-size: 0.92rem; color: var(--text-muted); max-width: 480px; margin: 0 auto; line-height: 1.5;">
                    Reconfiguration access is restricted to verified administrators to safeguard active database records and
                    operational settings.
                </p>
            </div>

            <div style="padding: 2rem 2.5rem 2.5rem;">
                <?php if (!empty($unlock_error)): ?>
                    <div class="alert-error">
                        <span style="font-size: 1.3rem;">🚫</span>
                        <span><?= htmlspecialchars($unlock_error) ?></span>
                    </div>
                <?php endif; ?>

                <div
                    style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 14px; padding: 1.2rem; margin-bottom: 1.75rem; font-size: 0.84rem; color: #cbd5e1; line-height: 1.5;">
                    <div
                        style="display: flex; align-items: center; gap: 8px; color: #fbbf24; font-weight: 700; margin-bottom: 6px;">
                        <span>⚠️</span>
                        <span>System Warning: Active Warehouse Environment</span>
                    </div>
                    This installation has a password in place and is live. Modifying system identity, trade presets, or
                    authentication parameters directly impacts active orders, technician diagnostics, and staff logins.
                </div>

                <form method="POST" action="index.php?reconfigure=1">
                    <?= UI::csrf_field() ?>
                    <input type="hidden" name="action" value="unlock_reconfigure">

                    <div style="margin-bottom: 1.5rem;">
                        <label for="admin_password"
                            style="display: flex; justify-content: space-between; align-items: center; font-size: 0.88rem; font-weight: 700; color: #e2e8f0; margin-bottom: 6px;">
                            <span>Current Administrator Password *</span>
                            <span style="font-size: 0.72rem; color: var(--accent-primary); font-weight: 500;">(Password or
                                PPP row passcode)</span>
                        </label>
                        <div style="position: relative;">
                            <input type="password" id="admin_password" name="admin_password" required autofocus
                                placeholder="Enter administrator password..." style="padding-right: 44px;">
                            <button type="button" onclick="togglePassVisibility('admin_password')"
                                style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.1rem; padding: 4px;"
                                title="Toggle visibility">
                                👁️
                            </button>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <button type="submit" class="btn-wizard btn-submit"
                            style="width: 100%; justify-content: center; padding: 0.95rem; font-size: 0.95rem;">
                            🔓 Verify Password &amp; Unlock Wizard
                        </button>
                        <a href="../index.php" class="btn-wizard btn-prev"
                            style="width: 100%; justify-content: center; text-decoration: none; padding: 0.8rem; font-size: 0.85rem; text-align: center;">
                            &larr; Cancel &amp; Return to Warehouse Portal
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function togglePassVisibility(id) {
                const input = document.getElementById(id);
                if (!input) return;
                input.type = input.type === 'password' ? 'text' : 'password';
            }
        </script>
    </body>

    </html>
    <?php
    exit();
endif;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Systems Setup Wizard | <?= htmlspecialchars($curr_company) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/components.css">
    <style>
        :root {
            /* LatinosPC Official Color Palette */
            --primary-color: #0056b3;
            /* Royal Cobalt Blue */
            --primary-dark: #082d45;
            /* Deep Navy Blue */
            --secondary-color: #218838;
            /* Vibrant Green */
            --secondary-dark: #155724;
            /* Forest Green */
            --bannerAndFooter-bg: #daedfb;
            /* Sky Slate */

            --bg-base: #041521;
            --card-bg: rgba(8, 45, 69, 0.85);
            --card-border: rgba(218, 237, 251, 0.14);
            --accent-primary: #38bdf8;
            --accent-gradient: linear-gradient(135deg, #0056b3 0%, #38bdf8 50%, #218838 100%);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --input-bg: rgba(4, 21, 33, 0.75);
            --input-border: rgba(218, 237, 251, 0.16);
            --success: #218838;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-base);
            color: var(--text-main);
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 2rem 1rem;
            position: relative;
            overflow-x: hidden;
            background: radial-gradient(circle at 50% 0%, rgba(0, 86, 179, 0.25) 0%, transparent 60%),
                linear-gradient(135deg, #041521 0%, #082d45 100%);
        }

        /* Ambient Glow Blobs */
        .glow-blob {
            position: fixed;
            border-radius: 50%;
            filter: blur(120px);
            z-index: 0;
            opacity: 0.35;
            pointer-events: none;
        }

        .blob-1 {
            top: -10%;
            left: -10%;
            width: 500px;
            height: 500px;
            background: #0056b3;
        }

        .blob-2 {
            bottom: -10%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: #218838;
        }

        .wizard-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 820px;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.7);
            overflow: hidden;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .wizard-header {
            padding: 2.5rem 2.5rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid var(--card-border);
            background: rgba(255, 255, 255, 0.02);
        }

        .badge-step {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(56, 189, 248, 0.15);
            color: var(--accent-primary);
            border: 1px solid rgba(56, 189, 248, 0.3);
            border-radius: 100px;
            padding: 4px 14px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .wizard-title {
            font-size: 2rem;
            font-weight: 800;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .wizard-subtitle {
            font-size: 0.95rem;
            color: var(--text-muted);
            max-width: 580px;
            margin: 0 auto;
            line-height: 1.5;
        }

        /* Progress Steps Bar */
        .step-nav {
            display: flex;
            justify-content: space-between;
            padding: 1rem 2.5rem;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid var(--card-border);
        }

        .step-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
        }

        .step-item.active {
            color: var(--accent-primary);
        }

        .step-item.completed {
            color: var(--success);
        }

        .step-num {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            border: 1px solid currentColor;
        }

        .step-item.active .step-num {
            background: var(--accent-primary);
            color: #0f172a;
            border-color: var(--accent-primary);
        }

        .step-item.completed .step-num {
            background: var(--success);
            color: white;
            border-color: var(--success);
        }

        .wizard-body {
            padding: 2.5rem;
        }

        .step-content {
            display: none;
        }

        .step-content.active {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .form-grid.full {
            grid-template-columns: 1fr;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.col-span-2 {
            grid-column: span 2;
        }

        label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #cbd5e1;
        }

        .input-hint {
            font-size: 0.75rem;
            color: #64748b;
        }

        input[type="text"],
        input[type="email"],
        input[type="url"],
        input[type="password"],
        select,
        textarea {
            width: 100%;
            padding: 0.8rem 1rem;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 10px;
            color: white;
            font-family: inherit;
            font-size: 0.9rem;
            outline: none;
            transition: all 0.2s;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
        }

        /* Selectable Checkbox Cards */
        .preset-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px;
            margin-top: 8px;
        }

        .preset-checkbox {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
        }

        .preset-checkbox:hover {
            border-color: rgba(56, 189, 248, 0.4);
            background: rgba(15, 23, 42, 0.9);
        }

        .preset-checkbox input[type="checkbox"] {
            accent-color: var(--accent-primary);
            width: 16px;
            height: 16px;
        }

        .preset-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: #e2e8f0;
        }

        .wizard-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2.5rem 2.5rem;
            border-top: 1px solid var(--card-border);
        }

        .btn-wizard {
            padding: 0.85rem 1.8rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-prev {
            background: rgba(255, 255, 255, 0.08);
            color: #cbd5e1;
        }

        .btn-prev:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
        }

        .btn-next,
        .btn-submit {
            background: var(--accent-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(56, 189, 248, 0.35);
        }

        .btn-next:hover,
        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(56, 189, 248, 0.5);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        /* Success Card View */
        .success-box {
            text-align: center;
            padding: 3rem 1.5rem;
        }

        .success-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: bounce 1s infinite alternate;
        }

        @keyframes bounce {
            from {
                transform: translateY(0);
            }

            to {
                transform: translateY(-8px);
            }
        }

        .launch-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.2rem;
            margin-top: 2.5rem;
            text-align: left;
        }

        .launch-card {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid var(--card-border);
            padding: 1.5rem;
            border-radius: 16px;
            text-decoration: none;
            color: white;
            transition: all 0.25s;
        }

        .launch-card:hover {
            border-color: var(--accent-primary);
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
        }

        .launch-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .launch-card h3 {
            font-size: 1.05rem;
            margin-bottom: 4px;
        }

        .launch-card p {
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        /* Auth Mode Selector Cards */
        .auth-mode-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .auth-mode-card {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            position: relative;
            user-select: none;
        }

        .auth-mode-card:hover {
            border-color: rgba(56, 189, 248, 0.4);
            background: rgba(15, 23, 42, 0.9);
        }

        .auth-mode-card.active {
            border-color: var(--accent-primary);
            background: rgba(8, 45, 69, 0.7);
            box-shadow: 0 0 15px rgba(56, 189, 248, 0.2);
        }

        .auth-mode-icon {
            font-size: 1.5rem;
            margin-bottom: 4px;
        }

        .auth-mode-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: white;
            margin-bottom: 4px;
        }

        .auth-mode-badge {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: 100px;
            margin-bottom: 6px;
        }

        .badge-recom {
            background: rgba(56, 189, 248, 0.2);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.4);
        }

        .badge-fast {
            background: rgba(33, 136, 56, 0.2);
            color: #4ade80;
            border: 1px solid rgba(33, 136, 56, 0.4);
        }

        .badge-bypass {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.4);
        }

        .auth-mode-desc {
            font-size: 0.72rem;
            color: var(--text-muted);
            line-height: 1.3;
        }

        /* PPP Container Elements */
        .ppp-control-box {
            display: flex;
            gap: 15px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 12px 16px;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn-tool {
            background: rgba(255, 255, 255, 0.08);
            color: white;
            border: 1px solid var(--card-border);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-tool:hover {
            background: rgba(255, 255, 255, 0.16);
        }

        .ppp-selection-banner {
            background: rgba(8, 45, 69, 0.8);
            border: 1px solid rgba(56, 189, 248, 0.4);
            border-radius: 10px;
            padding: 10px 14px;
            margin-top: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .ppp-qr-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: white;
            border-radius: 10px;
            padding: 8px;
            cursor: pointer;
            transition: all 0.2s;
            height: fit-content;
        }

        .ppp-qr-box:hover {
            transform: scale(1.03);
            box-shadow: 0 4px 15px rgba(56, 189, 248, 0.3);
        }

        .ppp-qr-box img {
            width: 100px;
            height: 100px;
            display: block;
            border-radius: 6px;
        }

        .ppp-table-container {
            max-height: 220px;
            overflow-y: auto;
            border: 1px solid var(--card-border);
            border-radius: 10px;
            background: rgba(15, 23, 42, 0.8);
        }

        .ppp-grid-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'JetBrains Mono', monospace, monospace;
            font-size: 0.75rem;
            text-align: center;
        }

        .ppp-grid-table thead th {
            position: sticky;
            top: 0;
            background: #082d45;
            color: #38bdf8;
            padding: 6px 4px;
            font-weight: 800;
            border-bottom: 1px solid var(--card-border);
            z-index: 1;
        }

        .ppp-grid-table tbody tr {
            cursor: pointer;
            transition: background 0.15s;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }

        .ppp-grid-table tbody tr:hover {
            background: rgba(56, 189, 248, 0.1);
        }

        .ppp-grid-table tbody tr.active-row {
            background: rgba(56, 189, 248, 0.25) !important;
            outline: 1px solid #38bdf8;
        }

        .ppp-grid-table td {
            padding: 6px 4px;
            color: #e2e8f0;
            word-break: break-all;
        }

        .ppp-grid-table td.row-label {
            font-weight: 800;
            color: #94a3b8;
            background: rgba(0, 0, 0, 0.2);
            border-right: 1px solid var(--card-border);
            width: 48px;
        }

        /* Fast Track Card */
        .fast-track-box {
            text-align: center;
            padding: 2rem 1rem;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--card-border);
            border-radius: 14px;
        }

        .credentials-badge-box {
            display: inline-flex;
            align-items: center;
            gap: 15px;
            background: rgba(8, 45, 69, 0.7);
            border: 1px solid rgba(56, 189, 248, 0.3);
            border-radius: 10px;
            padding: 10px 20px;
        }

        .cred-item {
            display: flex;
            flex-direction: column;
            text-align: left;
        }

        .cred-label {
            font-size: 0.65rem;
            color: #94a3b8;
            text-transform: uppercase;
            font-weight: 700;
        }

        .cred-val {
            font-size: 0.95rem;
            color: white;
            font-family: monospace;
        }

        .cred-divider {
            width: 1px;
            height: 25px;
            background: rgba(255, 255, 255, 0.1);
        }

        @media (max-width: 680px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.col-span-2 {
                grid-column: span 1;
            }

            .launch-grid {
                grid-template-columns: 1fr;
            }

            .auth-mode-grid {
                grid-template-columns: 1fr;
            }

            .step-item span.label-text {
                display: none;
            }
        }
    </style>
</head>

<body>

    <div class="glow-blob blob-1"></div>
    <div class="glow-blob blob-2"></div>

    <div class="wizard-container">

        <?php if ($system_protected && $is_unlocked && !$success): ?>
            <!-- Persistent Live Reconfiguration Warning Banner -->
            <div
                style="background: rgba(245, 158, 11, 0.15); border-bottom: 1px solid rgba(245, 158, 11, 0.35); padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div
                    style="display: flex; align-items: center; gap: 10px; color: #fde68a; font-size: 0.88rem; font-weight: 600;">
                    <span style="font-size: 1.2rem;">⚠️</span>
                    <span><strong>LIVE RECONFIGURATION ACTIVE:</strong> You are modifying active production settings.
                        Changes will update live operations and admin access.</span>
                </div>
                <a href="index.php?lock=1"
                    style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.4); border-radius: 8px; padding: 6px 14px; font-size: 0.8rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                    🔒 Lock &amp; Exit
                </a>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <!-- Success Screen -->
            <div class="wizard-header">
                <div class="badge-step"
                    style="background: rgba(33, 136, 56, 0.2); color: #4ade80; border: 1px solid rgba(33, 136, 56, 0.4);">✓
                    <?= $reconfigure ? 'Reconfiguration Applied' : '🚀 Initialization Complete' ?></div>
                <h1 class="wizard-title"><?= htmlspecialchars($company_name) ?>
                    <?= $reconfigure ? 'Updated!' : 'is Ready!' ?></h1>
                <p class="wizard-subtitle">Your warehouse management suite configuration has been safely updated in
                    production. All databases are isolated outside HTTP scope.</p>
            </div>

            <div class="wizard-body success-box">
                <div class="success-icon">✨</div>
                <h2 style="font-size: 1.4rem; margin-bottom: 0.5rem;">Welcome to your new operations hub</h2>
                <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto;">Company profile, electronics
                    refurbishing presets, and administrator credentials have been stored successfully.</p>

                <div class="launch-grid">
                    <a href="../tech/index.php" class="launch-card">
                        <div class="launch-icon">🔧</div>
                        <h3>Technician Center</h3>
                        <p>Diagnostics, testing logs, grading, and parts inventory.</p>
                    </a>
                    <a href="../orders/index.php" class="launch-card">
                        <div class="launch-icon">📊</div>
                        <h3>Order Manager</h3>
                        <p>Batch fulfillment, customer registry, and manifest logistics.</p>
                    </a>
                    <a href="../marketing/index.php" class="launch-card">
                        <div class="launch-icon">📣</div>
                        <h3>Marketing Hub</h3>
                        <p>Lead generation, photo bucket, and sales copy automation.</p>
                    </a>
                </div>

                <div style="margin-top: 2.5rem;">
                    <a href="../index.php" class="btn-wizard btn-next" style="padding: 1rem 2.5rem; font-size: 1rem;">
                        Enter Main Portal &rarr;
                    </a>
                </div>
            </div>

        <?php else: ?>

            <div class="wizard-header">
                <div class="badge-step">Setup &amp; Brand Wizard</div>
                <h1 class="wizard-title"><?= $reconfigure ? 'System Configuration' : 'Welcome to Warehouse Systems' ?></h1>
                <p class="wizard-subtitle">Tailor the warehouse suite specifically to your business identity, electronics
                    refurbishing standards, and security preferences.</p>
            </div>

            <!-- Progress Nav -->
            <div class="step-nav">
                <div class="step-item active" id="nav-step-1" onclick="jumpToStep(1)">
                    <div class="step-num">1</div>
                    <span class="label-text">Company Profile</span>
                </div>
                <div class="step-item" id="nav-step-2" onclick="jumpToStep(2)">
                    <div class="step-num">2</div>
                    <span class="label-text">Trade Presets</span>
                </div>
                <div class="step-item" id="nav-step-3" onclick="jumpToStep(3)">
                    <div class="step-num">3</div>
                    <span class="label-text">Administrator</span>
                </div>
                <div class="step-item" id="nav-step-4" onclick="jumpToStep(4)">
                    <div class="step-num">4</div>
                    <span class="label-text">Final Review</span>
                </div>
            </div>

            <form method="POST" id="wizardForm">
                <?= UI::csrf_field() ?>
                <input type="hidden" name="action" value="complete_setup">

                <div class="wizard-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert-error">
                            ⚠️ <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <!-- STEP 1: COMPANY PROFILE -->
                    <div class="step-content active" id="step-1">
                        <h2 style="font-size: 1.25rem; margin-bottom: 6px;">🏢 Company Profile &amp; Identity</h2>
                        <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">Set your business
                            brand details. These will appear in the portal, document headers, manifests, and receipts.</p>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="company_name">Company Name *</label>
                                <input type="text" id="company_name" name="company_name"
                                    value="<?= htmlspecialchars($curr_company) ?>" placeholder="e.g. IQA Metal" required>
                                <span class="input-hint">Your registered trade or store brand name.</span>
                            </div>

                            <div class="form-group">
                                <label for="system_name">System Portal Title *</label>
                                <input type="text" id="system_name" name="system_name"
                                    value="<?= htmlspecialchars($curr_system) ?>"
                                    placeholder="e.g. IQA Metal Warehouse Systems" required>
                                <span class="input-hint">Displays in the browser tab and portal header.</span>
                            </div>

                            <div class="form-group">
                                <label for="company_url">Official Website / Domain</label>
                                <input type="url" id="company_url" name="company_url"
                                    value="<?= htmlspecialchars($curr_url) ?>" placeholder="https://latinospc.com">
                                <span class="input-hint">Linked in footer notes and manifest signatures.</span>
                            </div>

                            <div class="form-group">
                                <label for="support_email">Contact / Operations Email</label>
                                <input type="email" id="support_email" name="support_email"
                                    value="<?= htmlspecialchars($curr_email) ?>" placeholder="sales@latinospc.com">
                                <span class="input-hint">Receives system alerts and customer inquiries.</span>
                            </div>

                            <div class="form-group">
                                <label for="currency_symbol">Currency Symbol</label>
                                <select id="currency_symbol" name="currency_symbol">
                                    <option value="$" <?= $curr_currency === '$' ? 'selected' : '' ?>>$ (USD - US Dollar)
                                    </option>
                                    <option value="C$" <?= $curr_currency === 'C$' ? 'selected' : '' ?>>C$ (CAD - Canadian
                                        Dollar)</option>
                                    <option value="MX$" <?= $curr_currency === 'MX$' ? 'selected' : '' ?>>MX$ (MXN - Mexican
                                        Peso)</option>
                                    <option value="€" <?= $curr_currency === '€' ? 'selected' : '' ?>>€ (EUR - Euro)</option>
                                    <option value="£" <?= $curr_currency === '£' ? 'selected' : '' ?>>£ (GBP - British Pound)
                                    </option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="tagline">Operations Tagline</label>
                                <input type="text" id="tagline" name="tagline"
                                    value="<?= htmlspecialchars($curr_tagline) ?>"
                                    placeholder="e.g. Intelligent inventory management & rapid label logistics.">
                                <span class="input-hint">Short mission description on the landing page.</span>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2: TRADE PRESETS -->
                    <div class="step-content" id="step-2">
                        <h2 style="font-size: 1.25rem; margin-bottom: 6px;">💻 Used Computer &amp; Electronics Trade Presets
                        </h2>
                        <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">Tailor diagnostic
                            checklists, inventory types, and grading standards to your refurbishing workflow.</p>

                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label>Primary Hardware Inventory Lines</label>
                            <div class="preset-grid">
                                <?php
                                $lines = ['Laptops & Notebooks', 'Desktop PCs', 'Gaming PCs', 'Enterprise Servers', 'Monitors & Displays', 'RAM & Storage (SSDs)', 'Smartphones & Tablets', 'E-Waste / Scrap'];
                                foreach ($lines as $line):
                                    ?>
                                    <label class="preset-checkbox">
                                        <input type="checkbox" name="hardware_lines[]" value="<?= htmlspecialchars($line) ?>"
                                            checked>
                                        <span class="preset-label"><?= htmlspecialchars($line) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label>Refurbishing Grading Categories</label>
                            <div class="preset-grid">
                                <?php
                                $grades = ['A-Grade (Like New)', 'B-Grade (Minor Scuffs)', 'C-Grade (Heavy Wear)', 'Untested (As-Is)', 'Parts / Repair Only', 'E-Waste / Scrap'];
                                foreach ($grades as $grade):
                                    ?>
                                    <label class="preset-checkbox">
                                        <input type="checkbox" name="grading_standards[]"
                                            value="<?= htmlspecialchars($grade) ?>" checked>
                                        <span class="preset-label"><?= htmlspecialchars($grade) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="label_preset">Hardware Label &amp; Barcode Standard</label>
                                <select id="label_preset" name="label_preset">
                                    <option value="4x6_thermal">4" x 6" Thermal Shipping / Manifest (Zebra / Rollo)</option>
                                    <option value="2x1_asset">2" x 1" Small Hardware Asset Tag / Barcode</option>
                                    <option value="compact_qr">Compact 2.25" x 1.25" QR Code &amp; Specs</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="db_security_mode">Storage &amp; Database Architecture</label>
                                <input type="text" id="db_security_mode" value="Isolated Outside HTTP Root (data/db)"
                                    disabled style="opacity: 0.75; cursor: not-allowed;">
                                <span class="input-hint" style="color: #10b981;">✓ Secure path automatically active.</span>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3: ADMINISTRATOR ACCOUNT & PPP SECURITY -->
                    <div class="step-content" id="step-3">
                        <h2 style="font-size: 1.25rem; margin-bottom: 6px;">🔐 Administrator Security &amp; Authentication
                        </h2>
                        <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">
                            Configure master administrator credentials. Use Steve Gibson's <strong>Perfect Paper Passwords
                                (PPP)</strong> offline passcard system, fast-track with <strong>default
                                credentials</strong>, or set a custom password.
                        </p>

                        <!-- Admin Account Identity (Username & Display Name) -->
                        <div class="form-grid" style="margin-bottom: 1.5rem;">
                            <div class="form-group">
                                <label for="admin_user">Admin Username</label>
                                <input type="text" id="admin_user" name="admin_user" value="admin" required>
                                <span class="input-hint">Username used to log into Order Manager &amp; Tech Center.</span>
                            </div>

                            <div class="form-group">
                                <label for="admin_name">Display Name</label>
                                <input type="text" id="admin_name" name="admin_name" value="System Administrator" required>
                                <span class="input-hint">Name displayed in headers, manifests, and audit logs.</span>
                            </div>
                        </div>

                        <!-- Hidden Authentication State Inputs -->
                        <input type="hidden" name="auth_mode" id="auth_mode_input"
                            value="<?= $system_protected ? 'keep_existing' : 'ppp' ?>">
                        <input type="hidden" name="ppp_sequence_key" id="ppp_sequence_key_input"
                            value="<?= htmlspecialchars($existing_seq_key) ?>">
                        <input type="hidden" name="ppp_row_index" id="ppp_row_index_input"
                            value="<?= $existing_row_index ?>">
                        <input type="hidden" name="ppp_password_len" id="ppp_password_len_input"
                            value="<?= $existing_pass_len ?>">
                        <input type="hidden" name="selected_passcode" id="selected_passcode_input" value="">

                        <!-- Authentication Mode Selector Tabs -->
                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 8px;">Choose Authentication Method</label>
                            <div class="auth-mode-grid"
                                style="<?= $system_protected ? 'grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));' : '' ?>">
                                <?php if ($system_protected): ?>
                                    <div class="auth-mode-card active" id="mode-card-keep"
                                        onclick="switchAuthMode('keep_existing')">
                                        <div class="auth-mode-icon">🛡️</div>
                                        <div class="auth-mode-title">Keep Current Password</div>
                                        <div class="auth-mode-badge"
                                            style="background: rgba(56, 189, 248, 0.2); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.4);">
                                            Preserve (Active)</div>
                                        <div class="auth-mode-desc">Retain active admin credentials and passcard without
                                            modification.</div>
                                    </div>
                                <?php endif; ?>

                                <div class="auth-mode-card <?= !$system_protected ? 'active' : '' ?>" id="mode-card-ppp"
                                    onclick="switchAuthMode('ppp')">
                                    <div class="auth-mode-icon">🔑</div>
                                    <div class="auth-mode-title">Perfect Paper Passwords</div>
                                    <div class="auth-mode-badge badge-recom">Recommended</div>
                                    <div class="auth-mode-desc">High-entropy Steve Gibson GRC passcard grid. Offline paper
                                        MFA.</div>
                                </div>

                                <div class="auth-mode-card" id="mode-card-default"
                                    onclick="switchAuthMode('default_creds')">
                                    <div class="auth-mode-icon">⚡</div>
                                    <div class="auth-mode-title">Default Credentials</div>
                                    <div class="auth-mode-badge badge-fast">Fast Track</div>
                                    <div class="auth-mode-desc">Use standard <code>admin</code> / <code>123</code> to enter
                                        Order Manager right away.</div>
                                </div>

                                <div class="auth-mode-card" id="mode-card-custom" onclick="switchAuthMode('custom')">
                                    <div class="auth-mode-icon">🔒</div>
                                    <div class="auth-mode-title">Custom Password</div>
                                    <div class="auth-mode-badge badge-bypass">Standard</div>
                                    <div class="auth-mode-desc">Bypass PPP grid and set a traditional typed password.</div>
                                </div>
                            </div>
                        </div>

                        <?php if ($system_protected): ?>
                            <!-- PANEL 0: KEEP CURRENT CREDENTIALS -->
                            <div id="auth-panel-keep" class="auth-panel active"
                                style="text-align: center; padding: 2rem 1.5rem; background: rgba(15, 23, 42, 0.6); border: 1px solid var(--card-border); border-radius: 14px;">
                                <div style="font-size: 2.5rem; margin-bottom: 8px;">🛡️</div>
                                <h3 style="font-size: 1.15rem; margin-bottom: 6px; color: white;">Current Credentials Will Be
                                    Maintained</h3>
                                <p
                                    style="color: var(--text-muted); font-size: 0.85rem; max-width: 480px; margin: 0 auto 1.2rem;">
                                    Your active administrator password, passcard row index, and sequence keys will remain
                                    untouched. Only business identity, trade presets, and label standards will be updated.
                                </p>
                                <div
                                    style="display: inline-flex; align-items: center; gap: 8px; background: rgba(33, 136, 56, 0.15); border: 1px solid rgba(33, 136, 56, 0.3); border-radius: 8px; padding: 8px 16px; color: #4ade80; font-size: 0.85rem; font-weight: 600;">
                                    ✓ Active Master Password Maintained
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- PANEL 1: PERFECT PAPER PASSWORDS (PPP) -->
                        <div id="auth-panel-ppp" class="auth-panel <?= !$system_protected ? 'active' : '' ?>"
                            style="<?= $system_protected ? 'display: none;' : '' ?>">
                            <!-- Top Toolbar: Length Range & 64-Hex Key -->
                            <div class="ppp-control-box">
                                <div style="flex: 1; min-width: 140px;">
                                    <label for="ppp_length_input"
                                        style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Password
                                        Length</label>
                                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                        <input type="number" id="ppp_length_input" value="<?= $existing_pass_len ?>"
                                            min="25" max="80" onchange="onPPPConfigChange()"
                                            style="width: 80px; text-align: center; font-weight: 800; font-family: monospace;">
                                        <span id="entropy-badge"
                                            style="font-size: 0.75rem; color: #38bdf8; font-weight: 600; background: rgba(56, 189, 248, 0.12); padding: 4px 8px; border-radius: 6px;">180-bit
                                            Entropy</span>
                                    </div>
                                </div>

                                <div style="flex: 2; min-width: 260px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <label for="ppp_display_key"
                                            style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Sequence
                                            Key (128 / 256-Bit Hex)</label>
                                        <span id="key-bit-badge"
                                            style="font-size: 0.72rem; color: #38bdf8; font-weight: 700; background: rgba(56, 189, 248, 0.12); padding: 2px 6px; border-radius: 4px;">256-bit
                                            Key</span>
                                    </div>
                                    <div style="display: flex; gap: 6px; margin-top: 4px;">
                                        <input type="text" id="ppp_display_key"
                                            value="<?= htmlspecialchars($existing_seq_key) ?>"
                                            placeholder="Enter 32 or 64-hex key, or generate..."
                                            style="font-family: monospace; font-size: 0.8rem; letter-spacing: 0.5px;"
                                            oninput="onKeyInputChange()" onchange="applyManualKey()"
                                            onkeydown="if(event.key==='Enter'){event.preventDefault();applyManualKey();}">
                                        <button type="button" class="btn-tool" onclick="triggerGenKey()"
                                            title="Generate Random 64-Hex Key">🎲 Gen Key</button>
                                        <button type="button" class="btn-tool" onclick="copySequenceKey()"
                                            title="Copy Sequence Key">📋</button>
                                        <button type="button" class="btn-tool" id="btn_load_key" onclick="applyManualKey()"
                                            title="Load Grid" style="background: var(--accent-gradient); color: white;">🔍
                                            Load</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Selected Passcode Banner -->
                            <div id="selected-passcode-callout" class="ppp-selection-banner"
                                style="<?= $existing_row_index > 0 ? '' : 'display:none;' ?>">
                                <div
                                    style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                                    <div>
                                        <span style="font-size: 0.85rem; color: #94a3b8;">Active Secret Passcode:</span>
                                        <strong id="active-row-badge"
                                            style="color: #38bdf8; font-size: 1rem; margin-left: 6px;">Row
                                            <?= str_pad($existing_row_index, 2, '0', STR_PAD_LEFT) ?></strong>
                                        <div id="passcode-preview-str"
                                            style="font-family: monospace; font-size: 0.85rem; color: #a7f3d0; margin-top: 4px; word-break: break-all;">
                                            ••••••••••••••••••••••••••••••
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 6px;">
                                        <button type="button" class="btn-tool" onclick="togglePasscodeVisibility()"
                                            id="btnTogglePasscode">👁️ Reveal</button>
                                        <button type="button" class="btn-tool" onclick="copyActivePasscode()">📋 Copy
                                            Passcode</button>
                                    </div>
                                </div>
                                <div style="font-size: 0.72rem; color: #94a3b8; margin-top: 6px;">
                                    💡 Keep this card printed or saved offline. When logging into Order Manager, use this
                                    row's passcode.
                                </div>
                            </div>

                            <!-- Grid & QR Split Container -->
                            <div style="display: flex; gap: 15px; margin-top: 15px; flex-wrap: wrap;">
                                <!-- QR Thumbnail -->
                                <div class="ppp-qr-box" onclick="viewLargeQR()" title="Click to enlarge Sequence QR Code">
                                    <img id="ppp_qr_img"
                                        src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&amp;data=<?= urlencode($existing_seq_key) ?>"
                                        alt="PPP Sequence QR Code">
                                    <span
                                        style="font-size: 0.65rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-top: 6px;">Sequence
                                        QR</span>
                                </div>

                                <!-- Passcard Table Preview -->
                                <div style="flex: 1; min-width: 280px;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                        <span style="font-size: 0.8rem; font-weight: 700; color: #cbd5e1;">Live Passcard
                                            Grid Preview</span>
                                        <span style="font-size: 0.72rem; color: #38bdf8; font-style: italic;">Click any row
                                            to choose it as your secret passcode</span>
                                    </div>
                                    <div class="ppp-table-container">
                                        <table class="ppp-grid-table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 48px;">Row</th>
                                                    <th>A</th>
                                                    <th>B</th>
                                                    <th>C</th>
                                                    <th>D</th>
                                                    <th>E</th>
                                                </tr>
                                            </thead>
                                            <tbody id="ppp-grid-tbody">
                                                <!-- Dynamically populated -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Bar: Print, View, Guide -->
                            <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
                                <button type="button" class="btn-wizard btn-prev" onclick="printPPPCard()"
                                    style="flex: 1; min-width: 150px; justify-content: center; background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3);">
                                    🖨️ Print Passcard
                                </button>
                                <button type="button" class="btn-wizard btn-prev" onclick="viewPPPCard()"
                                    style="flex: 1; min-width: 150px; justify-content: center;">
                                    📄 View Passcard
                                </button>
                                <button type="button" class="btn-wizard btn-prev" onclick="togglePPPExplanation()"
                                    style="padding: 0.8rem 1rem;">
                                    ❓ What is PPP?
                                </button>
                            </div>

                            <!-- How PPP Works Collapsible Box -->
                            <div id="ppp-explanation-box"
                                style="display: none; background: rgba(15, 23, 42, 0.8); border: 1px solid var(--card-border); border-radius: 12px; padding: 1.2rem; margin-top: 15px; font-size: 0.82rem; color: #94a3b8; line-height: 1.5;">
                                <h4 style="color: white; margin-top: 0; margin-bottom: 6px; font-size: 0.95rem;">🔑 How
                                    Perfect Paper Passwords (PPP) Works</h4>
                                <p style="margin-bottom: 8px;">Designed by Steve Gibson of Gibson Research Corporation
                                    (GRC), PPP is an offline authentication system. Using AES-256 in counter mode, your
                                    64-hexadecimal sequence key generates a pseudo-random 25-row passcard.</p>
                                <ul style="padding-left: 20px; margin: 0;">
                                    <li>Print this passcard and keep it in your wallet, desk drawer, or smartphone.</li>
                                    <li>When logging into Order Manager, simply enter the passcode from your chosen secret
                                        row.</li>
                                    <li>Your computer never stores the master key in browser storage, defeating keyloggers
                                        and database leaks.</li>
                                </ul>
                            </div>
                        </div>

                        <!-- PANEL 2: DEFAULT CREDENTIALS FAST TRACK -->
                        <div id="auth-panel-default" class="auth-panel" style="display: none;">
                            <div class="fast-track-box">
                                <div style="font-size: 2.5rem; margin-bottom: 8px;">⚡</div>
                                <h3 style="font-size: 1.15rem; margin-bottom: 6px; color: white;">Instant Fast-Track Access
                                    Active</h3>
                                <p
                                    style="color: var(--text-muted); font-size: 0.85rem; max-width: 480px; margin: 0 auto 1.2rem;">
                                    Default warehouse credentials will be committed to <code>users.db</code> so you can log
                                    into the Order Manager immediately.
                                </p>
                                <div class="credentials-badge-box">
                                    <div class="cred-item">
                                        <span class="cred-label">Username</span>
                                        <strong class="cred-val">admin</strong>
                                    </div>
                                    <div class="cred-divider"></div>
                                    <div class="cred-item">
                                        <span class="cred-label">Password</span>
                                        <strong class="cred-val">123</strong>
                                    </div>
                                    <div class="cred-divider"></div>
                                    <div class="cred-item">
                                        <span class="cred-label">Access Level</span>
                                        <strong class="cred-val" style="color: #38bdf8;">Administrator</strong>
                                    </div>
                                </div>
                                <p style="font-size: 0.75rem; color: #10b981; margin-top: 1rem;">
                                    ✓ Ready to begin! Click "Continue" below to proceed.
                                </p>
                            </div>
                        </div>

                        <!-- PANEL 3: CUSTOM PASSWORD BYPASS -->
                        <div id="auth-panel-custom" class="auth-panel" style="display: none;">
                            <div class="alert-error"
                                style="background: rgba(245, 158, 11, 0.12); border-color: rgba(245, 158, 11, 0.3); color: #fde68a; margin-bottom: 1.2rem;">
                                ⚠️ <strong>PPP Bypass Notice:</strong> Traditional passwords are prone to brute-forcing,
                                dictionary attacks, and keylogging. Consider using Perfect Paper Passwords for high
                                security.
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="admin_pass">Custom Admin Password *</label>
                                    <input type="password" id="admin_pass" name="admin_pass"
                                        placeholder="Enter secure password">
                                    <span class="input-hint">Minimum 4 characters (recommended 12+).</span>
                                </div>

                                <div class="form-group">
                                    <label for="admin_pass_confirm">Confirm Password *</label>
                                    <input type="password" id="admin_pass_confirm" name="admin_pass_confirm"
                                        placeholder="Confirm password exactly">
                                    <span class="input-hint">Repeat the custom password.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 4: FINAL REVIEW -->
                    <div class="step-content" id="step-4">
                        <h2 style="font-size: 1.25rem; margin-bottom: 6px;">📋 Review &amp; Launch</h2>
                        <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">Review your
                            operational setup before initializing the warehouse ecosystem.</p>

                        <div
                            style="background: rgba(15, 23, 42, 0.6); border: 1px solid var(--card-border); border-radius: 14px; padding: 1.5rem; display: grid; gap: 12px; font-size: 0.9rem;">
                            <div
                                style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">
                                <span style="color: var(--text-muted);">Company Name:</span>
                                <strong id="rev-company-name">IQA Metal</strong>
                            </div>
                            <div
                                style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">
                                <span style="color: var(--text-muted);">System Title:</span>
                                <strong id="rev-system-title">IQA Metal Warehouse Systems</strong>
                            </div>
                            <div
                                style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">
                                <span style="color: var(--text-muted);">Website URL:</span>
                                <strong id="rev-company-url">https://latinospc.com</strong>
                            </div>
                            <div
                                style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">
                                <span style="color: var(--text-muted);">Administrator:</span>
                                <strong id="rev-admin-user">admin</strong>
                            </div>
                            <div
                                style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">
                                <span style="color: var(--text-muted);">Auth Security Mode:</span>
                                <span id="rev-auth-mode"><strong style="color: #38bdf8;">🔑 Perfect Paper
                                        Passwords</strong></span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-muted);">Database Isolation:</span>
                                <span style="color: #10b981; font-weight: 700;">✓ Active (Protected outside HTTP)</span>
                            </div>
                        </div>

                        <?php if ($system_protected): ?>
                            <div
                                style="margin-top: 1.5rem; background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); border-radius: 14px; padding: 1.25rem;">
                                <label for="current_admin_password"
                                    style="display: flex; justify-content: space-between; align-items: center; font-size: 0.88rem; font-weight: 700; color: #fca5a5; margin-bottom: 6px;">
                                    <span>🔒 Authorize Changes: Current Admin Password *</span>
                                    <span style="font-size: 0.72rem; color: #f87171; font-weight: 600;">Mandatory
                                        Confirmation</span>
                                </label>
                                <p style="font-size: 0.78rem; color: #94a3b8; margin-bottom: 12px; line-height: 1.4;">
                                    To safeguard live production databases against unauthorized modifications, confirm your
                                    <strong>current administrator password</strong> (or active PPP passcode) to commit changes.
                                </p>
                                <div style="position: relative;">
                                    <input type="password" id="current_admin_password" name="current_admin_password"
                                        placeholder="Enter current admin password to commit changes..." required
                                        style="padding-right: 44px; font-size: 0.9rem;">
                                    <button type="button" onclick="togglePassVisibility('current_admin_password')"
                                        style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.1rem; padding: 4px;"
                                        title="Toggle visibility">
                                        👁️
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

                <div class="wizard-footer">
                    <button type="button" class="btn-wizard btn-prev" id="btnPrev" onclick="prevStep()"
                        style="visibility: hidden;">
                        &larr; Back
                    </button>
                    <button type="button" class="btn-wizard btn-next" id="btnNext" onclick="nextStep()">
                        Continue &rarr;
                    </button>
                    <button type="submit" class="btn-wizard btn-submit" id="btnSubmit" style="display: none;">
                        <?= $reconfigure ? '💾 Save &amp; Apply Reconfiguration' : '🚀 Initialize &amp; Launch System' ?>
                    </button>
                </div>
            </form>

        <?php endif; ?>

    </div>

    <!-- Hidden Printable Passcard Source -->
    <div id="ppp-printable-card-source" style="display: none;"></div>

    <script>
        let currentStep = 1;
        const totalSteps = 4;
        let pppPasscodes = [];
        let pppActiveSeqKey = "<?= htmlspecialchars($existing_seq_key) ?>";
        let pppSelectedRow = <?= (int) $existing_row_index ?>;
        let passcodeRevealed = false;

        function updateStepUI() {
            for (let i = 1; i <= totalSteps; i++) {
                const el = document.getElementById('step-' + i);
                const nav = document.getElementById('nav-step-' + i);
                if (el) el.classList.remove('active');
                if (nav) {
                    nav.classList.remove('active');
                    if (i < currentStep) {
                        nav.classList.add('completed');
                    } else {
                        nav.classList.remove('completed');
                    }
                }
            }

            const activeEl = document.getElementById('step-' + currentStep);
            const activeNav = document.getElementById('nav-step-' + currentStep);
            if (activeEl) activeEl.classList.add('active');
            if (activeNav) activeNav.classList.add('active');

            const btnPrev = document.getElementById('btnPrev');
            const btnNext = document.getElementById('btnNext');
            const btnSubmit = document.getElementById('btnSubmit');

            if (btnPrev) btnPrev.style.visibility = (currentStep === 1) ? 'hidden' : 'visible';

            if (currentStep === totalSteps) {
                if (btnNext) btnNext.style.display = 'none';
                if (btnSubmit) btnSubmit.style.display = 'inline-flex';
                populateReview();
            } else {
                if (btnNext) btnNext.style.display = 'inline-flex';
                if (btnSubmit) btnSubmit.style.display = 'none';
            }

            // If navigating to step 3, ensure grid is loaded
            if (currentStep === 3 && pppPasscodes.length === 0) {
                loadPPPGrid();
            }
        }

        function nextStep() {
            if (currentStep < totalSteps) {
                if (validateStep(currentStep)) {
                    currentStep++;
                    updateStepUI();
                }
            }
        }

        function prevStep() {
            if (currentStep > 1) {
                currentStep--;
                updateStepUI();
            }
        }

        function jumpToStep(step) {
            if (step < currentStep || validateStep(currentStep)) {
                currentStep = step;
                updateStepUI();
            }
        }

        function switchAuthMode(mode) {
            document.getElementById('auth_mode_input').value = mode;

            document.querySelectorAll('.auth-mode-card').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.auth-panel').forEach(p => p.style.display = 'none');

            if (mode === 'keep_existing') {
                const cardKeep = document.getElementById('mode-card-keep');
                const panelKeep = document.getElementById('auth-panel-keep');
                if (cardKeep) cardKeep.classList.add('active');
                if (panelKeep) panelKeep.style.display = 'block';
            } else if (mode === 'ppp') {
                document.getElementById('mode-card-ppp').classList.add('active');
                document.getElementById('auth-panel-ppp').style.display = 'block';
                if (pppPasscodes.length === 0) loadPPPGrid();
            } else if (mode === 'default_creds') {
                document.getElementById('mode-card-default').classList.add('active');
                document.getElementById('auth-panel-default').style.display = 'block';
                document.getElementById('admin_user').value = 'admin';
            } else if (mode === 'custom') {
                document.getElementById('mode-card-custom').classList.add('active');
                document.getElementById('auth-panel-custom').style.display = 'block';
            }
        }

        function onPPPConfigChange() {
            const lengthInput = document.getElementById('ppp_length_input');
            let length = parseInt(lengthInput.value, 10) || 30;
            if (length < 25) length = 25;
            if (length > 80) length = 80;
            lengthInput.value = length;
            document.getElementById('ppp_password_len_input').value = length;

            // Update entropy calculation (6 bits per character from 64-char alphabet)
            const entropyBits = Math.round(length * 5.95);
            document.getElementById('entropy-badge').textContent = `${entropyBits}-bit Entropy`;

            loadPPPGrid();
        }

        function sanitizeHexKey(raw) {
            return (raw || '').replace(/[^0-9a-fA-F]/g, '').toUpperCase();
        }

        function updateKeyBitBadge(key) {
            const badge = document.getElementById('key-bit-badge');
            if (!badge) return;
            const len = key ? key.length : 0;
            if (len === 32) {
                badge.textContent = '128-bit Key (GRC Standard)';
                badge.style.color = '#38bdf8';
                badge.style.background = 'rgba(56, 189, 248, 0.15)';
            } else if (len === 64) {
                badge.textContent = '256-bit Key (GRC Extended)';
                badge.style.color = '#4ade80';
                badge.style.background = 'rgba(74, 222, 128, 0.15)';
            } else if (len === 48) {
                badge.textContent = '192-bit Key';
                badge.style.color = '#38bdf8';
                badge.style.background = 'rgba(56, 189, 248, 0.15)';
            } else if (len >= 16 && len < 32) {
                badge.textContent = `${len * 4}-bit Key (Padded to 128-bit)`;
                badge.style.color = '#f59e0b';
                badge.style.background = 'rgba(245, 158, 11, 0.15)';
            } else if (len > 32 && len < 64) {
                badge.textContent = `${len * 4}-bit Key (Padded to 256-bit)`;
                badge.style.color = '#f59e0b';
                badge.style.background = 'rgba(245, 158, 11, 0.15)';
            } else if (len === 0) {
                badge.textContent = 'No Key Entered';
                badge.style.color = '#94a3b8';
                badge.style.background = 'rgba(148, 163, 184, 0.15)';
            } else {
                badge.textContent = `Invalid (${len} hex chars)`;
                badge.style.color = '#f87171';
                badge.style.background = 'rgba(248, 113, 113, 0.15)';
            }
        }

        function onKeyInputChange() {
            const input = document.getElementById('ppp_display_key');
            const key = sanitizeHexKey(input.value);
            updateKeyBitBadge(key);
            if (key.length >= 16 && key.length <= 64) {
                pppActiveSeqKey = key;
                document.getElementById('ppp_sequence_key_input').value = key;
                updateQR(key);
            }
        }

        function triggerGenKey() {
            const chars = '0123456789ABCDEF';
            let key = '';
            for (let i = 0; i < 64; i++) {
                key += chars[Math.floor(Math.random() * 16)];
            }
            pppActiveSeqKey = key;
            document.getElementById('ppp_display_key').value = key;
            document.getElementById('ppp_sequence_key_input').value = key;
            updateKeyBitBadge(key);
            updateQR(key);
            loadPPPGrid();
        }

        function copySequenceKey() {
            const key = pppActiveSeqKey || document.getElementById('ppp_display_key').value;
            if (!key) return;
            navigator.clipboard.writeText(key).then(() => {
                alert("Sequence key copied to clipboard!");
            }).catch(() => {
                prompt("Copy Sequence Key:", key);
            });
        }

        async function applyManualKey() {
            const input = document.getElementById('ppp_display_key');
            const btn = document.getElementById('btn_load_key');
            let key = sanitizeHexKey(input.value);
            if (key.length < 16 || key.length > 64) {
                alert("Please enter a valid hexadecimal sequence key (between 32 and 64 hex characters, e.g. standard 32-hex 128-bit or 64-hex 256-bit).");
                return;
            }
            input.value = key;
            pppActiveSeqKey = key;
            document.getElementById('ppp_sequence_key_input').value = key;
            updateKeyBitBadge(key);
            updateQR(key);

            if (btn) {
                btn.disabled = true;
                btn.textContent = '⏳ Loading...';
            }

            const success = await loadPPPGrid();

            if (btn) {
                btn.disabled = false;
                if (success) {
                    btn.textContent = '✅ Loaded!';
                    setTimeout(() => { btn.textContent = '🔍 Load'; }, 1800);
                } else {
                    btn.textContent = '🔍 Load';
                }
            }
        }

        function updateQR(key) {
            const encoded = encodeURIComponent(key);
            const img = document.getElementById('ppp_qr_img');
            if (img) img.src = `https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=${encoded}`;
        }

        function viewLargeQR() {
            const key = pppActiveSeqKey || document.getElementById('ppp_display_key').value;
            if (!key) return;
            window.open(`https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=${encodeURIComponent(key)}`, '_blank');
        }

        async function loadPPPGrid() {
            const rawKey = pppActiveSeqKey || document.getElementById('ppp_display_key').value;
            const key = sanitizeHexKey(rawKey);
            const length = parseInt(document.getElementById('ppp_length_input').value, 10) || 30;
            if (key.length < 16 || key.length > 64) return false;

            let baseUrl = window.location.pathname.split('?')[0];
            if (!baseUrl.endsWith('.php')) {
                baseUrl = baseUrl.replace(/\/$/, '') + '/index.php';
            }

            try {
                const res = await fetch(`${baseUrl}?action=ajax_generate_ppp&seq_key=${encodeURIComponent(key)}&length=${length}`);
                const data = await res.json();
                if (data.success && data.passcodes) {
                    pppPasscodes = data.passcodes;
                    renderGrid(data.passcodes);
                    return true;
                } else if (data.error) {
                    console.error("PPP generation error:", data.error);
                    alert("Error generating passcodes: " + data.error);
                    return false;
                }
            } catch (err) {
                console.error("Failed to load PPP passcodes:", err);
                return false;
            }
            return false;
        }

        function renderGrid(passcodes) {
            const tbody = document.getElementById('ppp-grid-tbody');
            tbody.innerHTML = '';

            for (let r = 0; r < 25; r++) {
                const rowNum = r + 1;
                const isSelected = (pppSelectedRow === rowNum);
                const tr = document.createElement('tr');
                if (isSelected) tr.classList.add('active-row');
                tr.onclick = () => selectPasscodeRow(rowNum);

                const tdRow = document.createElement('td');
                tdRow.className = 'row-label';
                tdRow.textContent = String(rowNum).padStart(2, '0');
                tr.appendChild(tdRow);

                for (let c = 0; c < 5; c++) {
                    const tdCell = document.createElement('td');
                    tdCell.textContent = passcodes[r * 5 + c] || '';
                    tr.appendChild(tdCell);
                }
                tbody.appendChild(tr);
            }

            // Auto-select row 1 if none chosen
            if (!pppSelectedRow || pppSelectedRow < 1 || pppSelectedRow > 25) {
                selectPasscodeRow(1);
            } else {
                selectPasscodeRow(pppSelectedRow);
            }

            updatePrintCardSource(passcodes);
        }

        function selectPasscodeRow(rowNum) {
            pppSelectedRow = rowNum;
            document.getElementById('ppp_row_index_input').value = rowNum;

            // Highlight row in table
            const rows = document.querySelectorAll('#ppp-grid-tbody tr');
            rows.forEach((r, idx) => {
                if (idx === (rowNum - 1)) {
                    r.classList.add('active-row');
                } else {
                    r.classList.remove('active-row');
                }
            });

            // Compute passcode string
            if (pppPasscodes.length >= (rowNum * 5)) {
                const rowSlice = pppPasscodes.slice((rowNum - 1) * 5, rowNum * 5);
                const passcodeStr = rowSlice.join('');
                document.getElementById('selected_passcode_input').value = passcodeStr;

                // Show callout
                const callout = document.getElementById('selected-passcode-callout');
                if (callout) callout.style.display = 'block';

                document.getElementById('active-row-badge').textContent = `Row ${String(rowNum).padStart(2, '0')}`;
                updatePasscodeDisplay(passcodeStr);
            }
        }

        function updatePasscodeDisplay(str) {
            const previewEl = document.getElementById('passcode-preview-str');
            if (!previewEl) return;
            if (passcodeRevealed) {
                previewEl.textContent = str;
                document.getElementById('btnTogglePasscode').textContent = '🔒 Hide';
            } else {
                previewEl.textContent = '•'.repeat(str.length || 30);
                document.getElementById('btnTogglePasscode').textContent = '👁️ Reveal';
            }
        }

        function togglePasscodeVisibility() {
            passcodeRevealed = !passcodeRevealed;
            const passcode = document.getElementById('selected_passcode_input').value;
            updatePasscodeDisplay(passcode);
        }

        function copyActivePasscode() {
            const passcode = document.getElementById('selected_passcode_input').value;
            if (!passcode) return;
            navigator.clipboard.writeText(passcode).then(() => {
                alert(`Row ${String(pppSelectedRow).padStart(2, '0')} passcode copied to clipboard!`);
            }).catch(() => {
                prompt("Passcode:", passcode);
            });
        }

        function togglePPPExplanation() {
            const box = document.getElementById('ppp-explanation-box');
            if (box) box.style.display = (box.style.display === 'none') ? 'block' : 'none';
        }

        function updatePrintCardSource(passcodes) {
            const source = document.getElementById('ppp-printable-card-source');
            if (!source) return;

            const companyName = document.getElementById('company_name').value || 'IQA Metal';
            const username = document.getElementById('admin_user').value || 'admin';
            const length = document.getElementById('ppp_length_input').value || 30;

            let tableRowsHtml = '';
            for (let r = 0; r < 25; r++) {
                const rowLabel = String(r + 1).padStart(2, '0');
                let cellsHtml = '';
                for (let c = 0; c < 5; c++) {
                    cellsHtml += `<td style='padding: 5px 3px; border: 1px solid #ccc; font-weight: bold; letter-spacing: 0.5px;'>${passcodes[r * 5 + c] || ''}</td>`;
                }
                tableRowsHtml += `<tr>
                    <td style='padding: 5px 3px; border: 1px solid #ccc; font-weight: bold; background: #fafafa;'>${rowLabel}</td>
                    ${cellsHtml}
                </tr>`;
            }

            source.innerHTML = `
                <div style="border: 2px dashed #0056b3; border-radius: 12px; padding: 20px; max-width: 600px; margin: 20px auto; background: white; color: black; font-family: 'Courier New', Courier, monospace;">
                    <div style="display: flex; justify-content: space-between; border-bottom: 2px solid #0056b3; padding-bottom: 8px; margin-bottom: 12px;">
                        <div>
                            <strong style="font-size: 16px; color: #082d45;">${companyName} PASSCARD</strong>
                            <div style="font-size: 11px; color: #555;">Perfect Paper Passwords (Steve Gibson GRC)</div>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 13px; font-weight: bold;">User: ${username}</span>
                        </div>
                    </div>
                    <div style="font-size: 10px; margin-bottom: 12px; word-break: break-all; border: 1px solid #ddd; padding: 8px; background: #f9f9f9; border-radius: 6px;">
                        <strong>SEQUENCE KEY:</strong><br>${pppActiveSeqKey}
                    </div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 11px; text-align: center;">
                        <thead>
                            <tr style="background: #e2e8f0;">
                                <th style="padding: 5px 3px; border: 1px solid #ccc; width: 45px;">Row</th>
                                <th style="padding: 5px 3px; border: 1px solid #ccc;">A</th>
                                <th style="padding: 5px 3px; border: 1px solid #ccc;">B</th>
                                <th style="padding: 5px 3px; border: 1px solid #ccc;">C</th>
                                <th style="padding: 5px 3px; border: 1px solid #ccc;">D</th>
                                <th style="padding: 5px 3px; border: 1px solid #ccc;">E</th>
                            </tr>
                        </thead>
                        <tbody>${tableRowsHtml}</tbody>
                    </table>
                    <div style="margin-top: 12px; text-align: center; font-size: 9px; color: #666; border-top: 1px solid #eee; padding-top: 6px;">
                        Password Length: ${length} &bull; Keep this card secure and offline. Enter your secret row code at login.
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
            viewWindow.document.write('<html><head><title>PPP Passcard</title></head><body style="margin:20px; background:#f1f5f9;">' + source.innerHTML + '</body></html>');
            viewWindow.document.close();
            viewWindow.focus();
        }

        function togglePassVisibility(id) {
            const input = document.getElementById(id);
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        function validateStep(step) {
            if (step === 1) {
                const name = document.getElementById('company_name').value.trim();
                const sys = document.getElementById('system_name').value.trim();
                if (!name || !sys) {
                    alert('Please enter your company name and system title.');
                    return false;
                }
            } else if (step === 3) {
                const authMode = document.getElementById('auth_mode_input').value;
                if (authMode === 'keep_existing') {
                    return true;
                } else if (authMode === 'custom') {
                    const pass = document.getElementById('admin_pass').value;
                    const passConfirm = document.getElementById('admin_pass_confirm').value;
                    if (!pass || pass.length < 4) {
                        alert('Please provide an administrator password of at least 4 characters.');
                        return false;
                    }
                    if (pass !== passConfirm) {
                        alert('Administrator passwords do not match.');
                        return false;
                    }
                } else if (authMode === 'ppp') {
                    const rawKey = document.getElementById('ppp_sequence_key_input').value.trim();
                    const cleanKey = sanitizeHexKey(rawKey);
                    const rowIdx = parseInt(document.getElementById('ppp_row_index_input').value, 10);
                    if (cleanKey.length < 16 || cleanKey.length > 64) {
                        alert('Please generate or enter a valid hexadecimal PPP sequence key (32-hex 128-bit or 64-hex 256-bit).');
                        return false;
                    }
                    if (!rowIdx || rowIdx < 1 || rowIdx > 25) {
                        alert('Please click to select an authentication row (Row 1-25) from the passcard grid to use as your passcode.');
                        return false;
                    }
                } else if (authMode === 'default_creds') {
                    return true;
                }
            } else if (step === 4) {
                const confirmInput = document.getElementById('current_admin_password');
                if (confirmInput && !confirmInput.value.trim()) {
                    alert('Please enter your current administrator password to authorize and commit changes.');
                    confirmInput.focus();
                    return false;
                }
            }
            return true;
        }

        function populateReview() {
            document.getElementById('rev-company-name').textContent = document.getElementById('company_name').value || 'IQA Metal';
            document.getElementById('rev-system-title').textContent = document.getElementById('system_name').value || 'IQA Metal Warehouse Systems';
            document.getElementById('rev-company-url').textContent = document.getElementById('company_url').value || 'https://latinospc.com';
            document.getElementById('rev-admin-user').textContent = document.getElementById('admin_user').value || 'admin';

            const authMode = document.getElementById('auth_mode_input').value;
            const revAuthMode = document.getElementById('rev-auth-mode');
            if (revAuthMode) {
                if (authMode === 'keep_existing') {
                    revAuthMode.innerHTML = `<strong style="color:#38bdf8;">🛡️ Keep Existing Active Credentials</strong>`;
                } else if (authMode === 'ppp') {
                    const rowIdx = document.getElementById('ppp_row_index_input').value || 1;
                    revAuthMode.innerHTML = `<strong style="color:#38bdf8;">🔑 Perfect Paper Passwords (Row ${String(rowIdx).padStart(2, '0')})</strong>`;
                } else if (authMode === 'default_creds') {
                    revAuthMode.innerHTML = `<strong style="color:#4ade80;">⚡ Default Credentials (admin / 123)</strong>`;
                } else {
                    revAuthMode.innerHTML = `<strong style="color:#fbbf24;">🔒 Custom Password</strong>`;
                }
            }
        }

        // Check for direct jump to step in URL (e.g. ?reconfigure=1 3 or ?step=3 or #step-3)
        document.addEventListener('DOMContentLoaded', () => {
            const initialMode = document.getElementById('auth_mode_input').value;
            if (initialMode === 'keep_existing') {
                switchAuthMode('keep_existing');
            }
            const urlParams = new URLSearchParams(window.location.search);
            let targetStep = 1;
            if (urlParams.has('step')) {
                targetStep = parseInt(urlParams.get('step'), 10) || 1;
            } else if (window.location.search.includes(' 3') || window.location.search.endsWith('3') || window.location.hash === '#step-3') {
                targetStep = 3;
            }
            currentStep = Math.min(Math.max(targetStep, 1), totalSteps);
            updateStepUI();
            updateKeyBitBadge(pppActiveSeqKey);
        });
    </script>
</body>

</html>