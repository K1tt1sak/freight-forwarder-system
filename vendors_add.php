<?php
// =====================================================
// vendors_add.php - Add New Vendor (Sequential Code Version)
// =====================================================

// Include functions first
require_once 'includes/functions.php';

// Require staff permission or higher
requirePermission('staff');

$errors = [];
$form_data = [
    'vendor_code' => '',
    'company_name' => '',
    'vendor_type' => '',
    'contact_person' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'tax_id' => '',
    'payment_term' => 30,
    'currency' => 'THB',
    'status' => 'active',
    'remark' => ''
];

// Function to generate next vendor code
function generateNextVendorCode() {
    $year = date('y');
    $prefix = "VEN{$year}";
    
    // Get the last vendor code for this year
    $last_vendor = fetchOne("
        SELECT vendor_code 
        FROM vendors 
        WHERE vendor_code LIKE ? 
        ORDER BY vendor_code DESC 
        LIMIT 1
    ", ["{$prefix}%"]);
    
    if ($last_vendor && isset($last_vendor['vendor_code'])) {
        // Extract the number part from the last code (e.g., VEN25001 -> 001)
        $last_number = (int)substr($last_vendor['vendor_code'], -3);
        $new_number = $last_number + 1;
    } else {
        // First vendor for this year
        $new_number = 1;
    }
    
    // Format as 3-digit number with leading zeros
    return $prefix . str_pad($new_number, 3, '0', STR_PAD_LEFT);
}

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
        // Check if vendor code already exists
        $existing = fetchOne("SELECT id FROM vendors WHERE vendor_code = ?", [$form_data['vendor_code']]);
        if ($existing) {
            $errors['vendor_code'] = 'Vendor code already exists';
        }
    }
    
    if (empty($form_data['company_name'])) {
        $errors['company_name'] = 'Company name is required';
    }
    
    if (empty($form_data['vendor_type'])) {
        $errors['vendor_type'] = 'Vendor type is required';
    }
    
    if (!empty($form_data['email']) && !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    
    if (!in_array($form_data['vendor_type'], ['shipping_line', 'airline', 'trucking', 'customs_broker', 'warehouse', 'other'])) {
        $errors['vendor_type'] = 'Invalid vendor type';
    }
    
    if (!in_array($form_data['status'], ['active', 'inactive'])) {
        $errors['status'] = 'Invalid status';
    }
    
    if ($form_data['payment_term'] < 0 || $form_data['payment_term'] > 365) {
        $errors['payment_term'] = 'Payment term must be between 0-365 days';
    }
    
    if (!in_array($form_data['currency'], ['THB', 'USD', 'EUR', 'SGD', 'CNY', 'JPY'])) {
        $errors['currency'] = 'Invalid currency';
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        $sql = "INSERT INTO vendors (
                    vendor_code, company_name, vendor_type, contact_person, phone, email, 
                    address, tax_id, payment_term, currency, 
                    status, remark, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
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
            $_SESSION['user_id']
        ];
        
        if (execute($sql, $params)) {
            $_SESSION['success_message'] = "Vendor '{$form_data['company_name']}' has been created successfully.";
            redirect('vendors.php');
            exit(); // Make sure script stops here
        } else {
            $errors['general'] = 'Failed to save vendor. Please try again.';
        }
    }
}

// Auto-generate vendor code suggestion (only if not submitted yet)
if (empty($form_data['vendor_code']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    $form_data['vendor_code'] = generateNextVendorCode();
}

// NOW set page variables and include header
$custom_page_title = "Add New Vendor";
$page_header = true;
$page_subtitle = "Create a new vendor profile in the system";
$breadcrumb = [
    ['name' => 'Vendors', 'url' => 'vendors.php'],
    ['name' => 'Add New Vendor']
];

include 'includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <!-- Main Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-truck-plus me-2"></i>Vendor Information
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $errors['general']; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" data-autosave="add_vendor">
                    <div class="row">
                        <!-- Vendor Code -->
                        <div class="col-md-6 mb-3">
                            <label for="vendor_code" class="form-label">
                                Vendor Code <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control <?php echo isset($errors['vendor_code']) ? 'is-invalid' : ''; ?>" 
                                       id="vendor_code" 
                                       name="vendor_code" 
                                       value="<?php echo htmlspecialchars($form_data['vendor_code'] ?? ''); ?>"
                                       placeholder="e.g., VEN25001"
                                       maxlength="20"
                                       required>
                                <button type="button" class="btn btn-outline-secondary" onclick="generateNewCode()" title="Generate Next Code">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                            <?php if (isset($errors['vendor_code'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['vendor_code']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>Sequential format: VEN + Year + 3-digit number (e.g., VEN25001)
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
                                <option value="shipping_line" <?php echo ($form_data['vendor_type'] ?? '') == 'shipping_line' ? 'selected' : ''; ?>>
                                    <i class="fas fa-ship"></i> Shipping Line
                                </option>
                                <option value="airline" <?php echo ($form_data['vendor_type'] ?? '') == 'airline' ? 'selected' : ''; ?>>
                                    <i class="fas fa-plane"></i> Airline
                                </option>
                                <option value="trucking" <?php echo ($form_data['vendor_type'] ?? '') == 'trucking' ? 'selected' : ''; ?>>
                                    <i class="fas fa-truck"></i> Trucking Company
                                </option>
                                <option value="customs_broker" <?php echo ($form_data['vendor_type'] ?? '') == 'customs_broker' ? 'selected' : ''; ?>>
                                    <i class="fas fa-file-alt"></i> Customs Broker
                                </option>
                                <option value="warehouse" <?php echo ($form_data['vendor_type'] ?? '') == 'warehouse' ? 'selected' : ''; ?>>
                                    <i class="fas fa-warehouse"></i> Warehouse
                                </option>
                                <option value="other" <?php echo ($form_data['vendor_type'] ?? '') == 'other' ? 'selected' : ''; ?>>
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
                                   value="<?php echo htmlspecialchars($form_data['contact_person'] ?? ''); ?>"
                                   placeholder="John Doe"
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
                        
                        <!-- Payment Term -->
                        <div class="col-md-6 mb-3">
                            <label for="payment_term" class="form-label">Payment Term (Days)</label>
                            <input type="number" 
                                   class="form-control <?php echo isset($errors['payment_term']) ? 'is-invalid' : ''; ?>" 
                                   id="payment_term" 
                                   name="payment_term" 
                                   value="<?php echo htmlspecialchars($form_data['payment_term'] ?? '30'); ?>"
                                   min="0" 
                                   max="365"
                                   placeholder="30">
                            <?php if (isset($errors['payment_term'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['payment_term']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">Days we have to pay this vendor after receiving invoice</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Currency -->
                        <div class="col-md-6 mb-3">
                            <label for="currency" class="form-label">
                                Preferred Currency <span class="text-danger">*</span>
                            </label>
                            <select class="form-select <?php echo isset($errors['currency']) ? 'is-invalid' : ''; ?>" 
                                    id="currency" 
                                    name="currency" 
                                    required>
                                <option value="THB" <?php echo ($form_data['currency'] ?? 'THB') == 'THB' ? 'selected' : ''; ?>>THB - Thai Baht</option>
                                <option value="USD" <?php echo ($form_data['currency'] ?? '') == 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                <option value="EUR" <?php echo ($form_data['currency'] ?? '') == 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                                <option value="SGD" <?php echo ($form_data['currency'] ?? '') == 'SGD' ? 'selected' : ''; ?>>SGD - Singapore Dollar</option>
                                <option value="CNY" <?php echo ($form_data['currency'] ?? '') == 'CNY' ? 'selected' : ''; ?>>CNY - Chinese Yuan</option>
                                <option value="JPY" <?php echo ($form_data['currency'] ?? '') == 'JPY' ? 'selected' : ''; ?>>JPY - Japanese Yen</option>
                            </select>
                            <?php if (isset($errors['currency'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['currency']; ?></div>
                            <?php endif; ?>
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
                                  placeholder="Additional notes about this vendor"><?php echo htmlspecialchars($form_data['remark'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Vendor
                        </button>
                        <a href="vendors.php" class="btn btn-outline-secondary">
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
                    <i class="fas fa-info-circle me-2"></i>Vendor Information Guide
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Vendor Code:</strong>
                    <p class="small text-muted mb-2">
                        Sequential unique identifier: VEN + Year + 3-digit number<br>
                        <strong>Examples:</strong> VEN25001, VEN25002, VEN25003...
                    </p>
                </div>
                
                <div class="mb-3">
                    <strong>Vendor Types:</strong>
                    <ul class="small text-muted mb-2">
                        <li><strong>Shipping Line:</strong> Ocean freight carriers</li>
                        <li><strong>Airline:</strong> Air freight carriers</li>
                        <li><strong>Trucking:</strong> Land transportation</li>
                        <li><strong>Customs Broker:</strong> Customs clearance services</li>
                        <li><strong>Warehouse:</strong> Storage and handling</li>
                        <li><strong>Other:</strong> Miscellaneous services</li>
                    </ul>
                </div>
                
                <div class="mb-3">
                    <strong>Payment Terms:</strong>
                    <p class="small text-muted mb-2">
                        Number of days we have to pay vendor invoices. Common terms: 0 (Cash), 30, 45, 60 days.
                    </p>
                </div>
                
                <div class="mb-0">
                    <strong>Currency:</strong>
                    <p class="small text-muted mb-0">
                        Preferred currency for transactions with this vendor. Used for cost calculations.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Vendor Type Statistics -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>Current Vendor Types
                </h6>
            </div>
            <div class="card-body">
                <?php
                $vendor_stats = [
                    'shipping_line' => fetchOne("SELECT COUNT(*) as count FROM vendors WHERE vendor_type = 'shipping_line'")['count'],
                    'airline' => fetchOne("SELECT COUNT(*) as count FROM vendors WHERE vendor_type = 'airline'")['count'],
                    'trucking' => fetchOne("SELECT COUNT(*) as count FROM vendors WHERE vendor_type = 'trucking'")['count'],
                    'customs_broker' => fetchOne("SELECT COUNT(*) as count FROM vendors WHERE vendor_type = 'customs_broker'")['count'],
                    'warehouse' => fetchOne("SELECT COUNT(*) as count FROM vendors WHERE vendor_type = 'warehouse'")['count'],
                    'other' => fetchOne("SELECT COUNT(*) as count FROM vendors WHERE vendor_type = 'other'")['count']
                ];
                ?>
                
                <div class="row text-center">
                    <div class="col-6 mb-2">
                        <div class="d-flex justify-content-between">
                            <span class="small"><i class="fas fa-ship text-info"></i> Shipping</span>
                            <span class="badge bg-info"><?php echo $vendor_stats['shipping_line']; ?></span>
                        </div>
                    </div>
                    <div class="col-6 mb-2">
                        <div class="d-flex justify-content-between">
                            <span class="small"><i class="fas fa-plane text-warning"></i> Airlines</span>
                            <span class="badge bg-warning"><?php echo $vendor_stats['airline']; ?></span>
                        </div>
                    </div>
                    <div class="col-6 mb-2">
                        <div class="d-flex justify-content-between">
                            <span class="small"><i class="fas fa-truck text-purple"></i> Trucking</span>
                            <span class="badge bg-purple"><?php echo $vendor_stats['trucking']; ?></span>
                        </div>
                    </div>
                    <div class="col-6 mb-2">
                        <div class="d-flex justify-content-between">
                            <span class="small"><i class="fas fa-file-alt text-secondary"></i> Customs</span>
                            <span class="badge bg-secondary"><?php echo $vendor_stats['customs_broker']; ?></span>
                        </div>
                    </div>
                    <div class="col-6 mb-2">
                        <div class="d-flex justify-content-between">
                            <span class="small"><i class="fas fa-warehouse text-success"></i> Warehouse</span>
                            <span class="badge bg-success"><?php echo $vendor_stats['warehouse']; ?></span>
                        </div>
                    </div>
                    <div class="col-6 mb-2">
                        <div class="d-flex justify-content-between">
                            <span class="small"><i class="fas fa-ellipsis-h text-dark"></i> Other</span>
                            <span class="badge bg-dark"><?php echo $vendor_stats['other']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Vendors -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>Recent Vendors
                </h6>
            </div>
            <div class="card-body">
                <?php
                $recent_vendors = fetchAll("
                    SELECT vendor_code, company_name, vendor_type, created_at 
                    FROM vendors 
                    ORDER BY created_at DESC 
                    LIMIT 5
                ");
                ?>
                
                <?php if (empty($recent_vendors)): ?>
                    <p class="text-muted small mb-0">No vendors yet. This will be your first vendor!</p>
                <?php else: ?>
                    <?php foreach ($recent_vendors as $recent): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <div class="fw-bold small"><?php echo htmlspecialchars($recent['vendor_code']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($recent['company_name']); ?></div>
                            <div class="text-muted small">
                                <?php
                                $type_icons = [
                                    'shipping_line' => '<i class="fas fa-ship text-info"></i>',
                                    'airline' => '<i class="fas fa-plane text-warning"></i>',
                                    'trucking' => '<i class="fas fa-truck text-purple"></i>',
                                    'customs_broker' => '<i class="fas fa-file-alt text-secondary"></i>',
                                    'warehouse' => '<i class="fas fa-warehouse text-success"></i>',
                                    'other' => '<i class="fas fa-ellipsis-h text-dark"></i>'
                                ];
                                echo $type_icons[$recent['vendor_type']] ?? '';
                                echo ' ' . ucfirst(str_replace('_', ' ', $recent['vendor_type']));
                                ?>
                            </div>
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

<style>
.text-purple {
    color: #6f42c1 !important;
}

.bg-purple {
    background-color: #6f42c1 !important;
}

.vendor-type-icon {
    font-size: 0.9em;
    margin-right: 0.5rem;
}
</style>

<script>
// Generate next sequential vendor code
function generateNewCode() {
    // Make AJAX request to get next sequential code
    fetch('ajax/generate_vendor_code.php', {
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
        if (data.success && data.vendor_code) {
            document.getElementById('vendor_code').value = data.vendor_code;
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
    const newCode = 'VEN' + year + String(seqNum).padStart(3, '0');
    document.getElementById('vendor_code').value = newCode;
}

// Reset form function
function resetForm() {
    if (confirm('Are you sure you want to reset all fields? All entered data will be lost.')) {
        document.querySelector('form').reset();
        // Clear localStorage auto-save data
        localStorage.removeItem('form_add_vendor');
        // Generate new vendor code
        generateNewCode();
    }
}

// Form validation before submit
document.querySelector('form').addEventListener('submit', function(e) {
    const vendorCode = document.getElementById('vendor_code').value.trim();
    const companyName = document.getElementById('company_name').value.trim();
    const vendorType = document.getElementById('vendor_type').value;
    
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
    // Focus on company name field since vendor code is auto-generated
    document.getElementById('company_name').focus();
});

// Auto-suggest common settings based on vendor type
document.getElementById('vendor_type').addEventListener('change', function() {
    const vendorType = this.value;
    const paymentTermField = document.getElementById('payment_term');
    const currencyField = document.getElementById('currency');
    
    // Auto-suggest payment terms based on vendor type
    switch(vendorType) {
        case 'shipping_line':
        case 'airline':
            paymentTermField.value = '30';
            currencyField.value = 'USD';
            break;
        case 'trucking':
        case 'warehouse':
            paymentTermField.value = '15';
            currencyField.value = 'THB';
            break;
        case 'customs_broker':
            paymentTermField.value = '7';
            currencyField.value = 'THB';
            break;
        default:
            paymentTermField.value = '30';
            currencyField.value = 'THB';
    }
});

// Phone number formatting (Thailand format)
document.getElementById('phone').addEventListener('input', function() {
    let value = this.value.replace(/\D/g, '');
    
    if (value.startsWith('66')) {
        // International format
        value = '+' + value.substring(0, 11);
    } else if (value.startsWith('0')) {
        // Thai format
        if (value.length > 10) value = value.substring(0, 10);
        value = value.replace(/(\d{2})(\d{3})(\d{4})/, '$1-$2-$3');
    }
    
    this.value = value;
});

// Tax ID formatting (Thailand format)
document.getElementById('tax_id').addEventListener('input', function() {
    let value = this.value.replace(/\D/g, '');
    if (value.length > 13) value = value.substring(0, 13);
    
    if (value.length >= 13) {
        value = value.replace(/(\d{1})(\d{4})(\d{5})(\d{2})(\d{1})/, '$1-$2-$3-$4-$5');
    }
    
    this.value = value;
});

// Payment term validation
document.getElementById('payment_term').addEventListener('change', function() {
    const value = parseInt(this.value);
    if (value < 0) {
        this.value = 0;
        alert('Payment term cannot be negative');
    } else if (value > 365) {
        this.value = 365;
        alert('Payment term cannot exceed 365 days');
    }
});

// Currency change warning
document.getElementById('currency').addEventListener('change', function() {
    const currency = this.value;
    if (currency !== 'THB') {
        const warningText = `Note: You selected ${currency}. Make sure to set up proper exchange rates for cost calculations.`;
        if (!document.getElementById('currency-warning')) {
            const warning = document.createElement('div');
            warning.id = 'currency-warning';
            warning.className = 'alert alert-info mt-2';
            warning.innerHTML = `<i class="fas fa-info-circle me-2"></i>${warningText}`;
            this.parentNode.appendChild(warning);
            
            // Auto-remove warning after 5 seconds
            setTimeout(() => {
                warning.remove();
            }, 5000);
        }
    } else {
        const existingWarning = document.getElementById('currency-warning');
        if (existingWarning) {
            existingWarning.remove();
        }
    }
});

// Form auto-save functionality
let autoSaveTimeout;
document.querySelectorAll('input, select, textarea').forEach(function(field) {
    field.addEventListener('input', function() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(function() {
            autoSaveForm();
        }, 2000); // Save after 2 seconds of inactivity
    });
});

function autoSaveForm() {
    const formData = {};
    document.querySelectorAll('input, select, textarea').forEach(function(field) {
        if (field.name) {
            formData[field.name] = field.value;
        }
    });
    
    localStorage.setItem('form_add_vendor', JSON.stringify(formData));
}

// Restore form data on page load
document.addEventListener('DOMContentLoaded', function() {
    const savedData = localStorage.getItem('form_add_vendor');
    if (savedData) {
        try {
            const formData = JSON.parse(savedData);
            Object.keys(formData).forEach(function(fieldName) {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (field && fieldName !== 'vendor_code') { // Don't restore vendor code
                    field.value = formData[fieldName];
                }
            });
        } catch (e) {
            console.log('Error restoring form data:', e);
        }
    }
});

// Clear auto-save data when form is submitted successfully
window.addEventListener('beforeunload', function() {
    if (document.querySelector('.alert-success')) {
        localStorage.removeItem('form_add_vendor');
    }
});

// Vendor code validation on input
document.getElementById('vendor_code').addEventListener('input', function() {
    let value = this.value.toUpperCase();
    // Allow only alphanumeric characters
    value = value.replace(/[^A-Z0-9]/g, '');
    this.value = value;
    
    // Real-time validation
    if (value.length >= 6) {
        checkVendorCodeAvailability(value);
    }
});

// Check if vendor code is available
function checkVendorCodeAvailability(code) {
    fetch('ajax/check_vendor_code.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            vendor_code: code
        })
    })
    .then(response => response.json())
    .then(data => {
        const codeField = document.getElementById('vendor_code');
        const feedback = codeField.parentNode.querySelector('.code-feedback');
        
        if (feedback) feedback.remove();
        
        const feedbackDiv = document.createElement('div');
        feedbackDiv.className = 'code-feedback form-text';
        
        if (data.available) {
            feedbackDiv.innerHTML = '<i class="fas fa-check text-success me-1"></i>Vendor code is available';
            feedbackDiv.classList.add('text-success');
            codeField.classList.remove('is-invalid');
            codeField.classList.add('is-valid');
        } else {
            feedbackDiv.innerHTML = '<i class="fas fa-times text-danger me-1"></i>Vendor code is already taken';
            feedbackDiv.classList.add('text-danger');
            codeField.classList.remove('is-valid');
            codeField.classList.add('is-invalid');
        }
        
        codeField.parentNode.appendChild(feedbackDiv);
    })
    .catch(error => {
        console.log('Error checking vendor code:', error);
    });
}

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
        resetForm();
    }
    
    // Escape = Cancel/Go back
    if (e.key === 'Escape') {
        if (confirm('Are you sure you want to leave? Any unsaved changes will be lost.')) {
            window.location.href = 'vendors.php';
        }
    }
});

// Enhanced vendor type selection with icons and descriptions
document.getElementById('vendor_type').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const typeDescriptions = {
        'shipping_line': 'Ocean freight carriers - handle sea transportation of containers and cargo',
        'airline': 'Air freight carriers - handle air transportation of cargo and packages',
        'trucking': 'Land transportation companies - handle truck delivery and pickup services',
        'customs_broker': 'Customs clearance specialists - handle import/export documentation and procedures',
        'warehouse': 'Storage and handling facilities - provide warehousing and distribution services',
        'other': 'Other service providers - miscellaneous freight and logistics services'
    };
    
    const description = typeDescriptions[this.value];
    let descDiv = document.getElementById('vendor-type-description');
    
    if (description) {
        if (!descDiv) {
            descDiv = document.createElement('div');
            descDiv.id = 'vendor-type-description';
            descDiv.className = 'alert alert-info mt-2';
            this.parentNode.appendChild(descDiv);
        }
        descDiv.innerHTML = `<i class="fas fa-info-circle me-2"></i>${description}`;
    } else if (descDiv) {
        descDiv.remove();
    }
});

// Progressive form enhancement
document.addEventListener('DOMContentLoaded', function() {
    // Add progress indicator
    const form = document.querySelector('form');
    const progressBar = document.createElement('div');
    progressBar.className = 'progress mb-3';
    progressBar.innerHTML = '<div class="progress-bar" role="progressbar" style="width: 0%"></div>';
    form.insertBefore(progressBar, form.firstChild);
    
    // Update progress as user fills form
    const requiredFields = form.querySelectorAll('[required]');
    
    function updateProgress() {
        let filledFields = 0;
        requiredFields.forEach(field => {
            if (field.value.trim()) filledFields++;
        });
        
        const progress = (filledFields / requiredFields.length) * 100;
        const progressBarFill = progressBar.querySelector('.progress-bar');
        progressBarFill.style.width = progress + '%';
        progressBarFill.setAttribute('aria-valuenow', progress);
        
        if (progress === 100) {
            progressBarFill.classList.add('bg-success');
            progressBarFill.innerHTML = 'Ready to submit!';
        } else {
            progressBarFill.classList.remove('bg-success');
            progressBarFill.innerHTML = Math.round(progress) + '% complete';
        }
    }
    
    requiredFields.forEach(field => {
        field.addEventListener('input', updateProgress);
        field.addEventListener('change', updateProgress);
    });
    
    // Initial progress check
    updateProgress();
});
</script>

<?php include 'includes/footer.php'; ?>