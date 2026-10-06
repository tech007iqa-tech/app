<?php
/**
 * IQA Metal Warehouse Systems - Setup & Reconfiguration Wizard Controller
 * Encapsulates setup business logic, security verifications, and state management.
 */

require_once __DIR__ . '/../../core/Company.php';
require_once __DIR__ . '/../../core/Security.php';
require_once __DIR__ . '/../../core/UI.php';
require_once __DIR__ . '/../../core/Database.php';

class SetupController
{
    private ?array $admin_record = null;
    private bool $has_password_in_place = false;
    private bool $is_already_setup = false;
    private bool $system_protected = false;
    private bool $is_unlocked = false;
    private bool $reconfigure = false;
    private string $unlock_error = '';
    private string $error = '';
    private bool $success = false;
    private string $company_name = 'IQA Metal';

    private string $curr_company = '';
    private string $curr_system = '';
    private string $curr_url = '';
    private string $curr_email = '';
    private string $curr_currency = '$';
    private string $curr_tagline = '';

    private string $existing_seq_key = '';
    private int $existing_row_index = 0;
    private int $existing_pass_len = 30;

    public function __construct()
    {
        Security::init();
        $this->loadAdminRecord();
        $this->is_already_setup = Company::isSetupComplete();
        $this->system_protected = $this->is_already_setup || $this->has_password_in_place;

        // Check reconfigure session unlock status (15-minute validity window)
        if (isset($_SESSION['setup_reconfigure_unlocked']) && (time() - (int)$_SESSION['setup_reconfigure_unlocked'] < 900)) {
            $this->is_unlocked = true;
        }

        $this->reconfigure = isset($_GET['reconfigure']) && $_GET['reconfigure'] === '1';
    }

    public function handle(): void
    {
        $this->handleLock();
        $this->handleAjax();
        $this->handleUnlockChallenge();
        $this->checkDirectAccessRedirect();
        $this->handleSetupSubmission();
        $this->loadPrefillData();
    }

    private function loadAdminRecord(): void
    {
        try {
            $conn_u = Database::users();
            $stmt_u = $conn_u->query("SELECT * FROM users WHERE username = 'admin' OR role = 'Admin' ORDER BY id ASC LIMIT 1");
            if ($stmt_u && ($row = $stmt_u->fetch(PDO::FETCH_ASSOC))) {
                $this->admin_record = $row;
                if (!empty($row['password'])) {
                    $this->has_password_in_place = true;
                }
            }
        } catch (Exception $e) {
            // Graceful fallback if database/table is still pending initialization
        }
    }

    private function handleLock(): void
    {
        if (isset($_GET['lock']) || (isset($_POST['action']) && $_POST['action'] === 'lock_setup')) {
            unset($_SESSION['setup_reconfigure_unlocked']);
            header("Location: ../index.php");
            exit();
        }
    }

    private function handleAjax(): void
    {
        if (isset($_GET['action']) && $_GET['action'] === 'ajax_generate_ppp') {
            header('Content-Type: application/json');
            if ($this->system_protected && !$this->is_unlocked) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Security challenge required. Setup wizard is locked.']);
                exit();
            }
            $seq_key = preg_replace('/[^a-fA-F0-9]/', '', trim($_GET['seq_key'] ?? ''));
            $length = (int)($_GET['length'] ?? 30);
            if (strlen($seq_key) < 16 || strlen($seq_key) > 64) {
                echo json_encode(['success' => false, 'error' => 'Invalid sequence key (must be 16 to 64 hex characters)']);
                exit();
            }

            $cell_len = (int)ceil($length / 5.0);
            $passcodes = Security::generate_ppp_passcodes($seq_key, $cell_len);
            echo json_encode(['success' => true, 'passcodes' => $passcodes]);
            exit();
        }
    }

    private function handleUnlockChallenge(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unlock_reconfigure') {
            if (!Security::validate($_POST['csrf_token'] ?? '')) {
                $this->unlock_error = 'Security session expired. Please refresh the page and try again.';
            } else {
                $submitted_pass = $_POST['admin_password'] ?? '';
                if (empty($submitted_pass)) {
                    $this->unlock_error = 'Please enter your current administrator password to proceed.';
                } elseif ($this->verifyAdminPassword($submitted_pass)) {
                    $_SESSION['setup_reconfigure_unlocked'] = time();
                    $_SESSION['authenticated'] = true;
                    $_SESSION['username'] = $this->admin_record['username'] ?? 'admin';
                    $_SESSION['role'] = 'Admin';
                    $_SESSION['display_name'] = $this->admin_record['display_name'] ?: 'Administrator';

                    header("Location: index.php?reconfigure=1");
                    exit();
                } else {
                    $this->unlock_error = 'Access Denied: The administrator password entered is incorrect.';
                }
            }
        }
    }

    private function checkDirectAccessRedirect(): void
    {
        if ($this->is_already_setup && !$this->reconfigure && !isset($_POST['action'])) {
            header("Location: ../index.php");
            exit();
        }
    }

    private function handleSetupSubmission(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'complete_setup') {
            return;
        }

        if (!Security::validate($_POST['csrf_token'] ?? '')) {
            $this->error = 'Security session expired. Please refresh and try again.';
            return;
        }

        if ($this->system_protected) {
            $confirm_pass = $_POST['current_admin_password'] ?? '';
            if (empty($confirm_pass) || !$this->verifyAdminPassword($confirm_pass)) {
                $this->error = 'Security Authorization Failed: You must confirm your current administrator password to commit changes. No modifications were saved.';
                return;
            }
        }

        $company_name = trim($_POST['company_name'] ?? 'IQA Metal');
        $system_name = trim($_POST['system_name'] ?? 'IQA Metal Warehouse Systems');
        $company_url = trim($_POST['company_url'] ?? 'https://iqametal.com');
        $support_email = trim($_POST['support_email'] ?? 'contact@iqametal.com');
        $currency_symbol = trim($_POST['currency_symbol'] ?? '$');
        $tagline = trim($_POST['tagline'] ?? 'Intelligent inventory management & rapid label logistics.');

        $hardware_lines = $_POST['hardware_lines'] ?? ['Laptops', 'Desktops', 'Monitors', 'Parts'];
        $grading_standards = $_POST['grading_standards'] ?? ['A-Grade', 'B-Grade', 'C-Grade', 'Untested', 'Scrap'];
        $diagnostics = $_POST['diagnostics'] ?? ['CPU', 'RAM', 'Storage', 'Battery', 'BIOS', 'OS'];
        $label_preset = trim($_POST['label_preset'] ?? '4x6_thermal');

        $auth_mode = trim($_POST['auth_mode'] ?? ($this->system_protected ? 'keep_existing' : 'ppp'));
        $admin_user = trim($_POST['admin_user'] ?? 'admin');
        $admin_name = trim($_POST['admin_name'] ?? 'System Administrator');
        $ppp_sequence_key = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', trim($_POST['ppp_sequence_key'] ?? '')));
        $ppp_row_index = (int)($_POST['ppp_row_index'] ?? 0);
        $ppp_password_len = (int)($_POST['ppp_password_len'] ?? 30);
        $selected_passcode = trim($_POST['selected_passcode'] ?? '');
        $admin_pass = $_POST['admin_pass'] ?? '';
        $admin_pass_confirm = $_POST['admin_pass_confirm'] ?? '';

        if (empty($company_name)) {
            $this->error = 'Company name is required.';
            return;
        }

        if ($auth_mode === 'custom' && !$this->is_already_setup && (empty($admin_pass) || strlen($admin_pass) < 4)) {
            $this->error = 'Please provide a secure administrator password of at least 4 characters.';
            return;
        }

        if ($auth_mode === 'custom' && !empty($admin_pass) && ($admin_pass !== $admin_pass_confirm)) {
            $this->error = 'Administrator passwords do not match.';
            return;
        }

        if ($auth_mode === 'ppp' && (empty($ppp_sequence_key) || strlen($ppp_sequence_key) < 16 || strlen($ppp_sequence_key) > 64)) {
            $this->error = 'Please generate or enter a valid hexadecimal PPP sequence key (32-hex 128-bit or 64-hex 256-bit).';
            return;
        }

        if ($auth_mode === 'ppp' && ($ppp_row_index < 1 || $ppp_row_index > 25)) {
            $this->error = 'Please click to select an authentication row (Row 1-25) from the passcard grid.';
            return;
        }

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
                $stmt_u = $conn_users->prepare("UPDATE users SET display_name = ? WHERE username = ?");
                $stmt_u->execute([$admin_name, $admin_user]);
            } elseif ($auth_mode === 'default_creds') {
                $password_hash = password_hash('123', PASSWORD_BCRYPT);
                $stmt_u = $conn_users->prepare("INSERT INTO users (username, password, display_name, role, ppp_sequence_key, ppp_row_index, ppp_password_len) 
                    VALUES (?, ?, ?, 'Admin', '', 0, 0)
                    ON CONFLICT(username) DO UPDATE SET password = excluded.password, display_name = excluded.display_name, role = 'Admin', ppp_sequence_key = '', ppp_row_index = 0, ppp_password_len = 0");
                $stmt_u->execute([$admin_user, $password_hash, $admin_name]);
            } elseif ($auth_mode === 'ppp') {
                if (empty($selected_passcode)) {
                    $cell_len = (int)ceil($ppp_password_len / 5.0);
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
                $password_hash = password_hash($admin_pass, PASSWORD_BCRYPT);
                $stmt_u = $conn_users->prepare("INSERT INTO users (username, password, display_name, role, ppp_sequence_key, ppp_row_index, ppp_password_len) 
                    VALUES (?, ?, ?, 'Admin', '', 0, 0)
                    ON CONFLICT(username) DO UPDATE SET password = excluded.password, display_name = excluded.display_name, role = 'Admin', ppp_sequence_key = '', ppp_row_index = 0, ppp_password_len = 0");
                $stmt_u->execute([$admin_user, $password_hash, $admin_name]);
            }

            try {
                $conn_users->exec("DELETE FROM login_attempts");
            } catch (Exception $eAttempts) {
            }

            unset($_SESSION['setup_reconfigure_unlocked']);
            $_SESSION['authenticated'] = true;
            $_SESSION['username'] = $admin_user;
            $_SESSION['role'] = 'Admin';
            $_SESSION['display_name'] = $admin_name;
            $this->company_name = $company_name;
            $this->success = true;
        } catch (Exception $e) {
            $this->error = 'Failed to save configuration: ' . $e->getMessage();
        }
    }

    private function loadPrefillData(): void
    {
        $this->curr_company = Company::getName();
        $this->curr_system = Company::getSystemName();
        $this->curr_url = Company::getUrl();
        $this->curr_email = Company::getEmail();
        $this->curr_currency = Company::getCurrency();
        $this->curr_tagline = Company::getTagline();

        $this->existing_seq_key = $this->admin_record['ppp_sequence_key'] ?? '';
        $this->existing_row_index = (int)($this->admin_record['ppp_row_index'] ?? 0);
        $this->existing_pass_len = (int)($this->admin_record['ppp_password_len'] ?: 30);

        if (empty($this->existing_seq_key)) {
            $this->existing_seq_key = Security::generate_ppp_key();
        }
    }

    public function verifyAdminPassword(string $input_password): bool
    {
        if (!$this->admin_record || empty($input_password)) {
            return false;
        }
        $clean_password = preg_replace('/\s+/', '', $input_password);
        $verified = false;
        if (!empty($this->admin_record['ppp_sequence_key'])) {
            $verified = password_verify($input_password . $this->admin_record['ppp_sequence_key'], $this->admin_record['password'])
                || password_verify($clean_password . $this->admin_record['ppp_sequence_key'], $this->admin_record['password']);
        }
        if (!$verified) {
            $verified = password_verify($input_password, $this->admin_record['password'])
                || password_verify($clean_password, $this->admin_record['password']);
        }
        return $verified;
    }

    public function isProtected(): bool
    {
        return $this->system_protected;
    }

    public function isUnlocked(): bool
    {
        return $this->is_unlocked;
    }

    public function getCompany(): string
    {
        return $this->curr_company ?: Company::getName();
    }

    public function getUnlockError(): string
    {
        return $this->unlock_error;
    }

    public function getViewData(): array
    {
        return [
            'curr_company'        => $this->curr_company,
            'curr_system'         => $this->curr_system,
            'curr_url'            => $this->curr_url,
            'curr_email'          => $this->curr_email,
            'curr_currency'       => $this->curr_currency,
            'curr_tagline'        => $this->curr_tagline,
            'existing_seq_key'    => $this->existing_seq_key,
            'existing_row_index'  => $this->existing_row_index,
            'existing_pass_len'   => $this->existing_pass_len,
            'system_protected'    => $this->system_protected,
            'is_unlocked'         => $this->is_unlocked,
            'reconfigure'         => $this->reconfigure,
            'success'             => $this->success,
            'error'               => $this->error,
            'unlock_error'        => $this->unlock_error,
            'company_name'        => $this->company_name,
        ];
    }
}
