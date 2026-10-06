<?php
/**
 * Security & Cryptography Unit Tests
 */

require_once __DIR__ . '/../../core/Security.php';
require_once __DIR__ . '/../../core/Auth.php';

TestRunner::suite('Security & Cryptography Subsystem', function() {

    TestRunner::test('CSRF token is generated as a secure 64-char hex string', function() {
        $token = Security::getToken();
        Assert::true(is_string($token), 'Token should be a string');
        Assert::same(64, strlen($token), 'Token length must be 64 characters');
        Assert::matches('/^[a-f0-9]{64}$/i', $token, 'Token must be valid hex');
    });

    TestRunner::test('CSRF validation succeeds for valid token and rejects invalid/empty', function() {
        $token = Security::getToken();
        Assert::true(Security::validate($token), 'Valid token must be accepted');
        Assert::false(Security::validate('invalid_token_12345'), 'Arbitrary token must be rejected');
        Assert::false(Security::validate(''), 'Empty token must be rejected');
        Assert::false(Security::validate(null), 'Null token must be rejected');
    });

    TestRunner::test('Security::sanitize_float properly handles currency strings & punctuation', function() {
        Assert::same(1234.56, Security::sanitize_float('$1,234.56'));
        Assert::same(99.0, Security::sanitize_float('  $99.00 USD '));
        Assert::same(-50.25, Security::sanitize_float('-$50.25'));
        Assert::same(0.0, Security::sanitize_float('invalid_text'));
    });

    TestRunner::test('Security::sanitize_int properly handles commas & whitespace', function() {
        Assert::same(12500, Security::sanitize_int('12,500'));
        Assert::same(42, Security::sanitize_int(' 42 items '));
        Assert::same(0, Security::sanitize_int('zero'));
    });

    TestRunner::test('PPP Sequence Key generation produces 64-hex 256-bit uppercase keys', function() {
        $key = Security::generate_ppp_key();
        Assert::same(64, strlen($key), 'Key length must be 64 characters');
        Assert::matches('/^[0-9A-F]{64}$/', $key, 'Key must be uppercase hex');
    });

    TestRunner::test('PPP passcodes generator produces 125 passcodes with exact requested cell length', function() {
        $key = "C2F6C631B6FFE9638BB8FCD8C43B6B47132BD90F3D9F6D533BA17DD720E67EFA";
        $passcodes = Security::generate_ppp_passcodes($key, 6);
        Assert::count(125, $passcodes, 'Must generate exactly 125 passcodes (25 rows x 5 cols)');
        foreach ($passcodes as $code) {
            Assert::same(6, strlen($code), 'Each cell code must match requested cell length');
        }
    });

    TestRunner::test('PPP passcode generator is 100% deterministic (reproducible)', function() {
        $key = "A1B2C3D4E5F60718293A4B5C6D7E8F90112233445566778899AABBCCDDEEFF00";
        $run1 = Security::generate_ppp_passcodes($key, 6);
        $run2 = Security::generate_ppp_passcodes($key, 6);
        Assert::same($run1, $run2, 'Same sequence key must yield identical passcodes every time');
    });

    TestRunner::test('PPP passcode generator rejects malformed or too short sequence keys', function() {
        Assert::same([], Security::generate_ppp_passcodes('short', 6), 'Short key must return empty array');
        Assert::same([], Security::generate_ppp_passcodes('', 6), 'Empty key must return empty array');
    });

    TestRunner::test('Password policy enforces minimum 25 characters and complexity', function() {
        $err = '';
        Assert::false(Security::validatePassword('Short1!', $err, 25), 'Password shorter than 25 chars must fail');
        Assert::contains('between 25 and 125 characters', $err);

        // Weak 25-char password (no numbers)
        Assert::false(Security::validatePassword('Abcdefghijklmnopqrstuvwxy!', $err, 25), 'Missing digit must fail');
        Assert::contains('number', $err);

        // Weak 25-char password (no symbols)
        Assert::false(Security::validatePassword('Abcdefghijklmnopqrstuvwx12', $err, 25), 'Missing special char must fail');
        Assert::contains('special character', $err);

        // Strong compliant password
        Assert::true(Security::validatePassword('IQA-Warehouse-Metal-2026!Secure', $err, 25), 'Compliant password must pass');
    });

    TestRunner::test('AuthGuard allows non-interactive CLI environments without redirect', function() {
        Assert::true(AuthGuard::check(), 'AuthGuard must pass under CLI');
    });
});
