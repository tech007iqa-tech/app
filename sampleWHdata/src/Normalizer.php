<?php
namespace Src;

class Normalizer
{
    private string $lastBaseLocation = '';
    private string $lastFullLocation = '';
    private string $lastBrandModelPrefix = '';

    public function __construct()
    {
    }

    public function normalizeRow(array $row): array
    {
        // Standardize keys case
        $normalizedRow = [];
        foreach ($row as $k => $v) {
            $normalizedRow[ucfirst(strtolower($k))] = $v;
        }
        $row = $normalizedRow;

        $row['Date'] = $row['Date'] ?? date('Y-m-d');
        $row['QTY'] = $row['Qty'] ?? $row['QTY'] ?? '1';
        $row['Item'] = $row['Item'] ?? '';
        $row['Serial'] = $row['Serial'] ?? '';
        $row['Notes'] = $row['Notes'] ?? '';

        $loc = isset($row['Location']) ? trim($row['Location']) : '';
        if (!empty($loc)) {
            if (preg_match('/^[-–—\/]?L(\d+)/i', $loc, $m)) {
                $level = 'L' . $m[1];
                if (!empty($this->lastBaseLocation)) {
                    $loc = $this->lastBaseLocation . '-' . $level;
                } else {
                    $loc = $level;
                }
            } else {
                // Parse Letter - Number - (Optional Level)
                if (preg_match('/^([A-Z])[-–—]?(\d+)(?:[-–—\/\s]?(L\d+))?/i', $loc, $matches)) {
                    $letter = strtoupper($matches[1]);
                    $num = $matches[2];
                    $level = !empty($matches[3]) ? strtoupper($matches[3]) : null;

                    if ($level !== null) {
                        $zone = $letter . $num;
                        $this->lastBaseLocation = $zone;
                        $loc = $zone . '-' . $level;
                    } else {
                        $loc = $letter . '-' . $num;
                        $this->lastBaseLocation = $letter . $num;
                    }
                } else {
                    $loc = strtoupper(str_replace(' ', '', $loc));
                }
            }
            $this->lastFullLocation = $loc;
        } else {
            if (!empty($this->lastFullLocation)) {
                $loc = $this->lastFullLocation;
            }
        }

        $row['Location'] = $loc;

        $item = trim($row['Item']);
        $serial = trim($row['Serial']);

        // Propagate brand/model prefix if missing (e.g. only "3380 6th-7th")
        if (!empty($item)) {
            if (preg_match('/^(Dell\s+(?:Latitude|Precision|Inspiron|XPS)|HP\s+(?:ProBook|EliteBook|ZBook|ProDesk|Pavilion|Envy|Notebook|Laptop)|Lenovo\s+(?:ThinkPad|Yoga|Ideapad)|Panasonic(?:\s+Toughbook)?|Asus|Acer|Getac)/i', $item, $matches)) {
                $this->lastBrandModelPrefix = $matches[1];
            } else {
                if (!empty($this->lastBrandModelPrefix) && !preg_match('/^(Dell|HP|Lenovo|Panasonic|Asus|Acer|Getac|Apple)/i', $item)) {
                    $item = $this->lastBrandModelPrefix . ' ' . $item;
                }
            }
        }

        // 1. If Serial is a CPU configuration or a model number, merge it into Item
        if (!empty($serial)) {
            $is_cpu_serial = false;
            if (preg_match('/^(i[3579]|ryzen|amd|celeron|pentium|xeon|core|dual[- ]*core|\d+th(?:[- \/]\d+th)?)$/i', $serial)) {
                $is_cpu_serial = true;
            } elseif (preg_match('/^(i|1|i5|i7|i9|15|17|19)?[- ]*(\d{1,2})(th)?$/i', $serial)) {
                $is_cpu_serial = true;
            } elseif (preg_match('/\b(i[3579]-\d+th|i[3579]\s+\d+th|\d+th\s+gen)\b/i', $serial)) {
                $is_cpu_serial = true;
            }

            if ($is_cpu_serial) {
                $item .= " " . $serial;
                $serial = '';
            } elseif (preg_match('/^(CF-?\d+|FZ-?\w+|A\d+|V\d+|B\d+|\d{3,})$/i', $serial)) {
                $item .= " " . $serial;
                $serial = '';
            }
        }

        // 2. Clean up Item name CPU and Suffix typos
        $item = preg_replace('/\b15([- ]\d+th?)\b/i', 'i5-$1', $item);
        $item = preg_replace('/\b17\b([- ]\d+th?)\b/i', 'i7-$1', $item);
        $item = preg_replace('/\b15\s+(\d+th?)\b/i', 'i5-$1', $item);
        $item = preg_replace('/\b17\s+(\d+th?)\b/i', 'i7-$1', $item);

        $item = preg_replace('/\b(i[3579]|core|gen|generation)[- ]*(\d{1,2})\b/i', '$1-$2th', $item);
        $item = preg_replace('/\b(\d{1,2})th[- ]*(\d{1,2})\b/i', '$1th-$2th', $item);
        $item = preg_replace('/\b(i[3579])[- ]+(\d{1,2}th)\b/i', '$1-$2', $item);
        $item = preg_replace('/(\d+)thth/i', '$1th', $item);
        $item = preg_replace_callback('/(\d+)(th|rd|nd|st)/i', function($m) { return $m[1] . strtolower($m[2]); }, $item);
        $item = preg_replace('/\b(\d+(?:th|rd|nd|st))\s+(gen|generation)\b/i', '$1', $item);
        $item = preg_replace('/\((i[3579]-\d+th)\)/i', '$1', $item);
        $item = preg_replace('/\((i[3579])\)/i', '$1', $item);
        $item = preg_replace('/\(([0-9]+th(?:-[0-9]+th)?)\)/i', '$1', $item);
        $item = preg_replace('/\b(i[3579])[- ]*(\d+th)\s+(\d+00\s+Series)\b/i', '$3 $1-$2', $item);
        $item = preg_replace('/\b(i[3579])[- ]*(\d+th)\s+(G\d+)\b/i', '$3 $1-$2', $item);
        $item = preg_replace('/\b(\d+)\s*\/+\s*(\d+)\b/', '$1/$2', $item);
        $item = preg_replace('/\b(i[3579]-\d+th)\s+(\d+\/\d+)\b/i', '$2 $1', $item);

        if (stripos($item, 'Panasonic') !== false && stripos($item, 'Toughbook') === false && stripos($item, 'Toughpad') === false) {
            if (preg_match('/(CF\-?[A-Z0-9]+|FZ\-?[A-Z0-9]+)/i', $item)) {
                $item = preg_replace('/Panasonic/i', 'Panasonic Toughbook', $item);
            }
        }

        // Title Case Standardization
        $words = explode(' ', $item);
        foreach ($words as &$word) {
            if (preg_match('/^[a-z0-9]+$/i', $word)) {
                if (preg_match('/^XPS(\d*)$/i', $word, $m)) {
                    $word = 'XPS' . $m[1];
                    continue;
                }
                if (in_array(strtoupper($word), ['HP', 'CF', 'FZ', 'GB', 'TB', 'SSD', 'HDD', 'PC', 'OS', 'UI', 'AI', 'S/N'])) {
                    $word = strtoupper($word);
                    continue;
                }
                if (preg_match('/^[A-Z]\d{2,3}[A-Z]$/i', $word)) {
                    $word = strtoupper($word);
                    continue;
                }
                if (strcasecmp($word, 'DELL') === 0) { $word = 'Dell'; continue; }
                if (strcasecmp($word, 'LATITUDE') === 0) { $word = 'Latitude'; continue; }
                if (strcasecmp($word, 'PRECISION') === 0) { $word = 'Precision'; continue; }
                if (strcasecmp($word, 'INSPIRON') === 0) { $word = 'Inspiron'; continue; }
                if (strcasecmp($word, 'GETAC') === 0) { $word = 'Getac'; continue; }
                if (strcasecmp($word, 'PANASONIC') === 0) { $word = 'Panasonic'; continue; }
                if (strcasecmp($word, 'ELITEBOOK') === 0) { $word = 'EliteBook'; continue; }
                if (strcasecmp($word, 'PROBOOK') === 0) { $word = 'ProBook'; continue; }
                if (strcasecmp($word, 'THINKPAD') === 0) { $word = 'ThinkPad'; continue; }
                if (strcasecmp($word, 'YOGA') === 0) { $word = 'Yoga'; continue; }
                if (strcasecmp($word, 'PRODESK') === 0) { $word = 'ProDesk'; continue; }

                if (preg_match('/[a-zA-Z]/', $word)) {
                    $word = ucfirst(strtolower($word));
                }
            }
        }
        $item = implode(' ', $words);

        $row['Item'] = $item;
        $row['Serial'] = $serial;

        $row['Confidence'] = 98;
        return $row;
    }
}
