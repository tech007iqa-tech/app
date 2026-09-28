<?php
// core/UI.php
/**
 * IQA Metal Store - UI Component & Template Helper Library
 * Clean, lightweight utility methods for store views.
 */

class UI {
    /**
     * Escape HTML output safely
     */
    public static function escape($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Shorthand for HTML escaping
     */
    public static function h($string) {
        return self::escape($string);
    }

    /**
     * Format currency amount ($XX.XX)
     */
    public static function formatPrice($amount) {
        return '$' . number_format((float)$amount, 2);
    }

    /**
     * Render an origin badge (Warehouse Shelf vs Store Custom)
     */
    public static function originBadge($locationCode, $isWarehouse = true) {
        if ($isWarehouse && !empty($locationCode)) {
            return '<span class="card-origin-badge" title="Physical Shelf">📦 ' . self::escape($locationCode) . '</span>';
        }
        return '<span class="card-origin-badge custom" title="Custom Item">🏪 Custom</span>';
    }

    /**
     * Render a sector badge
     */
    public static function sectorBadge($sector) {
        return '<span class="wh-sec-badge">' . self::escape($sector) . '</span>';
    }

    /**
     * Intelligently parses raw specs (JSON, structured markdown/HTML blocks, or text)
     * into a clean, modern data structure for cards and detail modals.
     */
    public static function parseDetails($raw) {
        $raw = trim((string)$raw);
        if (empty($raw)) {
            return [
                'summary' => 'Certified hardware clearance unit.',
                'chips' => [],
                'specs' => [],
                'condition' => [],
                'accessories' => '',
                'terms' => 'Sold strictly as-is under IQA Metal terms of sale.',
                'has_details' => false
            ];
        }

        // 1. Check if valid JSON
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $specs = [];
            $chips = [];
            $condition = [];
            $notes = $decoded['notes'] ?? '';

            foreach ($decoded as $k => $v) {
                if ($v === null || $v === '') continue;
                $val = is_scalar($v) ? trim((string)$v) : json_encode($v);
                if (empty($val)) continue;

                $kLower = strtolower($k);
                if ($val === 'No' || $val === 'None') {
                    if (in_array($kLower, ['ram', 'storage', 'battery', 'gpu'])) {
                        $specs[ucfirst($k)] = 'None / Not installed';
                    }
                    continue;
                }

                $label = ucfirst($k);
                if ($kLower === 'cpu') $label = 'Processor';
                if ($kLower === 'gpu') $label = 'Graphics';
                if ($kLower === 'ram') $label = 'Memory (RAM)';
                if ($kLower === 'storage') $label = 'Storage Drive';
                if ($kLower === 'condition') {
                    $condition['Overall Condition'] = $val;
                    $chips[] = ['icon' => '🏷️', 'text' => $val];
                    continue;
                }

                $specs[$label] = $val;

                if ($kLower === 'cpu' || $kLower === 'processor') {
                    $chips[] = ['icon' => '⚡', 'text' => $val];
                } elseif ($kLower === 'ram' || $kLower === 'memory') {
                    $chips[] = ['icon' => '💾', 'text' => $val];
                } elseif ($kLower === 'storage' || $kLower === 'ssd' || $kLower === 'hdd') {
                    $chips[] = ['icon' => '💽', 'text' => $val];
                } elseif ($kLower === 'series') {
                    $chips[] = ['icon' => '📌', 'text' => $val];
                }
            }

            $summary = !empty($notes) ? $notes : 'Certified warehouse inventory unit ready for dispatch.';

            return [
                'summary' => $summary,
                'chips' => array_slice($chips, 0, 4),
                'specs' => $specs,
                'condition' => $condition,
                'accessories' => '',
                'terms' => 'Sold strictly as-is for parts, repair, or tech projects. Complies with IQA Metal Terms of Sale.',
                'has_details' => (count($specs) + count($condition) > 0)
            ];
        }

        // 2. Structured text / eBay template parsing (e.g. HP EliteBook)
        $clean = strip_tags($raw);
        $lines = array_values(array_filter(array_map('trim', explode("\n", str_replace(["\r\n", "\r"], "\n", $clean))), 'strlen'));

        $sections = ['general' => []];
        $currentSec = 'general';
        $knownHeaders = [
            'description' => 'Description',
            'system specifications' => 'Specifications',
            'specifications' => 'Specifications',
            'condition & testing notes' => 'Condition',
            'condition notes' => 'Condition',
            'testing notes' => 'Condition',
            'included accessories' => 'Accessories',
            'accessories' => 'Accessories',
            'terms of sale' => 'Terms',
            'terms' => 'Terms'
        ];

        foreach ($lines as $line) {
            $lower = strtolower(rtrim($line, ':'));
            if (isset($knownHeaders[$lower])) {
                $currentSec = $knownHeaders[$lower];
                $sections[$currentSec] = [];
                continue;
            }
            $sections[$currentSec][] = $line;
        }

        // Extract key-value specifications and condition notes
        $specs = [];
        $condition = [];
        $accessories = '';
        $terms = '';

        foreach ($sections as $secName => $secLines) {
            foreach ($secLines as $line) {
                if (strpos($line, ':') !== false) {
                    list($k, $v) = explode(':', $line, 2);
                    $k = trim($k);
                    $v = trim($v);
                    if (strlen($k) <= 35 && !empty($v)) {
                        if ($secName === 'Condition' || stripos($k, 'condition') !== false || stripos($k, 'power') !== false || stripos($k, 'cosmetics') !== false || stripos($k, 'boot') !== false) {
                            $condition[$k] = $v;
                        } else {
                            $specs[$k] = $v;
                        }
                    }
                }
            }
        }

        if (!empty($sections['Accessories'])) {
            $accessories = implode(' ', $sections['Accessories']);
        }
        if (!empty($sections['Terms'])) {
            $terms = implode(' ', $sections['Terms']);
        }

        // Summary extraction
        $summaryLines = !empty($sections['Description']) ? $sections['Description'] : (!empty($sections['general']) ? $sections['general'] : []);
        $summary = implode(' ', array_slice($summaryLines, 0, 2));

        // Strip redundant leading model title from summary if repeated
        if (preg_match('/^(?:HP|Dell|Lenovo|Apple|Asus|Acer|Microsoft)[^\.]*\.\s*(.*)/i', $summary, $m)) {
            if (!empty($m[1])) {
                $summary = $m[1];
            }
        }
        if (empty($summary)) {
            $summary = 'Detailed hardware specification and condition report available below.';
        }

        // Generate smart chips
        $chips = [];
        foreach ($specs as $k => $v) {
            $kLower = strtolower($k);
            if (strpos($kLower, 'processor') !== false || strpos($kLower, 'cpu') !== false) {
                $chips[] = ['icon' => '⚡', 'text' => $v];
            } elseif (strpos($kLower, 'ram') !== false || strpos($kLower, 'memory') !== false) {
                $chips[] = ['icon' => '💾', 'text' => (stripos($v, 'none') !== false ? 'No RAM' : $v)];
            } elseif (strpos($kLower, 'storage') !== false || strpos($kLower, 'drive') !== false || strpos($kLower, 'ssd') !== false || strpos($kLower, 'hdd') !== false) {
                $chips[] = ['icon' => '💽', 'text' => (stripos($v, 'none') !== false ? 'No Drive' : $v)];
            }
        }

        foreach ($condition as $k => $v) {
            $kLower = strtolower($k);
            if (strpos($kLower, 'overall') !== false || strpos($kLower, 'condition') !== false) {
                $chips[] = ['icon' => '🏷️', 'text' => $v];
            }
        }

        // If no chips were found, fallback to regex keywords
        if (empty($chips)) {
            if (preg_match('/(i[3579][\s\-]?\w+|\bM[123]\b|\bRyzen\s?\d\b)/i', $raw, $m)) {
                $chips[] = ['icon' => '⚡', 'text' => $m[1]];
            }
            if (preg_match('/(\d+\s?GB\s?RAM)/i', $raw, $m)) {
                $chips[] = ['icon' => '💾', 'text' => $m[1]];
            }
            if (preg_match('/(\d+(?:GB|TB)\s?(?:SSD|NVMe|HDD))/i', $raw, $m)) {
                $chips[] = ['icon' => '💽', 'text' => $m[1]];
            }
        }

        return [
            'summary' => $summary,
            'chips' => array_slice($chips, 0, 4),
            'specs' => $specs,
            'condition' => $condition,
            'accessories' => $accessories,
            'terms' => !empty($terms) ? $terms : 'Sold strictly as-is for parts, repair, or tech projects. Complies with IQA Metal Terms of Sale.',
            'has_details' => (count($specs) + count($condition) > 0)
        ];
    }
}