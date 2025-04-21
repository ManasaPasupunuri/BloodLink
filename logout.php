
<?php
require_once 'config.php';

// Log the logout activity if user is logged in
if (isLoggedIn()) {
    $userId = $_SESSION['user_id'];
    $ip = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, ip_address, user_agent) VALUES (?, 'logout', 'users', ?, ?)");
    $stmt->execute([$userId, $ip, $userAgent]);
    
    // Clear remember me cookie if it exists
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        
        // Delete token from database
        $stmt = $conn->prepare("DELETE FROM auth_tokens WHERE token = ?");
        $stmt->execute([$token]);
        
        // Expire the cookie
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }
}

// Destroy session
session_unset();
session_destroy();

// Redirect to homepage
header("Location: index.html");
exit;
?>