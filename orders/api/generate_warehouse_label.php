<?php
// orders/api/generate_warehouse_label.php
header('Content-Type: application/json');

require_once __DIR__ . '/../core/warehouse_db.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/Audit.php';

function send_json_response($success, $data = null, $error = null) {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'error' => $error
    ]);
    exit;
}

session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    send_json_response(false, null, "Unauthorized access.");
}

try {
    // 1. Security Check
    if (!Security::validate($_POST['csrf_token'] ?? '')) {
        throw new Exception("Security Error: Invalid form submission.");
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        throw new Exception("Valid Warehouse Item ID is required.");
    }

    // 2. Fetch Warehouse Item Data
    $stmt = $conn_wh->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception("Warehouse Item not found.");
    }

    $specs = json_decode($item['specs_json'], true) ?: [];

    // 3. Map Data
    $brand    = $item['brand'] ?? '';
    $model    = $item['model'] ?? '';
    $series   = $specs['series'] ?? '';
    $cpu_gen  = $specs['gen'] ?? $specs['cpu_gen'] ?? '';
    $cpu_specs= $specs['cpu'] ?? '';
    $ram      = $specs['ram'] ?? '';
    $storage  = $specs['storage'] ?? '';
    $gpu      = $specs['gpu'] ?? '';
    $os_version = $specs['windows'] ?? '';

    // Normalize battery to 1 or 0
    $battery_val = $specs['battery'] ?? '';
    $battery = null;
    if ($battery_val !== '') {
        $battery = (stripos($battery_val, 'missing') !== false || stripos($battery_val, 'no') !== false) ? 0 : 1;
    }

    $bios_state = $specs['bios'] ?? 'Unknown';
    $description = $specs['condition'] ?? 'Untested';
    $warehouse_location = $item['location_code'] ?? 'Unassigned';

    // Log audit event locally
    $summary = "Warehouse Intake Label: Generated for $brand $model" . ($series ? " ($series)" : "");
    Audit::log('LABEL_GENERATED', $id, $summary, 'warehouse');

    // 4. Generate Flat XML Content (.fodt) for label
    // Escaping values for XML
    $brand_xml    = htmlspecialchars($brand ?? '', ENT_XML1, 'UTF-8');
    $model_xml    = htmlspecialchars($model ?? '', ENT_XML1, 'UTF-8');
    $series_xml   = htmlspecialchars($series ?? '', ENT_XML1, 'UTF-8');
    $cpu_spec_line = htmlspecialchars($cpu_specs ?? '—', ENT_XML1, 'UTF-8');
    $cpu_gen_display = $cpu_gen ? htmlspecialchars($cpu_gen, ENT_XML1, 'UTF-8') : 'Gen Unknown';
    $ram_xml      = htmlspecialchars($ram ?: 'None', ENT_XML1, 'UTF-8');
    $storage_xml  = htmlspecialchars($storage ?: 'None', ENT_XML1, 'UTF-8');
    $battery_display = ($battery === null) ? 'N/A' : ($battery === 1 ? 'YES' : 'NO');
    $gpu_xml      = htmlspecialchars($gpu ?: 'Integrated', ENT_XML1, 'UTF-8');
    $os_xml       = htmlspecialchars($os_version ?: '—', ENT_XML1, 'UTF-8');
    $bios_xml     = htmlspecialchars($bios_state, ENT_XML1, 'UTF-8');

    $notes = $specs['notes'] ?? '';
    $notes_xml = htmlspecialchars($notes, ENT_XML1, 'UTF-8');

    $labels_xml = '';
    // Page A: Branding Label
    $labels_xml .= '<text:p text:style-name="P1">' . $brand_xml . ' ' . $model_xml . ($series_xml ? ' ' . $series_xml : '') . '</text:p>';
    $labels_xml .= '<text:p text:style-name="P2">' . $cpu_spec_line . '</text:p>';
    if ($notes !== '') {
        $labels_xml .= '<text:p text:style-name="P4">' . $notes_xml . '</text:p>';
    }

    // Page B: Technical Specs Label
    $labels_xml .= '<text:p text:style-name="P2B">CPU: ' . $cpu_spec_line . ' (' . $cpu_gen_display . ')</text:p>';
    $labels_xml .= '<text:p text:style-name="P2">RAM: ' . $ram_xml . ' | Storage: ' . $storage_xml . ' | Battery: ' . $battery_display . '</text:p>';

    if ($gpu !== '' || $description === 'Refurbished' || !empty($specs['bios'])) {
        $labels_xml .= '<text:p text:style-name="P2">GPU: ' . $gpu_xml . ' | OS: ' . $os_xml . ' | BIOS: ' . $bios_xml . '</text:p>';
    }

    $flat_xml = '<?xml version="1.0" encoding="UTF-8"?>
<office:document xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
                 xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
                 xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
                 xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0"
                 xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"
                 office:version="1.2" office:mimetype="application/vnd.oasis.opendocument.text">
  <office:automatic-styles>
    <style:page-layout style:name="pm1">
      <style:page-layout-properties fo:page-width="2in" fo:page-height="1in" fo:margin-top="0.05in" fo:margin-bottom="0in" fo:margin-left="0.05in" fo:margin-right="0.05in" />
    </style:page-layout>
    <style:style style:name="P1" style:family="paragraph">
      <style:paragraph-properties fo:text-align="center" fo:margin-top="0in" fo:margin-bottom="0.02in"/>
      <style:text-properties fo:font-size="14pt" fo:font-weight="bold" style:font-name="Arial"/>
    </style:style>
    <style:style style:name="P1B" style:family="paragraph" style:parent-style-name="P1">
      <style:paragraph-properties fo:break-before="page"/>
    </style:style>
    <style:style style:name="P2" style:family="paragraph">
      <style:paragraph-properties fo:text-align="center" fo:margin-top="0in" fo:margin-bottom="0in"/>
      <style:text-properties fo:font-size="8.5pt" style:font-name="Arial"/>
    </style:style>
    <style:style style:name="P2B" style:family="paragraph" style:parent-style-name="P2">
      <style:paragraph-properties fo:break-before="page"/>
    </style:style>
    <style:style style:name="P4" style:family="paragraph">
      <style:paragraph-properties fo:text-align="center" fo:margin-top="0in" fo:margin-bottom="0in"/>
      <style:text-properties fo:font-size="6pt" style:font-name="Arial"/>
    </style:style>
    <style:font-face style:name="Arial" svg:font-family="Arial" style:font-family-generic="swiss"/>
  </office:automatic-styles>
  <office:master-styles>
    <style:master-page style:name="Standard" style:page-layout-name="pm1"/>
  </office:master-styles>
  <office:body>
    <office:text>
      ' . $labels_xml . '
    </office:text>
  </office:body>
</office:document>';

    // 5. Save ODT to local exports folder
    $export_dir = __DIR__ . '/../assets/exports/labels/';
    if (!is_dir($export_dir)) {
        mkdir($export_dir, 0755, true);
    }

    $safe_brand = preg_replace('/[^a-zA-Z0-9]/', '', $brand);
    $safe_model = preg_replace('/[^a-zA-Z0-9]/', '', $model);
    $safe_gen   = preg_replace('/[^a-zA-Z0-9]/', '', $cpu_gen);

    $final_odt_name = "{$safe_brand}_{$safe_model}_{$safe_gen}_ID{$id}.odt";
    $final_odt_path = $export_dir . $final_odt_name;

    file_put_contents($final_odt_path, $flat_xml);

    if (file_exists($final_odt_path)) {
        send_json_response(true, [
            'file_name' => $final_odt_name,
            'file_path' => 'assets/exports/labels/' . $final_odt_name
        ]);
    } else {
        throw new Exception("ODT generation failed: Output file not created.");
    }

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
