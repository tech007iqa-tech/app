<?php
// api/reprint_label.php
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/hardware_mapping.php';

try {
    $F = HW_FIELDS;
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        throw new Exception("Valid Item ID is required.");
    }

    // 1. Fetch Item Data
    $stmt = $pdo_labels->prepare("SELECT * FROM items WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception("Item not found.");
    }

    // 2. Map and Escape data for XML
    $brand    = htmlspecialchars($item[$F['BRAND']] ?? '', ENT_XML1, 'UTF-8');
    $model    = htmlspecialchars($item[$F['MODEL']] ?? '', ENT_XML1, 'UTF-8');
    $series   = htmlspecialchars($item[$F['SERIES']] ?? '', ENT_XML1, 'UTF-8');
    $cpu_gen  = htmlspecialchars($item[$F['CPU_GEN']] ?? '', ENT_XML1, 'UTF-8');
    $cpu_specs= htmlspecialchars($item[$F['CPU_SPECS']] ?? ($item[$F['CPU_DETAILS']] ?? ''), ENT_XML1, 'UTF-8');
    $cpu_cores= htmlspecialchars($item[$F['CPU_CORES']] ?? '', ENT_XML1, 'UTF-8');
    $cpu_speed= htmlspecialchars($item[$F['CPU_SPEED']] ?? '', ENT_XML1, 'UTF-8');
    $ram      = htmlspecialchars($item[$F['RAM']] ?? 'None', ENT_XML1, 'UTF-8');
    $storage  = htmlspecialchars($item[$F['STORAGE']] ?? 'None', ENT_XML1, 'UTF-8');
    $b_val = $item[$F['BATTERY']] ?? null;
    $battery  = ($b_val === null || $b_val === '') ? 'N/A' : ((int)$b_val === 1 ? 'YES' : 'NO');
    $bios_state = htmlspecialchars($item[$F['BIOS_STATE']] ?? 'Unknown', ENT_XML1, 'UTF-8');
    $warehouse_location = htmlspecialchars($item[$F['LOCATION']] ?? 'Unassigned', ENT_XML1, 'UTF-8');
    $description = htmlspecialchars($item[$F['DESCRIPTION']] ?? 'Untested', ENT_XML1, 'UTF-8');

    // 3. Generate the XML Content
    $qty = max(1, min(100, (int)($_POST['qty'] ?? 1)));
    $show_a = ($_POST['print_a'] ?? '1') === '1';
    $show_b = ($_POST['print_b'] ?? '1') === '1';

    $cpu_detail_parts = array_filter([trim($cpu_cores), trim($cpu_speed)]);
    $cpu_detail_str   = implode(' @ ', $cpu_detail_parts);
    $cpu_spec_line    = trim($cpu_specs . ($cpu_detail_str ? ' (' . $cpu_detail_str . ')' : ''));
    if (!$cpu_spec_line) $cpu_spec_line = '—';
    $cpu_gen_display = $cpu_gen ? $cpu_gen : 'Gen Unknown';

    $labels_xml = '';
    for ($i = 0; $i < $qty; $i++) {
        $is_first_copy = ($i === 0);

        // ── PAGE A: Branding Label ───────────────────────────────────────────
        if ($show_a) {
            $p1_style = $is_first_copy ? 'P1' : 'P1B';
            $labels_xml .= '<text:p text:style-name="' . $p1_style . '">' . $brand . ' ' . $model . ($series ? ' ' . $series : '') . '</text:p>';
            $labels_xml .= '<text:p text:style-name="P2">' . $cpu_spec_line . '</text:p>';
        }

        // ── PAGE B: Technical Specs Label ────────────────────────────────────
        if ($show_b) {
            // Page break needed if A was shown or if it's a subsequent copy
            $p2_style = ($show_a || !$is_first_copy) ? 'P2B' : 'P2';
            $labels_xml .= '<text:p text:style-name="' . $p2_style . '">CPU: ' . $cpu_spec_line . ' (' . $cpu_gen_display . ')</text:p>';
            $labels_xml .= '<text:p text:style-name="P2">RAM: ' . ($ram ?: 'None') . ' | Storage: ' . ($storage ?: 'None') . ' | Battery: ' . $battery . '</text:p>';

            $gpu_val = trim($item[$F['GPU']] ?? '');
            if ($gpu_val !== '' || $item[$F['DESCRIPTION']] === 'Refurbished') {
                $gpu = htmlspecialchars($gpu_val !== '' ? $gpu_val : 'Integrated', ENT_XML1, 'UTF-8');
                $os  = htmlspecialchars($item[$F['OS_VERSION']] ?? '—', ENT_XML1, 'UTF-8');
                $labels_xml .= '<text:p text:style-name="P2">GPU: ' . $gpu . ' | OS: ' . $os . ' | BIOS: ' . $bios_state . '</text:p>';
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
        send_json_response(true, [
            'file_name' => $final_odt_name,
            'file_path' => 'exports/labels/' . $final_odt_name
        ]);
    } else {
        throw new Exception("ODT generation failed: Output file not created.");
    }

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>

