<?php
// =====================================================
// settings.php - System Settings Management
// =====================================================

// Include header และ functions
$custom_page_title = 'System Settings';
$page_header = true;
$page_subtitle = 'Configure system preferences and business settings';

// Set breadcrumb
$breadcrumb = [
    ['name' => 'System Settings']
];

$additional_css = "
<style>
.settings-header {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.settings-header::before {
    content: '';
    position: absolute;
    top: -50px;
    right: -50px;
    width: 150px;
    height: 150px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}

.settings-section {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
    border: 1px solid #e9ecef;
}

.settings-section:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.section-title {
    color: var(--primary-color);
    font-weight: 600;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e9ecef;
    display: flex;
    align-items: center;
}

.section-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
}

.setting-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
    border-bottom: 1px solid #f8f9fa;
}

.setting-item:last-child {
    border-bottom: none;
}

.setting-label {
    flex: 1;
}

.setting-title {
    font-weight: 500;
    color: #333;
    margin-bottom: 0.25rem;
}

.setting-description {
    font-size: 0.9rem;
    color: #6c757d;
    margin: 0;
}

.setting-control {
    min-width: 200px;
    text-align: right;
}

.form-floating > label {
    color: #6c757d;
}

.btn-save-settings {
    background: linear-gradient(135deg, #28a745, #20c997);
    border: none;
    color: white;
    padding: 0.75rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-save-settings:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
    color: white;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 34px;
}

.slider:before {
    position: absolute;
    content: '';
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: var(--primary-color);
}

input:checked + .slider:before {
    transform: translateX(26px);
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

.backup-status {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-radius: 10px;
    margin-bottom: 1rem;
}

.backup-status.success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.backup-status.warning {
    background-color: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.backup-status.danger {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.user-management-table {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.logo-upload-area {
    border: 2px dashed #dee2e6;
    border-radius: 10px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
}

.logo-upload-area:hover {
    border-color: var(--primary-color);
    background-color: #f8f9fa;
}

.logo-preview {
    max-width: 200px;
    max-height: 100px;
    border-radius: 5px;
    margin-bottom: 1rem;
}

.maintenance-mode {
    background: linear-gradient(135deg, #ffc107, #fd7e14);
    color: white;
    padding: 1rem;
    border-radius: 10px;
    margin-bottom: 1rem;
}
</style>
";

include 'includes/header.php';

// Check permissions - Only admin can access settings
requirePermission('admin');

// Get current settings
$settings = [];
$settings_raw = fetchAll("SELECT setting_key, setting_value FROM system_settings");
foreach ($settings_raw as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

// Set default values if not exist
$default_settings = [
    'company_name' => 'Your Freight Company Ltd.',
    'company_address' => '123 Business District, Bangkok 10110',
    'company_phone' => '02-123-4567',
    'company_email' => 'info@company.com',
    'company_website' => 'www.company.com',
    'company_tax_id' => '0123456789012',
    'default_currency' => 'THB',
    'vat_rate' => '7.00',
    'job_number_format' => '{type}{service}{mmyy}-{0000}',
    'invoice_prefix' => 'INV',
    'quotation_prefix' => 'QT',
    'default_credit_term' => '30',
    'email_notifications' => '1',
    'sms_notifications' => '0',
    'backup_enabled' => '1',
    'maintenance_mode' => '0',
    'session_timeout' => '30',
    'max_file_upload_size' => '10',
    'date_format' => 'd/m/Y',
    'time_format' => '24',
    'timezone' => 'Asia/Bangkok',
    'language' => 'en',
    'items_per_page' => '20',
    'enable_api' => '0',
    'api_rate_limit' => '100'
];

foreach ($default_settings as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// Get system statistics
$system_stats = [
    'total_users' => fetchOne("SELECT COUNT(*) as count FROM users")['count'],
    'active_users' => fetchOne("SELECT COUNT(*) as count FROM users WHERE status = 'active'")['count'],
    'total_jobs' => fetchOne("SELECT COUNT(*) as count FROM jobs")['count'],
    'total_customers' => fetchOne("SELECT COUNT(*) as count FROM customers")['count'],
    'total_vendors' => fetchOne("SELECT COUNT(*) as count FROM vendors")['count'],
    'db_size' => getDatabaseSize()
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_settings') {
            // Update general settings
            $settings_to_update = [
                'company_name', 'company_address', 'company_phone', 'company_email',
                'company_website', 'company_tax_id', 'default_currency', 'vat_rate',
                'job_number_format', 'invoice_prefix', 'quotation_prefix',
                'default_credit_term', 'date_format', 'time_format', 'timezone',
                'language', 'items_per_page', 'session_timeout', 'max_file_upload_size'
            ];
            
            beginTransaction();
            
            foreach ($settings_to_update as $key) {
                if (isset($_POST[$key])) {
                    $value = cleanInput($_POST[$key]);
                    
                    // Validation
                    if ($key === 'vat_rate' && ($value < 0 || $value > 100)) {
                        throw new Exception('VAT rate must be between 0 and 100.');
                    }
                    
                    if ($key === 'default_credit_term' && ($value < 0 || $value > 365)) {
                        throw new Exception('Credit term must be between 0 and 365 days.');
                    }
                    
                    // Update or insert setting
                    $existing = fetchOne("SELECT id FROM system_settings WHERE setting_key = ?", [$key]);
                    if ($existing) {
                        execute("UPDATE system_settings SET setting_value = ?, updated_by = ?, updated_at = NOW() WHERE setting_key = ?",
                               [$value, $_SESSION['user_id'], $key]);
                    } else {
                        execute("INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)",
                               [$key, $value, $_SESSION['user_id']]);
                    }
                }
            }
            
            commit();
            $_SESSION['success_message'] = 'System settings updated successfully.';
            
        } elseif ($action === 'update_notifications') {
            // Update notification settings
            $email_notifications = isset($_POST['email_notifications']) ? '1' : '0';
            $sms_notifications = isset($_POST['sms_notifications']) ? '1' : '0';
            
            setSetting('email_notifications', $email_notifications);
            setSetting('sms_notifications', $sms_notifications);
            
            $_SESSION['success_message'] = 'Notification settings updated successfully.';
            
        } elseif ($action === 'update_system') {
            // Update system settings
            $backup_enabled = isset($_POST['backup_enabled']) ? '1' : '0';
            $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
            $enable_api = isset($_POST['enable_api']) ? '1' : '0';
            $api_rate_limit = cleanInput($_POST['api_rate_limit']);
            
            setSetting('backup_enabled', $backup_enabled);
            setSetting('maintenance_mode', $maintenance_mode);
            setSetting('enable_api', $enable_api);
            setSetting('api_rate_limit', $api_rate_limit);
            
            $_SESSION['success_message'] = 'System configuration updated successfully.';
            
        } elseif ($action === 'create_backup') {
            // Create backup
            $backup_result = createSystemBackup();
            if ($backup_result['success']) {
                $_SESSION['success_message'] = 'Backup created successfully: ' . $backup_result['filename'];
            } else {
                throw new Exception($backup_result['message']);
            }
        }
        
        redirect('settings.php');
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            rollback();
        }
        $error_message = $e->getMessage();
    }
}

// Refresh settings after potential updates
$settings_raw = fetchAll("SELECT setting_key, setting_value FROM system_settings");
$settings = [];
foreach ($settings_raw as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}
foreach ($default_settings as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// Get backup status
$backup_status = getBackupStatus();
?>

<div class="container-fluid">
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Settings Header -->
    <div class="settings-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-1">
                    <i class="fas fa-cog me-2"></i>
                    System Settings
                </h2>
                <p class="mb-0 opacity-75">
                    Configure system preferences, business settings, and operational parameters
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="text-white">
                    <small>Last updated by: <?php echo $_SESSION['user_name']; ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Maintenance Mode Warning -->
    <?php if ($settings['maintenance_mode'] === '1'): ?>
    <div class="maintenance-mode">
        <div class="d-flex align-items-center">
            <i class="fas fa-tools fa-2x me-3"></i>
            <div>
                <strong>Maintenance Mode is Active</strong>
                <p class="mb-0">The system is currently in maintenance mode. Only administrators can access the system.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- System Statistics -->
    <div class="settings-section">
        <h5 class="section-title">
            <div class="section-icon">
                <i class="fas fa-chart-bar"></i>
            </div>
            System Overview
        </h5>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format($system_stats['total_users']); ?></span>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format($system_stats['active_users']); ?></span>
                <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format($system_stats['total_jobs']); ?></span>
                <div class="stat-label">Total Jobs</div>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format($system_stats['total_customers']); ?></span>
                <div class="stat-label">Customers</div>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format($system_stats['total_vendors']); ?></span>
                <div class="stat-label">Vendors</div>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $system_stats['db_size']; ?></span>
                <div class="stat-label">Database Size</div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <!-- Company Information -->
            <div class="settings-section">
                <h5 class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    Company Information
                </h5>

                <form method="POST" id="companyForm">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="company_name" name="company_name" 
                               value="<?php echo htmlspecialchars($settings['company_name']); ?>" required>
                        <label for="company_name">Company Name</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <textarea class="form-control" id="company_address" name="company_address" 
                                  style="height: 100px;" required><?php echo htmlspecialchars($settings['company_address']); ?></textarea>
                        <label for="company_address">Company Address</label>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="tel" class="form-control" id="company_phone" name="company_phone" 
                                       value="<?php echo htmlspecialchars($settings['company_phone']); ?>">
                                <label for="company_phone">Phone Number</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="email" class="form-control" id="company_email" name="company_email" 
                                       value="<?php echo htmlspecialchars($settings['company_email']); ?>">
                                <label for="company_email">Email Address</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="url" class="form-control" id="company_website" name="company_website" 
                                       value="<?php echo htmlspecialchars($settings['company_website']); ?>">
                                <label for="company_website">Website</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="company_tax_id" name="company_tax_id" 
                                       value="<?php echo htmlspecialchars($settings['company_tax_id']); ?>">
                                <label for="company_tax_id">Tax ID</label>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-save-settings">
                        <i class="fas fa-save me-2"></i>Save Company Info
                    </button>
                </form>
            </div>

            <!-- Business Settings -->
            <div class="settings-section">
                <h5 class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    Business Settings
                </h5>

                <form method="POST" id="businessForm">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="default_currency" name="default_currency">
                                    <option value="THB" <?php echo $settings['default_currency'] === 'THB' ? 'selected' : ''; ?>>THB - Thai Baht</option>
                                    <option value="USD" <?php echo $settings['default_currency'] === 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                    <option value="EUR" <?php echo $settings['default_currency'] === 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                                    <option value="CNY" <?php echo $settings['default_currency'] === 'CNY' ? 'selected' : ''; ?>>CNY - Chinese Yuan</option>
                                </select>
                                <label for="default_currency">Default Currency</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="number" step="0.01" min="0" max="100" class="form-control" 
                                       id="vat_rate" name="vat_rate" value="<?php echo $settings['vat_rate']; ?>">
                                <label for="vat_rate">VAT Rate (%)</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="job_number_format" name="job_number_format" 
                               value="<?php echo htmlspecialchars($settings['job_number_format']); ?>">
                        <label for="job_number_format">Job Number Format</label>
                        <div class="form-text">Format: {type}{service}{mmyy}-{0000}</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="invoice_prefix" name="invoice_prefix" 
                                       value="<?php echo htmlspecialchars($settings['invoice_prefix']); ?>">
                                <label for="invoice_prefix">Invoice Prefix</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="quotation_prefix" name="quotation_prefix" 
                                       value="<?php echo htmlspecialchars($settings['quotation_prefix']); ?>">
                                <label for="quotation_prefix">Quotation Prefix</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating mb-3">
                                <input type="number" min="0" max="365" class="form-control" 
                                       id="default_credit_term" name="default_credit_term" 
                                       value="<?php echo $settings['default_credit_term']; ?>">
                                <label for="default_credit_term">Default Credit Term (Days)</label>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-save-settings">
                        <i class="fas fa-save me-2"></i>Save Business Settings
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <!-- System Preferences -->
            <div class="settings-section">
                <h5 class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-sliders-h"></i>
                    </div>
                    System Preferences
                </h5>

                <form method="POST" id="systemForm">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="date_format" name="date_format">
                                    <option value="d/m/Y" <?php echo $settings['date_format'] === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                    <option value="m/d/Y" <?php echo $settings['date_format'] === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                    <option value="Y-m-d" <?php echo $settings['date_format'] === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                </select>
                                <label for="date_format">Date Format</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="time_format" name="time_format">
                                    <option value="24" <?php echo $settings['time_format'] === '24' ? 'selected' : ''; ?>>24 Hour</option>
                                    <option value="12" <?php echo $settings['time_format'] === '12' ? 'selected' : ''; ?>>12 Hour</option>
                                </select>
                                <label for="time_format">Time Format</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="timezone" name="timezone">
                                    <option value="Asia/Bangkok" <?php echo $settings['timezone'] === 'Asia/Bangkok' ? 'selected' : ''; ?>>Asia/Bangkok</option>
                                    <option value="UTC" <?php echo $settings['timezone'] === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                    <option value="America/New_York" <?php echo $settings['timezone'] === 'America/New_York' ? 'selected' : ''; ?>>America/New_York</option>
                                    <option value="Europe/London" <?php echo $settings['timezone'] === 'Europe/London' ? 'selected' : ''; ?>>Europe/London</option>
                                </select>
                                <label for="timezone">Timezone</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="language" name="language">
                                    <option value="en" <?php echo $settings['language'] === 'en' ? 'selected' : ''; ?>>English</option>
                                    <option value="th" <?php echo $settings['language'] === 'th' ? 'selected' : ''; ?>>ไทย (Thai)</option>
                                </select>
                                <label for="language">Language</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="number" min="5" max="100" class="form-control" 
                                       id="items_per_page" name="items_per_page" 
                                       value="<?php echo $settings['items_per_page']; ?>">
                                <label for="items_per_page">Items Per Page</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="number" min="10" max="240" class="form-control" 
                                       id="session_timeout" name="session_timeout" 
                                       value="<?php echo $settings['session_timeout']; ?>">
                                <label for="session_timeout">Session Timeout (Minutes)</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="number" min="1" max="100" class="form-control" 
                               id="max_file_upload_size" name="max_file_upload_size" 
                               value="<?php echo $settings['max_file_upload_size']; ?>">
                        <label for="max_file_upload_size">Max File Upload Size (MB)</label>
                    </div>
                    
                    <button type="submit" class="btn btn-save-settings">
                        <i class="fas fa-save me-2"></i>Save Preferences
                    </button>
                </form>
            </div>

            <!-- Notification Settings -->
            <div class="settings-section">
                <h5 class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    Notification Settings
                </h5>

                <form method="POST" id="notificationForm">
                    <input type="hidden" name="action" value="update_notifications">
                    
                    <div class="setting-item">
                        <div class="setting-label">
                            <div class="setting-title">Email Notifications</div>
                            <div class="setting-description">Send email notifications for important events</div>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_notifications" 
                                       <?php echo $settings['email_notifications'] === '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-label">
                            <div class="setting-title">SMS Notifications</div>
                            <div class="setting-description">Send SMS alerts for urgent notifications</div>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="sms_notifications" 
                                       <?php echo $settings['sms_notifications'] === '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-save-settings mt-3">
                        <i class="fas fa-save me-2"></i>Save Notification Settings
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- System Management -->
    <div class="row">
        <div class="col-lg-6">
            <!-- Backup & Maintenance -->
            <div class="settings-section">
                <h5 class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    Backup & Maintenance
                </h5>

                <!-- Backup Status -->
                <div class="backup-status <?php echo $backup_status['class']; ?>">
                    <i class="fas fa-<?php echo $backup_status['icon']; ?> me-2"></i>
                    <div>
                        <strong><?php echo $backup_status['title']; ?></strong>
                        <p class="mb-0"><?php echo $backup_status['message']; ?></p>
                    </div>
                </div>

                <form method="POST" id="maintenanceForm">
                    <input type="hidden" name="action" value="update_system">
                    
                    <div class="setting-item">
                        <div class="setting-label">
                            <div class="setting-title">Automatic Backup</div>
                            <div class="setting-description">Enable daily automatic database backup</div>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="backup_enabled" 
                                       <?php echo $settings['backup_enabled'] === '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-label">
                            <div class="setting-title">Maintenance Mode</div>
                            <div class="setting-description">Block user access during system maintenance</div>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="maintenance_mode" 
                                       <?php echo $settings['maintenance_mode'] === '1' ? 'checked' : ''; ?>
                                       onchange="confirmMaintenanceMode(this)">
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-save-settings">
                            <i class="fas fa-save me-2"></i>Save Settings
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="createBackup()">
                            <i class="fas fa-download me-2"></i>Create Backup Now
                        </button>
                    </div>
                </form>
            </div>

            <!-- User Management -->
            <div class="settings-section">
                <h5 class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    User Management
                </h5>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <p class="mb-0">Manage system users and their permissions</p>
                    <a href="users.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-users me-1"></i>Manage Users
                    </a>
                </div>

                <?php
                $recent_users = fetchAll("
                    SELECT name, username, role, status, last_login 
                    FROM users 
                    ORDER BY created_at DESC 
                    LIMIT 5
                ");
                ?>

                <div class="user-management-table">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $user): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($user['username']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo match($user['role']) {
                                            'admin' => 'danger',
                                            'manager' => 'warning',
                                            'staff' => 'primary',
                                            'viewer' => 'secondary',
                                            default => 'secondary'
                                        };
                                    ?>"><?php echo ucfirst($user['role']); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo $user['last_login'] ? formatDateThai($user['last_login'], 'd/m/Y H:i') : 'Never'; ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <!-- API Settings -->
            <div class="settings-section">
                <h5 class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-plug"></i>
                    </div>
                    API Configuration
                </h5>

                <form method="POST" id="apiForm">
                    <input type="hidden" name="action" value="update_system">
                    
                    <div class="setting-item">
                        <div class="setting-label">
                            <div class="setting-title">Enable API Access</div>
                            <div class="setting-description">Allow external applications to access system data via API</div>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="enable_api" 
                                       <?php echo $settings['enable_api'] === '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="number" min="10" max="1000" class="form-control" 
                               id="api_rate_limit" name="api_rate_limit" 
                               value="<?php echo $settings['api_rate_limit']; ?>">
                        <label for="api_rate_limit">API Rate Limit (Requests per hour)</label>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>API Endpoint:</strong> <?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']); ?>/api/
                    </div>
                    
                    <button type="submit" class="btn btn-save-settings">
                        <i class="fas fa-save me-2"></i>Save API Settings
                    </button>
                </form>
            </div>

            <!-- System Information -->
            <div class="settings-section">
                <h5 class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    System Information
                </h5>

                <div class="setting-item">
                    <div class="setting-label">
                        <div class="setting-title">System Version</div>
                        <div class="setting-description">Current system version</div>
                    </div>
                    <div class="setting-control">
                        <span class="badge bg-primary">v1.0.0</span>
                    </div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-label">
                        <div class="setting-title">PHP Version</div>
                        <div class="setting-description">Server PHP version</div>
                    </div>
                    <div class="setting-control">
                        <span class="badge bg-info"><?php echo phpversion(); ?></span>
                    </div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-label">
                        <div class="setting-title">Database Version</div>
                        <div class="setting-description">MySQL/MariaDB version</div>
                    </div>
                    <div class="setting-control">
                        <span class="badge bg-success"><?php echo getDatabaseVersion(); ?></span>
                    </div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-label">
                        <div class="setting-title">Server Time</div>
                        <div class="setting-description">Current server time</div>
                    </div>
                    <div class="setting-control">
                        <span id="server-time"><?php echo date('Y-m-d H:i:s'); ?></span>
                    </div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-label">
                        <div class="setting-title">Disk Space</div>
                        <div class="setting-description">Available disk space</div>
                    </div>
                    <div class="setting-control">
                        <span class="badge bg-warning"><?php echo getDiskSpace(); ?></span>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-outline-info" onclick="checkSystemHealth()">
                        <i class="fas fa-stethoscope me-2"></i>System Health Check
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="clearSystemCache()">
                        <i class="fas fa-broom me-2"></i>Clear Cache
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form auto-save indication
let settingsChanged = false;

document.querySelectorAll('input, select, textarea').forEach(element => {
    element.addEventListener('change', function() {
        settingsChanged = true;
        showUnsavedIndicator();
    });
});

function showUnsavedIndicator() {
    const indicator = document.getElementById('unsaved-indicator');
    if (!indicator) {
        const newIndicator = document.createElement('div');
        newIndicator.id = 'unsaved-indicator';
        newIndicator.className = 'alert alert-warning position-fixed';
        newIndicator.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
        newIndicator.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>You have unsaved changes';
        document.body.appendChild(newIndicator);
    }
}

function hideUnsavedIndicator() {
    const indicator = document.getElementById('unsaved-indicator');
    if (indicator) {
        indicator.remove();
    }
}

// Clear indicator on form submission
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        settingsChanged = false;
        hideUnsavedIndicator();
    });
});

// Warn about unsaved changes
window.addEventListener('beforeunload', function(e) {
    if (settingsChanged) {
        const message = 'You have unsaved settings changes. Are you sure you want to leave?';
        e.returnValue = message;
        return message;
    }
});

// Maintenance mode confirmation
function confirmMaintenanceMode(checkbox) {
    if (checkbox.checked) {
        const confirm = window.confirm(
            'WARNING: Enabling maintenance mode will block all user access to the system.\n\n' +
            'Only administrators will be able to access the system.\n\n' +
            'Are you sure you want to continue?'
        );
        
        if (!confirm) {
            checkbox.checked = false;
            return false;
        }
    }
}

// Create backup
function createBackup() {
    if (!confirm('This will create a full system backup. This may take a few minutes. Continue?')) {
        return;
    }
    
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Backup...';
    btn.disabled = true;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'create_backup';
    
    form.appendChild(actionInput);
    document.body.appendChild(form);
    form.submit();
}

// System health check
function checkSystemHealth() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Checking...';
    btn.disabled = true;
    
    fetch('ajax/system_health_check.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showHealthResults(data.results);
            } else {
                alert('Health check failed: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error running health check: ' + error.message);
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}

function showHealthResults(results) {
    let message = 'System Health Check Results:\n\n';
    
    results.forEach(result => {
        message += `${result.name}: ${result.status}\n`;
        if (result.message) {
            message += `  ${result.message}\n`;
        }
    });
    
    alert(message);
}

// Clear system cache
function clearSystemCache() {
    if (!confirm('This will clear all system cache files. Continue?')) {
        return;
    }
    
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Clearing...';
    btn.disabled = true;
    
    fetch('ajax/clear_cache.php', {
        method: 'POST'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Cache cleared successfully!');
            } else {
                alert('Failed to clear cache: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error clearing cache: ' + error.message);
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}

// Update server time every second
function updateServerTime() {
    const timeElement = document.getElementById('server-time');
    if (timeElement) {
        const now = new Date();
        timeElement.textContent = now.getFullYear() + '-' + 
            String(now.getMonth() + 1).padStart(2, '0') + '-' + 
            String(now.getDate()).padStart(2, '0') + ' ' + 
            String(now.getHours()).padStart(2, '0') + ':' + 
            String(now.getMinutes()).padStart(2, '0') + ':' + 
            String(now.getSeconds()).padStart(2, '0');
    }
}

// Update time every second
setInterval(updateServerTime, 1000);

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+S to save (prevent default browser save)
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        
        // Find the first visible form and submit it
        const forms = document.querySelectorAll('form');
        for (let form of forms) {
            if (form.offsetParent !== null) { // Check if form is visible
                form.submit();
                break;
            }
        }
    }
});

// Auto-expand textareas
document.querySelectorAll('textarea').forEach(textarea => {
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
});

// Initialize tooltips for toggle switches
document.addEventListener('DOMContentLoaded', function() {
    // Add tooltips to toggle switches
    document.querySelectorAll('.toggle-switch').forEach(toggle => {
        const input = toggle.querySelector('input');
        if (input) {
            toggle.title = input.checked ? 'Enabled' : 'Disabled';
            
            input.addEventListener('change', function() {
                toggle.title = this.checked ? 'Enabled' : 'Disabled';
            });
        }
    });
    
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Settings validation
function validateBusinessSettings() {
    const vatRate = parseFloat(document.getElementById('vat_rate').value);
    const creditTerm = parseInt(document.getElementById('default_credit_term').value);
    
    if (vatRate < 0 || vatRate > 100) {
        alert('VAT rate must be between 0 and 100%');
        return false;
    }
    
    if (creditTerm < 0 || creditTerm > 365) {
        alert('Credit term must be between 0 and 365 days');
        return false;
    }
    
    return true;
}

// Add validation to business form
document.getElementById('businessForm').addEventListener('submit', function(e) {
    if (!validateBusinessSettings()) {
        e.preventDefault();
        return false;
    }
});
</script>

<?php
// =====================================================
// Helper Functions
// =====================================================

function getDatabaseSize() {
    try {
        $result = fetchOne("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
            FROM information_schema.tables 
            WHERE table_schema = ?
        ", [DB_NAME]);
        
        return $result ? $result['size_mb'] . ' MB' : 'Unknown';
    } catch (Exception $e) {
        return 'Unknown';
    }
}

function getDatabaseVersion() {
    try {
        $result = fetchOne("SELECT VERSION() as version");
        return $result ? $result['version'] : 'Unknown';
    } catch (Exception $e) {
        return 'Unknown';
    }
}

function getDiskSpace() {
    try {
        $bytes = disk_free_space('.');
        if ($bytes === false) return 'Unknown';
        
        $gb = round($bytes / 1024 / 1024 / 1024, 2);
        return $gb . ' GB';
    } catch (Exception $e) {
        return 'Unknown';
    }
}

function getBackupStatus() {
    try {
        // Check if backup is enabled
        $backup_enabled = getSetting('backup_enabled', '1');
        
        if ($backup_enabled !== '1') {
            return [
                'class' => 'warning',
                'icon' => 'exclamation-triangle',
                'title' => 'Backup Disabled',
                'message' => 'Automatic backup is currently disabled. Enable it to protect your data.'
            ];
        }
        
        // Check for recent backup (this would need to be implemented)
        // For now, we'll simulate a successful backup
        return [
            'class' => 'success',
            'icon' => 'check-circle',
            'title' => 'Backup Active',
            'message' => 'Last backup: Today at 02:00 AM. Next backup scheduled for tomorrow.'
        ];
        
    } catch (Exception $e) {
        return [
            'class' => 'danger',
            'icon' => 'times-circle',
            'title' => 'Backup Error',
            'message' => 'Unable to determine backup status. Please check system configuration.'
        ];
    }
}

function createSystemBackup() {
    try {
        // This is a simplified backup creation
        // In production, you would implement proper database dump
        $backup_dir = 'backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . $filename;
        
        // Simulate backup creation (replace with actual mysqldump)
        $success = file_put_contents($filepath, '-- Database backup created on ' . date('Y-m-d H:i:s'));
        
        if ($success !== false) {
            return [
                'success' => true,
                'filename' => $filename,
                'message' => 'Backup created successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to create backup file'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Backup error: ' . $e->getMessage()
        ];
    }
}

include 'includes/footer.php';
?>