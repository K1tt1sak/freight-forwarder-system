<?php
// =====================================================
// profile.php - User Profile Management
// =====================================================

// Include header และ functions
$custom_page_title = 'My Profile';
$page_header = true;
$page_subtitle = 'Manage your account information and preferences';

// Set breadcrumb
$breadcrumb = [
    ['name' => 'My Profile']
];

$additional_css = "
<style>
.profile-header {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.profile-header::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 200px;
    height: 200px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    transform: translate(50px, -50px);
}

.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 4px solid rgba(255,255,255,0.3);
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    margin: 0 auto 1rem;
    position: relative;
    z-index: 2;
}

.profile-section {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.profile-section:hover {
    transform: translateY(-2px);
}

.section-title {
    color: var(--primary-color);
    font-weight: 600;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e9ecef;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f8f9fa;
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 500;
    color: #6c757d;
    min-width: 120px;
}

.info-value {
    flex: 1;
    text-align: right;
    color: #333;
}

.edit-form {
    display: none;
    background: #f8f9fa;
    border-radius: 10px;
    padding: 1.5rem;
    margin-top: 1rem;
}

.edit-form.active {
    display: block;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: white;
    border-radius: 10px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: 1px solid #e9ecef;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary-color);
    display: block;
}

.stat-label {
    color: #6c757d;
    font-size: 0.9rem;
    margin-top: 0.5rem;
}

.activity-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f8f9fa;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    font-size: 0.9rem;
}

.activity-content {
    flex: 1;
}

.activity-time {
    color: #6c757d;
    font-size: 0.8rem;
}

.role-badge {
    display: inline-block;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.role-admin {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
}

.role-manager {
    background: linear-gradient(135deg, #fd7e14, #e55a4e);
    color: white;
}

.role-staff {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
}

.role-viewer {
    background: linear-gradient(135deg, #6c757d, #545b62);
    color: white;
}

.change-password-section {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border: 1px solid #dee2e6;
}

.security-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
    border-bottom: 1px solid #e9ecef;
}

.security-item:last-child {
    border-bottom: none;
}

.btn-edit {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    border: none;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    transition: all 0.3s ease;
}

.btn-edit:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
    color: white;
}

.form-floating > label {
    color: #6c757d;
}

.avatar-upload {
    position: absolute;
    bottom: 0;
    right: 0;
    background: white;
    border-radius: 50%;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
}

.avatar-upload:hover {
    transform: scale(1.1);
}
</style>
";

include 'includes/header.php';

// Check login
requireLogin();

// Get current user data
$user_id = $_SESSION['user_id'];
$user = fetchOne("
    SELECT u.*, 
           (SELECT COUNT(*) FROM jobs WHERE created_by = u.id) as total_jobs,
           (SELECT COUNT(*) FROM jobs WHERE created_by = u.id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as jobs_this_month,
           (SELECT COUNT(*) FROM invoices WHERE created_by = u.id) as total_invoices,
           (SELECT COUNT(*) FROM quotations WHERE created_by = u.id) as total_quotations
    FROM users u 
    WHERE u.id = ?
", [$user_id]);

if (!$user) {
    $_SESSION['error_message'] = 'User not found.';
    redirect('dashboard.php');
}

// Get recent activity
$recent_activities = fetchAll("
    SELECT 'job' as type, 'Created job' as action, job_no as reference, created_at
    FROM jobs WHERE created_by = ? 
    UNION ALL
    SELECT 'invoice' as type, 'Created invoice' as action, invoice_no as reference, created_at
    FROM invoices WHERE created_by = ?
    UNION ALL
    SELECT 'quotation' as type, 'Created quotation' as action, quotation_no as reference, created_at
    FROM quotations WHERE created_by = ?
    ORDER BY created_at DESC
    LIMIT 10
", [$user_id, $user_id, $user_id]);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_profile') {
            // Update basic profile information
            $name = cleanInput($_POST['name']);
            $email = cleanInput($_POST['email']);
            $phone = cleanInput($_POST['phone']);
            
            // Validation
            if (empty($name)) {
                throw new Exception('Name is required.');
            }
            
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Please enter a valid email address.');
            }
            
            // Check if email is already used by another user
            if (!empty($email)) {
                $email_check = fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $user_id]);
                if ($email_check) {
                    throw new Exception('This email is already used by another user.');
                }
            }
            
            // Update user
            $result = execute("
                UPDATE users SET 
                    name = ?, 
                    email = ?, 
                    phone = ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [$name, $email, $phone, $user_id]);
            
            if ($result) {
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['success_message'] = 'Profile updated successfully.';
            } else {
                throw new Exception('Failed to update profile.');
            }
            
        } elseif ($action === 'change_password') {
            // Change password
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Validation
            if (empty($current_password)) {
                throw new Exception('Current password is required.');
            }
            
            if (empty($new_password)) {
                throw new Exception('New password is required.');
            }
            
            if (strlen($new_password) < 6) {
                throw new Exception('New password must be at least 6 characters long.');
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception('New password and confirmation do not match.');
            }
            
            // Verify current password
            if (!password_verify($current_password, $user['password'])) {
                throw new Exception('Current password is incorrect.');
            }
            
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $result = execute("
                UPDATE users SET 
                    password = ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [$hashed_password, $user_id]);
            
            if ($result) {
                $_SESSION['success_message'] = 'Password changed successfully.';
            } else {
                throw new Exception('Failed to change password.');
            }
        }
        
        redirect('profile.php');
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Refresh user data after potential updates
$user = fetchOne("
    SELECT u.*, 
           (SELECT COUNT(*) FROM jobs WHERE created_by = u.id) as total_jobs,
           (SELECT COUNT(*) FROM jobs WHERE created_by = u.id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as jobs_this_month,
           (SELECT COUNT(*) FROM invoices WHERE created_by = u.id) as total_invoices,
           (SELECT COUNT(*) FROM quotations WHERE created_by = u.id) as total_quotations
    FROM users u 
    WHERE u.id = ?
", [$user_id]);
?>

<div class="container-fluid">
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="row align-items-center">
            <div class="col-md-3 text-center">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                    <div class="avatar-upload" title="Change Avatar (Coming Soon)">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-9">
                <h2 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h2>
                <p class="mb-2 opacity-75">
                    <i class="fas fa-user-tag me-2"></i>
                    <span class="role-badge role-<?php echo $user['role']; ?>">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                </p>
                <p class="mb-2 opacity-75">
                    <i class="fas fa-at me-2"></i>
                    <?php echo htmlspecialchars($user['email'] ?: 'No email set'); ?>
                </p>
                <p class="mb-0 opacity-75">
                    <i class="fas fa-calendar me-2"></i>
                    Member since <?php echo formatDateThai($user['created_at'], 'M Y'); ?>
                    <?php if ($user['last_login']): ?>
                        | Last login: <?php echo formatDateThai($user['last_login'], 'd/m/Y H:i'); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column: Profile Information -->
        <div class="col-lg-8">
            <!-- Basic Information -->
            <div class="profile-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="section-title mb-0">
                        <i class="fas fa-user-circle me-2"></i>
                        Personal Information
                    </h5>
                    <button class="btn btn-edit" onclick="toggleEdit('profile')">
                        <i class="fas fa-edit me-1"></i>Edit
                    </button>
                </div>

                <div id="profile-view">
                    <div class="info-item">
                        <span class="info-label">Full Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Username</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['email'] ?: 'Not set'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['phone'] ?: 'Not set'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Role</span>
                        <span class="info-value">
                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Status</span>
                        <span class="info-value">
                            <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </span>
                    </div>
                </div>

                <!-- Edit Form -->
                <div id="profile-edit" class="edit-form">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                    <label for="name">Full Name *</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="username_display" 
                                           value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                    <label for="username_display">Username (Cannot change)</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>">
                                    <label for="email">Email Address</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user['phone']); ?>">
                                    <label for="phone">Phone Number</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-1"></i>Save Changes
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleEdit('profile')">
                                <i class="fas fa-times me-1"></i>Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Security Settings -->
            <div class="profile-section change-password-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="section-title mb-0">
                        <i class="fas fa-shield-alt me-2"></i>
                        Security Settings
                    </h5>
                    <button class="btn btn-edit" onclick="toggleEdit('password')">
                        <i class="fas fa-key me-1"></i>Change Password
                    </button>
                </div>

                <div id="password-view">
                    <div class="security-item">
                        <div>
                            <strong>Password</strong>
                            <br><small class="text-muted">Last changed: 
                                <?php echo formatDateThai($user['updated_at'], 'd/m/Y'); ?>
                            </small>
                        </div>
                        <span class="badge bg-success">Protected</span>
                    </div>
                    <div class="security-item">
                        <div>
                            <strong>Two-Factor Authentication</strong>
                            <br><small class="text-muted">Additional security layer</small>
                        </div>
                        <span class="badge bg-secondary">Coming Soon</span>
                    </div>
                    <div class="security-item">
                        <div>
                            <strong>Login Sessions</strong>
                            <br><small class="text-muted">Manage active sessions</small>
                        </div>
                        <span class="badge bg-info">1 Active</span>
                    </div>
                </div>

                <!-- Change Password Form -->
                <div id="password-edit" class="edit-form">
                    <form method="POST" onsubmit="return validatePasswordForm()">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="current_password" 
                                   name="current_password" required>
                            <label for="current_password">Current Password *</label>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="password" class="form-control" id="new_password" 
                                           name="new_password" required minlength="6">
                                    <label for="new_password">New Password *</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="password" class="form-control" id="confirm_password" 
                                           name="confirm_password" required minlength="6">
                                    <label for="confirm_password">Confirm New Password *</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Password must be at least 6 characters long and should contain a mix of letters and numbers.
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key me-1"></i>Change Password
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleEdit('password')">
                                <i class="fas fa-times me-1"></i>Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="profile-section">
                <h5 class="section-title">
                    <i class="fas fa-history me-2"></i>
                    Recent Activity
                </h5>

                <?php if (!empty($recent_activities)): ?>
                    <div class="activity-list">
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-<?php 
                                    echo match($activity['type']) {
                                        'job' => 'shipping-fast',
                                        'invoice' => 'file-invoice',
                                        'quotation' => 'file-contract',
                                        default => 'circle'
                                    };
                                ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div><?php echo $activity['action']; ?>: <strong><?php echo htmlspecialchars($activity['reference']); ?></strong></div>
                                <div class="activity-time"><?php echo formatDateThai($activity['created_at'], 'd/m/Y H:i'); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-clock fa-2x mb-2"></i>
                        <p>No recent activity found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Statistics and Quick Info -->
        <div class="col-lg-4">
            <!-- Statistics -->
            <div class="profile-section">
                <h5 class="section-title">
                    <i class="fas fa-chart-bar me-2"></i>
                    My Statistics
                </h5>

                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-number"><?php echo number_format($user['total_jobs']); ?></span>
                        <div class="stat-label">Total Jobs Created</div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo number_format($user['jobs_this_month']); ?></span>
                        <div class="stat-label">Jobs This Month</div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo number_format($user['total_invoices']); ?></span>
                        <div class="stat-label">Invoices Created</div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo number_format($user['total_quotations']); ?></span>
                        <div class="stat-label">Quotations Created</div>
                    </div>
                </div>
            </div>

            <!-- Account Information -->
            <div class="profile-section">
                <h5 class="section-title">
                    <i class="fas fa-info-circle me-2"></i>
                    Account Information
                </h5>

                <div class="info-item">
                    <span class="info-label">User ID</span>
                    <span class="info-value">#<?php echo $user['id']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Member Since</span>
                    <span class="info-value"><?php echo formatDateThai($user['created_at'], 'd/m/Y'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Last Updated</span>
                    <span class="info-value"><?php echo formatDateThai($user['updated_at'], 'd/m/Y H:i'); ?></span>
                </div>
                <?php if ($user['last_login']): ?>
                <div class="info-item">
                    <span class="info-label">Last Login</span>
                    <span class="info-value"><?php echo formatDateThai($user['last_login'], 'd/m/Y H:i'); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="profile-section">
                <h5 class="section-title">
                    <i class="fas fa-bolt me-2"></i>
                    Quick Actions
                </h5>

                <div class="d-grid gap-2">
                    <a href="jobs_add.php" class="btn btn-outline-primary">
                        <i class="fas fa-plus me-2"></i>Create New Job
                    </a>
                    <a href="customers_add.php" class="btn btn-outline-success">
                        <i class="fas fa-user-plus me-2"></i>Add Customer
                    </a>
                    <a href="quotations_add.php" class="btn btn-outline-info">
                        <i class="fas fa-file-contract me-2"></i>Create Quotation
                    </a>
                    <a href="reports.php" class="btn btn-outline-warning">
                        <i class="fas fa-chart-line me-2"></i>View Reports
                    </a>
                </div>
            </div>

            <!-- System Preferences -->
            <div class="profile-section">
                <h5 class="section-title">
                    <i class="fas fa-cog me-2"></i>
                    Preferences
                </h5>

                <div class="info-item">
                    <span class="info-label">Language</span>
                    <span class="info-value">English</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Timezone</span>
                    <span class="info-value">Asia/Bangkok</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date Format</span>
                    <span class="info-value">DD/MM/YYYY</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Notifications</span>
                    <span class="info-value">
                        <span class="badge bg-success">Enabled</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle edit forms
function toggleEdit(section) {
    const viewElement = document.getElementById(section + '-view');
    const editElement = document.getElementById(section + '-edit');
    
    if (editElement.classList.contains('active')) {
        editElement.classList.remove('active');
        viewElement.style.display = 'block';
    } else {
        editElement.classList.add('active');
        viewElement.style.display = 'none';
        
        // Focus on first input
        const firstInput = editElement.querySelector('input:not([readonly])');
        if (firstInput) {
            firstInput.focus();
        }
    }
}

// Validate password form
function validatePasswordForm() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        alert('New password and confirmation do not match.');
        return false;
    }
    
    if (newPassword.length < 6) {
        alert('Password must be at least 6 characters long.');
        return false;
    }
    
    // Check password strength
    const hasLetter = /[a-zA-Z]/.test(newPassword);
    const hasNumber = /\d/.test(newPassword);
    
    if (!hasLetter || !hasNumber) {
        const confirm = window.confirm('Password should contain both letters and numbers for better security. Continue anyway?');
        if (!confirm) {
            return false;
        }
    }
    
    return true;
}

// Real-time password strength indicator
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('new_password');
    const confirmInput = document.getElementById('confirm_password');
    
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            updatePasswordStrength(this.value);
        });
    }
    
    if (confirmInput) {
        confirmInput.addEventListener('input', function() {
            checkPasswordMatch();
        });
    }
});

function updatePasswordStrength(password) {
    // Create strength indicator if it doesn't exist
    let strengthIndicator = document.getElementById('password-strength');
    if (!strengthIndicator) {
        strengthIndicator = document.createElement('div');
        strengthIndicator.id = 'password-strength';
        strengthIndicator.className = 'mt-2';
        document.getElementById('new_password').parentNode.appendChild(strengthIndicator);
    }
    
    if (!password) {
        strengthIndicator.innerHTML = '';
        return;
    }
    
    let strength = 0;
    let feedback = [];
    
    // Length check
    if (password.length >= 6) strength++;
    else feedback.push('At least 6 characters');
    
    // Letter check
    if (/[a-zA-Z]/.test(password)) strength++;
    else feedback.push('Include letters');
    
    // Number check
    if (/\d/.test(password)) strength++;
    else feedback.push('Include numbers');
    
    // Special character check
    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
    else feedback.push('Include special characters');
    
    const strengthLevels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    const strengthColors = ['danger', 'warning', 'info', 'success', 'success'];
    
    const level = Math.min(strength, 4);
    strengthIndicator.innerHTML = `
        <small class="text-${strengthColors[level]}">
            <i class="fas fa-shield-alt me-1"></i>
            Password Strength: ${strengthLevels[level]}
            ${feedback.length > 0 ? ' - ' + feedback.join(', ') : ''}
        </small>
    `;
}

function checkPasswordMatch() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    let matchIndicator = document.getElementById('password-match');
    if (!matchIndicator) {
        matchIndicator = document.createElement('div');
        matchIndicator.id = 'password-match';
        matchIndicator.className = 'mt-2';
        document.getElementById('confirm_password').parentNode.appendChild(matchIndicator);
    }
    
    if (!confirmPassword) {
        matchIndicator.innerHTML = '';
        return;
    }
    
    if (newPassword === confirmPassword) {
        matchIndicator.innerHTML = '<small class="text-success"><i class="fas fa-check me-1"></i>Passwords match</small>';
    } else {
        matchIndicator.innerHTML = '<small class="text-danger"><i class="fas fa-times me-1"></i>Passwords do not match</small>';
    }
}

// Auto-save profile changes to localStorage as backup
let profileFormChanged = false;

document.querySelectorAll('#profile-edit input').forEach(input => {
    input.addEventListener('change', function() {
        profileFormChanged = true;
        saveProfileDraft();
    });
});

function saveProfileDraft() {
    const formData = {
        name: document.getElementById('name').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        timestamp: new Date().getTime()
    };
    
    localStorage.setItem('profile_draft', JSON.stringify(formData));
}

function loadProfileDraft() {
    const draft = localStorage.getItem('profile_draft');
    if (draft) {
        try {
            const data = JSON.parse(draft);
            const timeDiff = new Date().getTime() - data.timestamp;
            
            // Only load if draft is less than 1 hour old
            if (timeDiff < 3600000) {
                if (confirm('A draft of your profile changes was found. Would you like to restore it?')) {
                    document.getElementById('name').value = data.name || '';
                    document.getElementById('email').value = data.email || '';
                    document.getElementById('phone').value = data.phone || '';
                    
                    toggleEdit('profile');
                }
            }
            
            localStorage.removeItem('profile_draft');
        } catch (e) {
            console.error('Error loading profile draft:', e);
        }
    }
}

// Clear draft when form is successfully submitted
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        localStorage.removeItem('profile_draft');
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Escape key to cancel editing
    if (e.key === 'Escape') {
        const activeEdit = document.querySelector('.edit-form.active');
        if (activeEdit) {
            const section = activeEdit.id.replace('-edit', '');
            toggleEdit(section);
        }
    }
    
    // Ctrl+E to edit profile
    if (e.ctrlKey && e.key === 'e') {
        e.preventDefault();
        const profileEdit = document.getElementById('profile-edit');
        if (!profileEdit.classList.contains('active')) {
            toggleEdit('profile');
        }
    }
});

// Avatar upload simulation (placeholder for future implementation)
document.querySelector('.avatar-upload').addEventListener('click', function() {
    alert('Avatar upload feature coming soon!\n\nThis will allow you to upload a custom profile picture.');
});

// Session timeout warning
let sessionWarningShown = false;
function checkSession() {
    fetch('ajax/check_session.php')
        .then(response => response.json())
        .then(data => {
            if (!data.valid && !sessionWarningShown) {
                sessionWarningShown = true;
                if (confirm('Your session is about to expire. Click OK to stay logged in.')) {
                    window.location.reload();
                } else {
                    window.location.href = 'logout.php';
                }
            }
        })
        .catch(error => {
            console.error('Session check failed:', error);
        });
}

// Check session every 5 minutes
setInterval(checkSession, 300000);

// Activity refresh
function refreshActivity() {
    fetch('ajax/get_user_activity.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateActivityDisplay(data.activities);
            }
        })
        .catch(error => {
            console.error('Activity refresh failed:', error);
        });
}

function updateActivityDisplay(activities) {
    const container = document.querySelector('.activity-list');
    if (container && activities.length > 0) {
        container.innerHTML = activities.map(activity => `
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-${getActivityIcon(activity.type)}"></i>
                </div>
                <div class="activity-content">
                    <div>${activity.action}: <strong>${activity.reference}</strong></div>
                    <div class="activity-time">${activity.formatted_date}</div>
                </div>
            </div>
        `).join('');
    }
}

function getActivityIcon(type) {
    const icons = {
        'job': 'shipping-fast',
        'invoice': 'file-invoice',
        'quotation': 'file-contract',
        'customer': 'users',
        'vendor': 'truck'
    };
    return icons[type] || 'circle';
}

// Load profile draft on page load
document.addEventListener('DOMContentLoaded', function() {
    loadProfileDraft();
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Add smooth transitions
    document.querySelectorAll('.edit-form').forEach(form => {
        form.style.transition = 'all 0.3s ease';
    });
});

// Form auto-save warning
window.addEventListener('beforeunload', function(e) {
    if (profileFormChanged) {
        const message = 'You have unsaved profile changes. Are you sure you want to leave?';
        e.returnValue = message;
        return message;
    }
});

// Statistics animation on page load
function animateStats() {
    const statNumbers = document.querySelectorAll('.stat-number');
    
    statNumbers.forEach(stat => {
        const target = parseInt(stat.textContent.replace(/,/g, ''));
        let current = 0;
        const increment = target / 50; // 50 steps animation
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            stat.textContent = Math.floor(current).toLocaleString();
        }, 30);
    });
}

// Trigger stats animation when section comes into view
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateStats();
            observer.unobserve(entry.target);
        }
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const statsSection = document.querySelector('.stats-grid');
    if (statsSection) {
        observer.observe(statsSection);
    }
});

// Copy user ID to clipboard
function copyUserId() {
    const userId = <?php echo $user['id']; ?>;
    navigator.clipboard.writeText(userId.toString()).then(function() {
        // Show temporary success message
        const element = document.querySelector('[data-user-id]');
        if (element) {
            const originalText = element.textContent;
            element.textContent = 'Copied!';
            element.style.color = '#28a745';
            
            setTimeout(() => {
                element.textContent = originalText;
                element.style.color = '';
            }, 2000);
        }
    });
}

// Add click handler to user ID
document.addEventListener('DOMContentLoaded', function() {
    const userIdElement = document.querySelector('.info-value:has-text("#<?php echo $user['id']; ?>")');
    if (userIdElement) {
        userIdElement.style.cursor = 'pointer';
        userIdElement.title = 'Click to copy';
        userIdElement.setAttribute('data-user-id', true);
        userIdElement.addEventListener('click', copyUserId);
    }
});
</script>

<?php include 'includes/footer.php'; ?>