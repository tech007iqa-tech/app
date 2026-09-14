<?php
$hash = '$2y$10$QtukD50aCLXlLUuSP9WkDO.UC2t2sVpOP1zaMTjiKrINot2e1uhm2';
$seq_key = 'C2F6C631B6FFE9638BB8FCD8C43B6B47132BD90F3D9F6D533BA17DD720E67EFA';

$candidates = [
    '123',
    'admin',
    'password',
    '123' . $seq_key,
    'admin' . $seq_key,
];

foreach ($candidates as $c) {
    if (password_verify($c, $hash)) {
        echo "MATCH: '$c'\n";
        exit();
    }
}
echo "No match found for standard passwords.\n";
?>
