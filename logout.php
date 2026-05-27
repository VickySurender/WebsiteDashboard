<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    // Log activity
    $userId = $_SESSION['user_id'];
    $ip     = $_SERVER['REMOTE_ADDR'];
    $act    = 'Logged out';
    $stmt   = $conn->prepare('INSERT INTO activity_log (user_id, action, ip_address) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $userId, $act, $ip);
    $stmt->execute();
}

// Clear session and cookies
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
setcookie('remember_token', '', time() - 3600, '/');

setFlash('success', 'You have been securely logged out.');
redirect('login.php');
