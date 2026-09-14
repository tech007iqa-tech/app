<?php
$sequence_key = "1DBED7E3ED8D50D9EDCA11A749BDB7A835473D5C75F6F90B065A610213D492C7";
$cell_len = 25;
$key_bin = hex2bin($sequence_key);

function generate_ppp_passcodes_test($sequence_key, $alphabet, $cell_len = 25) {
    $key_bin = hex2bin($sequence_key);
    $passcodes = [];

    for ($i = 0; $i < 125; $i++) {
        $ciphertext = "";
        $blocks_needed = (int)ceil(($cell_len * 6) / 128.0);
        for ($b = 0; $b < $blocks_needed; $b++) {
            $counter_bin = pack('P', $i) . pack('P', $b);
            $ciphertext .= openssl_encrypt($counter_bin, 'aes-256-ecb', $key_bin, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        }

        $passcode = "";
        $bit_buffer = 0;
        $bit_count = 0;
        $byte_index = 0;
        $cipher_len = strlen($ciphertext);

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
        $passcodes[] = $passcode;
    }
    return $passcodes;
}

$alphabet_settings = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!#%+=:?@';
$alphabet_test = '!#%+23456789:=?@ABCDEFGHJKLMNPRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

$codes_settings = generate_ppp_passcodes_test($sequence_key, $alphabet_settings);
$codes_test = generate_ppp_passcodes_test($sequence_key, $alphabet_test);

echo "Settings Alphabet Row 1 (first 5 cell values concatenated):\n";
echo implode("", array_slice($codes_settings, 0, 5)) . "\n\n";

echo "Test Alphabet Row 1 (first 5 cell values concatenated):\n";
echo implode("", array_slice($codes_test, 0, 5)) . "\n\n";
