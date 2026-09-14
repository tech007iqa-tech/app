<?php
// orders/core/NormalizerWorkOrder.php
require_once __DIR__ . '/database.php';

class NormalizerWorkOrder
{
    /**
     * Deduce the Brand based on Model or Series name.
     */
    public static function deduceBrand(string $model, string $series): string
    {
        $text = strtolower($model . ' ' . $series);
        if (strpos($text, 'elitebook') !== false || strpos($text, 'probook') !== false || strpos($text, 'pavilion') !== false || strpos($text, 'zbook') !== false || strpos($text, 'omen') !== false || strpos($text, 'victus') !== false || strpos($text, 'hp') !== false) {
            return 'HP';
        }
        if (strpos($text, 'latitude') !== false || strpos($text, 'inspiron') !== false || strpos($text, 'precision') !== false || strpos($text, 'xps') !== false || strpos($text, 'alienware') !== false || strpos($text, 'dell') !== false || strpos($text, 'latitue') !== false) {
            return 'Dell';
        }
        if (strpos($text, 'thinkpad') !== false || strpos($text, 'ideapad') !== false || strpos($text, 'yoga') !== false || strpos($text, 'legion') !== false || strpos($text, 'lenovo') !== false) {
            return 'Lenovo';
        }
        if (strpos($text, 'macbook') !== false || strpos($text, 'apple') !== false || strpos($text, 'ipad') !== false) {
            return 'Apple';
        }
        if (strpos($text, 'toughbook') !== false || strpos($text, 'panasonic') !== false || strpos($text, 'cf-') !== false) {
            return 'Panasonic';
        }
        if (strpos($text, 'getac') !== false) {
            return 'Getac';
        }
        if (strpos($text, 'acer') !== false || strpos($text, 'predator') !== false || strpos($text, 'aspire') !== false || strpos($text, 'nitro') !== false) {
            return 'Acer';
        }
        if (strpos($text, 'asus') !== false || strpos($text, 'tuf') !== false || strpos($text, 'rog') !== false || strpos($text, 'zephyrus') !== false || strpos($text, 'vivobook') !== false || strpos($text, 'zenbook') !== false) {
            return 'Asus';
        }
        if (strpos($text, 'msi') !== false || strpos($text, 'stealth') !== false || strpos($text, 'katana') !== false || strpos($text, 'gf65') !== false || strpos($text, 'thin') !== false) {
            return 'MSI';
        }
        if (strpos($text, 'razer') !== false || strpos($text, 'blade') !== false) {
            return 'Razer';
        }
        if (strpos($text, 'microsoft') !== false || strpos($text, 'surface') !== false) {
            return 'Microsoft';
        }
        return 'Generic';
    }

    /**
     * Smart parse RAM and Storage from Note/Description.
     */
    public static function parseRamStorage(string $desc, string $note): array
    {
        // Extract all integers from note & desc
        preg_match_all('/\d+/', $note . ' ' . $desc, $matches);
        $numbers = array_map('intval', $matches[0]);

        $ram = null;
        $storage = null;

        if (count($numbers) >= 2) {
            // Take the first two numbers
            $num1 = $numbers[0];
            $num2 = $numbers[1];

            if ($num1 >= 128) $storage = $num1;
            elseif ($num1 < 64) $ram = $num1;

            if ($num2 >= 128) $storage = $num2;
            elseif ($num2 < 64) $ram = $num2;

            // Fallback matching if one is still unassigned
            if ($ram === null && $storage !== null) {
                $ram = ($num1 === $storage) ? $num2 : $num1;
            } elseif ($storage === null && $ram !== null) {
                $storage = ($num1 === $ram) ? $num2 : $num1;
            } elseif ($ram === null && $storage === null) {
                $ram = min($num1, $num2);
                $storage = max($num1, $num2);
            }
        } elseif (count($numbers) === 1) {
            $num = $numbers[0];
            if ($num >= 128) {
                $storage = $num;
            } elseif ($num < 64) {
                $ram = $num;
            } else {
                // intermediate 64-127 values default to storage
                $storage = $num;
            }
        }

        return ['ram' => $ram, 'storage' => $storage];
    }

    /**
     * Helper to clean up any RAM/storage size strings to avoid double formatting.
     */
    public static function cleanSpecsString(string $text): string
    {
        // 1. Remove paired formats like 8/512, 16/256, 8 / 256
        $text = preg_replace('/\b\d+\s*\/+\s*\d+\s*(?:gb|tb|mb)?\b/i', '', $text);

        // 2. Remove formats like 8gb, 16gb
        $text = preg_replace('/\b\d+\s*gb\b/i', '', $text);

        // 3. Remove trailing slashes like 8/, 16/
        $text = preg_replace('/\b\d+\s*\/+/i', '', $text);

        // 4. Remove leading slashes like /128, /256
        $text = preg_replace('/\/+\s*\d+\s*(?:gb|tb|mb)?\b/i', '', $text);

        // Clean up double spaces, double pipes, leading/trailing pipes
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        return $text;
    }

    /**
     * Standard CPU category classifier (matching get_cpu_pricing_details.php)
     */
    public static function categorizeCpu(string $cpuStr): string
    {
        $cpu = strtolower(trim($cpuStr));
        if (empty($cpu) || $cpu === '—' || $cpu === '-' || $cpu === 'em dash') {
            return 'Apple';
        }

        if (strpos($cpu, 'apple') !== false || strpos($cpu, 'm1') !== false || strpos($cpu, 'm2') !== false || strpos($cpu, 'm3') !== false || strpos($cpu, 'm4') !== false || strpos($cpu, 'silicon') !== false) {
            return 'Apple';
        }

        if (strpos($cpu, 'ryzen') !== false || strpos($cpu, 'amd') !== false) {
            return 'Ryzen';
        }

        if (strpos($cpu, 'core 2') !== false || strpos($cpu, 'core2') !== false || strpos($cpu, 'duo') !== false) {
            return 'Core 2 Duo';
        }

        $is2nd3rd = (strpos($cpu, '2nd') !== false || strpos($cpu, '3rd') !== false);
        $is4th5th = (strpos($cpu, '4th') !== false || strpos($cpu, '5th') !== false);
        $is6th7th = (strpos($cpu, '6th') !== false || strpos($cpu, '7th') !== false);

        if ($is2nd3rd) return '2nd & 3rd Gen';
        if ($is4th5th) return '4th & 5th Gen';
        if ($is6th7th) return '6th & 7th Gen';

        $gens = ['8th', '9th', '10th', '11th', '12th', '13th', '14th'];
        foreach ($gens as $gen) {
            if (strpos($cpu, strtolower($gen)) !== false) {
                if (strpos($cpu, 'i3') !== false) return "$gen Gen i3";
                if (strpos($cpu, 'i5') !== false) return "$gen Gen i5";
                if (strpos($cpu, 'i7') !== false || strpos($cpu, 'i9') !== false) return "$gen Gen i7";
                return "$gen Gen i5";
            }
        }

        if (preg_match('/i(3|5|7|9)-(\d{1,2})\d{3}/', $cpu, $matches)) {
            $tier = 'i' . ($matches[1] == '9' ? '7' : $matches[1]);
            $num = intval($matches[2]);
            if ($num >= 8 && $num <= 14) {
                return $num . 'th Gen ' . $tier;
            }
        }

        if (strpos($cpu, 'i3') !== false) return '8th Gen i3';
        if (strpos($cpu, 'i5') !== false) return '8th Gen i5';
        if (strpos($cpu, 'i7') !== false || strpos($cpu, 'i9') !== false) return '8th Gen i7';

        return 'Other';
    }

    /**
     * Checks database for average price in this CPU category, or returns default mock averages.
     */
    public static function mapCpuToPricingGen(string $cpu): string
    {
        $cpuLower = strtolower($cpu);
        if (strpos($cpuLower, '4th') !== false || strpos($cpuLower, '5th') !== false) {
            return '4th-5th';
        }
        if (strpos($cpuLower, '6th') !== false || strpos($cpuLower, '7th') !== false) {
            return '6th-7th';
        }

        $gen = 8;
        if (preg_match('/(\d+)th/i', $cpu, $matches)) {
            $gen = (int)$matches[1];
        }
        $tier = 'i5';
        if (strpos($cpuLower, 'i7') !== false || strpos($cpuLower, 'i9') !== false) {
            $tier = 'i7';
        }
        if (strpos($cpuLower, 'i3') !== false) {
            $tier = 'i3';
        }

        $gen = max(8, min(12, $gen));
        return $tier . '-' . $gen . 'th';
    }

    /**
     * Look up base price and upgrades in pricing matrix of warehouse.db (Untested grade)
     */
    public static function getSuggestedPrice(string $cpuGen, ?int $ram, ?int $storage, string $brand, string $model, string $desc, string $note): float
    {
        $basePrice = 0.0;
        $ramPrice = 0.0;
        $storagePrice = 0.0;

        try {
            $db_wh = Database::warehouse();

            // 1. Identify Category and if it is a parts machine
            $category = 'Regular';
            $normalized_brand = strtolower($brand);
            $normalized_model = strtolower($model);
            $normalized_desc = strtolower($desc);
            $normalized_note = strtolower($note);
            $combined = $normalized_brand . ' ' . $normalized_model . ' ' . $normalized_desc . ' ' . $normalized_note;
            $is_parts = (strpos($combined, 'parts') !== false || strpos($combined, 'part') !== false);

            if (strpos($normalized_brand, 'apple') !== false || strpos($combined, 'macbook') !== false) {
                $category = 'Apple';
            } elseif (strpos($normalized_brand, 'microsoft') !== false || strpos($combined, 'surface') !== false) {
                $category = 'Microsoft';
            } elseif (strpos($combined, 'chromebook') !== false) {
                $category = 'Chromebook';
            }

            // 2. Lookup Base Price (using Untested or Parts grade)
            if ($category === 'Regular') {
                $genKey = self::mapCpuToPricingGen($cpuGen);
                if (!empty($genKey)) {
                    $grade = $is_parts ? 'Parts' : 'Untested';
                    $stmt = $db_wh->prepare("SELECT price FROM pricing_rules WHERE category = 'Regular' AND cpu_gen = ? AND grade = ?");
                    $stmt->execute([$genKey, $grade]);
                    $dbVal = $stmt->fetchColumn();
                    if ($dbVal !== false) {
                        $basePrice = (float)$dbVal;
                    }
                }
            } elseif ($category === 'Apple') {
                $appleModel = 'A1466'; // Default fallback
                if (preg_match('/(A\d{4})/i', $combined, $m)) {
                    $appleModel = strtoupper($m[1]);
                }
                $grade = $is_parts ? 'For Parts' : 'Untested';
                $stmt = $db_wh->prepare("SELECT price FROM pricing_rules WHERE category = 'Apple' AND cpu_gen = ? AND grade = ?");
                $stmt->execute([$appleModel, $grade]);
                $dbVal = $stmt->fetchColumn();
                if ($dbVal !== false) {
                    $basePrice = (float)$dbVal;
                }
            } elseif ($category === 'Chromebook') {
                $grade = 'Untested Lot';
                $stmt = $db_wh->prepare("SELECT price FROM pricing_rules WHERE category = 'Chromebook' AND cpu_gen = 'Dell Chromebook 3180 / HP G5 EE' AND grade = ?");
                $stmt->execute([$grade]);
                $dbVal = $stmt->fetchColumn();
                if ($dbVal !== false) {
                    $basePrice = (float)$dbVal;
                }
            } elseif ($category === 'Microsoft') {
                $grade = $is_parts ? 'For Parts' : 'Untested';
                $stmt = $db_wh->prepare("SELECT price FROM pricing_rules WHERE category = 'Microsoft' AND cpu_gen = 'Surface Pro 5 (1796)' AND grade = ?");
                $stmt->execute([$grade]);
                $dbVal = $stmt->fetchColumn();
                if ($dbVal !== false) {
                    $basePrice = (float)$dbVal;
                }
            }

            // Fallbacks (using Untested/Parts as baseline) if lookup returns 0 or fails
            if ($basePrice === 0.0) {
                if ($is_parts) {
                    $mock_prices = [
                        'i5-8th' => 50.00,
                        'i7-8th' => 60.00,
                        'i5-9th' => 75.00,
                        'i7-9th' => 80.00,
                        'i5-10th' => 85.00,
                        'i7-10th' => 95.00,
                        'i5-11th' => 105.00,
                        'i7-11th' => 115.00,
                        'i5-12th' => 125.00,
                        'i7-12th' => 135.00,
                        '4th-5th' => 30.00,
                        '6th-7th' => 45.00
                    ];
                } else {
                    $mock_prices = [
                        'i5-8th' => 60.00,
                        'i7-8th' => 65.00,
                        'i5-9th' => 85.00,
                        'i7-9th' => 90.00,
                        'i5-10th' => 95.00,
                        'i7-10th' => 105.00,
                        'i5-11th' => 115.00,
                        'i7-11th' => 125.00,
                        'i5-12th' => 135.00,
                        'i7-12th' => 145.00,
                        '4th-5th' => 35.00,
                        '6th-7th' => 55.00
                    ];
                }

                if ($category === 'Regular') {
                    $genKey = self::mapCpuToPricingGen($cpuGen);
                    $basePrice = $mock_prices[$genKey] ?? 0.00;
                } elseif ($category === 'Apple') {
                    $basePrice = $is_parts ? 60.00 : 100.00;
                } elseif ($category === 'Chromebook') {
                    $basePrice = 30.00;
                } else {
                    $basePrice = 0.00;
                }
            }

            // 3. Lookup RAM Price (using Untested grade)
            if ($ram !== null && $ram > 0) {
                $ramType = 'DDR4';
                if (strpos(strtolower($cpuGen), '4th') !== false || strpos(strtolower($cpuGen), '5th') !== false) {
                    $ramType = 'DDR3';
                }
                $ramQuery = $ram . 'GB ' . $ramType;
                $stmt = $db_wh->prepare("SELECT price FROM pricing_rules WHERE category = 'RAM' AND cpu_gen = ? AND grade = 'Untested'");
                $stmt->execute([$ramQuery]);
                $dbVal = $stmt->fetchColumn();
                if ($dbVal !== false) {
                    $ramPrice = (float)$dbVal;
                } else {
                    if ($ram === 8) $ramPrice = ($ramType === 'DDR3') ? 2.0 : 3.5;
                    elseif ($ram === 16) $ramPrice = ($ramType === 'DDR3') ? 6.0 : 9.5;
                    elseif ($ram === 32) $ramPrice = ($ramType === 'DDR3') ? 12.0 : 22.0;
                    elseif ($ram === 4) $ramPrice = ($ramType === 'DDR3') ? 0.25 : 0.5;
                }
            }

            // 4. Lookup Storage Price (using Untested grade)
            if ($storage !== null && $storage > 0) {
                $storageQuery = '';
                if ($storage >= 1000) {
                    $storageQuery = round($storage / 1024) . 'TB M.2';
                } elseif ($storage <= 2) {
                    $storageQuery = $storage . 'TB M.2';
                } else {
                    $storageQuery = $storage . 'GB M.2';
                }

                $stmt = $db_wh->prepare("SELECT price FROM pricing_rules WHERE category = 'Storage' AND cpu_gen = ? AND grade = 'Untested'");
                $stmt->execute([$storageQuery]);
                $dbVal = $stmt->fetchColumn();
                if ($dbVal !== false) {
                    $storagePrice = (float)$dbVal;
                } else {
                    if ($storage === 128) $storagePrice = 10.0;
                    elseif ($storage === 256) $storagePrice = 16.0;
                    elseif ($storage === 512) $storagePrice = 26.0;
                    elseif ($storage === 1024 || $storage === 1) $storagePrice = 50.0;
                    elseif ($storage === 2048 || $storage == 2) $storagePrice = 100.0;
                }
            }

        } catch (Exception $e) {
            // Fail silently
        }

        $totalPrice = $basePrice + $ramPrice + $storagePrice;
        return round($totalPrice, 2);
    }

    private static function isCategoryMatch(string $itemCategory, string $requestedCategory): bool
    {
        if (strtolower($itemCategory) === strtolower($requestedCategory)) {
            return true;
        }

        // Handle 8th Gen+ i5 / i7 matches
        if ($requestedCategory === '8th Gen+ i5') {
            return (bool)preg_match('/^(8th|9th|10th|11th|12th|13th|14th) Gen i5$/i', $itemCategory);
        }
        if ($requestedCategory === '8th Gen+ i7') {
            return (bool)preg_match('/^(8th|9th|10th|11th|12th|13th|14th) Gen i7$/i', $itemCategory);
        }

        return false;
    }

    /**
     * Normalize a raw OCR row extracted by Gemini.
     */
    public function normalizeRow(array $row): array
    {
        // Standardize keys
        $normalizedKeys = [];
        foreach ($row as $k => $v) {
            $normalizedKeys[strtolower(str_replace('_', '', $k))] = $v;
        }

        $model = trim($normalizedKeys['model'] ?? '');
        $series = trim($normalizedKeys['series'] ?? '');
        $cpuGen = trim($normalizedKeys['cpugen'] ?? '');
        $desc = trim($normalizedKeys['description'] ?? '');
        $qty = (int)($normalizedKeys['qty'] ?? 1);
        $note = trim($normalizedKeys['note'] ?? '');

        // 1. Deduce Brand
        $brand = self::deduceBrand($model, $series);

        // 2. Clean Spelling of Model
        $modelLower = strtolower($model);
        if ($modelLower === 'latitue') $model = 'Latitude';
        elseif ($modelLower === 'pavillion') $model = 'Pavilion';
        elseif ($modelLower === 'zbook') $model = 'ZBook';
        elseif ($modelLower === 'elitebook') $model = 'EliteBook';
        elseif ($modelLower === 'probook') $model = 'ProBook';
        elseif ($modelLower === 'thinkpad') $model = 'ThinkPad';

        // 3. Clean CPU Gen
        if (is_numeric($cpuGen)) {
            $cpuGen = $cpuGen . 'th';
        }
        if (preg_match('/^\d+th$/i', $cpuGen)) {
            $cpuGen = $cpuGen . ' Gen';
        }

        // 4. Parse RAM/Storage & construct clean description
        $specs = self::parseRamStorage($desc, $note);

        // Strip out existing RAM/storage abbreviations from note/description to avoid redundancy
        $cleanDesc = self::cleanSpecsString($desc);
        $cleanNote = self::cleanSpecsString($note);

        $descParts = [];
        if (!empty($cleanDesc) && strtolower($cleanDesc) !== 'untested' && strtolower($cleanDesc) !== 'parts') {
            $descParts[] = $cleanDesc;
        } else {
            $descParts[] = $desc; // keep original like "Untested" or "Parts"
        }

        if ($specs['ram']) {
            $descParts[] = $specs['ram'] . "GB RAM";
        }
        if ($specs['storage']) {
            $descParts[] = $specs['storage'] . "GB SSD";
        }

        if (!empty($cleanNote) && strtolower($cleanNote) !== 'untested' && strtolower($cleanNote) !== 'parts') {
            $descParts[] = $cleanNote;
        }

        $finalDesc = implode(' | ', array_filter($descParts));

        // 5. Lookup Suggested Price
        $suggestedPrice = self::getSuggestedPrice($cpuGen, $specs['ram'], $specs['storage'], $brand, $model, $desc, $note);

        return [
            'brand' => $brand,
            'model' => $model,
            'series' => $series,
            'cpu' => $cpuGen,
            'description' => $finalDesc,
            'notes' => $cleanNote,
            'quantity' => $qty,
            'unit_price' => round($suggestedPrice, 2),
            'is_suggested_price' => true
        ];
    }
}
