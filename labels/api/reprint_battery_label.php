<?php
/**
 * labels/api/reprint_battery_label.php
 * Generates OpenDocument Flat XML (.odt) for battery pack thermal printing.
 * Zero ZipArchive dependency (GEMINI.md Invariant).
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        throw new Exception("Valid Battery ID is required.");
    }

    $stmt = $pdo_labels->prepare("SELECT * FROM batteries WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $bat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bat) {
        throw new Exception("Battery not found.");
    }

    $brand       = htmlspecialchars($bat['brand'] ?? '', ENT_XML1, 'UTF-8');
    $part_number = htmlspecialchars($bat['part_number'] ?? '', ENT_XML1, 'UTF-8');
    $voltage     = htmlspecialchars($bat['voltage'] ?? '', ENT_XML1, 'UTF-8');
    $capacity_wh = htmlspecialchars($bat['capacity_wh'] ?? '', ENT_XML1, 'UTF-8');
    $cell_count  = htmlspecialchars($bat['cell_count'] ?? '', ENT_XML1, 'UTF-8');
    $chemistry   = htmlspecialchars($bat['chemistry'] ?? 'Li-ion', ENT_XML1, 'UTF-8');
    $location    = htmlspecialchars($bat['warehouse_location'] ?? 'Unassigned', ENT_XML1, 'UTF-8');
    $condition   = htmlspecialchars($bat['condition'] ?? 'Tested OEM 80%+', ENT_XML1, 'UTF-8');
    $models_raw  = $bat['compatible_models'] ?? '';

    $models_arr = array_map('trim', explode(',', $models_raw));
    $fits_sample = array_slice($models_arr, 0, 4);
    $fits_str = implode(', ', $fits_sample);
    if (count($models_arr) > 4) {
        $fits_str .= ' +' . (count($models_arr) - 4) . ' more';
    }
    $fits_xml = htmlspecialchars($fits_str, ENT_XML1, 'UTF-8');

    $specs_xml = htmlspecialchars(trim(implode(' · ', array_filter([$voltage, $capacity_wh, $cell_count, $chemistry]))), ENT_XML1, 'UTF-8');

    $qty = max(1, min(100, (int)($_POST['qty'] ?? 1)));

    $labels_xml = '';
    for ($i = 0; $i < $qty; $i++) {
        $is_first = ($i === 0);
        $p_style = $is_first ? 'P1' : 'P1B';

        $labels_xml .= '<text:p text:style-name="' . $p_style . '">' . $brand . ' ' . $part_number . '</text:p>';
        $labels_xml .= '<text:p text:style-name="P2">' . $specs_xml . '</text:p>';
        $labels_xml .= '<text:p text:style-name="P3">FITS: ' . $fits_xml . '</text:p>';
        $labels_xml .= '<text:p text:style-name="P4">BIN: ' . $location . ' | ' . strtoupper($condition) . ' | ID#' . $id . '</text:p>';
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
      <style:page-layout-properties fo:page-width="2in" fo:page-height="1in" fo:margin-top="0.04in" fo:margin-bottom="0in" fo:margin-left="0.05in" fo:margin-right="0.05in" />
    </style:page-layout>
    <style:style style:name="P1" style:family="paragraph">
      <style:paragraph-properties fo:text-align="center" fo:margin-top="0in" fo:margin-bottom="0.01in"/>
      <style:text-properties fo:font-size="13pt" fo:font-weight="bold" style:font-name="Arial"/>
    </style:style>
    <style:style style:name="P1B" style:family="paragraph" style:parent-style-name="P1">
      <style:paragraph-properties fo:break-before="page"/>
    </style:style>
    <style:style style:name="P2" style:family="paragraph">
      <style:paragraph-properties fo:text-align="center" fo:margin-top="0in" fo:margin-bottom="0.01in"/>
      <style:text-properties fo:font-size="8pt" fo:font-weight="bold" style:font-name="Arial"/>
    </style:style>
    <style:style style:name="P3" style:family="paragraph">
      <style:paragraph-properties fo:text-align="center" fo:margin-top="0in" fo:margin-bottom="0.01in"/>
      <style:text-properties fo:font-size="6.5pt" style:font-name="Arial"/>
    </style:style>
    <style:style style:name="P4" style:family="paragraph">
      <style:paragraph-properties fo:text-align="center" fo:margin-top="0.02in" fo:margin-bottom="0in"/>
      <style:text-properties fo:font-size="6pt" fo:font-weight="bold" style:font-name="Arial"/>
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

    $export_dir = __DIR__ . '/../exports/labels/';
    if (!is_dir($export_dir)) mkdir($export_dir, 0777, true);

    $safe_brand = preg_replace('/[^a-zA-Z0-9]/', '', $brand);
    $safe_part  = preg_replace('/[^a-zA-Z0-9]/', '', $part_number);

    $final_odt_name = "Battery_{$safe_brand}_{$safe_part}_ID{$id}.odt";
    $final_odt_path = realpath($export_dir) . DIRECTORY_SEPARATOR . $final_odt_name;

    file_put_contents($final_odt_path, $flat_xml);

    if (file_exists($final_odt_path)) {
        send_json_response(true, [
            'file_name' => $final_odt_name,
            'file_path' => 'exports/labels/' . $final_odt_name
        ]);
    } else {
        throw new Exception("ODT generation failed: Output file could not be created.");
    }

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
