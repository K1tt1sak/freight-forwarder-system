<?php
// =====================================================
// customers_add.php - Add New Customer (Sequential Code Version)
// =====================================================

// Include functions first
require_once 'includes/functions.php';

// Require staff permission or higher
requirePermission('staff');

$errors = [];
$form_data = [
    'customer_code' => '',
    'company_name' => '',
    'contact_person' => '',
    'phone' => '',
    'email' => '',
    'fax' => '',
    'address' => '',
    'tax_id' => '',
    'customer_type' => '',
    'credit_term' => 30,
    'credit_limit' => 0,
    'status' => 'active',
    'remark' => ''
];

// Function to generate next customer code
function generateNextCustomerCode() {
    $year = date('y');
    $prefix = "CUS{$year}";
    
    // Get the last customer code for this year
    $last_customer = fetchOne("
        SELECT customer_code 
        FROM customers 
        WHERE customer_code LIKE ? 
        ORDER BY customer_code DESC 
        LIMIT 1
    ", ["{$prefix}%"]);
    
    if ($last_customer && isset($last_customer['customer_code'])) {
        // Extract the number part from the last code (e.g., CUS25001 -> 001)
        $last_number = (int)substr($last_customer['customer_code'], -3);
        $new_number = $last_number + 1;
    } else {
        // First customer for this year
        $new_number = 1;
    }
    
    // Format as 3-digit number with leading zeros
    return $prefix . str_pad($new_number, 3, '0', STR_PAD_LEFT);
}

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
        // Check if customer code already exists
        $existing = fetchOne("SELECT id FROM customers WHERE customer_code = ?", [$form_data['customer_code']]);
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
    
    if (!in_array($form_data['status'], ['active', 'inactive'])) {
        $errors['status'] = 'Invalid status';
    }
    
    if ($form_data['credit_term'] < 0 || $form_data['credit_term'] > 365) {
        $errors['credit_term'] = 'Credit term must be between 0-365 days';
    }
    
    if ($form_data['credit_limit'] < 0) {
        $errors['credit_limit'] = 'Credit limit cannot be negative';
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        $sql = "INSERT INTO customers (
                    customer_code, company_name, contact_person, phone, email, fax, 
                    address, tax_id, customer_type, credit_term, credit_limit, 
                    status, remark, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
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
            $_SESSION['user_id']
        ];
        
        if (execute($sql, $params)) {
            $_SESSION['success_message'] = "Customer '{$form_data['company_name']}' has been created successfully.";
            redirect('customers.php');
            exit(); // Make sure script stops here
        } else {
            $errors['general'] = 'Failed to save customer. Please try again.';
        }
    }
}

// Auto-generate customer code suggestion (only if not submitted yet)
if (empty($form_data['customer_code']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    $form_data['customer_code'] = generateNextCustomerCode();
}

// NOW set page variables and include header
$custom_page_title = "Add New Customer";
$page_header = true;
$page_subtitle = "Create a new customer profile in the system";
$breadcrumb = [
    ['name' => 'Customers', 'url' => 'customers.php'],
    ['name' => 'Add New Customer']
];

include 'includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <!-- Main Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-plus me-2"></i>Customer Information
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $errors['general']; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" data-autosave="add_customer">
                    <div class="row">
                        <!-- Customer Code -->
                        <div class="col-md-6 mb-3">
                            <label for="customer_code" class="form-label">
                                Customer Code <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control <?php echo isset($errors['customer_code']) ? 'is-invalid' : ''; ?>" 
                                       id="customer_code" 
                                       name="customer_code" 
                                       value="<?php echo htmlspecialchars($form_data['customer_code'] ?? ''); ?>"
                                       placeholder="e.g., CUS25001"
                                       maxlength="20"
                                       required>
                                <button type="button" class="btn btn-outline-secondary" onclick="generateNewCode()" title="Generate Next Code">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                            <?php if (isset($errors['customer_code'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['customer_code']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>Sequential format: CUS + Year + 3-digit number (e.g., CUS25001)
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
                                   value="<?php echo htmlspecialchars($form_data['company_name'] ?? ''); ?>"
                                   placeholder="Company Name Ltd."
                                   maxlength="200"
                                   required>
                            <?php if (isset($errors['company_name'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['company_name']; ?></div>
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
                                   value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>"
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
                                   value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
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
                                   value="<?php echo htmlspecialchars($form_data['fax'] ?? ''); ?>"
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
                                   value="<?php echo htmlspecialchars($form_data['tax_id'] ?? ''); ?>"
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
                                  placeholder="Complete address with postal code"><?php echo htmlspecialchars($form_data['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <!-- Contact Person -->
                        <div class="col-md-6 mb-3">
                            <label for="contact_person" class="form-label">Contact Person</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="contact_person" 
                                   name="contact_person" 
                                   value="<?php echo htmlspecialchars($form_data['contact_person'] ?? ''); ?>"
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
                                <option value="shipper" <?php echo ($form_data['customer_type'] ?? '') == 'shipper' ? 'selected' : ''; ?>>
                                    Shipper Only
                                </option>
                                <option value="consignee" <?php echo ($form_data['customer_type'] ?? '') == 'consignee' ? 'selected' : ''; ?>>
                                    Consignee Only
                                </option>
                                <option value="agent" <?php echo ($form_data['customer_type'] ?? '') == 'agent' ? 'selected' : ''; ?>>
                                    Agent
                                </option>
                                <option value="both" <?php echo ($form_data['customer_type'] ?? '') == 'both' ? 'selected' : ''; ?>>
                                    Both Shipper & Consignee
                                </option>
                            </select>
                            <?php if (isset($errors['customer_type'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['customer_type']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>    
                    
                    <div class="row">
                        <!-- Credit Term -->
                        <div class="col-md-4 mb-3">
                            <label for="credit_term" class="form-label">Credit Term (Days)</label>
                            <input type="number" 
                                   class="form-control <?php echo isset($errors['credit_term']) ? 'is-invalid' : ''; ?>" 
                                   id="credit_term" 
                                   name="credit_term" 
                                   value="<?php echo htmlspecialchars($form_data['credit_term'] ?? '30'); ?>"
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
                                   value="<?php echo htmlspecialchars($form_data['credit_limit'] ?? '0'); ?>"
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
                                <option value="active" <?php echo ($form_data['status'] ?? 'active') == 'active' ? 'selected' : ''; ?>>
                                    Active
                                </option>
                                <option value="inactive" <?php echo ($form_data['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>
                                    Inactive
                                </option>
                            </select>
                            <?php if (isset($errors['status'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['status']; ?></div>
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
                                  placeholder="Additional notes about this customer"><?php echo htmlspecialchars($form_data['remark'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Customer
                        </button>
                        <a href="customers.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="reset" class="btn btn-outline-warning" onclick="resetForm()">
                            <i class="fas fa-undo me-2"></i>Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Sidebar Info -->
    <div class="col-lg-4">
        <!-- Help Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>Customer Information Guide
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Customer Code:</strong>
                    <p class="small text-muted mb-2">
                        Sequential unique identifier: CUS + Year + 3-digit number<br>
                        <strong>Examples:</strong> CUS25001, CUS25002, CUS25003...
                    </p>
                </div>
                
                <div class="mb-3">
                    <strong>Customer Types:</strong>
                    <ul class="small text-muted mb-2">
                        <li><strong>Shipper Only:</strong> Only sends goods</li>
                        <li><strong>Consignee Only:</strong> Only receives goods</li>
                        <li><strong>Agent:</strong> Acts as representative</li>
                        <li><strong>Both:</strong> Can be shipper or consignee</li>
                    </ul>
                </div>
                
                <div class="mb-3">
                    <strong>Credit Terms:</strong>
                    <p class="small text-muted mb-2">
                        Number of days customer has to pay invoices. Common terms: 0 (Cash), 30, 45, 60 days.
                    </p>
                </div>
                
                <div class="mb-0">
                    <strong>Credit Limit:</strong>
                    <p class="small text-muted mb-0">
                        Maximum outstanding amount allowed for this customer. Set to 0 for unlimited credit.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Recent Customers -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>Recent Customers
                </h6>
            </div>
            <div class="card-body">
                <?php
                $recent_customers = fetchAll("
                    SELECT customer_code, company_name, created_at 
                    FROM customers 
                    ORDER BY created_at DESC 
                    LIMIT 5
                ");
                ?>
                
                <?php if (empty($recent_customers)): ?>
                    <p class="text-muted small mb-0">No customers yet. This will be your first customer!</p>
                <?php else: ?>
                    <?php foreach ($recent_customers as $recent): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <div class="fw-bold small"><?php echo htmlspecialchars($recent['customer_code']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($recent['company_name']); ?></div>
                        </div>
                        <small class="text-muted">
                            <?php echo formatDateThai($recent['created_at'], 'd/m'); ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Generate next sequential customer code
function generateNewCode() {
    // Make AJAX request to get next sequential code
    fetch('ajax/generate_customer_code.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_next_sequential'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.customer_code) {
            document.getElementById('customer_code').value = data.customer_code;
        } else {
            // Fallback: generate client-side sequential
            generateSequentialCodeFallback();
        }
    })
    .catch(error => {
        console.log('Error generating code:', error);
        // Fallback: generate client-side sequential
        generateSequentialCodeFallback();
    });
}

// Fallback function for client-side sequential generation
function generateSequentialCodeFallback() {
    const year = new Date().getFullYear().toString().substr(-2);
    const now = Date.now();
    // Use timestamp seconds as a reasonably sequential number
    const seqNum = Math.floor(now / 1000) % 1000;
    const newCode = 'CUS' + year + String(seqNum).padStart(3, '0');
    document.getElementById('customer_code').value = newCode;
}

// Format credit limit with thousand separators
document.getElementById('credit_limit').addEventListener('input', function() {
    let value = this.value.replace(/[^\d.]/g, '');
    if (value) {
        const parts = value.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        this.value = parts.join('.');
    }
});

// Reset form function
function resetForm() {
    if (confirm('Are you sure you want to reset all fields? All entered data will be lost.')) {
        document.querySelector('form').reset();
        // Clear localStorage auto-save data
        localStorage.removeItem('form_add_customer');
        // Generate new customer code
        generateNewCode();
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
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
    submitBtn.disabled = true;
});

// Email validation helper
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Auto-focus on first editable field when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Focus on company name field since customer code is auto-generated
    document.getElementById('company_name').focus();
});
</script>

<?php include 'includes/footer.php'; ?>