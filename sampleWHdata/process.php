<?php
header('Content-Type: application/json');

require_once __DIR__ . '/src/Config.php';
require_once __DIR__ . '/src/Normalizer.php';
require_once __DIR__ . '/src/DbHandler.php';
require_once __DIR__ . '/src/OcrEngine.php';

use Src\Config;
use Src\Normalizer;
use Src\DbHandler;
use Src\OcrEngine;

function sendError($msg)
{
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

$action = $_GET['action'] ?? '';
$configHandler = new Config();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !is_array($data)) {
            sendError('Invalid input data');
        }

        try {
            $dbHandler = new DbHandler();
            if ($dbHandler->insertRows($data)) {
                echo json_encode(['success' => true]);
            } else {
                sendError('Failed to save to database');
            }
        } catch (\Exception $e) {
            sendError($e->getMessage());
        }
        exit;
    }

    if ($action === 'save_config') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            sendError('Invalid config data');
        }
        if ($configHandler->saveConfig($data)) {
            echo json_encode(['success' => true]);
        } else {
            sendError('Failed to save config');
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
            sendError($e->getMessage());
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
        sendError($e->getMessage());
    }
    exit;
}

if ($action === 'clear_committed') {
    try {
        $dbHandler = new DbHandler();
        if ($dbHandler->clearAll()) {
            echo json_encode(['success' => true]);
        } else {
            sendError('Failed to clear database');
        }
    } catch (\Exception $e) {
        sendError($e->getMessage());
    }
    exit;
}

if ($action === 'get_config') {
    $config = $configHandler->loadConfig();
    echo json_encode(['success' => true, 'config' => (object) $config]);
    exit;
}

// Fallback: send config directly
$config = $configHandler->loadConfig();
echo json_encode(['success' => true, 'config' => $config]);
