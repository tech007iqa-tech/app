<?php
header('Content-Type: text/plain');
$sequence_key = "80D0393458C82A885082E3D3D790567DFBF65A69ECBC9B3A384EA6288E646237";
$cell_len = 25;

$alphabet = '!#%+23456789:=?@ABCDEFGHJKLMNPRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
$key_bin = hex2bin($sequence_key);

echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHP OS: " . PHP_OS . "\n";
echo "PHP INT SIZE: " . PHP_INT_SIZE . "\n";

for ($i = 0; $i < 5; $i++) {
    $ciphertext = "";
    $blocks_needed = (int)ceil(($cell_len * 6) / 128.0);
    echo "i: $i, cell_len: $cell_len, blocks_needed: $blocks_needed\n";
    for ($b = 0; $b < $blocks_needed; $b++) {
        $counter_bin = pack('P', $i) . pack('P', $b);
        echo "  b: $b, counter_bin len: " . strlen($counter_bin) . "\n";
        $block_cipher = openssl_encrypt($counter_bin, 'aes-256-ecb', $key_bin, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        if ($block_cipher === false) {
            echo "    Block $b encryption failed: " . openssl_error_string() . "\n";
        } else {
            echo "    Block $b encryption succeeded, cipher len: " . strlen($block_cipher) . "\n";
            $ciphertext .= $block_cipher;
        }
    }

    $passcode = "";
    $bit_buffer = 0;
    $bit_count = 0;
    $byte_index = 0;
    $cipher_len = strlen($ciphertext);
    echo "  Total cipher_len: $cipher_len\n";

    for ($char_idx = 0; $char_idx < $cell_len; $char_idx++) {
        while ($bit_count < 6 && $byte_index < $cipher_len) {
            $bit_buffer = ($bit_buffer << 8) | ord($ciphertext[$byte_index]);
            $byte_index++;
            $bit_count += 8;
        }
        if ($bit_count >= 6) {
            $shift = $bit_count - 6;
            $idx = ($bit_buffer >> $shift) & 0x3F;
            $bit_count = $shift;
            $passcode .= $alphabet[$idx];
        } else {
            $passcode .= $alphabet[0];
        }
    }
    echo "  Passcode: $passcode\n\n";
}
