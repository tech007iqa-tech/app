<?php
/**
 * IQA Metal Warehouse Systems - Test Suite Master CLI Runner
 *
 * Usage:
 *   php tests/run.php
 *   & 'c:\xampp\php\php.exe' tests/run.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Initialize CLI session prior to any output to avoid header warnings
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/TestRunner.php';

// Display Banner
TestRunner::writeln();
TestRunner::writeln(TestRunner::color("╔═══════════════════════════════════════════════════════════════════╗", 'blue', true));
TestRunner::writeln(TestRunner::color("║    IQA Metal Warehouse Systems — Automated Health & Test Suite    ║", 'cyan', true));
TestRunner::writeln(TestRunner::color("║    Strict Zero-Dependency CLI Harness • PHP " . PHP_VERSION . "            ║", 'blue'));
TestRunner::writeln(TestRunner::color("╚═══════════════════════════════════════════════════════════════════╝", 'blue', true));

TestRunner::init();

// Load Test Suites
require_once __DIR__ . '/Unit/SecurityTest.php';
require_once __DIR__ . '/Unit/ApiResponseTest.php';
require_once __DIR__ . '/Integration/DatabaseHealthTest.php';
require_once __DIR__ . '/Integration/SchemaRegressionTest.php';
require_once __DIR__ . '/Integration/InvariantsTest.php';

// Generate Summary and Exit with Appropriate Code
$exitCode = TestRunner::summary();
exit($exitCode);
