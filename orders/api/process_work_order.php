<?php
// orders/api/process_work_order.php
header('Content-Type: application/json');

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/ConfigWorkOrder.php';
require_once __DIR__ . '/../core/NormalizerWorkOrder.php';
require_once __DIR__ . '/../core/Security.php';

session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

function sendError($msg)
{
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

$action = $_GET['action'] ?? '';
$configHandler = new ConfigWorkOrder();

// Action: get_config
if ($action === 'get_config') {
    $config = $configHandler->loadConfig();
    echo json_encode(['success' => true, 'config' => (object) $config]);
    exit;
}

// Action: save_config
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_config') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        sendError('Invalid config data.');
    }
    if ($configHandler->saveConfig($data)) {
        echo json_encode(['success' => true]);
    } else {
        sendError('Failed to save config.');
    }
    exit;
}

// Action: extract (OCR Vision)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'extract') {
    if (empty($_FILES['images']['name'][0])) {
        sendError('No files uploaded.');
    }

    $config = $configHandler->loadConfig();
    $apiKey = $config['gemini_api_key'] ?? '';

    if (empty($apiKey)) {
        sendError('Gemini API Key is not configured. Please go to settings and configure your gemini_api_key first.');
    }

    $promptSettings = $config['prompt_settings'] ?? [];

    // Build prompt dynamically from settings
    $defaultPromptSettings = [
        'role' => "You are a B2B sales order coordinator. Analyze this handwritten B2B Work Order image/document and extract all table rows.",
        'translations' => [
            "pb" => "ProBook",
            "eb" => "EliteBook",
            "tp" => "ThinkPad",
            "pd" => "ProDesk",
            "lat" => "Latitude",
            "latitude" => "Latitude"
        ],
        'formatting_rules' => [
            "Deduce 'Brand' (HP, Lenovo, Dell, Apple, Asus, Acer, MSI, Razer, Panasonic, etc.) based on 'Model' or 'Series'.",
            "Correct brand/model spelling: e.g. 'Latitue' -> 'Latitude', 'Pavillion' -> 'Pavilion', 'Zbook' -> 'ZBook'.",
            "Extract 'Model' (e.g. EliteBook, Latitude, Laptop, Legion, Alienware, Predator, TUF, Omen, Yoga, GF65) and 'Series' (e.g. 840 G6, E5440, F505DU, Y7000P).",
            "Extract CPU generation in 'CPU' field (e.g. i5-8th, i7-8th, i3-11th, AMD Ryzen 5 13th, or 4th).",
            "Extract RAM and Storage specs (e.g., 8gb, 16gb, 8/512, 16/256, 8/) into the 'Note' field exactly as written."
        ],
        'schema_rules' => [
            "Model" => "string, broad product line or brand name (e.g. EliteBook, Latitude, ProBook, Laptop, ZBook, Pavilion, Legion, Alienware, Predator, TUF, Omen, Yoga, IdeaPad, GF65, Blade)",
            "Series" => "string, specific series or model identifier (e.g. 840 G6, E5440, Fd15, 14U G6, X360, Y7000P, F505DU, C740)",
            "CPU_Gen" => "string, processor type and generation (e.g. i5-8th, i7-8th, i3-11th, i5-13th, AMD Ryzen 5 13th, 4th)",
            "Description" => "string, general notes from description column (e.g. Untested, Parts)",
            "QTY" => "integer, quantity of units",
            "Note" => "string, notes or specifications including RAM and Storage (e.g. 8gb, 8/, 8/512, 16/256, 16/128, etc.)"
        ]
    ];

    $promptSettings = array_replace_recursive($defaultPromptSettings, $promptSettings);

    $prompt = $promptSettings['role'] . "\n\nFor each row, extract:\n";
    foreach ($promptSettings['schema_rules'] as $field => $desc) {
        $prompt .= "- '$field' ($desc)\n";
    }

    if (!empty($promptSettings['translations'])) {
        $trans = [];
        foreach ($promptSettings['translations'] as $abbr => $expanded) {
            if (!empty($abbr) && !empty($expanded)) {
                $trans[] = "$abbr -> $expanded";
            }
        }
        if (!empty($trans)) {
            $prompt .= "\nUse these translation rules for abbreviations in item names: " . implode(', ', $trans) . ".\n";
        }
    }

    if (!empty($promptSettings['formatting_rules'])) {
        $prompt .= "\nNormalization rules for B2B items:\n";
        $idx = 1;
        foreach ($promptSettings['formatting_rules'] as $rule) {
            if (!empty(trim($rule))) {
                $prompt .= "$idx. $rule\n";
                $idx++;
            }
        }
    }

    $prompt .= "\nReturn a JSON array of these row objects. If a field is empty, return an empty string. Output ONLY the JSON array (do not wrap in markdown ```json blocks).";

    $tmpName = $_FILES['images']['tmp_name'][0];
    $type = $_FILES['images']['type'][0];
    $name = $_FILES['images']['name'][0];

    try {
        $fileData = base64_encode(file_get_contents($tmpName));
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . urlencode($apiKey);

        $requestData = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inlineData' => [
                                'mimeType' => $type,
                                'data' => $fileData
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json'
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \Exception("Gemini API call failed (HTTP $httpCode): " . ($curlError ?: $response));
        }

        $resDecoded = json_decode($response, true);
        $textResult = $resDecoded['candidates'][0]['content']['parts'][0]['text'] ?? '';

        $rows = json_decode(trim($textResult), true);
        if (!is_array($rows)) {
            preg_match('/\[.*\]/s', $textResult, $matches);
            if (!empty($matches[0])) {
                $rows = json_decode($matches[0], true);
            }
        }

        if (!is_array($rows)) {
            throw new \Exception("Failed to parse JSON response from Gemini API: " . $textResult);
        }

        $normalizer = new NormalizerWorkOrder();
        $normalizedRows = [];

        foreach ($rows as $row) {
            $normalizedRows[] = $normalizer->normalizeRow($row);
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'rows' => $normalizedRows,
                'RawOCR' => "Gemini AI OCR Output:\n" . $textResult,
                'AvgConfidence' => 98
            ]
        ]);

    } catch (\Exception $e) {
        sendError($e->getMessage());
    }
    exit;
}

sendError('Invalid action.');
