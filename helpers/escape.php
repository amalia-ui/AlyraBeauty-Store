<?php
function e(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
function rupiah(mixed $value): string {
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
}
