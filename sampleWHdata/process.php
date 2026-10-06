<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$parent_core_dir = dirname(__DIR__) . '/core';
$has_parent_core = file_exists($parent_core_dir . '/Auth.php');

// Enforce portal authentication if integrated with parent system
if ($has_parent_core) {
    require_once $parent_core_dir . '/Auth.php';
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required. Please log in to the Operations Portal.']);
        exit;
    }
}

if (file_exists($parent_core_dir . '/Security.php')) {
    require_once $parent_core_dir . '/Security.php';
}

require_once __DIR__ . '/src/Config.php';
require_once __DIR__ . '/src/Normalizer.php';
require_once __DIR__ . '/src/DbHandler.php';
require_once __DIR__ . '/src/OcrEngine.php';

use Src\Config;
use Src\Normalizer;
use Src\DbHandler;
use Src\OcrEngine;

function sendError($msg, $code = 400)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function checkAdminRole()
{
    if (isset($_SESSION['authenticated']) && isset($_SESSION['role'])) {
        $allowed = ['Admin', 'Manager'];
        if (!in_array($_SESSION['role'], $allowed)) {
            sendError('Forbidden: Administrative privileges required.', 403);
        }
    }
}

function checkCsrf($payload = [])
{
    if (!class_exists('Security') || empty($_SESSION['csrf_token'])) {
        return;
    }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? $_POST['csrf_token']
        ?? ($payload['csrf_token'] ?? '');

    if (!Security::validate($token)) {
        sendError('Security Error: Invalid or missing CSRF token.', 403);
    }
}

$action = $_GET['action'] ?? '';
$configHandler = new Config();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !is_array($data)) {
            sendError('Invalid input data');
        }
        checkCsrf(is_array($data) ? $data : []);

        try {
            $dbHandler = new DbHandler();
            if ($dbHandler->insertRows($data)) {
                echo json_encode(['success' => true]);
            } else {
                sendError('Failed to save to database', 500);
            }
        } catch (\Exception $e) {
            sendError($e->getMessage(), 500);
        }
        exit;
    }

    if ($action === 'save_config') {
        checkAdminRole();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            sendError('Invalid config data');
        }
        checkCsrf($data);

        // Preserve existing Gemini API key if submitted blank or masked
        $existingConfig = $configHandler->loadConfig();
        if (empty($data['gemini_api_key']) || strpos($data['gemini_api_key'], '••••') !== false) {
            $data['gemini_api_key'] = $existingConfig['gemini_api_key'] ?? '';
        }

        if ($configHandler->saveConfig($data)) {
            echo json_encode(['success' => true]);
        } else {
            sendError('Failed to save config', 500);
        }
        exit;
    }

    if ($action === 'clear_committed') {
        checkAdminRole();
        $data = json_decode(file_get_contents('php://input'), true);
        checkCsrf(is_array($data) ? $data : []);

        try {
            $dbHandler = new DbHandler();
            if ($dbHandler->clearAll()) {
                echo json_encode(['success' => true]);
            } else {
                sendError('Failed to clear database', 500);
            }
        } catch (\Exception $e) {
            sendError($e->getMessage(), 500);
        }
        exit;
    }

    if ($action === 'normalize') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            sendError('Invalid data');
        }
        $normalizer = new Normalizer();
        foreach ($data as &$row) {
            $row = $normalizer->normalizeRow($row);
        }
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    if ($action === 'extract') {
        checkCsrf();
        if (empty($_FILES['images']['name'][0])) {
            sendError('No files uploaded');
        }

        $config = $configHandler->loadConfig();
        $apiKey = $config['gemini_api_key'] ?? '';
        $promptSettings = $config['prompt_settings'] ?? [];

        // Build prompt dynamically from settings
        $prompt = OcrEngine::buildPrompt($promptSettings);

        $file = [
            'name' => $_FILES['images']['name'][0],
            'type' => $_FILES['images']['type'][0],
            'tmp_name' => $_FILES['images']['tmp_name'][0],
            'error' => $_FILES['images']['error'][0],
            'size' => $_FILES['images']['size'][0]
        ];

        try {
            $ocrEngine = new OcrEngine($apiKey, $prompt);
            $ocrResult = $ocrEngine->extract($file);
            $rows = $ocrResult['rows'];
            $rawOCR = $ocrResult['rawOCR'];

            $normalizer = new Normalizer();

            foreach ($rows as &$row) {
                $row = $normalizer->normalizeRow($row);
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'rows' => $rows,
                    'RawOCR' => $rawOCR,
                    'AvgConfidence' => count($rows) > 0 ? array_sum(array_column($rows, 'Confidence')) / count($rows) : 98
                ]
            ]);
        } catch (\Exception $e) {
            sendError($e->getMessage(), 500);
        }
        exit;
    }
}

if ($action === 'get_committed') {
    try {
        $dbHandler = new DbHandler();
        $rows = $dbHandler->fetchAll();
        echo json_encode(['success' => true, 'data' => $rows]);
    } catch (\Exception $e) {
        sendError($e->getMessage(), 500);
    }
    exit;
}

if ($action === 'clear_committed') {
    sendError('Method Not Allowed. POST request required.', 405);
}

if ($action === 'get_config') {
    $config = $configHandler->loadConfig();
    $rawKey = $config['gemini_api_key'] ?? '';
    $user_role = $_SESSION['role'] ?? 'Operator';
    $isAdmin = !isset($_SESSION['authenticated']) || in_array($user_role, ['Admin', 'Manager']);

    // Mask API key for non-administrative roles to prevent credential exposure
    if (!$isAdmin && !empty($rawKey)) {
        $config['gemini_api_key'] = substr($rawKey, 0, 4) . '••••••••' . substr($rawKey, -4);
    }
    $config['has_key'] = !empty($rawKey);

    $csrfToken = class_exists('Security') ? Security::getToken() : ($_SESSION['csrf_token'] ?? '');
    echo json_encode([
        'success' => true,
        'config' => (object) $config,
        'csrf_token' => $csrfToken
    ]);
    exit;
}

// Reject unknown or missing actions instead of dumping config
sendError('Invalid or unsupported action: ' . htmlspecialchars($action), 400);
