<?php
// =====================================================
// vendors_edit.php - Edit Vendor (Full Featured Version)
// =====================================================

// Include functions first
require_once 'includes/functions.php';

// Require staff permission or higher
requirePermission('staff');

// Get vendor ID
$vendor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$vendor_id) {
    $_SESSION['error_message'] = "Vendor ID is required.";
    redirect('vendors.php');
    exit();
}

// Get vendor data
$vendor = fetchOne("SELECT * FROM vendors WHERE id = ?", [$vendor_id]);

if (!$vendor) {
    $_SESSION['error_message'] = "Vendor not found.";
    redirect('vendors.php');
    exit();
}

$errors = [];
$form_data = $vendor; // Initialize with existing data

// Handle form submission BEFORE any output
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and clean form data
    $form_data = [
        'vendor_code' => strtoupper(cleanInput($_POST['vendor_code'])),
        'company_name' => cleanInput($_POST['company_name']),
        'vendor_type' => cleanInput($_POST['vendor_type']),
        'contact_person' => cleanInput($_POST['contact_person']),
        'phone' => cleanInput($_POST['phone']),
        'email' => cleanInput($_POST['email']),
        'address' => cleanInput($_POST['address']),
        'tax_id' => cleanInput($_POST['tax_id']),
        'payment_term' => (int)$_POST['payment_term'],
        'currency' => cleanInput($_POST['currency']),
        'status' => cleanInput($_POST['status']),
        'remark' => cleanInput($_POST['remark'])
    ];
    
    // Validation
    if (empty($form_data['vendor_code'])) {
        $errors['vendor_code'] = 'Vendor code is required';
    } elseif (strlen($form_data['vendor_code']) < 2) {
        $errors['vendor_code'] = 'Vendor code must be at least 2 characters';
    } else {
        // Check if vendor code already exists (exclude current vendor)
        $existing = fetchOne("SELECT id FROM vendors WHERE vendor_code = ? AND id != ?", 
                           [$form_data['vendor_code'], $vendor_id]);
        if ($existing) {
            $errors['vendor_code'] = 'Vendor code already exists';
        }
    }
    
    if (empty($form_data['company_name'])) {
        $errors['company_name'] = 'Company name is required';
    }
    
    if (!in_array($form_data['vendor_type'], ['shipping_line', 'airline', 'trucking', 'customs_broker', 'warehouse', 'other'])) {
        $errors['vendor_type'] = 'Invalid vendor type';
    }
    
    if (!empty($form_data['email']) && !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    
    if (!in_array($form_data['status'], ['active', 'inactive'])) {
        $errors['status'] = 'Invalid status';
    }
    
    if ($form_data['payment_term'] < 0 || $form_data['payment_term'] > 365) {
        $errors['payment_term'] = 'Payment term must be between 0-365 days';
    }
    
    if (!in_array($form_data['currency'], ['THB', 'USD', 'EUR', 'CNY', 'SGD', 'JPY'])) {
        $errors['currency'] = 'Invalid currency';
    }
    
    // If no errors, update database
    if (empty($errors)) {
        $sql = "UPDATE vendors SET 
                    vendor_code = ?, company_name = ?, vendor_type = ?, contact_person = ?, 
                    phone = ?, email = ?, address = ?, tax_id = ?, payment_term = ?, 
                    currency = ?, status = ?, remark = ?, updated_at = NOW()
                WHERE id = ?";
        
        $params = [
            $form_data['vendor_code'],
            $form_data['company_name'],
            $form_data['vendor_type'],
            $form_data['contact_person'],
            $form_data['phone'],
            $form_data['email'],
            $form_data['address'],
            $form_data['tax_id'],
            $form_data['payment_term'],
            $form_data['currency'],
            $form_data['status'],
            $form_data['remark'],
            $vendor_id
        ];
        
        if (execute($sql, $params)) {
            $_SESSION['success_message'] = "Vendor '{$form_data['company_name']}' has been updated successfully.";
            redirect('vendors_view.php?id=' . $vendor_id);
            exit(); // Make sure script stops here
        } else {
            $errors['general'] = 'Failed to update vendor. Please try again.';
        }
    }
}

// Get vendor statistics
$vendor_stats = [
    'total_costs' => fetchOne("SELECT COUNT(*) as count FROM job_costs WHERE vendor_id = ?", 
                            [$vendor_id])['count'],
    'active_jobs' => fetchOne("SELECT COUNT(DISTINCT jc.job_id) as count FROM job_costs jc 
                              JOIN jobs j ON jc.job_id = j.id 
                              WHERE jc.vendor_id = ? AND j.status NOT IN ('completed', 'cancelled')", 
                            [$vendor_id])['count'],
    'total_amount' => fetchOne("SELECT COALESCE(SUM(amount_thb), 0) as amount FROM job_costs WHERE vendor_id = ?", 
                             [$vendor_id])['amount'],
    'pending_payments' => fetchOne("SELECT COALESCE(SUM(amount_thb), 0) as amount FROM job_costs WHERE vendor_id = ? AND payment_status = 'pending'", 
                                 [$vendor_id])['amount']
];

// Get recent jobs with this vendor
$recent_jobs = fetchAll("
    SELECT DISTINCT j.id, j.job_no, j.status, j.created_at,
           c1.company_name as shipper_name,
           c2.company_name as consignee_name,
           SUM(jc.amount_thb) as total_cost
    FROM job_costs jc
    JOIN jobs j ON jc.job_id = j.id
    LEFT JOIN customers c1 ON j.shipper_id = c1.id
    LEFT JOIN customers c2 ON j.consignee_id = c2.id
    WHERE jc.vendor_id = ?
    GROUP BY j.id, j.job_no, j.status, j.created_at, c1.company_name, c2.company_name
    ORDER BY j.created_at DESC
    LIMIT 5
", [$vendor_id]);

// NOW set page variables and include header
$custom_page_title = "Edit Vendor - " . $vendor['company_name'];
$page_header = true;
$page_subtitle = "Update vendor information and settings";
$breadcrumb = [
    ['name' => 'Vendors', 'url' => 'vendors.php'],
    ['name' => $vendor['company_name'], 'url' => 'vendors_view.php?id=' . $vendor_id],
    ['name' => 'Edit']
];

include 'includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <!-- Vendor Status Alert -->
        <?php if ($vendor['status'] != 'active'): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            This vendor is currently <strong><?php echo strtoupper($vendor['status']); ?></strong>. 
            No new costs can be added for inactive vendors.
        </div>
        <?php endif; ?>
        
        <!-- Main Form -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-truck-loading me-2"></i>Edit Vendor Information
                    </h5>
                    <div class="text-light small">
                        Last updated: <?php echo formatDateThai($vendor['updated_at'], 'd/m/Y H:i'); ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $errors['general']; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" data-autosave="edit_vendor_<?php echo $vendor_id; ?>">
                    <div class="row">
                        <!-- Vendor Code -->
                        <div class="col-md-6 mb-3">
                            <label for="vendor_code" class="form-label">
                                Vendor Code <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control <?php echo isset($errors['vendor_code']) ? 'is-invalid' : ''; ?>" 
                                   id="vendor_code" 
                                   name="vendor_code" 
                                   value="<?php echo htmlspecialchars($form_data['vendor_code']); ?>"
                                   placeholder="e.g., VEN25001"
                                   maxlength="20"
                                   required>
                            <?php if (isset($errors['vendor_code'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['vendor_code']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>Original code: <?php echo htmlspecialchars($vendor['vendor_code']); ?>
                            </div>
                        </div>
                        
                        <!-- Company Name -->
                        <div class="col-md-6 mb-3">
                            <label for="company_name" class="form-label">
                                Company Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control <?php echo isset($errors['company_name']) ? 'is-invalid' : ''; ?>" 
                                   id="company_name" 
                                   name="company_name" 
                                   value="<?php echo htmlspecialchars($form_data['company_name']); ?>"
                                   placeholder="Vendor Company Ltd."
                                   maxlength="200"
                                   required>
                            <?php if (isset($errors['company_name'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['company_name']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Vendor Type -->
                        <div class="col-md-6 mb-3">
                            <label for="vendor_type" class="form-label">
                                Vendor Type <span class="text-danger">*</span>
                            </label>
                            <select class="form-select <?php echo isset($errors['vendor_type']) ? 'is-invalid' : ''; ?>" 
                                    id="vendor_type" 
                                    name="vendor_type" 
                                    required>
                                <option value="">Select Vendor Type</option>
                                <option value="shipping_line" <?php echo $form_data['vendor_type'] == 'shipping_line' ? 'selected' : ''; ?>>
                                    <i class="fas fa-ship"></i> Shipping Line
                                </option>
                                <option value="airline" <?php echo $form_data['vendor_type'] == 'airline' ? 'selected' : ''; ?>>
                                    <i class="fas fa-plane"></i> Airline
                                </option>
                                <option value="trucking" <?php echo $form_data['vendor_type'] == 'trucking' ? 'selected' : ''; ?>>
                                    <i class="fas fa-truck"></i> Trucking Company
                                </option>
                                <option value="customs_broker" <?php echo $form_data['vendor_type'] == 'customs_broker' ? 'selected' : ''; ?>>
                                    <i class="fas fa-file-alt"></i> Customs Broker
                                </option>
                                <option value="warehouse" <?php echo $form_data['vendor_type'] == 'warehouse' ? 'selected' : ''; ?>>
                                    <i class="fas fa-warehouse"></i> Warehouse
                                </option>
                                <option value="other" <?php echo $form_data['vendor_type'] == 'other' ? 'selected' : ''; ?>>
                                    <i class="fas fa-ellipsis-h"></i> Other
                                </option>
                            </select>
                            <?php if (isset($errors['vendor_type'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['vendor_type']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Contact Person -->
                        <div class="col-md-6 mb-3">
                            <label for="contact_person" class="form-label">Contact Person</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="contact_person" 
                                   name="contact_person" 
                                   value="<?php echo htmlspecialchars($form_data['contact_person']); ?>"
                                   placeholder="Jane Smith"
                                   maxlength="100">
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Phone -->
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="phone" 
                                   name="phone" 
                                   value="<?php echo htmlspecialchars($form_data['phone']); ?>"
                                   placeholder="+66 2-123-4567"
                                   maxlength="50">
                        </div>
                        
                        <!-- Email -->
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" 
                                   class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($form_data['email']); ?>"
                                   placeholder="contact@vendor.com"
                                   maxlength="100">
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Tax ID -->
                        <div class="col-md-6 mb-3">
                            <label for="tax_id" class="form-label">Tax ID</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="tax_id" 
                                   name="tax_id" 
                                   value="<?php echo htmlspecialchars($form_data['tax_id']); ?>"
                                   placeholder="1234567890123"
                                   maxlength="100">
                        </div>
                        
                        <!-- Status -->
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">
                                Status <span class="text-danger">*</span>
                            </label>
                            <select class="form-select <?php echo isset($errors['status']) ? 'is-invalid' : ''; ?>" 
                                    id="status" 
                                    name="status" 
                                    required>
                                <option value="active" <?php echo $form_data['status'] == 'active' ? 'selected' : ''; ?>>
                                    <i class="fas fa-check-circle"></i> Active
                                </option>
                                <option value="inactive" <?php echo $form_data['status'] == 'inactive' ? 'selected' : ''; ?>>
                                    <i class="fas fa-times-circle"></i> Inactive
                                </option>
                            </select>
                            <?php if (isset($errors['status'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['status']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Address -->
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" 
                                  id="address" 
                                  name="address" 
                                  rows="3"
                                  placeholder="Complete business address with postal code"><?php echo htmlspecialchars($form_data['address']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <!-- Payment Term -->
                        <div class="col-md-6 mb-3">
                            <label for="payment_term" class="form-label">Payment Term (Days)</label>
                            <input type="number" 
                                   class="form-control <?php echo isset($errors['payment_term']) ? 'is-invalid' : ''; ?>" 
                                   id="payment_term" 
                                   name="payment_term" 
                                   value="<?php echo htmlspecialchars($form_data['payment_term']); ?>"
                                   min="0" 
                                   max="365"
                                   placeholder="30">
                            <?php if (isset($errors['payment_term'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['payment_term']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">Payment term we get from this vendor</div>
                        </div>
                        
                        <!-- Currency -->
                        <div class="col-md-6 mb-3">
                            <label for="currency" class="form-label">
                                Primary Currency <span class="text-danger">*</span>
                            </label>
                            <select class="form-select <?php echo isset($errors['currency']) ? 'is-invalid' : ''; ?>" 
                                    id="currency" 
                                    name="currency" 
                                    required>
                                <option value="THB" <?php echo $form_data['currency'] == 'THB' ? 'selected' : ''; ?>>THB - Thai Baht</option>
                                <option value="USD" <?php echo $form_data['currency'] == 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                <option value="EUR" <?php echo $form_data['currency'] == 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                                <option value="CNY" <?php echo $form_data['currency'] == 'CNY' ? 'selected' : ''; ?>>CNY - Chinese Yuan</option>
                                <option value="SGD" <?php echo $form_data['currency'] == 'SGD' ? 'selected' : ''; ?>>SGD - Singapore Dollar</option>
                                <option value="JPY" <?php echo $form_data['currency'] == 'JPY' ? 'selected' : ''; ?>>JPY - Japanese Yen</option>
                            </select>
                            <?php if (isset($errors['currency'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['currency']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Remark -->
                    <div class="mb-3">
                        <label for="remark" class="form-label">Remarks</label>
                        <textarea class="form-control" 
                                  id="remark" 
                                  name="remark" 
                                  rows="3"
                                  placeholder="Additional notes about this vendor, service quality, special requirements, etc."><?php echo htmlspecialchars($form_data['remark']); ?></textarea>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Vendor
                        </button>
                        <a href="vendors_view.php?id=<?php echo $vendor_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="button" class="btn btn-outline-info" onclick="resetToOriginal()">
                            <i class="fas fa-undo me-2"></i>Reset to Original
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Sidebar Info -->
    <div class="col-lg-4">
        <!-- Vendor Statistics -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>Vendor Statistics
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="border-end">
                            <h4 class="text-primary mb-1"><?php echo $vendor_stats['total_costs']; ?></h4>
                            <small class="text-muted">Total Costs</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <h4 class="text-warning mb-1"><?php echo $vendor_stats['active_jobs']; ?></h4>
                        <small class="text-muted">Active Jobs</small>
                    </div>
                    <div class="col-6">
                        <div class="border-end">
                            <h4 class="text-success mb-1"><?php echo formatNumber($vendor_stats['total_amount'], 0); ?></h4>
                            <small class="text-muted">Total Amount (THB)</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h4 class="text-danger mb-1"><?php echo formatNumber($vendor_stats['pending_payments'], 0); ?></h4>
                        <small class="text-muted">Pending (THB)</small>
                    </div>
                </div>
                
                <?php if ($vendor_stats['total_costs'] > 0): ?>
                <hr>
                <div class="d-grid gap-2">
                    <a href="job_costs.php?vendor_id=<?php echo $vendor_id; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-money-bill-wave me-2"></i>View All Costs
                    </a>
                    <?php if ($vendor_stats['pending_payments'] > 0): ?>
                    <a href="job_costs.php?vendor_id=<?php echo $vendor_id; ?>&status=pending" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-clock me-2"></i>Pending Payments
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Jobs -->
        <?php if (!empty($recent_jobs)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>Recent Jobs
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($recent_jobs as $job): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">
                                    <a href="jobs_view.php?id=<?php echo $job['id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($job['job_no']); ?>
                                    </a>
                                </h6>
                                <p class="mb-1 small text-muted">
                                    <?php echo htmlspecialchars($job['shipper_name'] ?: 'No shipper'); ?> 
                                    → <?php echo htmlspecialchars($job['consignee_name'] ?: 'No consignee'); ?>
                                </p>
                                <small class="text-muted"><?php echo formatDateThai($job['created_at'], 'd/m/Y'); ?></small>
                            </div>
                            <div class="text-end">
                                <?php echo getStatusBadge($job['status']); ?>
                                <br><small class="text-primary fw-bold"><?php echo formatMoney($job['total_cost']); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer">
                    <a href="jobs.php?vendor_id=<?php echo $vendor_id; ?>" class="btn btn-sm btn-outline-primary w-100">
                        View All Jobs with This Vendor
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Vendor Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>Vendor Information
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Vendor Type:</strong><br>
                    <small class="text-muted">
                        <?php
                        $type_names = [
                            'shipping_line' => 'Shipping Line',
                            'airline' => 'Airline',
                            'trucking' => 'Trucking Company',
                            'customs_broker' => 'Customs Broker',
                            'warehouse' => 'Warehouse',
                            'other' => 'Other'
                        ];
                        echo $type_names[$vendor['vendor_type']] ?? 'Unknown';
                        ?>
                    </small>
                </div>
                
                <div class="mb-3">
                    <strong>Payment Terms:</strong><br>
                    <small class="text-muted">
                        <?php echo $vendor['payment_term']; ?> days in <?php echo $vendor['currency']; ?>
                    </small>
                </div>
                
                <div class="mb-3">
                    <strong>Created:</strong><br>
                    <small class="text-muted">
                        <?php echo formatDateThai($vendor['created_at'], 'd/m/Y H:i'); ?>
                        <?php if ($vendor['created_by']): ?>
                            <?php 
                            $creator = fetchOne("SELECT name FROM users WHERE id = ?", [$vendor['created_by']]);
                            if ($creator): ?>
                                <br>by <?php echo htmlspecialchars($creator['name']); ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </small>
                </div>
                
                <div class="mb-3">
                    <strong>Last Updated:</strong><br>
                    <small class="text-muted">
                        <?php echo formatDateThai($vendor['updated_at'], 'd/m/Y H:i'); ?>
                    </small>
                </div>
                
                <?php if ($vendor_stats['pending_payments'] > 0): ?>
                <div class="alert alert-warning py-2">
                    <small>
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Pending Payments:</strong><br>
                        <?php echo formatMoney($vendor_stats['pending_payments']); ?>
                    </small>
                </div>
                <?php endif; ?>
                
                <?php if ($vendor['status'] == 'inactive'): ?>
                <div class="alert alert-warning py-2">
                    <small>
                        <i class="fas fa-pause-circle me-1"></i>
                        <strong>Inactive Vendor</strong><br>
                        Cannot add new costs for this vendor.
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="vendors_view.php?id=<?php echo $vendor_id; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-eye me-2"></i>View Vendor Details
                    </a>
                    
                    <?php if ($vendor['status'] == 'active'): ?>
                    <a href="job_costs_add.php?vendor_id=<?php echo $vendor_id; ?>" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-plus me-2"></i>Add New Cost
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($vendor['email']): ?>
                    <a href="mailto:<?php echo htmlspecialchars($vendor['email']); ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-envelope me-2"></i>Send Email
                    </a>
                    <?php endif; ?>
                    
                    <button class="btn btn-outline-info btn-sm" onclick="generateVendorReport()">
                        <i class="fas fa-file-chart me-2"></i>Generate Report
                    </button>
                    
                    <?php if (hasPermission('manager') && $vendor_stats['total_costs'] == 0): ?>
                    <a href="vendors.php?action=delete&id=<?php echo $vendor_id; ?>" 
                       class="btn btn-outline-danger btn-sm confirm-delete">
                        <i class="fas fa-trash me-2"></i>Delete Vendor
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Store original form values for reset functionality
const originalFormData = <?php echo json_encode($vendor); ?>;

// Reset to original values
function resetToOriginal() {
    if (confirm('Are you sure you want to reset all fields to their original values? All changes will be lost.')) {
        // Reset all form fields to original values
        document.getElementById('vendor_code').value = originalFormData.vendor_code || '';
        document.getElementById('company_name').value = originalFormData.company_name || '';
        document.getElementById('vendor_type').value = originalFormData.vendor_type || '';
        document.getElementById('contact_person').value = originalFormData.contact_person || '';
        document.getElementById('phone').value = originalFormData.phone || '';
        document.getElementById('email').value = originalFormData.email || '';
        document.getElementById('address').value = originalFormData.address || '';
        document.getElementById('tax_id').value = originalFormData.tax_id || '';
        document.getElementById('payment_term').value = originalFormData.payment_term || '30';
        document.getElementById('currency').value = originalFormData.currency || 'THB';
        document.getElementById('status').value = originalFormData.status || 'active';
        document.getElementById('remark').value = originalFormData.remark || '';
        
        // Clear localStorage auto-save data
        localStorage.removeItem('form_edit_vendor_<?php echo $vendor_id; ?>');
        
        // Remove any validation error classes
        document.querySelectorAll('.is-invalid').forEach(function(element) {
            element.classList.remove('is-invalid');
        });
        
        // Remove change indicators
        document.querySelectorAll('.border-warning').forEach(function(element) {
            element.classList.remove('border-warning');
        });
    }
}

// Form validation before submit
document.querySelector('form').addEventListener('submit', function(e) {
    const vendorCode = document.getElementById('vendor_code').value.trim();
    const companyName = document.getElementById('company_name').value.trim();
    const vendorType = document.getElementById('vendor_type').value;
    const currency = document.getElementById('currency').value;
    
    if (!vendorCode || vendorCode.length < 2) {
        e.preventDefault();
        alert('Please enter a valid vendor code (at least 2 characters)');
        document.getElementById('vendor_code').focus();
        return false;
    }
    
    if (!companyName) {
        e.preventDefault();
        alert('Please enter the company name');
        document.getElementById('company_name').focus();
        return false;
    }
    
    if (!vendorType) {
        e.preventDefault();
        alert('Please select a vendor type');
        document.getElementById('vendor_type').focus();
        return false;
    }
    
    if (!currency) {
        e.preventDefault();
        alert('Please select a currency');
        document.getElementById('currency').focus();
        return false;
    }
    
    // Additional validation for email
    const email = document.getElementById('email').value.trim();
    if (email && !isValidEmail(email)) {
        e.preventDefault();
        alert('Please enter a valid email address');
        document.getElementById('email').focus();
        return false;
    }
    
    // Payment term validation
    const paymentTerm = parseInt(document.getElementById('payment_term').value);
    if (paymentTerm < 0 || paymentTerm > 365) {
        e.preventDefault();
        alert('Payment term must be between 0-365 days');
        document.getElementById('payment_term').focus();
        return false;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
    submitBtn.disabled = true;
});

// Email validation helper
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Warning for status changes
document.getElementById('status').addEventListener('change', function() {
    const newStatus = this.value;
    const hasCosts = <?php echo $vendor_stats['total_costs']; ?>;
    const pendingPayments = <?php echo $vendor_stats['pending_payments']; ?>;
    
    if (newStatus === 'inactive' && hasCosts > 0) {
        let warningMessage = 'Warning: This vendor has existing cost records.';
        if (pendingPayments > 0) {
            warningMessage += ' There are pending payments of <?php echo formatMoney($vendor_stats['pending_payments']); ?>.';
        }
        warningMessage += ' Setting to inactive will prevent new costs from being added. Are you sure?';
        
        if (!confirm(warningMessage)) {
            this.value = originalFormData.status;
            return false;
        }
    }
});

// Vendor type change handler
document.getElementById('vendor_type').addEventListener('change', function() {
    const vendorType = this.value;
    const vendorCode = document.getElementById('vendor_code');
    
    // Auto-suggest vendor code prefix based on type
    if (vendorCode.value === '' || vendorCode.value === originalFormData.vendor_code) {
        const prefixes = {
            'shipping_line': 'SL',
            'airline': 'AL', 
            'trucking': 'TR',
            'customs_broker': 'CB',
            'warehouse': 'WH',
            'other': 'VEN'
        };
        
        const prefix = prefixes[vendorType] || 'VEN';
        const currentYear = new Date().getFullYear().toString().substr(-2);
        
        // Only suggest if it's a new vendor or unchanged from original
        if (vendorCode.value === originalFormData.vendor_code) {
            const confirmChange = confirm(`Would you like to update the vendor code to use the ${vendorType.replace('_', ' ')} prefix (${prefix})?`);
            if (confirmChange) {
                vendorCode.value = prefix + currentYear + '001';
                vendorCode.classList.add('border-warning');
            }
        }
    }
});

// Highlight changed fields
document.querySelectorAll('input, select, textarea').forEach(function(field) {
    field.addEventListener('input', function() {
        const fieldName = this.name;
        const currentValue = this.value;
        const originalValue = originalFormData[fieldName] || '';
        
        if (currentValue !== originalValue.toString()) {
            this.classList.add('border-warning');
            this.setAttribute('title', 'Field has been modified');
        } else {
            this.classList.remove('border-warning');
            this.removeAttribute('title');
        }
    });
});

// Auto-format phone number
document.getElementById('phone').addEventListener('input', function() {
    let value = this.value.replace(/\D/g, '');
    if (value.startsWith('66')) {
        // Thai format: +66 X-XXX-XXXX
        value = value.replace(/(\d{2})(\d{1})(\d{3})(\d{4})/, '+$1 $2-$3-$4');
    } else if (value.length === 10) {
        // Local format: 0X-XXXX-XXXX
        value = value.replace(/(\d{2})(\d{4})(\d{4})/, '$1-$2-$3');
    }
    this.value = value;
});

// Currency change warning
document.getElementById('currency').addEventListener('change', function() {
    const newCurrency = this.value;
    const hasCosts = <?php echo $vendor_stats['total_costs']; ?>;
    
    if (newCurrency !== originalFormData.currency && hasCosts > 0) {
        if (!confirm(`Warning: Changing currency from ${originalFormData.currency} to ${newCurrency} may affect existing cost records. Are you sure?`)) {
            this.value = originalFormData.currency;
            return false;
        }
    }
});

// Generate vendor report function
function generateVendorReport() {
    const vendorId = <?php echo $vendor_id; ?>;
    const vendorName = '<?php echo addslashes($vendor['company_name']); ?>';
    
    if (confirm(`Generate performance report for ${vendorName}?`)) {
        // In a real implementation, this would open a report generation page
        window.open(`vendor_report.php?id=${vendorId}`, '_blank');
    }
}

// Auto-focus on first field
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('vendor_code').focus();
    
    // Initialize tooltips for form help
    const tooltipElements = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipElements.forEach(function(element) {
        new bootstrap.Tooltip(element);
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + S = Save form
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.querySelector('form').submit();
    }
    
    // Ctrl/Cmd + R = Reset form
    if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
        e.preventDefault();
        resetToOriginal();
    }
    
    // Escape = Cancel
    if (e.key === 'Escape') {
        if (confirm('Are you sure you want to cancel editing? Any unsaved changes will be lost.')) {
            window.location.href = 'vendors_view.php?id=<?php echo $vendor_id; ?>';
        }
    }
});

// Form dirty state tracking
let formIsDirty = false;

document.querySelectorAll('input, select, textarea').forEach(function(field) {
    field.addEventListener('change', function() {
        formIsDirty = true;
    });
});

// Warn before leaving page if form is dirty
window.addEventListener('beforeunload', function(e) {
    if (formIsDirty) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        return e.returnValue;
    }
});

// Clear dirty flag on form submit
document.querySelector('form').addEventListener('submit', function() {
    formIsDirty = false;
});

// Delete confirmation
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.confirm-delete').forEach(function(element) {
        element.addEventListener('click', function(e) {
            const vendorName = '<?php echo addslashes($vendor['company_name']); ?>';
            const confirmMessage = `Are you sure you want to delete vendor "${vendorName}"?\n\nThis action cannot be undone.`;
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            this.classList.add('disabled');
        });
    });
});

// Smart form completion suggestions
document.getElementById('company_name').addEventListener('input', function() {
    const companyName = this.value;
    const contactField = document.getElementById('contact_person');
    const emailField = document.getElementById('email');
    
    // Auto-suggest email domain
    if (companyName && !emailField.value) {
        const domain = companyName.toLowerCase()
            .replace(/[^a-z0-9]/g, '')
            .replace(/ltd|limited|company|corp|corporation|inc/g, '');
        if (domain.length > 3) {
            emailField.placeholder = `contact@${domain}.com`;
        }
    }
});

// Vendor type specific validations and suggestions
document.getElementById('vendor_type').addEventListener('change', function() {
    const vendorType = this.value;
    const remarkField = document.getElementById('remark');
    
    // Add type-specific placeholder suggestions
    const suggestions = {
        'shipping_line': 'Service routes, vessel capacity, schedule reliability...',
        'airline': 'Flight frequencies, cargo capacity, delivery performance...',
        'trucking': 'Fleet size, coverage areas, delivery timeframes...',
        'customs_broker': 'License numbers, special certifications, clearance expertise...',
        'warehouse': 'Storage capacity, handling equipment, security features...',
        'other': 'Service specializations, unique capabilities...'
    };
    
    if (suggestions[vendorType] && !remarkField.value.trim()) {
        remarkField.placeholder = suggestions[vendorType];
    }
});

// Performance analytics for large vendors
<?php if ($vendor_stats['total_amount'] > 100000): ?>
console.log('High-value vendor detected. Consider implementing performance analytics.');

// Add performance indicator
document.addEventListener('DOMContentLoaded', function() {
    const statsCard = document.querySelector('.card-header h6');
    if (statsCard) {
        const performanceIcon = document.createElement('span');
        performanceIcon.className = 'badge bg-warning ms-2';
        performanceIcon.innerHTML = '<i class="fas fa-star"></i> High Value';
        performanceIcon.title = 'This vendor has high transaction volume';
        statsCard.appendChild(performanceIcon);
    }
});
<?php endif; ?>

// Real-time validation feedback
document.getElementById('vendor_code').addEventListener('input', function() {
    const code = this.value.toUpperCase();
    const pattern = /^[A-Z0-9]{2,20}$/;
    
    if (code && !pattern.test(code)) {
        this.classList.add('is-invalid');
        this.nextElementSibling.textContent = 'Vendor code should contain only letters and numbers';
    } else {
        this.classList.remove('is-invalid');
    }
    
    // Auto-uppercase
    this.value = code;
});

// Enhanced email validation with domain suggestions
document.getElementById('email').addEventListener('blur', function() {
    const email = this.value.trim();
    if (email && !isValidEmail(email)) {
        this.classList.add('is-invalid');
        
        // Suggest common fixes
        if (email.includes('@') && !email.includes('.')) {
            const domain = email.split('@')[1];
            const suggestions = ['com', 'co.th', 'net', 'org'];
            const suggestion = email + '.' + suggestions[0];
            
            if (confirm(`Did you mean: ${suggestion}?`)) {
                this.value = suggestion;
                this.classList.remove('is-invalid');
            }
        }
    } else {
        this.classList.remove('is-invalid');
    }
});

console.log('Vendor edit form initialized successfully');
</script>

<?php include 'includes/footer.php'; ?>