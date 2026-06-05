<?php
/* Logout: end session and redirect to login */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/database.php';

// Log audit before clearing the session
if (isset($_SESSION['user_id'])) {
    // Function = logAudit(), Class = data/database.php
    logAudit($pdo, (int)$_SESSION['user_id'], 'logout', 'User Logout', 'User logged out');
}

// Clear all session variables
$_SESSION = [];

// Destroy the session cookie
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']
    );
}

session_destroy();
header('Location: ../LoginAndRegister/LoginPage.php');
exit;