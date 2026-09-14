<?php
/**
 * Warehouse Import Parser & Pricing Rules Matrix Engine Partial
 * Handles brand inference, hardware specification regex tokenization, dynamic matrix lookup, and location registration.
 */

function mapCpuToMatrixGen($cpu, $gen) {
    $cpu = strtolower($cpu);
    $gen = strtolower($gen);

    if (strpos($gen, '4') !== false || strpos($gen, '5') !== false) {
        return '4th-5th';
    }
    if (strpos($gen, '6') !== false || strpos($gen, '7') !== false) {
        return '6th-7th';
    }

    $gen_num = 0;
    if (preg_match('/(\d+)/', $gen, $m)) {
        $gen_num = (int)$m[1];
    }

    if ($gen_num >= 8 && $gen_num <= 12) {
        $tier = 'i5';
        if (strpos($cpu, 'i7') !== false || strpos($cpu, 'i9') !== false) {
            $tier = 'i7';
        }
        return $tier . '-' . $gen_num . 'th';
    }

    foreach ([8, 9, 10, 11, 12] as $g) {
        if (strpos($cpu, $g . 'th') !== false || strpos($cpu, '-' . $g) !== false) {
            $tier = 'i5';
            if (strpos($cpu, 'i7') !== false || strpos($cpu, 'i9') !== false) {
                $tier = 'i7';
            }
            return $tier . '-' . $g . 'th';
        }
    }

    return '';
}

function parseItemString($itemStr, $notesStr = '', $serialStr = '') {
    $brands = ['Dell', 'HP', 'Lenovo', 'Apple', 'Microsoft', 'Samsung', 'Asus', 'Acer', 'MSI', 'Sony', 'Nintendo', 'Panasonic', 'Getac'];
    $brand = 'Unknown';
    $model = 'Unknown';
    $series = '';
    $cpu = '';
    $gen = '';
    $ram = '';
    $storage = '';
    $battery = '';
    $condition = 'Untested';
    $price = 0.00;

    // Detect Brand
    foreach ($brands as $b) {
        if (stripos($itemStr, $b) !== false) {
            $brand = $b;
            break;
        }
    }

    // Infer Brand if Unknown based on popular model keywords
    if ($brand === 'Unknown') {
        $brandInferenceMap = [
            'Dell' => ["Alienware","Inspiron","Latitude","Precision","Vostro","XPS"],
            'HP' => ["EliteBook","Envy","Omen","Pavilion","ProBook","Spectre","Victus","ZBook","Z-Book"],
            'Lenovo' => ["IdeaPad","LOQ","Legion","ThinkBook","ThinkPad","Yoga","Flex"]
        ];
        foreach ($brandInferenceMap as $inferredBrand => $modelKeywords) {
            foreach ($modelKeywords as $keyword) {
                if (stripos($itemStr, $keyword) !== false) {
                    $brand = $inferredBrand;
                    break 2;
                }
            }
        }
    }

    // Detect Model based on Brand
    $modelsMap = [
        'Dell' => ["Alienware","G-Series","Inspiron","Latitude","Precision","Vostro","XPS"],
        'HP' => ["ChromeBook","Dragonfly","EliteBook","Envy","Notebook","Omen","Pavilion","ProBook","Spectre","Victus","ZBook","mt"],
        'Lenovo' => ["ChromeBook","IdeaPad","LOQ","Legion","ThinkBook","ThinkPad","Yoga","Flex"],
        'Apple' => ["MacBook","MacBook Air","MacBook Pro"],
        'Microsoft' => ["Surface Book","Surface Go","Surface Laptop","Surface Laptop Go","Surface Laptop Studio","Surface Pro"],
        'Samsung' => ["ChromeBook","Galaxy Book","Galaxy Book Flex","Galaxy Book Ion","Notebook"],
        'Asus' => ["ChromeBook","ExpertBook","VivoBook","ZenBook"],
        'Acer' => ["Aspire","ChromeBook","Nitro","Predator","Spin","Swift","TravelMate"],
        'MSI' => ["Modern","Prestige","Stealth","Creator"],
        'Panasonic' => ["Toughbook","Toughpad"],
        'Getac' => ["Rugged","S410","A140","V110","B300","F110"]
    ];

    if ($brand !== 'Unknown' && isset($modelsMap[$brand])) {
        foreach ($modelsMap[$brand] as $m) {
            if (stripos($itemStr, $m) !== false) {
                $model = $m;
                break;
            }
        }
    }

    // Enhance Apple model detection to capture A-number model (e.g. A1466)
    if ($brand === 'Apple') {
        if (preg_match('/\b(A\d{4})\b/i', $itemStr, $matches)) {
            $model = strtoupper($matches[1]);
        }
    }

    // Default generic models if Brand is known but Model is still Unknown
    if ($brand !== 'Unknown' && $model === 'Unknown') {
        if ($brand === 'HP') {
            $model = 'Notebook';
        } elseif ($brand === 'Lenovo') {
            $model = 'ThinkPad';
        } elseif ($brand === 'Dell') {
            $model = 'Latitude';
        } elseif ($brand === 'Panasonic') {
            $model = 'Toughbook';
        } elseif ($brand === 'Getac') {
            $model = 'Rugged';
        }
    }

    // Detect RAM & Storage combined (e.g. 4/32, 16/256, 8-128)
    $combined_specs_matched = false;
    if (preg_match('/\b(\d+)\s*[\/-]\s*(\d+)\b/', $itemStr, $matches)) {
        $rVal = (int)$matches[1];
        $sVal = (int)$matches[2];
        if ($rVal <= 64 && $sVal >= 8) {
            $ram = $rVal . 'GB';
            if ($sVal <= 4) {
                $storage = $sVal . 'TB';
            } else {
                $storage = $sVal . 'GB';
            }
            $combined_specs_matched = true;
        }
    }

    // Detect RAM (if not matched combined)
    if (!$combined_specs_matched) {
        if (preg_match('/(\d+)\s*(?:GB|gb)\s*(?:RAM|ram)?/i', $itemStr, $matches)) {
            $val = (int)$matches[1];
            if (in_array($val, [2, 4, 8, 12, 16, 24, 32, 64, 128])) {
                $ram = $val . 'GB';
            }
        }
    }

    // Detect Storage (if not matched combined)
    if (!$combined_specs_matched) {
        if (preg_match('/(\d+)\s*(?:GB|gb|TB|tb)\s*(?:SSD|HDD|NVMe|Storage)?/i', $itemStr, $matches)) {
            $valStr = strtoupper($matches[0]);
            if (stripos($valStr, 'TB') !== false) {
                $storage = $matches[1] . 'TB';
            } else {
                $val = (int)$matches[1];
                if ($val >= 120 && $val != (int)str_replace('GB', '', $ram)) {
                    $storage = $val . 'GB';
                }
            }
        }
        if (empty($storage) && preg_match('/(\d+)\s*(?:GB|gb|TB|tb)\s*(?:SSD|HDD|NVMe)/i', $notesStr, $matches)) {
             $storage = strtoupper($matches[1] . (stripos($matches[0], 'TB') !== false ? 'TB' : 'GB'));
        }
    }

    // Detect CPU
    if (preg_match('/(i3|i5|i7|i9|Ryzen(?:\s*Pro)?(?:\s*[3579])?|Celeron|Pentium|Xeon|Core\s*2\s*Duo|M1|M2|M3)/i', $itemStr, $matches)) {
        $cpu = trim($matches[1]);
    }
    if (empty($cpu)) {
        if (stripos($itemStr, 'AMD') !== false || stripos($notesStr, 'AMD') !== false) {
            $cpu = 'AMD';
        }
    }

    // Detect Gen
    if (preg_match('/(\d+(?:th|rd|nd|st)(?:\s*[\/-]\s*\d+(?:th|rd|nd|st))?)\s*(?:Gen)?/i', $itemStr, $matches)) {
        $gen = $matches[1];
    }

    // Default CPU to i5 if missing but Gen is present
    if (empty($cpu) && !empty($gen)) {
        $cpu = 'i5';
    }

    // If CPU contains Ryzen, set Gen to AMD
    if (!empty($cpu) && stripos($cpu, 'Ryzen') !== false) {
        $gen = 'AMD';
    }

    // Detect Series
    if (stripos($itemStr, 'zbook') !== false || stripos($itemStr, 'z-book') !== false) {
        if (preg_match('/\b((?:Firefly|Fury|Studio|Power|Create)\s*(?:x360)?\s*(?:\d{2})?[a-z]?\s*[-\/]?\s*G\d{1,2})\b/i', $itemStr, $matches)) {
            $series = ucwords(strtolower($matches[1]));
            $series = preg_replace('/\bg(\d+)\b/i', 'G$1', $series);
        } elseif (preg_match('/\b(Fury)\b/i', $itemStr, $matches)) {
            $series = 'Fury';
        } elseif (preg_match('/\b(\d{2,3}[a-z]?\s*[-\/]?\s*G\d{1,2})\b/i', $itemStr, $matches)) {
            $series = strtoupper($matches[1]);
        } elseif (preg_match('/\b(\d{2,3}[a-z]?)\b/i', $itemStr, $matches)) {
            $series = strtoupper($matches[1]);
        }
    }

    if (empty($series)) {
        if (preg_match('/X1\s+Carbon/i', $itemStr)) {
            $series = 'X1 Carbon';
        } elseif (preg_match('/X1\s+Yoga/i', $itemStr)) {
            $series = 'X1 Yoga';
        } elseif (preg_match('/X1/i', $itemStr)) {
            $series = 'X1';
        } elseif (preg_match('/\b(CF\-?[A-Z0-9]+|FZ\-?[A-Z0-9]+|S410|A140|V110|B300|F110)\b/i', $itemStr, $matches)) {
            $series = strtoupper($matches[1]);
            if (strcasecmp($series, 'CF54') === 0) {
                $series = 'CF-54';
            }
        } elseif (preg_match('/\b(x360\-?\d{3,4}(?:\-?G\d{1,2})?)\b/i', $itemStr, $matches)) {
            $series = $matches[1];
        } elseif (preg_match('#\b(\d{3,4}\s*[-/]?\s*G\d{1,2})\b#i', $itemStr, $matches)) {
            $series = $matches[1];
        } elseif (preg_match('/\b((?:13|14|15|17)\-[a-z0-9]+)\b/i', $itemStr, $matches)) {
            $series = $matches[1];
        } elseif (preg_match('/\b(G\d{1,2})\b/i', $itemStr, $matches)) {
            $series = $matches[1];
        } elseif (preg_match('/\b(P\d{2,3}[A-Z])\b/i', $itemStr, $matches)) {
            $series = strtoupper($matches[1]);
        } elseif (preg_match('/\b(P\-?\d{2,3}[s-z]?)\b/i', $itemStr, $matches)) {
            $series = $matches[1];
        } elseif (preg_match('/\b(L\-?\d{2,3}[s-z]?)\b/i', $itemStr, $matches)) {
            $series = $matches[1];
        } elseif (preg_match('/\b(T\-?\d{2,3}[s-z]?)\b/i', $itemStr, $matches)) {
            $series = $matches[1];
        } elseif (preg_match('/\b(X\-?\d{1,3}[s-z]?)\b/i', $itemStr, $matches)) {
            $series = $matches[1];
        } elseif (preg_match('/\b(13|14|15|17|15s|14s)\b/i', $itemStr, $matches)) {
            $series = $matches[1];
        } else {
            $tokens = preg_split('/[\s,\-\/]+/', $itemStr);
            foreach ($tokens as $token) {
                $token = trim($token);
                if (empty($token)) continue;
                if (preg_match('/^[A-Z]?\d{3,4}[s-z]?$/i', $token)) {
                    $series = $token;
                }
            }
        }
    }

    // Detect Battery
    if (preg_match('/battery\s*:\s*(Yes|No|Unknown)/i', $notesStr, $matches)) {
        $battery = $matches[1];
    } elseif (stripos($itemStr, 'battery') !== false || stripos($notesStr, 'battery') !== false) {
        if (stripos($itemStr, 'no battery') !== false || stripos($notesStr, 'no battery') !== false || stripos($notesStr, 'missing battery') !== false) {
            $battery = 'No';
        } else {
            $battery = 'Yes';
        }
    }
    if (empty($battery)) {
        $battery = 'Unknown';
    }

    // Detect Condition
    if (preg_match('/(untested)/i', $itemStr . ' ' . $notesStr, $matches)) {
        $condition = 'Untested';
    } elseif (preg_match('/(for parts)/i', $itemStr . ' ' . $notesStr, $matches)) {
        $condition = 'For parts';
    } elseif (preg_match('/([ABC]\s*Grade|Grade\s*[ABC])/i', $itemStr . ' ' . $notesStr, $matches)) {
        $condition = trim($matches[1]);
    }

    // Price
    if (preg_match('/\$(\d+(?:\.\d{2})?)/', $itemStr . ' ' . $notesStr, $matches)) {
        $price = (float)$matches[1];
    }

    if (empty($ram)) {
        $ram = '-';
    }
    if (empty($storage)) {
        $storage = '-';
    }

    // Lookup Price in pricing matrix if not in string
    if ($price == 0.00) {
        global $conn_wh;
        if ($conn_wh) {
            $category = 'Regular';
            $normalized_item = strtolower($itemStr . ' ' . $notesStr);

            if (stripos($brand, 'ram') !== false || stripos($brand, 'memory') !== false || stripos($normalized_item, 'ddr3') !== false || stripos($normalized_item, 'ddr4') !== false || stripos($normalized_item, 'ram') !== false || stripos($normalized_item, 'sodimm') !== false || stripos($normalized_item, 'dimm') !== false) {
                $category = 'RAM';
            } elseif (stripos($brand, 'ssd') !== false || stripos($brand, 'hdd') !== false || stripos($brand, 'storage') !== false || stripos($normalized_item, 'ssd') !== false || stripos($normalized_item, 'hdd') !== false || stripos($normalized_item, 'hard drive') !== false || stripos($normalized_item, 'nvme') !== false) {
                $category = 'Storage';
            } elseif (stripos($normalized_item, 'chromebook') !== false || stripos($brand, 'chromebook') !== false || stripos($model, 'chromebook') !== false) {
                $category = 'Chromebook';
            } elseif (stripos($brand, 'Apple') !== false || stripos($normalized_item, 'macbook') !== false) {
                $category = 'Apple';
            } elseif (stripos($brand, 'Microsoft') !== false || stripos($normalized_item, 'surface') !== false) {
                $category = 'Microsoft';
            } elseif (stripos($brand, 'MSI') !== false || stripos($normalized_item, 'alienware') !== false || stripos($normalized_item, 'gaming') !== false || stripos($normalized_item, 'legion') !== false || stripos($normalized_item, 'omen') !== false || stripos($normalized_item, 'predator') !== false) {
                $category = 'Gaming';
            } elseif (stripos($normalized_item, 'rugged') !== false || stripos($normalized_item, 'toughbook') !== false || stripos($normalized_item, 'durabook') !== false || stripos($normalized_item, 'getac') !== false) {
                $category = 'Rugged';
            }

            $grade_key = 'Parts'; // default fallback
            $normalized_cond = strtolower($condition);
            if (strpos($normalized_cond, 'untested') !== false) {
                $grade_key = 'Untested';
            } elseif (strpos($normalized_cond, 'c grade') !== false || strpos($normalized_cond, 'grade c') !== false) {
                $grade_key = 'C Grade';
            } elseif (strpos($normalized_cond, 'parts') !== false || strpos($normalized_cond, 'part') !== false) {
                $grade_key = 'Parts';
            } elseif (strpos($normalized_cond, 'a grade') !== false || strpos($normalized_cond, 'grade a') !== false) {
                $grade_key = 'Untested';
            } elseif (strpos($normalized_cond, 'b grade') !== false || strpos($normalized_cond, 'grade b') !== false) {
                $grade_key = 'Parts';
            }

            try {
                $query_gen = 'Default';
                if ($category === 'Regular') {
                    $query_gen = mapCpuToMatrixGen($cpu, $gen);
                } elseif ($category === 'RAM') {
                    $ram_gigs = '';
                    if (preg_match('/\b(2|4|8|16|32)\s*(?:GB|gb)\b/i', $normalized_item, $matches)) {
                        $ram_gigs = $matches[1] . 'GB';
                    }
                    $ram_type = 'DDR4';
                    if (stripos($normalized_item, 'ddr3') !== false) {
                        $ram_type = 'DDR3';
                    }
                    if (!empty($ram_gigs)) {
                        $query_gen = $ram_gigs . ' ' . $ram_type;
                    }
                    if ($grade_key === 'Parts') {
                        $grade_key = 'Tested';
                    }
                } elseif ($category === 'Microsoft') {
                    $query_gen = 'Surface Pro 8 (Default)';

                    if (stripos($normalized_item, '1769') !== false) {
                        if (stripos($normalized_item, '7th') !== false || stripos($normalized_item, 'laptop 1') !== false) {
                            $query_gen = 'Surface Laptop 1 (1769)';
                        } else {
                            $query_gen = 'Surface Laptop 2 (1769)';
                        }
                    } elseif (stripos($normalized_item, '1782') !== false) {
                        $query_gen = 'Surface Laptop 2 (1782)';
                    } elseif (stripos($normalized_item, '1867') !== false || stripos($normalized_item, '1868') !== false) {
                        $query_gen = 'Surface Laptop 3 (1867/1868)';
                    } elseif (stripos($normalized_item, '1950') !== false || stripos($normalized_item, '1951') !== false) {
                        if (stripos($normalized_item, '12th') !== false || stripos($normalized_item, 'laptop 5') !== false) {
                            $query_gen = 'Surface Laptop 5 (1950/1951)';
                        } else {
                            $query_gen = 'Surface Laptop 4 (1950/1951)';
                        }
                    } elseif (stripos($normalized_item, '2033') !== false || stripos($normalized_item, '2035') !== false) {
                        $query_gen = 'Surface Laptop 6 (2033/2035)';
                    } elseif (stripos($normalized_item, '1943') !== false) {
                        $query_gen = 'Surface Laptop Go (1943)';
                    } elseif (stripos($normalized_item, '1703') !== false) {
                        $query_gen = 'Surface Book 1 (1703)';
                    } elseif (stripos($normalized_item, '1823') !== false) {
                        $query_gen = 'Surface Book 2 (1823)';
                    } elseif (stripos($normalized_item, '1834') !== false || stripos($normalized_item, '1835') !== false) {
                        $query_gen = 'Surface Book 2 (1834/1835)';
                    } elseif (stripos($normalized_item, '1899') !== false) {
                        if (stripos($normalized_item, '15"') !== false || stripos($normalized_item, '15-inch') !== false || stripos($normalized_item, '15 inch') !== false) {
                            $query_gen = '15" Surface Book 3 (1899)';
                        } else {
                            $query_gen = 'Surface Book 3 (1899)';
                        }
                    } elseif (stripos($normalized_item, '1900') !== false) {
                        $query_gen = 'Surface Book 3 (1900)';
                    } elseif (stripos($normalized_item, '1514') !== false) {
                        $query_gen = 'Surface Pro 1 (1514)';
                    } elseif (stripos($normalized_item, '1601') !== false) {
                        $query_gen = 'Surface Pro 2 (1601)';
                    } elseif (stripos($normalized_item, '1631') !== false) {
                        $query_gen = 'Surface Pro 3 (1631)';
                    } elseif (stripos($normalized_item, '1724') !== false) {
                        $query_gen = 'Surface Pro 4 (1724)';
                    } elseif (stripos($normalized_item, '1807') !== false) {
                        $query_gen = 'Surface Pro 5 (1807)';
                    } elseif (stripos($normalized_item, '1796') !== false) {
                        if (stripos($normalized_item, '8th') !== false || stripos($normalized_item, 'pro 6') !== false) {
                            $query_gen = 'Surface Pro 6 (1796)';
                        } else {
                            $query_gen = 'Surface Pro 5 (1796)';
                        }
                    } elseif (stripos($normalized_item, '1866') !== false) {
                        $query_gen = 'Surface Pro 7 (1866)';
                    } elseif (stripos($normalized_item, '1960') !== false) {
                        $query_gen = 'Surface Pro 7+ (1960)';
                    } elseif (stripos($normalized_item, '1983') !== false) {
                        $query_gen = 'Surface Pro 8 (1983)';
                    } elseif (stripos($normalized_item, '2038') !== false) {
                        $query_gen = 'Surface Pro 9 (2038)';
                    } elseif (stripos($normalized_item, '2079') !== false) {
                        $query_gen = 'Surface Pro 10 (2079)';
                    } else {
                        if (stripos($normalized_item, 'pro 8') !== false) {
                            $query_gen = 'Surface Pro 8 (Default)';
                        } elseif (stripos($normalized_item, 'pro 9') !== false) {
                            $query_gen = 'Surface Pro 9 (Default)';
                        } elseif (stripos($normalized_item, 'pro 10') !== false) {
                            $query_gen = 'Surface Pro 10 (Default)';
                        } elseif (stripos($normalized_item, 'pro 7') !== false) {
                            $query_gen = 'Surface Pro 7 (1866)';
                        } elseif (stripos($normalized_item, 'pro 6') !== false) {
                            $query_gen = 'Surface Pro 6 (1796)';
                        } elseif (stripos($normalized_item, 'pro 5') !== false) {
                            $query_gen = 'Surface Pro 5 (1796)';
                        } elseif (stripos($normalized_item, 'pro 4') !== false) {
                            $query_gen = 'Surface Pro 4 (1724)';
                        } elseif (stripos($normalized_item, 'pro 3') !== false) {
                            $query_gen = 'Surface Pro 3 (1631)';
                        } elseif (stripos($normalized_item, 'book 3') !== false) {
                            $query_gen = 'Surface Book 3 (1899)';
                        } elseif (stripos($normalized_item, 'book 2') !== false) {
                            $query_gen = 'Surface Book 2 (1823)';
                        } elseif (stripos($normalized_item, 'book 1') !== false) {
                            $query_gen = 'Surface Book 1 (1703)';
                        } elseif (stripos($normalized_item, 'laptop 4') !== false) {
                            $query_gen = 'Surface Laptop 4 (1950/1951)';
                        } elseif (stripos($normalized_item, 'laptop 3') !== false) {
                            $query_gen = 'Surface Laptop 3 (1867/1868)';
                        } elseif (stripos($normalized_item, 'laptop 2') !== false) {
                            $query_gen = 'Surface Laptop 2 (1769)';
                        } elseif (stripos($normalized_item, 'laptop 1') !== false || stripos($normalized_item, 'laptop') !== false) {
                            $query_gen = 'Surface Laptop 1 (1769)';
                        }
                    }

                    $is_untested = (strpos($normalized_cond, 'untested') !== false);
                    $is_parts = (stripos($normalized_item, 'parts') !== false || stripos($normalized_item, 'part') !== false || strpos($normalized_cond, 'parts') !== false);

                    if ($is_parts) {
                        $grade_key = 'For Parts';
                    } elseif ($is_untested) {
                        $grade_key = 'Untested';
                    } else {
                        $grade_key = 'Tested';
                    }
                } elseif ($category === 'Chromebook') {
                    $query_gen = 'Dell Chromebook 3180 / HP G5 EE';

                    if (stripos($normalized_item, '3180') !== false || stripos($normalized_item, 'g5') !== false) {
                        $query_gen = 'Dell Chromebook 3180 / HP G5 EE';
                    } elseif (stripos($normalized_item, '11a g6') !== false || stripos($normalized_item, '11a-g6') !== false) {
                        $query_gen = 'HP Chromebook 11A G6 EE';
                    } elseif (stripos($normalized_item, '11 g6') !== false || stripos($normalized_item, '11-g6') !== false) {
                        $query_gen = 'HP Chromebook 11 G6 EE';
                    } elseif (stripos($normalized_item, '11 g7') !== false || stripos($normalized_item, '11-g7') !== false) {
                        $query_gen = 'HP Chromebook 11 G7 EE';
                    } elseif (stripos($normalized_item, '11a g8') !== false || stripos($normalized_item, '11a-g8') !== false) {
                        $query_gen = 'HP Chromebook 11A G8 EE';
                    } elseif (stripos($normalized_item, '11 g8') !== false || stripos($normalized_item, '11-g8') !== false) {
                        $query_gen = 'HP Chromebook 11 G8 EE';
                    } elseif (stripos($normalized_item, '11 g9') !== false || stripos($normalized_item, '11-g9') !== false) {
                        $query_gen = 'HP Chromebook 11 G9 EE';
                    } elseif (stripos($normalized_item, '11 g10') !== false || stripos($normalized_item, '11-g10') !== false) {
                        $query_gen = 'HP Chromebook 11 G10 EE';
                    } elseif (stripos($normalized_item, 'x360 11 g3') !== false || stripos($normalized_item, 'g3 ee') !== false) {
                        $query_gen = 'HP x360 11 G3 EE (Convertible)';
                    } elseif (stripos($normalized_item, 'x360 11 g4') !== false || stripos($normalized_item, 'g4 ee') !== false) {
                        $query_gen = 'HP x360 11 G4 EE (Convertible)';
                    } elseif (stripos($normalized_item, '3100') !== false) {
                        $query_gen = 'Dell 3100 / 3100 2-in-1';
                    } elseif (stripos($normalized_item, '3110') !== false) {
                        $query_gen = 'Dell Chromebook 3110 / 2-in-1';
                    } elseif (stripos($normalized_item, '3120') !== false) {
                        $query_gen = 'Dell Chromebook 3120';
                    } elseif (stripos($normalized_item, '500e') !== false) {
                        $query_gen = 'Lenovo 500e 2nd Gen (Convertible)';
                    } elseif (stripos($normalized_item, 'samsung') !== false && (stripos($normalized_item, 'chromebook 4') !== false || stripos($normalized_item, 'cb4') !== false)) {
                        $query_gen = 'Samsung Chromebook 4 (11")';
                    } elseif (stripos($normalized_item, '100e') !== false || stripos($normalized_item, '300e') !== false) {
                        if (stripos($normalized_item, '3rd') !== false || stripos($normalized_item, '3rd gen') !== false) {
                            $query_gen = 'Lenovo 100e / 300e 3rd Gen';
                        } elseif (stripos($normalized_item, 'intel') !== false || stripos($normalized_item, 'celeron') !== false) {
                            $query_gen = 'Lenovo 100e / 300e 2nd Gen (Intel)';
                        } else {
                            $query_gen = 'Lenovo 100e / 300e 2nd Gen (MTK)';
                        }
                    }

                    $is_untested = (strpos($normalized_cond, 'untested') !== false || stripos($normalized_item, 'untested') !== false);
                    if ($is_untested) {
                        $grade_key = 'Untested Lot';
                    } else {
                        $grade_key = 'Tested - Clean (A/B)';
                    }
                } elseif ($category === 'Apple') {
                    $query_gen = $model;

                    $is_untested = (strpos($normalized_cond, 'untested') !== false);
                    $is_parts = (stripos($normalized_item, 'parts') !== false || stripos($normalized_item, 'part') !== false || strpos($normalized_cond, 'parts') !== false);

                    if ($is_parts) {
                        $grade_key = 'For Parts';
                    } elseif ($is_untested) {
                        $grade_key = 'Untested';
                    } else {
                        $grade_key = 'Tested';
                    }
                } elseif ($category === 'Storage') {
                    $storage_gigs = '';
                    if (preg_match('/\b(128|256|512)\s*(?:GB|gb)\b/i', $normalized_item, $matches)) {
                        $storage_gigs = $matches[1] . 'GB';
                    } elseif (preg_match('/\b([12])\s*(?:TB|tb)\b/i', $normalized_item, $matches)) {
                        $storage_gigs = $matches[1] . 'TB';
                    }
                    if (!empty($storage_gigs)) {
                        $query_gen = $storage_gigs . ' M.2';
                    }
                    if ($grade_key === 'Parts') {
                        $grade_key = 'Tested';
                    }
                } elseif ($category === 'Rugged') {
                    $query_gen = mapCpuToMatrixGen($cpu, $gen);

                    $is_untested = (strpos($normalized_cond, 'untested') !== false);
                    $has_battery_issue = (stripos($normalized_item, 'no battery') !== false || stripos($normalized_item, 'missing battery') !== false);
                    $is_parts = (stripos($normalized_item, 'parts') !== false || stripos($normalized_item, 'part') !== false || strpos($normalized_cond, 'parts') !== false);

                    if ($is_untested) {
                        if ($is_parts || $has_battery_issue) {
                            $grade_key = 'Untested Parts';
                        } else {
                            $grade_key = 'Untested Complete';
                        }
                    } else {
                        if ($has_battery_issue) {
                            $grade_key = 'Tested No Battery';
                        } else {
                            $grade_key = 'Tested Complete';
                        }
                    }
                }

                if (!empty($query_gen)) {
                    $stmt_pr = $conn_wh->prepare("SELECT price FROM pricing_rules WHERE category = ? AND cpu_gen = ? AND grade = ?");
                    $stmt_pr->execute([$category, $query_gen, $grade_key]);
                    $price_db = $stmt_pr->fetchColumn();
                    if ($price_db !== false) {
                        $price = (float)$price_db;
                    }
                }
            } catch (Exception $e) {
                // Keep 0.00
            }
        }
    }

    return [
        'brand' => $brand,
        'model' => $model,
        'series' => $series,
        'cpu' => $cpu,
        'gen' => $gen,
        'ram' => $ram,
        'storage' => $storage,
        'battery' => $battery,
        'condition' => $condition,
        'price' => $price
    ];
}

function getOrCreateLocation($conn, $locCode, $zoneName = null) {
    $locCode = strtoupper(trim($locCode));
    if (empty($locCode)) return 'General';

    // Check if location already exists
    $stmt = $conn->prepare("SELECT working_zone_name FROM locations WHERE location_code = ?");
    $stmt->execute([$locCode]);
    $existingZone = $stmt->fetchColumn();
    $exists = ($existingZone !== false);

    $overrideGiven = ($zoneName !== null && trim($zoneName) !== '');

    if (!$exists) {
        if (!$overrideGiven) {
            $zoneName = 'General';
            // Extract alphabetical prefix from location code (e.g. "N4" -> "N", "N-4" -> "N", "Zone N" -> "N", "A-2" -> "A")
            if (preg_match('/^(?:Zone\s*[-_]?)?([a-zA-Z]+)/iu', $locCode, $matches)) {
                $prefix = strtoupper($matches[1]);
                // Check if working_zones already has a matching zone name (e.g. "Zone N" or "N")
                $stmtCheck = $conn->prepare("SELECT name FROM working_zones WHERE UPPER(name) = ? OR UPPER(name) = ? OR UPPER(name) = ? LIMIT 1");
                $stmtCheck->execute(['ZONE ' . $prefix, $prefix, 'ZONE-' . $prefix]);
                $foundZone = $stmtCheck->fetchColumn();
                if ($foundZone) {
                    $zoneName = $foundZone;
                } else {
                    $zoneName = 'Zone ' . $prefix;
                }
            }
        }

        // Register the working zone if not already present
        $stmtZone = $conn->prepare("INSERT OR IGNORE INTO working_zones (name) VALUES (?)");
        $stmtZone->execute([$zoneName]);

        // Auto-create location and map it to its corresponding working zone
        $stmtLoc = $conn->prepare("INSERT OR IGNORE INTO locations (location_code, status, working_zone_name) VALUES (?, 'Idle', ?)");
        $stmtLoc->execute([$locCode, $zoneName]);
    } else {
        // If explicit override given and differs from existing, update it
        if ($overrideGiven && $zoneName !== $existingZone) {
            $stmtZone = $conn->prepare("INSERT OR IGNORE INTO working_zones (name) VALUES (?)");
            $stmtZone->execute([$zoneName]);

            $stmtLoc = $conn->prepare("UPDATE locations SET working_zone_name = ? WHERE location_code = ?");
            $stmtLoc->execute([$zoneName, $locCode]);
        } else {
            $zoneName = $existingZone ?: 'General';
        }
    }

    return $zoneName;
}

