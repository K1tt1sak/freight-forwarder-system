<?php
// =====================================================
// customers_edit.php - Edit Customer (Fixed Version)
// =====================================================

// Include functions first
require_once 'includes/functions.php';

// Require staff permission or higher
requirePermission('staff');

// Get customer ID
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$customer_id) {
    $_SESSION['error_message'] = "Customer ID is required.";
    redirect('customers.php');
    exit();
}

// Get customer data
$customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$customer_id]);

if (!$customer) {
    $_SESSION['error_message'] = "Customer not found.";
    redirect('customers.php');
    exit();
}

$errors = [];
$form_data = $customer; // Initialize with existing data

// Handle form submission BEFORE any output
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and clean form data
    $form_data = [
        'customer_code' => strtoupper(cleanInput($_POST['customer_code'])),
        'company_name' => cleanInput($_POST['company_name']),
        'contact_person' => cleanInput($_POST['contact_person']),
        'phone' => cleanInput($_POST['phone']),
        'email' => cleanInput($_POST['email']),
        'fax' => cleanInput($_POST['fax']),
        'address' => cleanInput($_POST['address']),
        'tax_id' => cleanInput($_POST['tax_id']),
        'customer_type' => cleanInput($_POST['customer_type']),
        'credit_term' => (int)$_POST['credit_term'],
        'credit_limit' => (float)str_replace(',', '', $_POST['credit_limit']),
        'status' => cleanInput($_POST['status']),
        'remark' => cleanInput($_POST['remark'])
    ];
    
    // Validation
    if (empty($form_data['customer_code'])) {
        $errors['customer_code'] = 'Customer code is required';
    } elseif (strlen($form_data['customer_code']) < 2) {
        $errors['customer_code'] = 'Customer code must be at least 2 characters';
    } else {
        // Check if customer code already exists (exclude current customer)
        $existing = fetchOne("SELECT id FROM customers WHERE customer_code = ? AND id != ?", 
                           [$form_data['customer_code'], $customer_id]);
        if ($existing) {
            $errors['customer_code'] = 'Customer code already exists';
        }
    }
    
    if (empty($form_data['company_name'])) {
        $errors['company_name'] = 'Company name is required';
    }
    
    if (!empty($form_data['email']) && !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    
    if (!in_array($form_data['customer_type'], ['shipper', 'consignee', 'agent', 'both'])) {
        $errors['customer_type'] = 'Invalid customer type';
    }
    
    if (!in_array($form_data['status'], ['active', 'inactive', 'blacklist'])) {
        $errors['status'] = 'Invalid status';
    }
    
    if ($form_data['credit_term'] < 0 || $form_data['credit_term'] > 365) {
        $errors['credit_term'] = 'Credit term must be between 0-365 days';
    }
    
    if ($form_data['credit_limit'] < 0) {
        $errors['credit_limit'] = 'Credit limit cannot be negative';
    }
    
    // If no errors, update database
    if (empty($errors)) {
        $sql = "UPDATE customers SET 
                    customer_code = ?, company_name = ?, contact_person = ?, phone = ?, 
                    email = ?, fax = ?, address = ?, tax_id = ?, customer_type = ?, 
                    credit_term = ?, credit_limit = ?, status = ?, remark = ?, 
                    updated_at = NOW()
                WHERE id = ?";
        
        $params = [
            $form_data['customer_code'],
            $form_data['company_name'],
            $form_data['contact_person'],
            $form_data['phone'],
            $form_data['email'],
            $form_data['fax'],
            $form_data['address'],
            $form_data['tax_id'],
            $form_data['customer_type'],
            $form_data['credit_term'],
            $form_data['credit_limit'],
            $form_data['status'],
            $form_data['remark'],
            $customer_id
        ];
        
        if (execute($sql, $params)) {
            $_SESSION['success_message'] = "Customer '{$form_data['company_name']}' has been updated successfully.";
            redirect('customers_view.php?id=' . $customer_id);
            exit(); // Make sure script stops here
        } else {
            $errors['general'] = 'Failed to update customer. Please try again.';
        }
    }
}

// Get customer statistics
$customer_stats = [
    'total_jobs' => fetchOne("SELECT COUNT(*) as count FROM jobs WHERE shipper_id = ? OR consignee_id = ?", 
                           [$customer_id, $customer_id])['count'],
    'active_jobs' => fetchOne("SELECT COUNT(*) as count FROM jobs WHERE (shipper_id = ? OR consignee_id = ?) AND status NOT IN ('completed', 'cancelled')", 
                            [$customer_id, $customer_id])['count'],
    'total_invoices' => fetchOne("SELECT COUNT(*) as count FROM invoices WHERE customer_id = ?", 
                               [$customer_id])['count'],
    'outstanding_amount' => fetchOne("SELECT COALESCE(SUM(total_amount - paid_amount), 0) as amount FROM invoices WHERE customer_id = ? AND payment_status IN ('pending', 'partial')", 
                                   [$customer_id])['amount']
];

// NOW set page variables and include header
$custom_page_title = "Edit Customer - " . $customer['company_name'];
$page_header = true;
$page_subtitle = "Update customer information and settings";
$breadcrumb = [
    ['name' => 'Customers', 'url' => 'customers.php'],
    ['name' => $customer['company_name'], 'url' => 'customers_view.php?id=' . $customer_id],
    ['name' => 'Edit']
];

include 'includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <!-- Customer Status Alert -->
        <?php if ($customer['status'] != 'active'): ?>
        <div class="alert alert-<?php echo $customer['status'] == 'blacklist' ? 'danger' : 'warning'; ?>">
            <i class="fas fa-<?php echo $customer['status'] == 'blacklist' ? 'ban' : 'exclamation-triangle'; ?> me-2"></i>
            This customer is currently <strong><?php echo strtoupper($customer['status']); ?></strong>. 
            <?php if ($customer['status'] == 'blacklist'): ?>
                No new jobs can be created for blacklisted customers.
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Main Form -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-user-edit me-2"></i>Edit Customer Information
                    </h5>
                    <div class="text-light small">
                        Last updated: <?php echo formatDateThai($customer['updated_at'], 'd/m/Y H:i'); ?>
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
                
                <form method="POST" action="" data-autosave="edit_customer_<?php echo $customer_id; ?>">
                    <div class="row">
                        <!-- Customer Code -->
                        <div class="col-md-6 mb-3">
                            <label for="customer_code" class="form-label">
                                Customer Code <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control <?php echo isset($errors['customer_code']) ? 'is-invalid' : ''; ?>" 
                                   id="customer_code" 
                                   name="customer_code" 
                                   value="<?php echo htmlspecialchars($form_data['customer_code']); ?>"
                                   placeholder="e.g., CUS25001"
                                   maxlength="20"
                                   required>
                            <?php if (isset($errors['customer_code'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['customer_code']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>Original code: <?php echo htmlspecialchars($customer['customer_code']); ?>
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
                                   placeholder="Company Name Ltd."
                                   maxlength="200"
                                   required>
                            <?php if (isset($errors['company_name'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['company_name']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Contact Person -->
                        <div class="col-md-6 mb-3">
                            <label for="contact_person" class="form-label">Contact Person</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="contact_person" 
                                   name="contact_person" 
                                   value="<?php echo htmlspecialchars($form_data['contact_person']); ?>"
                                   placeholder="John Doe"
                                   maxlength="100">
                        </div>
                        
                        <!-- Customer Type -->
                        <div class="col-md-6 mb-3">
                            <label for="customer_type" class="form-label">
                                Customer Type <span class="text-danger">*</span>
                            </label>
                            <select class="form-select <?php echo isset($errors['customer_type']) ? 'is-invalid' : ''; ?>" 
                                    id="customer_type" 
                                    name="customer_type" 
                                    required>
                                <option value="">Select Customer Type</option>
                                <option value="shipper" <?php echo $form_data['customer_type'] == 'shipper' ? 'selected' : ''; ?>>
                                    Shipper Only
                                </option>
                                <option value="consignee" <?php echo $form_data['customer_type'] == 'consignee' ? 'selected' : ''; ?>>
                                    Consignee Only
                                </option>
                                <option value="agent" <?php echo $form_data['customer_type'] == 'agent' ? 'selected' : ''; ?>>
                                    Agent
                                </option>
                                <option value="both" <?php echo $form_data['customer_type'] == 'both' ? 'selected' : ''; ?>>
                                    Both Shipper & Consignee
                                </option>
                            </select>
                            <?php if (isset($errors['customer_type'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['customer_type']; ?></div>
                            <?php endif; ?>
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
                                   placeholder="contact@company.com"
                                   maxlength="100">
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Fax -->
                        <div class="col-md-6 mb-3">
                            <label for="fax" class="form-label">Fax Number</label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="fax" 
                                   name="fax" 
                                   value="<?php echo htmlspecialchars($form_data['fax']); ?>"
                                   placeholder="+66 2-123-4568"
                                   maxlength="50">
                        </div>
                        
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
                    </div>
                    
                    <!-- Address -->
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" 
                                  id="address" 
                                  name="address" 
                                  rows="3"
                                  placeholder="Complete address with postal code"><?php echo htmlspecialchars($form_data['address']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <!-- Credit Term -->
                        <div class="col-md-4 mb-3">
                            <label for="credit_term" class="form-label">Credit Term (Days)</label>
                            <input type="number" 
                                   class="form-control <?php echo isset($errors['credit_term']) ? 'is-invalid' : ''; ?>" 
                                   id="credit_term" 
                                   name="credit_term" 
                                   value="<?php echo htmlspecialchars($form_data['credit_term']); ?>"
                                   min="0" 
                                   max="365"
                                   placeholder="30">
                            <?php if (isset($errors['credit_term'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['credit_term']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Credit Limit -->
                        <div class="col-md-4 mb-3">
                            <label for="credit_limit" class="form-label">Credit Limit (THB)</label>
                            <input type="text" 
                                   class="form-control <?php echo isset($errors['credit_limit']) ? 'is-invalid' : ''; ?>" 
                                   id="credit_limit" 
                                   name="credit_limit" 
                                   value="<?php echo formatNumber($form_data['credit_limit'], 0); ?>"
                                   placeholder="100,000"
                                   data-format="number">
                            <?php if (isset($errors['credit_limit'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['credit_limit']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Status -->
                        <div class="col-md-4 mb-3">
                            <label for="status" class="form-label">
                                Status <span class="text-danger">*</span>
                            </label>
                            <select class="form-select <?php echo isset($errors['status']) ? 'is-invalid' : ''; ?>" 
                                    id="status" 
                                    name="status" 
                                    required>
                                <option value="active" <?php echo $form_data['status'] == 'active' ? 'selected' : ''; ?>>
                                    Active
                                </option>
                                <option value="inactive" <?php echo $form_data['status'] == 'inactive' ? 'selected' : ''; ?>>
                                    Inactive
                                </option>
                                <?php if (hasPermission('manager')): ?>
                                <option value="blacklist" <?php echo $form_data['status'] == 'blacklist' ? 'selected' : ''; ?>>
                                    Blacklist
                                </option>
                                <?php endif; ?>
                            </select>
                            <?php if (isset($errors['status'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['status']; ?></div>
                            <?php endif; ?>
                            <?php if (!hasPermission('manager')): ?>
                                <div class="form-text">Only managers can blacklist customers</div>
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
                                  placeholder="Additional notes about this customer"><?php echo htmlspecialchars($form_data['remark']); ?></textarea>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Customer
                        </button>
                        <a href="customers_view.php?id=<?php echo $customer_id; ?>" class="btn btn-outline-secondary">
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
        <!-- Customer Statistics -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>Customer Statistics
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="border-end">
                            <h4 class="text-primary mb-1"><?php echo $customer_stats['total_jobs']; ?></h4>
                            <small class="text-muted">Total Jobs</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <h4 class="text-warning mb-1"><?php echo $customer_stats['active_jobs']; ?></h4>
                        <small class="text-muted">Active Jobs</small>
                    </div>
                    <div class="col-6">
                        <div class="border-end">
                            <h4 class="text-info mb-1"><?php echo $customer_stats['total_invoices']; ?></h4>
                            <small class="text-muted">Total Invoices</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h4 class="text-danger mb-1"><?php echo formatNumber($customer_stats['outstanding_amount'], 0); ?></h4>
                        <small class="text-muted">Outstanding (THB)</small>
                    </div>
                </div>
                
                <?php if ($customer_stats['total_jobs'] > 0): ?>
                <hr>
                <div class="d-grid gap-2">
                    <a href="jobs.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-shipping-fast me-2"></i>View All Jobs
                    </a>
                    <?php if ($customer_stats['total_invoices'] > 0): ?>
                    <a href="invoices.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-receipt me-2"></i>View All Invoices
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Customer Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>Customer Information
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Created:</strong><br>
                    <small class="text-muted">
                        <?php echo formatDateThai($customer['created_at'], 'd/m/Y H:i'); ?>
                        <?php if ($customer['created_by']): ?>
                            <?php 
                            $creator = fetchOne("SELECT name FROM users WHERE id = ?", [$customer['created_by']]);
                            if ($creator): ?>
                                <br>by <?php echo htmlspecialchars($creator['name']); ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </small>
                </div>
                
                <div class="mb-3">
                    <strong>Last Updated:</strong><br>
                    <small class="text-muted">
                        <?php echo formatDateThai($customer['updated_at'], 'd/m/Y H:i'); ?>
                    </small>
                </div>
                
                <?php if ($customer_stats['outstanding_amount'] > 0): ?>
                <div class="alert alert-warning py-2">
                    <small>
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Outstanding Balance:</strong><br>
                        <?php echo formatMoney($customer_stats['outstanding_amount']); ?>
                    </small>
                </div>
                <?php endif; ?>
                
                <?php if ($customer['status'] == 'blacklist'): ?>
                <div class="alert alert-danger py-2">
                    <small>
                        <i class="fas fa-ban me-1"></i>
                        <strong>Blacklisted Customer</strong><br>
                        Cannot create new jobs for this customer.
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
                    <a href="customers_view.php?id=<?php echo $customer_id; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-eye me-2"></i>View Customer Details
                    </a>
                    
                    <?php if ($customer['status'] == 'active'): ?>
                    <a href="jobs_add.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-plus me-2"></i>Create New Job
                    </a>
                    <a href="quotations_add.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Create Quotation
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($customer['email']): ?>
                    <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-envelope me-2"></i>Send Email
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('manager') && $customer_stats['total_jobs'] == 0): ?>
                    <a href="customers.php?action=delete&id=<?php echo $customer_id; ?>" 
                       class="btn btn-outline-danger btn-sm confirm-delete">
                        <i class="fas fa-trash me-2"></i>Delete Customer
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Store original form values for reset functionality
const originalFormData = <?php echo json_encode($customer); ?>;

// Format credit limit with thousand separators
document.getElementById('credit_limit').addEventListener('input', function() {
    let value = this.value.replace(/[^\d.]/g, '');
    if (value) {
        const parts = value.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        this.value = parts.join('.');
    }
</script>

<?php include 'includes/footer.php'; ?>

// Reset to original values
function resetToOriginal() {
    if (confirm('Are you sure you want to reset all fields to their original values? All changes will be lost.')) {
        // Reset all form fields to original values
        document.getElementById('customer_code').value = originalFormData.customer_code || '';
        document.getElementById('company_name').value = originalFormData.company_name || '';
        document.getElementById('contact_person').value = originalFormData.contact_person || '';
        document.getElementById('phone').value = originalFormData.phone || '';
        document.getElementById('email').value = originalFormData.email || '';
        document.getElementById('fax').value = originalFormData.fax || '';
        document.getElementById('address').value = originalFormData.address || '';
        document.getElementById('tax_id').value = originalFormData.tax_id || '';
        document.getElementById('customer_type').value = originalFormData.customer_type || '';
        document.getElementById('credit_term').value = originalFormData.credit_term || '30';
        document.getElementById('credit_limit').value = parseFloat(originalFormData.credit_limit || 0).toLocaleString();
        document.getElementById('status').value = originalFormData.status || 'active';
        document.getElementById('remark').value = originalFormData.remark || '';
        
        // Clear localStorage auto-save data
        localStorage.removeItem('form_edit_customer_<?php echo $customer_id; ?>');
        
        // Remove any validation error classes
        document.querySelectorAll('.is-invalid').forEach(function(element) {
            element.classList.remove('is-invalid');
        });
    }
}

// Form validation before submit
document.querySelector('form').addEventListener('submit', function(e) {
    const customerCode = document.getElementById('customer_code').value.trim();
    const companyName = document.getElementById('company_name').value.trim();
    const customerType = document.getElementById('customer_type').value;
    
    if (!customerCode || customerCode.length < 2) {
        e.preventDefault();
        alert('Please enter a valid customer code (at least 2 characters)');
        document.getElementById('customer_code').focus();
        return false;
    }
    
    if (!companyName) {
        e.preventDefault();
        alert('Please enter the company name');
        document.getElementById('company_name').focus();
        return false;
    }
    
    if (!customerType) {
        e.preventDefault();
        alert('Please select a customer type');
        document.getElementById('customer_type').focus();
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
    const hasJobs = <?php echo $customer_stats['total_jobs']; ?>;
    
    if (newStatus === 'blacklist' && hasJobs > 0) {
        if (!confirm('Warning: This customer has existing jobs. Blacklisting will prevent creation of new jobs. Are you sure?')) {
            this.value = originalFormData.status;
            return false;
        }
    }
    
    if (newStatus === 'inactive' && hasJobs > 0) {
        if (!confirm('Warning: This customer has existing jobs. Setting to inactive may affect ongoing operations. Are you sure?')) {
            this.value = originalFormData.status;
            return false;
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
        } else {
            this.classList.remove('border-warning');
        }
    });
});

// Auto-focus on first field
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('customer_code').focus();
});