<?php
$hash = '$2y$10$QtukD50aCLXlLUuSP9WkDO.UC2t2sVpOP1zaMTjiKrINot2e1uhm2';
$seq_key = 'C2F6C631B6FFE9638BB8FCD8C43B6B47132BD90F3D9F6D533BA17DD720E67EFA';
$cell_len = 25; // 125 / 5

function gen_single_block($seq_key, $alphabet, $cell_len) {
    $key_bin = hex2bin($seq_key);
    $passcodes = [];
    for ($i = 0; $i < 250; $i++) {
        $counter_bin = pack('P', $i) . pack('P', 0);
        $ciphertext = openssl_encrypt($counter_bin, 'aes-256-ecb', $key_bin, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        $passcode = '';
        $bit_buffer = 0;
        $bit_count = 0;
        $byte_index = 0;
        for ($char_idx = 0; $char_idx < $cell_len; $char_idx++) {
            while ($bit_count < 6 && $byte_index < 16) {
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

function gen_multi_block($seq_key, $alphabet, $cell_len) {
    $key_bin = hex2bin($seq_key);
    $passcodes = [];
    for ($i = 0; $i < 250; $i++) {
        $ciphertext = "";
        $blocks_needed = (int)ceil(($cell_len * 6) / 128.0);
        for ($b = 0; $b < $blocks_needed; $b++) {
            $counter_bin = pack('P', $i) . pack('P', $b);
            $ciphertext .= openssl_encrypt($counter_bin, 'aes-256-ecb', $key_bin, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        }
        $passcode = '';
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

$alpha_settings = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!#%+=:?@';
$alpha_test = '!#%+23456789:=?@ABCDEFGHJKLMNPRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

$grids = [
    'single_settings' => gen_single_block($seq_key, $alpha_settings, $cell_len),
    'single_test'     => gen_single_block($seq_key, $alpha_test, $cell_len),
    'multi_settings'  => gen_multi_block($seq_key, $alpha_settings, $cell_len),
    'multi_test'      => gen_multi_block($seq_key, $alpha_test, $cell_len),
];

foreach ($grids as $name => $codes) {
    for ($r = 0; $r < 50; $r++) {
        $pass = '';
        for ($c = 0; $c < 5; $c++) {
            $pass .= $codes[$r * 5 + $c];
        }
        if (password_verify($pass . $seq_key, $hash)) {
            echo "MATCH FOUND: Grid = $name, CellLen = $cell_len, Row = " . ($r + 1) . ", Passcode = $pass" . PHP_EOL;
            exit();
        }
        if (password_verify($pass, $hash)) {
            echo "MATCH FOUND (no seq key concat): Grid = $name, CellLen = $cell_len, Row = " . ($r + 1) . ", Passcode = $pass" . PHP_EOL;
            exit();
        }
    }
}

echo "No match found for cell_len = 25 up to row 50." . PHP_EOL;
?>
