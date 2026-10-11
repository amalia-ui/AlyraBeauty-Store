<?php
function post_string(string $key, int $max = 255): string {
    $value = trim((string)($_POST[$key] ?? ''));
    if (mb_strlen($value) > $max) {
        throw new InvalidArgumentException("Kolom {$key} terlalu panjang.");
    }
    return $value;
}
function positive_int(string $key): int {
    $value = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT);
    if ($value === false || $value === null || $value <= 0) {
        throw new InvalidArgumentException('Jumlah harus berupa bilangan bulat lebih dari nol.');
    }
    return $value;
}
function nonnegative_number(string $key): float {
    $raw = $_POST[$key] ?? null;
    if (!is_numeric($raw) || (float)$raw < 0) {
        throw new InvalidArgumentException('Harga dan nilai stok minimum tidak boleh negatif.');
    }
    return (float)$raw;
}
