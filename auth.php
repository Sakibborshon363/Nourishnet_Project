<?php

session_start();


function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}


function requireRole(string $role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header('Location: index.php?error=access_denied');
        exit;
    }
}

// ── বর্তমান user কে login করাও ────────────────────────────
function loginUser(array $user): void {
    session_regenerate_id(true); // Session fixation প্রতিরোধ
    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['org_name']  = $user['org_name'] ?? '';
}

// ── Logout ────────────────────────────────────────────────
function logoutUser(): void {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

// ── Helper: current user info ────────────────────────────
function currentUser(): array {
    return [
        'id'       => $_SESSION['user_id']   ?? null,
        'name'     => $_SESSION['full_name'] ?? 'Guest',
        'role'     => $_SESSION['role']      ?? null,
        'org_name' => $_SESSION['org_name']  ?? '',
    ];
}

// ── Helper: check if logged in ───────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}
?>
