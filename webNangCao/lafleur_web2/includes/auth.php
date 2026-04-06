<?php
require_once __DIR__ . '/config.php';

function session_init(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('lf_session');
        session_start();
    }
}

// ---- CUSTOMER AUTH ----
function auth_user(): ?array {
    session_init();
    return $_SESSION[SESSION_USER] ?? null;
}
function auth_check(): bool { return auth_user() !== null; }
function auth_require(string $redirect = ''): array {
    $user = auth_user();
    if (!$user) {
        $back = $redirect ?: BASE_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']);
        header('Location: ' . $back);
        exit;
    }
    return $user;
}
function auth_login_user(array $user): void {
    session_init();
    // Store minimal info (không lưu password hash vào session)
    $_SESSION[SESSION_USER] = [
        'id'       => $user['id'],
        'name'     => $user['name'],
        'email'    => $user['email'],
        'phone'    => $user['phone'] ?? '',
        'address'  => $user['address'] ?? '',
        'ward'     => $user['ward'] ?? '',
        'city'     => $user['city'] ?? 'TP.HCM',
        'role'     => $user['role'],
    ];
}
function auth_logout_user(): void {
    session_init();
    unset($_SESSION[SESSION_USER]);
}

// ---- ADMIN AUTH ----
function auth_admin(): ?array {
    session_init();
    return $_SESSION[SESSION_ADMIN] ?? null;
}
function auth_admin_check(): bool { return auth_admin() !== null; }
function auth_admin_require(): array {
    $admin = auth_admin();
    if (!$admin) {
        header('Location: ' . ADMIN_URL . '/admin_login.php');
        exit;
    }
    return $admin;
}
function auth_login_admin(array $user): void {
    session_init();
    $_SESSION[SESSION_ADMIN] = [
        'id'   => $user['id'],
        'name' => $user['name'],
        'email'=> $user['email'],
        'role' => $user['role'],
    ];
}
function auth_logout_admin(): void {
    session_init();
    unset($_SESSION[SESSION_ADMIN]);
}
