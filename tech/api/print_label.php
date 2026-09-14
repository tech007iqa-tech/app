<?php
// tech/api/print_label.php
header('Content-Type: application/json');
require_once '../core/database.php';
require_once '../core/auth.php';

try {
    $id = isset($_POST['id']) ? (int)($_POST['id']) : 0;
    if ($id <= 0) {
        throw new Exception("Valid Log ID is required.");
    }

    // 1. Fetch Log Data
    $conn = Database::tech();
    $stmt = $conn->prepare("SELECT * FROM logs WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        throw new Exception("Log not found.");
    }

    // 2. Map and Escape data for XML
    $brand      = htmlspecialchars($log['make'] ?? '', ENT_XML1, 'UTF-8');
    $model      = htmlspecialchars($log['model'] ?? '', ENT_XML1, 'UTF-8');
    $series     = htmlspecialchars($log['series'] ?? '', ENT_XML1, 'UTF-8');
    $cpu_specs  = htmlspecialchars($log['cpu'] ?? '—', ENT_XML1, 'UTF-8');
    $cpu_gen    = '—'; // extracted from CPU if possible, or just dash
    $ram        = htmlspecialchars($log['ram'] ?? 'None', ENT_XML1, 'UTF-8');
    $storage    = htmlspecialchars($log['storage'] ?? 'None', ENT_XML1, 'UTF-8');
    $battery    = htmlspecialchars($log['battery'] ?? 'N/A', ENT_XML1, 'UTF-8');
    $bios_state = htmlspecialchars($log['bios_state'] ?? 'Unknown', ENT_XML1, 'UTF-8');
    $gpu        = htmlspecialchars($log['gpu'] ?? 'Integrated', ENT_XML1, 'UTF-8');
    $os         = htmlspecialchars($log['os'] ?? '—', ENT_XML1, 'UTF-8');

    // Try to guess gen from CPU string (e.g. i5-8350U -> 8th Gen)
    if (preg_match('/i[3579]-(\d)\d{3}/', $cpu_specs, $matches)) {
        $cpu_gen = $matches[1] . 'th Gen';
    } elseif (preg_match('/i[3579]-(\d{2})\d{3}/', $cpu_specs, $matches)) {
        $cpu_gen = $matches[1] . 'th Gen';
    }
    $cpu_gen_display = $cpu_gen !== '—' ? $cpu_gen : 'Gen Unknown';

    // 3. Generate the XML Content
    $qty = max(1, min(100, (int)($_POST['qty'] ?? 1)));
    $show_a = ($_POST['print_a'] ?? '1') === '1';
    $show_b = ($_POST['print_b'] ?? '1') === '1';

    $labels_xml = '';
    for ($i = 0; $i < $qty; $i++) {
        $is_first_copy = ($i === 0);

        // ── PAGE A: Branding Label ───────────────────────────────────────────
        if ($show_a) {
            $p1_style = $is_first_copy ? 'P1' : 'P1B';
            $labels_xml .= '<text:p text:style-name="' . $p1_style . '">' . $brand . ' ' . $model . ($series ? ' ' . $series : '') . '</text:p>';
            $labels_xml .= '<text:p text:style-name="P2">' . $cpu_specs . '</text:p>';
        }

        // ── PAGE B: Technical Specs Label ────────────────────────────────────
        if ($show_b) {
            // Page break needed if A was shown or if it's a subsequent copy
            $p2_style = ($show_a || !$is_first_copy) ? 'P2B' : 'P2';
            $labels_xml .= '<text:p text:style-name="' . $p2_style . '">CPU: ' . $cpu_specs . ' (' . $cpu_gen_display . ')</text:p>';
            $labels_xml .= '<text:p text:style-name="P2">RAM: ' . ($ram ?: 'None') . ' | Storage: ' . ($storage ?: 'None') . ' | Battery: ' . $battery . '</text:p>';

            if ($gpu !== '' && strtolower($gpu) !== 'integrated') {
                $labels_xml .= '<text:p text:style-name="P2">GPU: ' . $gpu . ' | OS: ' . $os . ' | BIOS: ' . $bios_state . '</text:p>';
            } else {
                $labels_xml .= '<text:p text:style-name="P2">BIOS: ' . $bios_state . '</text:p>';
            }
        }
    }

    // 4. GENERATE FLAT XML CONTENT (.fodt)
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

    // 6. SAVE AND LAUNCH
    $export_dir = __DIR__ . '/../exports/labels/';
    if (!is_dir($export_dir)) mkdir($export_dir, 0777, true);

    $safe_brand = preg_replace('/[^a-zA-Z0-9]/', '', $brand);
    $safe_model = preg_replace('/[^a-zA-Z0-9]/', '', $model);
    $safe_gen   = preg_replace('/[^a-zA-Z0-9]/', '', $cpu_gen);

    $final_odt_name = "{$safe_brand}_{$safe_model}_{$safe_gen}_ID{$id}.odt";
    $final_odt_path = realpath($export_dir) . DIRECTORY_SEPARATOR . $final_odt_name;

    file_put_contents($final_odt_path, $flat_xml);

    // 7. Response — return file info for client-side download
    if (file_exists($final_odt_path)) {
        echo json_encode([
            'success' => true,
            'file_name' => $final_odt_name,
            'file_path' => '../exports/labels/' . $final_odt_name
        ]);
    } else {
        throw new Exception("ODT generation failed: Output file not created.");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
