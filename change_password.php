<?php
// =====================================================
// change_password.php - หน้าเปลี่ยนรหัสผ่าน
// =====================================================

// Include functions และ require login
require_once 'includes/functions.php';
requireLogin();

// Set page variables
$custom_page_title = 'Change Password';
$page_header = true;
$page_subtitle = 'Update your account password';
$breadcrumb = [
    ['name' => 'Change Password']
];

// Variables
$errors = [];
$success_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = cleanInput($_POST['current_password'] ?? '');
    $new_password = cleanInput($_POST['new_password'] ?? '');
    $confirm_password = cleanInput($_POST['confirm_password'] ?? '');
    
    // Validation
    if (empty($current_password)) {
        $errors[] = 'Please enter your current password';
    }
    
    if (empty($new_password)) {
        $errors[] = 'Please enter a new password';
    } elseif (strlen($new_password) < 6) {
        $errors[] = 'New password must be at least 6 characters long';
    }
    
    if (empty($confirm_password)) {
        $errors[] = 'Please confirm your new password';
    } elseif ($new_password !== $confirm_password) {
        $errors[] = 'New password and confirm password do not match';
    }
    
    // Check if current password is correct
    if (empty($errors)) {
        $user = fetchOne("SELECT password FROM users WHERE id = ?", [$_SESSION['user_id']]);
        
        if (!$user || !password_verify($current_password, $user['password'])) {
            $errors[] = 'Current password is incorrect';
        }
    }
    
    // Update password if no errors
    if (empty($errors)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $result = execute("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?", 
                         [$hashed_password, $_SESSION['user_id']]);
        
        if ($result) {
            $success_message = 'Password changed successfully!';
            
            // Log the activity
            error_log("Password changed for user ID: {$_SESSION['user_id']} - Username: {$_SESSION['username']}");
        } else {
            $errors[] = 'Failed to update password. Please try again.';
        }
    }
}

// Additional CSS for form styling
$additional_css = "
<style>
.password-form {
    max-width: 500px;
    margin: 0 auto;
}

.form-control {
    border-radius: 10px;
    border: 2px solid #e9ecef;
    padding: 12px 15px;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    border: none;
    border-radius: 10px;
    padding: 12px 30px;
    font-weight: 600;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
}

.password-requirements {
    font-size: 0.85rem;
    color: #6c757d;
    margin-top: 5px;
}

.password-requirements ul {
    padding-left: 20px;
    margin-bottom: 0;
}

.security-info {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 30px;
}

.security-tips {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 10px;
    padding: 15px;
    margin-top: 20px;
}

.password-strength {
    height: 5px;
    border-radius: 3px;
    margin-top: 5px;
    transition: all 0.3s ease;
}

.strength-weak { background: #dc3545; }
.strength-medium { background: #ffc107; }
.strength-strong { background: #28a745; }
</style>
";

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Security Information -->
            <div class="card mb-4">
                <div class="card-body security-info">
                    <h5 class="mb-3">
                        <i class="fas fa-shield-alt text-primary me-2"></i>
                        Password Security
                    </h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2"><strong>Current User:</strong> <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                            <p class="mb-2"><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                            <p class="mb-0"><strong>Role:</strong> <?php echo ucfirst($_SESSION['user_role']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-2"><i class="fas fa-clock text-muted me-1"></i> Change your password regularly</p>
                            <p class="mb-2"><i class="fas fa-eye-slash text-muted me-1"></i> Use a strong, unique password</p>
                            <p class="mb-0"><i class="fas fa-user-shield text-muted me-1"></i> Keep your password confidential</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Change Password Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-key me-2"></i>
                        Change Password
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Show errors -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Please fix the following errors:</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Show success message -->
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="password-form">
                        <form method="POST" id="changePasswordForm" autocomplete="off">
                            <!-- Current Password -->
                            <div class="mb-4">
                                <label for="current_password" class="form-label">
                                    <i class="fas fa-lock me-1"></i> Current Password
                                </label>
                                <div class="input-group">
                                    <input type="password" 
                                           class="form-control" 
                                           id="current_password" 
                                           name="current_password" 
                                           required
                                           autocomplete="current-password">
                                    <button class="btn btn-outline-secondary" 
                                            type="button" 
                                            onclick="togglePassword('current_password')">
                                        <i class="fas fa-eye" id="current_password_icon"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- New Password -->
                            <div class="mb-4">
                                <label for="new_password" class="form-label">
                                    <i class="fas fa-key me-1"></i> New Password
                                </label>
                                <div class="input-group">
                                    <input type="password" 
                                           class="form-control" 
                                           id="new_password" 
                                           name="new_password" 
                                           required
                                           autocomplete="new-password"
                                           onkeyup="checkPasswordStrength()">
                                    <button class="btn btn-outline-secondary" 
                                            type="button" 
                                            onclick="togglePassword('new_password')">
                                        <i class="fas fa-eye" id="new_password_icon"></i>
                                    </button>
                                </div>
                                <div class="password-strength" id="passwordStrength"></div>
                                <div class="password-requirements">
                                    <strong>Password Requirements:</strong>
                                    <ul>
                                        <li>At least 6 characters long</li>
                                        <li>Include both letters and numbers</li>
                                        <li>Use special characters for extra security</li>
                                        <li>Avoid common words or personal information</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Confirm Password -->
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-check-circle me-1"></i> Confirm New Password
                                </label>
                                <div class="input-group">
                                    <input type="password" 
                                           class="form-control" 
                                           id="confirm_password" 
                                           name="confirm_password" 
                                           required
                                           autocomplete="new-password"
                                           onkeyup="checkPasswordMatch()">
                                    <button class="btn btn-outline-secondary" 
                                            type="button" 
                                            onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye" id="confirm_password_icon"></i>
                                    </button>
                                </div>
                                <div id="passwordMatch" class="mt-2"></div>
                            </div>

                            <!-- Submit Buttons -->
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="fas fa-save me-2"></i>Change Password
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Security Tips -->
                    <div class="security-tips">
                        <h6 class="mb-2">
                            <i class="fas fa-lightbulb text-warning me-2"></i>
                            Security Tips
                        </h6>
                        <ul class="mb-0">
                            <li>Never share your password with anyone</li>
                            <li>Use different passwords for different accounts</li>
                            <li>Consider using a password manager</li>
                            <li>Log out from shared computers</li>
                            <li>Report any suspicious account activity immediately</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password visibility toggle
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '_icon');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Password strength checker
function checkPasswordStrength() {
    const password = document.getElementById('new_password').value;
    const strengthBar = document.getElementById('passwordStrength');
    
    let strength = 0;
    
    // Length check
    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    
    // Character variety checks
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    // Display strength
    strengthBar.style.width = '100%';
    
    if (strength <= 2) {
        strengthBar.className = 'password-strength strength-weak';
    } else if (strength <= 4) {
        strengthBar.className = 'password-strength strength-medium';
    } else {
        strengthBar.className = 'password-strength strength-strong';
    }
    
    if (password.length === 0) {
        strengthBar.style.width = '0%';
    }
}

// Password match checker
function checkPasswordMatch() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const matchDiv = document.getElementById('passwordMatch');
    
    if (confirmPassword.length === 0) {
        matchDiv.innerHTML = '';
        return;
    }
    
    if (newPassword === confirmPassword) {
        matchDiv.innerHTML = '<small class="text-success"><i class="fas fa-check me-1"></i>Passwords match</small>';
    } else {
        matchDiv.innerHTML = '<small class="text-danger"><i class="fas fa-times me-1"></i>Passwords do not match</small>';
    }
}

// Form validation
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New password and confirm password do not match!');
        return false;
    }
    
    if (newPassword.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long!');
        return false;
    }
    
    // Show loading state
    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Changing Password...';
    submitBtn.disabled = true;
    
    // Re-enable button after 10 seconds (in case of error)
    setTimeout(function() {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 10000);
});

// Clear form on successful change
<?php if ($success_message): ?>
setTimeout(function() {
    document.getElementById('changePasswordForm').reset();
    document.getElementById('passwordStrength').style.width = '0%';
    document.getElementById('passwordMatch').innerHTML = '';
}, 3000);
<?php endif; ?>

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+S to submit form
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        document.getElementById('changePasswordForm').submit();
    }
    
    // Escape to go back
    if (e.key === 'Escape') {
        if (confirm('Are you sure you want to go back? Any unsaved changes will be lost.')) {
            window.location.href = 'dashboard.php';
        }
    }
});

// Focus on first field when page loads
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('current_password').focus();
});
</script>

<?php include 'includes/footer.php'; ?>