<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}
function require_login(): void {
    if (!current_user()) {
        header('Location: index.php?page=login');
        exit;
    }
}
function require_admin(): void {
    require_login();
    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('403 Forbidden — halaman ini hanya dapat diakses Admin.');
    }
}
function is_admin(): bool {
    return (($_SESSION['user']['role'] ?? '') === 'admin');
}
