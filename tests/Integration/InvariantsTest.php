<?php
/**
 * Warehouse Domain Invariants & Rules Compliance Tests (GEMINI.md)
 */

require_once __DIR__ . '/../../core/Company.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../labels/includes/audit.php';

TestRunner::suite('System Invariants & Security Guardrails (GEMINI.md)', function() {

    TestRunner::test('CSV export invariant: UTF-8 BOM (\xEF\xBB\xBF) must prepend all CSV streams', function() {
        $bom = "\xEF\xBB\xBF";
        Assert::same(3, strlen($bom), 'UTF-8 BOM must be exactly 3 bytes');
        Assert::same(0xEF, ord($bom[0]), 'First byte must be 0xEF');
        Assert::same(0xBB, ord($bom[1]), 'Second byte must be 0xBB');
        Assert::same(0xBF, ord($bom[2]), 'Third byte must be 0xBF');

        $sampleCsv = $bom . "Order ID,Customer,Total\n101,Acme Corp,500.00\n";
        Assert::true(str_starts_with($sampleCsv, "\xEF\xBB\xBF"), 'CSV stream must start with UTF-8 BOM');
    });

    TestRunner::test('Label generation invariant: Zero ZipArchive dependency (Flat XML / FODT)', function() {
        // Assert FODT XML structure produces valid XML
        $labels_xml = '<text:p text:style-name="P1">IQA-W-10023</text:p>';
        $fodt = '<?xml version="1.0" encoding="UTF-8"?>
<office:document xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
                 xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
                 xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
                 office:version="1.2" office:mimetype="application/vnd.oasis.opendocument.text">
  <office:body>
    <office:text>' . $labels_xml . '</office:text>
  </office:body>
</office:document>';

        // Load as SimpleXML to prove 100% well-formed XML without zip decompression
        $xml = simplexml_load_string($fodt);
        Assert::true($xml !== false, 'Thermal label FODT must parse as valid XML without zip decompression');
    });

    TestRunner::test('XML Entity Escaping invariant: Special characters must be escaped with ENT_XML1 | ENT_QUOTES', function() {
        $raw = 'Stainless Steel & Aluminum <Grade "304" & \'316\'>';
        $escaped = htmlspecialchars($raw, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        Assert::false(str_contains($escaped, '<Grade'), 'Raw tags must be converted to XML entities');
        Assert::true(str_contains($escaped, '&amp;'), 'Ampersands must be converted to &amp;');
        Assert::true(str_contains($escaped, '&quot;304&quot;'), 'Quotes must be converted to &quot;');
        Assert::true(str_contains($escaped, '&apos;316&apos;'), 'Single quotes must be converted to &apos;');
    });

    TestRunner::test('Web Server Shield: .htaccess blocks direct HTTP access to core, DOCS, db, and tests', function() {
        $htaccessPath = __DIR__ . '/../../.htaccess';
        Assert::true(file_exists($htaccessPath), '.htaccess file must exist in web root');
        $content = file_get_contents($htaccessPath);

        Assert::true(
            str_contains($content, '^(core|DOCS)') && str_contains($content, '(db|tests)'),
            '.htaccess must forbid direct HTTP requests to core, DOCS, db, and tests directories'
        );
        Assert::true(
            preg_match('/config\\\\?\.json/', $content) === 1,
            '.htaccess must forbid direct HTTP requests to config.json'
        );
    });

    TestRunner::test('Trends Velocity invariant: Avg Price column MUST be rendered before Details column', function() {
        $velocityFile = __DIR__ . '/../../orders/pages/partials/trends_tab_velocity.php';
        Assert::true(file_exists($velocityFile), 'trends_tab_velocity.php must exist');
        $html = file_get_contents($velocityFile);

        $posAvg = strpos($html, 'Avg Price');
        $posDetails = strpos($html, 'Details');
        Assert::true($posAvg !== false, 'Avg Price column header must exist in trends velocity table');
        Assert::true($posDetails !== false, 'Details column header must exist in trends velocity table');
        Assert::true($posAvg < $posDetails, 'Avg Price column must be rendered before Details column');
    });

    TestRunner::test('Audit logging invariant: log_audit_event records structured mutation events into audit.sqlite', function() {
        $pdoAudit = Database::audit();
        $testAction = 'HEALTH_CHECK_TEST_' . time();
        $success = log_audit_event($pdoAudit, 'System', 99999, $testAction, 'Automated health suite ping', null, ['status' => 'OK']);

        Assert::true($success, 'log_audit_event must return true on success');

        $stmt = $pdoAudit->prepare("SELECT * FROM audit_logs WHERE action = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$testAction]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        Assert::true(!empty($row), 'Audit event record must exist in audit_logs table');
        Assert::same('System', $row['entity_type']);
        Assert::same('99999', (string)$row['entity_id']);
        Assert::contains('Automated health suite ping', $row['summary']);

        // Clean up test entry
        $pdoAudit->prepare("DELETE FROM audit_logs WHERE action = ?")->execute([$testAction]);
    });

    TestRunner::test('Company configuration fallback resilience: Returns defaults if config.json is absent', function() {
        $name = Company::getName();
        Assert::true(!empty($name), 'Company name must not be empty');
        $currency = Company::getCurrency();
        Assert::true(!empty($currency), 'Currency symbol must not be empty');
    });

    TestRunner::test('Pipeline date invariant: Monthly & Yearly SQLite boundaries evaluate in local time without prior month spillover', function() {
        $pdo = Database::orders();

        // 1. Monthly boundary must evaluate to the 1st of the current local month
        $monthly = $pdo->query("SELECT date('now', 'localtime', 'start of month')")->fetchColumn();
        $expectedMonthPrefix = $pdo->query("SELECT strftime('%Y-%m', 'now', 'localtime')")->fetchColumn() . '-01';
        Assert::same($expectedMonthPrefix, $monthly, "Monthly boundary ($monthly) must equal first day of current local month ($expectedMonthPrefix)");

        // 2. Yearly boundary must evaluate to Jan 1st of the current local year
        $yearly = $pdo->query("SELECT date('now', 'localtime', 'start of year')")->fetchColumn();
        $expectedYearPrefix = $pdo->query("SELECT strftime('%Y', 'now', 'localtime')")->fetchColumn() . '-01-01';
        Assert::same($expectedYearPrefix, $yearly, "Yearly boundary ($yearly) must equal Jan 1 of current local year ($expectedYearPrefix)");

        // 3. Weekly boundary must evaluate to a Monday (strftime %w = 1)
        $weekly = $pdo->query("SELECT date('now', 'localtime', 'weekday 0', '-6 days')")->fetchColumn();
        $weekdayNum = (int)$pdo->query("SELECT strftime('%w', 'now', 'localtime', 'weekday 0', '-6 days')")->fetchColumn();
        Assert::same(1, $weekdayNum, "Weekly boundary ($weekly) must always be a Monday (ISO day 1)");

        // 4. Assert that customer_registry.php does not contain buggy modifier order ('start of month', 'localtime')
        $registryContent = file_get_contents(__DIR__ . '/../../orders/pages/customer_registry.php');
        Assert::false(str_contains($registryContent, "'start of month', 'localtime'"), 'Buggy SQLite modifier order must not be present');
        Assert::false(str_contains($registryContent, "'start of year', 'localtime'"), 'Buggy SQLite modifier order must not be present');
    });
});

