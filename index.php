<?php
// =====================================================
// index.php - หน้าแรกสุดของระบบ
// Redirect ไป login หรือ dashboard ตามสถานะการล็อกอิน
// =====================================================

// เริ่ม session
session_start();

// Include functions
require_once 'includes/functions.php';

// ตรวจสอบสถานะการล็อกอิน
if (isLoggedIn()) {
    // ถ้าล็อกอินแล้ว ไปหน้า dashboard
    redirect('dashboard.php');
} else {
    // ถ้ายังไม่ล็อกอิน ไปหน้า login
    redirect('login.php');
}

// ไม่ควรมาถึงจุดนี้ แต่เผื่อไว้
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Freight Pro System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .loading-container {
            text-align: center;
            color: white;
        }
        .spinner {
            font-size: 3rem;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="loading-container">
        <div class="spinner">
            <i class="fas fa-ship"></i>
        </div>
        <h3 class="mt-3">Freight Pro System</h3>
        <p>Loading...</p>
        <small class="text-light opacity-75">
            Please wait while we redirect you to the appropriate page.
        </small>
    </div>

    <script>
        // JavaScript redirect เผื่อ PHP redirect ไม่ทำงาน
        setTimeout(function() {
            <?php if (isLoggedIn()): ?>
                window.location.href = 'dashboard.php';
            <?php else: ?>
                window.location.href = 'login.php';
            <?php endif; ?>
        }, 1500);
    </script>
</body>
</html>