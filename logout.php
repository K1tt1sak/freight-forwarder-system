<?php
// =====================================================
// logout.php - User Logout
// =====================================================

require_once 'includes/functions.php';

// Log the logout activity (optional)
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    
    // You can log the logout activity to database if needed
    // execute("INSERT INTO user_logs (user_id, action, ip_address, created_at) VALUES (?, 'logout', ?, NOW())", 
    //         [$user_id, $_SERVER['REMOTE_ADDR']]);
}

// Destroy session and redirect
logoutUser();
?>