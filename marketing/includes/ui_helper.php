<?php
/**
 * UI Helper Functions for Marketing Hub
 * Standardizes components across all modules.
 */

class UI {

    /**
     * Renders a standard dashboard stat card
     */
    public static function stat_card($title, $value, $class = '') {
        return "
        <section class='card {$class}'>
            <h2>{$title}</h2>
            <div class='stat'>{$value}</div>
        </section>";
    }

    /**
     * Renders a "Smart Opportunity" card
     */
    public static function opportunity_card($title, $desc, $action_url, $btn_text, $type = 'info') {
        $accent_color = 'var(--accent-primary)';
        if ($type === 'NEED_PHOTO') $accent_color = '#f59e0b'; // Amber
        if ($type === 'READY') $accent_color = '#10b981'; // Emerald

        return "
        <div class='opp-card' style='background: white; padding: 1.25rem; border-radius: 12px; border: 1px solid var(--border-color); display: flex; flex-direction: column; justify-content: space-between;'>
            <div>
                <h3 style='font-size: 0.85rem; text-transform: uppercase; margin-bottom: 0.5rem; color: {$accent_color};'>{$title}</h3>
                <p style='font-size: 0.95rem; margin-bottom: 1.25rem; line-height: 1.4;'>{$desc}</p>
            </div>
            <a href='{$action_url}' class='btn-small' style='text-align: center; background: {$accent_color}; color: white; border: none;'>{$btn_text}</a>
        </div>";
    }

    /**
     * Renders a badge
     */
    public static function badge($text, $type = 'default') {
        $class = 'badge-' . strtolower($type);
        // Fallback for types not explicitly in CSS yet
        $style = "";
        if ($type === 'new') $class = 'badge-new';
        if ($type === 'customer') $class = 'badge-customer';

        return "<span class='{$class}' style='{$style}'>{$text}</span>";
    }

    /**
     * Renders a standard action button
     */
    public static function action_button($text, $url, $icon = '', $style = '') {
        $icon_html = $icon ? "<span>{$icon}</span>" : "";
        return "
        <a href='{$url}' class='btn-action' style='text-decoration: none; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px; {$style}'>
            {$icon_html} {$text}
        </a>";
    }

    /**
     * Renders a table row for the activity feed
     */
    public static function activity_item($icon, $title, $subtitle) {
        return "
        <div style='display: flex; gap: 1rem; align-items: flex-start; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);'>
            <div style='font-size: 1.2rem;'>{$icon}</div>
            <div>
                <div style='font-weight: 700; font-size: 0.9rem;'>".htmlspecialchars($title)."</div>
                <div style='font-size: 0.75rem; color: var(--text-secondary); margin-top: 2px;'>
                    {$subtitle}
                </div>
            </div>
        </div>";
    }

    /**
     * Smart formats specifications (handles pasted Google Sheets data)
     */
    public static function format_specs($text) {
        if (empty($text)) return "<span style='color: var(--text-secondary); italic;'>No specs defined.</span>";

        // Detect if it's tab-separated (spreadsheet paste)
        if (strpos($text, "\t") !== false) {
            $lines = explode("\n", str_replace("\r", "", trim($text)));
            $html = "<div class='spec-table' style='display: flex; flex-direction: column; gap: 4px;'>";

            $isFirst = true;
            $gridTemplate = "";
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $parts = explode("\t", $line);

                if ($isFirst) {
                    $colCount = count($parts);
                    $gridTemplate = "grid-template-columns: repeat($colCount, 1fr);";

                    // Header Row
                    $html .= "<div style='display: grid; {$gridTemplate} gap: 10px; font-size: 0.7rem; text-transform: uppercase; font-weight: 800; color: var(--accent-primary); border-bottom: 2px solid var(--accent-tertiary); padding-bottom: 4px; margin-bottom: 4px; align-items: end;'>";
                    foreach ($parts as $part) {
                        $cleanPart = htmlspecialchars(trim($part));
                        // Specific formatting for 2-in-1 header
                        if (stripos($cleanPart, '2-in-1/x360') !== false) {
                            $cleanPart = str_ireplace('/', '<br />', $cleanPart);
                        }
                        $html .= "<span>" . $cleanPart . "</span>";
                    }
                    $html .= "</div>";
                    $isFirst = false;
                    continue;
                }

                // Data Row
                $html .= "<div style='display: grid; {$gridTemplate} gap: 10px; font-size: 0.8rem; border-bottom: 1px dashed #f1f5f9; padding: 4px 0;'>";
                foreach ($parts as $part) {
                    $html .= "<span style='color: var(--text-dim); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;'>" . htmlspecialchars(trim($part)) . "</span>";
                }
                $html .= "</div>";
            }
            $html .= "</div>";
            return $html;
        }

        // Fallback: simple line breaks
        return nl2br(htmlspecialchars($text));
    }

    /**
     * Multibyte-safe str_pad for visual alignment in monospace fonts
     */
    private static function mb_str_pad($input, $pad_length, $pad_string = " ", $pad_type = STR_PAD_RIGHT) {
        $diff = $pad_length - mb_strwidth($input);
        if ($diff <= 0) return $input;
        $left_pad = $right_pad = 0;
        if ($pad_type == STR_PAD_RIGHT) {
            $right_pad = $diff;
        } else if ($pad_type == STR_PAD_LEFT) {
            $left_pad = $diff;
        } else {
            $left_pad = floor($diff / 2);
            $right_pad = $diff - $left_pad;
        }
        return str_repeat($pad_string, $left_pad) . $input . str_repeat($pad_string, $right_pad);
    }

    /**
     * Smart formats specifications for plain text (handles pasted Google Sheets data)
     */
    public static function format_specs_plain($text) {
        if (empty($text)) return "No specs defined.";

        // Detect if it's tab-separated (spreadsheet paste)
        if (strpos($text, "\t") !== false) {
            $lines = array_filter(explode("\n", str_replace("\r", "", trim($text))));
            if (empty($lines)) return "";

            // First Pass: Calculate max VISUAL width for EVERY column
            $colMaxLens = [];
            foreach ($lines as $line) {
                $parts = explode("\t", $line);
                foreach ($parts as $i => $part) {
                    $len = mb_strwidth(trim($part));
                    if ($i === 0) $len += 1; // Account for the colon
                    if (!isset($colMaxLens[$i]) || $len > $colMaxLens[$i]) {
                        $colMaxLens[$i] = $len;
                    }
                }
            }

            // Second Pass: Build formatted string with perfect grid alignment
            $formatted = "";
            $isFirst = true;
            foreach ($lines as $line) {
                $parts = explode("\t", $line);
                $rowStr = "";

                if ($isFirst) {
                    // Header Logic: Handle potential multi-line headers (e.g. 2-in-1/x360)
                    $formatted .= "📋 ";
                    $headerLines = [[], []];
                    $hasMultiLine = false;

                    foreach ($parts as $i => $part) {
                        $val = strtoupper(trim($part));
                        if (strpos($val, '/') !== false) {
                            $subParts = explode('/', $val);
                            $headerLines[0][$i] = $subParts[0];
                            $headerLines[1][$i] = $subParts[1];
                            $hasMultiLine = true;
                        } else {
                            $headerLines[0][$i] = $val;
                            $headerLines[1][$i] = "";
                        }
                    }

                    // Render First Header Line
                    $rowStr1 = "";
                    foreach ($headerLines[0] as $i => $val) {
                        $isLast = ($i === count($colMaxLens) - 1);
                        if (!$isLast) {
                            $rowStr1 .= self::mb_str_pad($val, $colMaxLens[$i] + 3);
                        } else {
                            $rowStr1 .= self::mb_str_pad($val, $colMaxLens[$i], " ", STR_PAD_LEFT);
                        }
                    }
                    $formatted .= $rowStr1 . "\n";

                    // Render Second Header Line (if needed)
                    if ($hasMultiLine) {
                        $formatted .= "   "; // Offset for the emoji
                        $rowStr2 = "";
                        foreach ($headerLines[1] as $i => $val) {
                            $isLast = ($i === count($colMaxLens) - 1);
                            if (!$isLast) {
                                $rowStr2 .= self::mb_str_pad($val, $colMaxLens[$i] + 3);
                            } else {
                                $rowStr2 .= self::mb_str_pad($val, $colMaxLens[$i], " ", STR_PAD_LEFT);
                            }
                        }
                        $formatted .= $rowStr2 . "\n";
                    }

                    $formatted .= str_repeat("━", mb_strwidth("📋 " . $rowStr1)) . "\n";
                    $isFirst = false;
                    continue;
                }

                // Data Row Logic
                $formatted .= "✅ ";
                $rowStr = "";
                for ($i = 0; $i < count($colMaxLens); $i++) {
                    $val = isset($parts[$i]) ? trim($parts[$i]) : "";
                    if ($i === 0) $val .= ":"; // Add colon to first column (Brand)

                    $isLast = ($i === count($colMaxLens) - 1);
                    if (!$isLast) {
                        // Regular columns are left-aligned
                        $rowStr .= self::mb_str_pad($val, $colMaxLens[$i] + 3);
                    } else {
                        // Right-align the last column (usually QTY) for better number comparison
                        $rowStr .= self::mb_str_pad($val, $colMaxLens[$i], " ", STR_PAD_LEFT);
                    }
                }
                $formatted .= $rowStr . "\n";
            }
            return trim($formatted);
        }

        return trim($text);
    }
}
?>
