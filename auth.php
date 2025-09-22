<?php
// auth.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Call this at the top of any protected page.
function require_auth(): void {
    if (!($_SESSION['rc_tournament_authed'] ?? false)) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header("Location: /login.php?redirect={$redirect}");
        exit;
    }
}

// Helper for navbar.php
function is_logged_in(): bool {
    return ($_SESSION['rc_tournament_authed'] ?? false) === true;
}
