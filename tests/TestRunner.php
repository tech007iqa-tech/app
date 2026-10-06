<?php
/**
 * IQA Metal Warehouse Systems - Zero-Dependency CLI Test Runner & Assertion Library
 * Built strictly according to GEMINI.md guidelines (PHP 8.1+, Zero Dependencies).
 */

class TestRunner {
    private static int $passed = 0;
    private static int $failed = 0;
    private static int $skipped = 0;
    private static array $failures = [];
    private static float $startTime = 0.0;
    private static string $currentSuite = '';

    public static function init(): void {
        self::$passed = 0;
        self::$failed = 0;
        self::$skipped = 0;
        self::$failures = [];
        self::$startTime = microtime(true);
    }

    public static function suite(string $name, callable $tests): void {
        self::$currentSuite = $name;
        self::writeln("\n" . self::color("🔹 Suite: ", 'cyan', true) . self::color($name, 'white', true));
        self::writeln(str_repeat('─', 65));
        try {
            $tests();
        } catch (\Throwable $e) {
            self::$failed++;
            self::$failures[] = [
                'suite' => $name,
                'test' => 'Suite Execution Error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
            self::writeln("  " . self::color("❌ [CRITICAL SUITE ERROR] ", 'red', true) . $e->getMessage());
        }
    }

    public static function test(string $name, callable $test): void {
        $start = microtime(true);
        try {
            $test();
            self::$passed++;
            $duration = round((microtime(true) - $start) * 1000, 2);
            self::writeln(
                "  " . self::color("✔ PASS", 'green', true) . " " .
                self::color($name, 'white') . " " .
                self::color("({$duration}ms)", 'gray')
            );
        } catch (\AssertionError $e) {
            self::$failed++;
            self::$failures[] = [
                'suite' => self::$currentSuite,
                'test' => $name,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
            self::writeln("  " . self::color("✖ FAIL", 'red', true) . " " . self::color($name, 'red'));
            self::writeln("     " . self::color("└─ " . $e->getMessage(), 'yellow'));
        } catch (\Throwable $e) {
            self::$failed++;
            self::$failures[] = [
                'suite' => self::$currentSuite,
                'test' => $name,
                'message' => 'Unexpected Exception: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
            self::writeln("  " . self::color("✖ ERROR", 'red', true) . " " . self::color($name, 'red'));
            self::writeln("     " . self::color("└─ " . $e->getMessage(), 'yellow'));
        }
    }

    public static function summary(): int {
        $totalTime = round((microtime(true) - self::$startTime) * 1000, 2);
        $total = self::$passed + self::$failed + self::$skipped;

        self::writeln("\n" . str_repeat('═', 65));
        self::writeln(self::color("🏁 Test Suite Execution Summary", 'white', true));
        self::writeln(str_repeat('═', 65));

        if (self::$failed > 0) {
            self::writeln(self::color("\n❌ Detailed Failures (" . count(self::$failures) . "):", 'red', true));
            foreach (self::$failures as $idx => $f) {
                $num = $idx + 1;
                self::writeln(self::color("  {$num}) [{$f['suite']}] {$f['test']}", 'red', true));
                self::writeln("     File: {$f['file']}:{$f['line']}");
                self::writeln("     Error: " . self::color($f['message'], 'yellow'));
            }
        }

        self::writeln(
            "\n" .
            "Total Tests: " . self::color((string)$total, 'cyan', true) . "  |  " .
            "Passed: " . self::color((string)self::$passed, 'green', true) . "  |  " .
            "Failed: " . (self::$failed > 0 ? self::color((string)self::$failed, 'red', true) : self::color('0', 'gray')) . "  |  " .
            "Time: " . self::color("{$totalTime}ms", 'magenta', true)
        );

        if (self::$failed === 0) {
            self::writeln("\n" . self::color("✨ ALL TESTS PASSED SUCCESSFULLY! (0 Failures)", 'green', true) . "\n");
            return 0;
        } else {
            self::writeln("\n" . self::color("⚠️  TEST RUN COMPLETED WITH FAILURES!", 'red', true) . "\n");
            return 1;
        }
    }

    public static function color(string $text, string $color = 'white', bool $bold = false): string {
        $colors = [
            'black' => '30',
            'red' => '31',
            'green' => '32',
            'yellow' => '33',
            'blue' => '34',
            'magenta' => '35',
            'cyan' => '36',
            'white' => '37',
            'gray' => '90',
        ];

        $code = $colors[$color] ?? '37';
        $style = $bold ? "1;{$code}" : "0;{$code}";
        return "\033[{$style}m{$text}\033[0m";
    }

    public static function writeln(string $text = ''): void {
        echo $text . PHP_EOL;
    }
}

class Assert {
    public static function true(mixed $value, string $message = 'Expected true, got false'): void {
        if ($value !== true) {
            throw new \AssertionError($message . ' (Actual: ' . var_export($value, true) . ')');
        }
    }

    public static function false(mixed $value, string $message = 'Expected false, got true'): void {
        if ($value !== false) {
            throw new \AssertionError($message . ' (Actual: ' . var_export($value, true) . ')');
        }
    }

    public static function equals(mixed $expected, mixed $actual, string $message = ''): void {
        if ($expected != $actual) {
            $msg = $message ?: "Expected " . var_export($expected, true) . ", got " . var_export($actual, true);
            throw new \AssertionError($msg);
        }
    }

    public static function same(mixed $expected, mixed $actual, string $message = ''): void {
        if ($expected !== $actual) {
            $msg = $message ?: "Expected identical values.\nExpected: " . var_export($expected, true) . "\nActual:   " . var_export($actual, true);
            throw new \AssertionError($msg);
        }
    }

    public static function contains(string $needle, string $haystack, string $message = ''): void {
        if (strpos($haystack, $needle) === false) {
            $msg = $message ?: "String '{$needle}' was not found in target text.";
            throw new \AssertionError($msg);
        }
    }

    public static function matches(string $pattern, string $subject, string $message = ''): void {
        if (!preg_match($pattern, $subject)) {
            $msg = $message ?: "Subject does not match pattern {$pattern}";
            throw new \AssertionError($msg);
        }
    }

    public static function count(int $expectedCount, Countable|array $countable, string $message = ''): void {
        $actualCount = count($countable);
        if ($actualCount !== $expectedCount) {
            $msg = $message ?: "Expected count of {$expectedCount}, got {$actualCount}";
            throw new \AssertionError($msg);
        }
    }

    public static function tableExists(\PDO $pdo, string $tableName, string $message = ''): void {
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
        $stmt->execute([$tableName]);
        if (!$stmt->fetch()) {
            $msg = $message ?: "Table '{$tableName}' does not exist in SQLite database.";
            throw new \AssertionError($msg);
        }
    }

    public static function columnExists(\PDO $pdo, string $tableName, string $columnName, string $message = ''): void {
        $stmt = $pdo->query("PRAGMA table_info(\"{$tableName}\")");
        $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $names = array_column($columns, 'name');
        if (!in_array($columnName, $names, true)) {
            $colsStr = implode(', ', $names);
            $msg = $message ?: "Column '{$columnName}' missing in table '{$tableName}' (Available: {$colsStr})";
            throw new \AssertionError($msg);
        }
    }

    public static function pragmaEquals(\PDO $pdo, string $pragmaName, mixed $expectedValue, string $message = ''): void {
        $stmt = $pdo->query("PRAGMA {$pragmaName}");
        $val = $stmt->fetchColumn();
        if (strcasecmp((string)$val, (string)$expectedValue) !== 0) {
            $msg = $message ?: "PRAGMA {$pragmaName} expected '{$expectedValue}', got '{$val}'";
            throw new \AssertionError($msg);
        }
    }
}
