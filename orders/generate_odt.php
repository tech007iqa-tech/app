<?php
// Flat OpenDocument Text (FODT) generator - Bypasses ZipArchive dependency
// LibreOffice natively reads this as a standard ODT file.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid Request Method");
}

$brand = $_POST['brand'] ?? '';
$model = $_POST['model'] ?? '';
$series = $_POST['series'] ?? '';
$cpu = $_POST['cpu'] ?? '';
$desc = $_POST['description'] ?? '';
$notes = $_POST['notes'] ?? '';

// Word-wrap the brand/model/series to fit into precisely 3 rows.
$full_title = trim("$brand $model $series");
$words = explode(" ", $full_title);
$lines = ["", "", ""];
$line_idx = 0;
foreach($words as $w) {
    if ($line_idx > 2) break;
    // Maximum ~14 characters per line at 16pt font to prevent overflow on 2" label
    if (strlen($lines[$line_idx] . " " . $w) > 14 && strlen($lines[$line_idx]) > 0) {
        $line_idx++;
        if ($line_idx > 2) break;
    }
    $lines[$line_idx] = trim($lines[$line_idx] . " " . $w);
}

// Ensure at least empty spaces exist if title is short so vertical layout is maintained
$title1 = htmlspecialchars($lines[0]);
$title2 = htmlspecialchars($lines[1]);
$title3 = htmlspecialchars($lines[2]);
$specs = htmlspecialchars(trim("$cpu | $desc"));
$notes_xml = htmlspecialchars($notes);

$flat_xml = '<?xml version="1.0" encoding="UTF-8"?>
<office:document xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
                 xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
                 xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
                 xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0"
                 office:version="1.2" office:mimetype="application/vnd.oasis.opendocument.text">
  <office:automatic-styles>
    <style:page-layout style:name="pm1">
      <style:page-layout-properties fo:page-width="2in" fo:page-height="1in" fo:margin-top="0.02in" fo:margin-bottom="0.02in" fo:margin-left="0.05in" fo:margin-right="0.05in" />
    </style:page-layout>
    <style:style style:name="P1" style:family="paragraph" style:parent-style-name="Standard">
      <style:paragraph-properties fo:text-align="center" fo:margin-top="0in" fo:margin-bottom="0.02in"/>
      <style:text-properties fo:font-size="16pt" style:font-name="Times New Roman" fo:font-weight="bold"/>
    </style:style>
    <style:style style:name="P2" style:family="paragraph" style:parent-style-name="Standard">
      <style:paragraph-properties fo:text-align="center" fo:margin-top="0.04in" fo:margin-bottom="0in"/>
      <style:text-properties fo:font-size="7pt" style:font-name="Times New Roman" fo:font-weight="bold"/>
    </style:style>
    <style:style style:name="P4" style:family="paragraph" style:parent-style-name="Standard">
      <style:paragraph-properties fo:text-align="center" fo:margin-top="0.02in" fo:margin-bottom="0in"/>
      <style:text-properties fo:font-size="6pt" style:font-name="Times New Roman"/>
    </style:style>
  </office:automatic-styles>
  <office:master-styles>
    <style:master-page style:name="Standard" style:page-layout-name="pm1"/>
  </office:master-styles>
  <office:body>
    <office:text>
      <text:p text:style-name="P1">'.$title1.'</text:p>
      <text:p text:style-name="P1">'.$title2.'</text:p>
      <text:p text:style-name="P1">'.$title3.'</text:p>
      <text:p text:style-name="P2">'.$specs.'</text:p>';
if ($notes !== '') {
    $flat_xml .= '      <text:p text:style-name="P4">'.$notes_xml.'</text:p>';
}
$flat_xml .= '    </office:text>
  </office:body>
</office:document>';

$file_name = "Label_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $brand . "_" . $model) . ".odt";

$temp_file = tempnam(sys_get_temp_dir(), 'iqa_fodt_');
file_put_contents($temp_file, $flat_xml);

header('Content-Type: application/vnd.oasis.opendocument.text');
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Cache-Control: max-age=0');
header('Content-Length: ' . filesize($temp_file));
readfile($temp_file);
unlink($temp_file);
exit;
