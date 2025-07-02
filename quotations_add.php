<?php
// =====================================================
// quotations_add.php - Create New Quotation
// =====================================================

// Include functions first
require_once 'includes/functions.php';

// Require staff permission or higher
requirePermission('staff');

$errors = [];
$form_data = [
    'quotation_no' => '',
    'customer_id' => '',
    'quotation_date' => date('Y-m-d'),
    'valid_until' => date('Y-m-d', strtotime('+30 days')),
    'job_type' => '',
    'service_type' => '',
    'origin' => '',
    'destination' => '',
    'cargo_description' => '',
    'currency' => 'THB',
    'remark' => ''
];

// Pre-fill customer if provided
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
if ($customer_id > 0) {
    $form_data['customer_id'] = $customer_id;
}

// Function to generate next quotation number
function generateNextQuotationNumber() {
    $year = date('y');
    $month = date('m');
    $prefix = "QT{$year}{$month}";
    
    // Get the last quotation number for this month
    $last_quotation = fetchOne("
        SELECT quotation_no 
        FROM quotations 
        WHERE quotation_no LIKE ? 
        ORDER BY quotation_no DESC 
        LIMIT 1
    ", ["{$prefix}%"]);
    
    if ($last_quotation && isset($last_quotation['quotation_no'])) {
        // Extract the number part (e.g., QT2501001 -> 001)
        $last_number = (int)substr($last_quotation['quotation_no'], -3);
        $new_number = $last_number + 1;
    } else {
        // First quotation for this month
        $new_number = 1;
    }
    
    // Format as 3-digit number with leading zeros
    return $prefix . str_pad($new_number, 3, '0', STR_PAD_LEFT);
}

// Handle form submission BEFORE any output
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and clean form data
    $form_data = [
        'quotation_no' => strtoupper(cleanInput($_POST['quotation_no'])),
        'customer_id' => (int)$_POST['customer_id'],
        'quotation_date' => cleanInput($_POST['quotation_date']),
        'valid_until' => cleanInput($_POST['valid_until']),
        'job_type' => cleanInput($_POST['job_type']),
        'service_type' => cleanInput($_POST['service_type']),
        'origin' => cleanInput($_POST['origin']),
        'destination' => cleanInput($_POST['destination']),
        'cargo_description' => cleanInput($_POST['cargo_description']),
        'currency' => cleanInput($_POST['currency']),
        'remark' => cleanInput($_POST['remark'])
    ];
    
    // Get quotation items
    $quotation_items = [];
    if (isset($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $item) {
            if (!empty($item['description']) && !empty($item['unit_price'])) {
                $quotation_items[] = [
                    'item_type' => cleanInput($item['item_type']),
                    'description' => cleanInput($item['description']),
                    'unit' => cleanInput($item['unit']),
                    'quantity' => (float)$item['quantity'],
                    'unit_price' => (float)str_replace(',', '', $item['unit_price']),
                    'amount' => (float)str_replace(',', '', $item['amount']),
                    'currency' => cleanInput($item['currency'])
                ];
            }
        }
    }
    
    // Validation
    if (empty($form_data['quotation_no'])) {
        $errors['quotation_no'] = 'Quotation number is required';
    } else {
        // Check if quotation number already exists
        $existing = fetchOne("SELECT id FROM quotations WHERE quotation_no = ?", [$form_data['quotation_no']]);
        if ($existing) {
            $errors['quotation_no'] = 'Quotation number already exists';
        }
    }
    
    if ($form_data['customer_id'] <= 0) {
        $errors['customer_id'] = 'Please select a customer';
    } else {
        // Check if customer exists and is active
        $customer = fetchOne("SELECT id, status FROM customers WHERE id = ?", [$form_data['customer_id']]);
        if (!$customer) {
            $errors['customer_id'] = 'Selected customer not found';
        } elseif ($customer['status'] == 'blacklist') {
            $errors['customer_id'] = 'Cannot create quotations for blacklisted customers';
        }
    }
    
    if (empty($form_data['quotation_date'])) {
        $errors['quotation_date'] = 'Quotation date is required';
    } elseif (!strtotime($form_data['quotation_date'])) {
        $errors['quotation_date'] = 'Invalid quotation date format';
    }
    
    if (empty($form_data['valid_until'])) {
        $errors['valid_until'] = 'Valid until date is required';
    } elseif (!strtotime($form_data['valid_until'])) {
        $errors['valid_until'] = 'Invalid valid until date format';
    } elseif (strtotime($form_data['valid_until']) < strtotime($form_data['quotation_date'])) {
        $errors['valid_until'] = 'Valid until date must be after quotation date';
    }
    
    if (!in_array($form_data['job_type'], ['export_air', 'export_sea', 'import_air', 'import_sea'])) {
        $errors['job_type'] = 'Invalid job type';
    }
    
    if (!in_array($form_data['service_type'], ['customer_only', 'freight_only', 'mix'])) {
        $errors['service_type'] = 'Invalid service type';
    }
    
    if (!in_array($form_data['currency'], ['THB', 'USD', 'EUR', 'GBP', 'JPY', 'CNY'])) {
        $errors['currency'] = 'Invalid currency';
    }
    
    if (empty($quotation_items)) {
        $errors['items'] = 'Please add at least one quotation item';
    } else {
        // Validate quotation items
        foreach ($quotation_items as $index => $item) {
            if (empty($item['description'])) {
                $errors["item_{$index}_description"] = "Item description is required";
            }
            if ($item['quantity'] <= 0) {
                $errors["item_{$index}_quantity"] = "Quantity must be greater than 0";
            }
            if ($item['unit_price'] <= 0) {
                $errors["item_{$index}_unit_price"] = "Unit price must be greater than 0";
            }
        }
    }
    
    // Calculate total amount
    $total_amount = array_sum(array_column($quotation_items, 'amount'));
    
    if ($total_amount <= 0) {
        $errors['total_amount'] = 'Total amount must be greater than 0';
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        // Begin transaction
        beginTransaction();
        
        try {
            // Insert quotation
            $sql = "INSERT INTO quotations (
                        quotation_no, customer_id, quotation_date, valid_until, 
                        job_type, service_type, origin, destination, cargo_description,
                        total_amount, currency, status, remark, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?)";
            
            $params = [
                $form_data['quotation_no'],
                $form_data['customer_id'],
                $form_data['quotation_date'],
                $form_data['valid_until'],
                $form_data['job_type'],
                $form_data['service_type'],
                $form_data['origin'],
                $form_data['destination'],
                $form_data['cargo_description'],
                $total_amount,
                $form_data['currency'],
                $form_data['remark'],
                $_SESSION['user_id']
            ];
            
            if (!execute($sql, $params)) {
                throw new Exception('Failed to create quotation');
            }
            
            $quotation_id = lastInsertId();
            
            // Insert quotation items
            foreach ($quotation_items as $item) {
                $item_sql = "INSERT INTO quotation_items (
                                quotation_id, item_type, description, unit, quantity, 
                                unit_price, amount, currency
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                
                $item_params = [
                    $quotation_id,
                    $item['item_type'],
                    $item['description'],
                    $item['unit'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['amount'],
                    $item['currency']
                ];
                
                if (!execute($item_sql, $item_params)) {
                    throw new Exception('Failed to create quotation items');
                }
            }
            
            // Commit transaction
            commit();
            
            $_SESSION['success_message'] = "Quotation '{$form_data['quotation_no']}' has been created successfully.";
            redirect('quotations_view.php?id=' . $quotation_id);
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            rollback();
            $errors['general'] = 'Error creating quotation: ' . $e->getMessage();
        }
    }
}

// Auto-generate quotation number if not submitted yet
if (empty($form_data['quotation_no']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    $form_data['quotation_no'] = generateNextQuotationNumber();
}

// Get customers for dropdown
$customers = fetchAll("SELECT id, customer_code, company_name FROM customers WHERE status = 'active' ORDER BY company_name");

// Get system settings
$default_currency = getSetting('default_currency', 'THB');
$form_data['currency'] = $default_currency;

// NOW set page variables and include header
$custom_page_title = "Create New Quotation";
$page_header = true;
$page_subtitle = "Create a new price quotation for customer";
$breadcrumb = [
    ['name' => 'Quotations', 'url' => 'quotations.php'],
    ['name' => 'Create New Quotation']
];

include 'includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <!-- Main Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-file-invoice-dollar me-2"></i>Quotation Information
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $errors['general']; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="quotationForm" data-autosave="add_quotation">
                    <!-- Basic Information -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="quotation_no" class="form-label">
                                Quotation Number <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control <?php echo isset($errors['quotation_no']) ? 'is-invalid' : ''; ?>" 
                                       id="quotation_no" 
                                       name="quotation_no" 
                                       value="<?php echo htmlspecialchars($form_data['quotation_no']); ?>"
                                       placeholder="e.g., QT2501001"
                                       maxlength="20"
                                       required>
                                <button type="button" class="btn btn-outline-secondary" onclick="generateNewQuotationNo()" title="Generate Next Number">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                            <?php if (isset($errors['quotation_no'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['quotation_no']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>Format: QT + Year + Month + 3-digit sequence
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="customer_id" class="form-label">
                                Customer <span class="text-danger">*</span>
                            </label>
                            <select class="form-select <?php echo isset($errors['customer_id']) ? 'is-invalid' : ''; ?>" 
                                    id="customer_id" 
                                    name="customer_id" 
                                    required
                                    onchange="loadCustomerInfo()">
                                <option value="">Select Customer</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>" 
                                            <?php echo ($form_data['customer_id'] == $customer['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['customer_code'] . ' - ' . $customer['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['customer_id'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['customer_id']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="quotation_date" class="form-label">
                                Quotation Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" 
                                   class="form-control <?php echo isset($errors['quotation_date']) ? 'is-invalid' : ''; ?>" 
                                   id="quotation_date" 
                                   name="quotation_date" 
                                   value="<?php echo htmlspecialchars($form_data['quotation_date']); ?>"
                                   required
                                   onchange="updateValidUntil()">
                            <?php if (isset($errors['quotation_date'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['quotation_date']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="valid_until" class="form-label">
                                Valid Until <span class="text-danger">*</span>
                            </label>
                            <input type="date" 
                                   class="form-control <?php echo isset($errors['valid_until']) ? 'is-invalid' : ''; ?>" 
                                   id="valid_until" 
                                   name="valid_until" 
                                   value="<?php echo htmlspecialchars($form_data['valid_until']); ?>"
                                   required>
                            <?php if (isset($errors['valid_until'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['valid_until']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="job_type" class="form-label">
                                Job Type <span class="text-danger">*</span>
                            </label>
                            <select class="form-select <?php echo isset($errors['job_type']) ? 'is-invalid' : ''; ?>" 
                                    id="job_type" 
                                    name="job_type" 
                                    required>
                                <option value="">Select Job Type</option>
                                <option value="export_air" <?php echo ($form_data['job_type'] == 'export_air') ? 'selected' : ''; ?>>Export Air</option>
                                <option value="export_sea" <?php echo ($form_data['job_type'] == 'export_sea') ? 'selected' : ''; ?>>Export Sea</option>
                                <option value="import_air" <?php echo ($form_data['job_type'] == 'import_air') ? 'selected' : ''; ?>>Import Air</option>
                                <option value="import_sea" <?php echo ($form_data['job_type'] == 'import_sea') ? 'selected' : ''; ?>>Import Sea</option>
                            </select>
                            <?php if (isset($errors['job_type'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['job_type']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="service_type" class="form-label">
                                Service Type <span class="text-danger">*</span>
                            </label>
                            <select class="form-select <?php echo isset($errors['service_type']) ? 'is-invalid' : ''; ?>" 
                                    id="service_type" 
                                    name="service_type" 
                                    required>
                                <option value="">Select Service</option>
                                <option value="customer_only" <?php echo ($form_data['service_type'] == 'customer_only') ? 'selected' : ''; ?>>Customer Only</option>
                                <option value="freight_only" <?php echo ($form_data['service_type'] == 'freight_only') ? 'selected' : ''; ?>>Freight Only</option>
                                <option value="mix" <?php echo ($form_data['service_type'] == 'mix') ? 'selected' : ''; ?>>Mix</option>
                            </select>
                            <?php if (isset($errors['service_type'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['service_type']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="origin" class="form-label">Origin</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="origin" 
                                   name="origin" 
                                   value="<?php echo htmlspecialchars($form_data['origin']); ?>"
                                   placeholder="e.g., Bangkok, Thailand"
                                   maxlength="100">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="destination" class="form-label">Destination</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="destination" 
                                   name="destination" 
                                   value="<?php echo htmlspecialchars($form_data['destination']); ?>"
                                   placeholder="e.g., New York, USA"
                                   maxlength="100">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="currency" class="form-label">
                                Currency <span class="text-danger">*</span>
                            </label>
                            <select class="form-select <?php echo isset($errors['currency']) ? 'is-invalid' : ''; ?>" 
                                    id="currency" 
                                    name="currency" 
                                    required
                                    onchange="updateItemsCurrency()">
                                <option value="THB" <?php echo ($form_data['currency'] == 'THB') ? 'selected' : ''; ?>>THB - Thai Baht</option>
                                <option value="USD" <?php echo ($form_data['currency'] == 'USD') ? 'selected' : ''; ?>>USD - US Dollar</option>
                                <option value="EUR" <?php echo ($form_data['currency'] == 'EUR') ? 'selected' : ''; ?>>EUR - Euro</option>
                                <option value="GBP" <?php echo ($form_data['currency'] == 'GBP') ? 'selected' : ''; ?>>GBP - British Pound</option>
                                <option value="JPY" <?php echo ($form_data['currency'] == 'JPY') ? 'selected' : ''; ?>>JPY - Japanese Yen</option>
                                <option value="CNY" <?php echo ($form_data['currency'] == 'CNY') ? 'selected' : ''; ?>>CNY - Chinese Yuan</option>
                            </select>
                            <?php if (isset($errors['currency'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['currency']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cargo_description" class="form-label">Cargo Description</label>
                        <textarea class="form-control" 
                                  id="cargo_description" 
                                  name="cargo_description" 
                                  rows="3"
                                  placeholder="Describe the goods to be shipped"><?php echo htmlspecialchars($form_data['cargo_description']); ?></textarea>
                    </div>
                    
                    <!-- Quotation Items -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">
                                <i class="fas fa-list me-2"></i>Quotation Items
                                <span class="text-danger">*</span>
                            </h6>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addQuotationItem()">
                                <i class="fas fa-plus me-1"></i>Add Item
                            </button>
                        </div>
                        
                        <?php if (isset($errors['items'])): ?>
                            <div class="alert alert-danger"><?php echo $errors['items']; ?></div>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered" id="quotationItemsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th width="15%">Type</th>
                                        <th width="25%">Description</th>
                                        <th width="10%">Unit</th>
                                        <th width="10%">Qty</th>
                                        <th width="15%">Unit Price</th>
                                        <th width="15%">Amount</th>
                                        <th width="8%">Currency</th>
                                        <th width="2%">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="quotationItemsBody">
                                    <!-- Items will be added dynamically -->
                                </tbody>
                                <tfoot>
                                    <tr class="table-info">
                                        <td colspan="5" class="text-end"><strong>Total Amount:</strong></td>
                                        <td><strong><span id="totalAmount">0.00</span></strong></td>
                                        <td><strong><span id="totalCurrency">THB</span></strong></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="remark" class="form-label">Remarks</label>
                        <textarea class="form-control" 
                                  id="remark" 
                                  name="remark" 
                                  rows="3"
                                  placeholder="Additional notes for this quotation"><?php echo htmlspecialchars($form_data['remark']); ?></textarea>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Quotation
                        </button>
                        <a href="quotations.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="button" class="btn btn-outline-warning" onclick="resetForm()">
                            <i class="fas fa-undo me-2"></i>Reset Form
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="previewQuotation()">
                            <i class="fas fa-eye me-2"></i>Preview
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Sidebar Info -->
    <div class="col-lg-4">
        <!-- Customer Info (populated by JavaScript) -->
        <div class="card mb-4" id="customerInfoCard" style="display: none;">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-user me-2"></i>Customer Information
                </h6>
            </div>
            <div class="card-body" id="customerInfoBody">
                <!-- Will be populated by JavaScript -->
            </div>
        </div>
        
        <!-- Help Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>Quotation Guide
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Job Types:</strong>
                    <ul class="small text-muted mb-2">
                        <li><strong>Export Air/Sea:</strong> Goods leaving Thailand</li>
                        <li><strong>Import Air/Sea:</strong> Goods coming to Thailand</li>
                    </ul>
                </div>
                
                <div class="mb-3">
                    <strong>Service Types:</strong>
                    <ul class="small text-muted mb-2">
                        <li><strong>Customer Only:</strong> Services for customers</li>
                        <li><strong>Freight Only:</strong> Pure freight services</li>
                        <li><strong>Mix:</strong> Combined services</li>
                    </ul>
                </div>
                
                <div class="mb-3">
                    <strong>Item Types:</strong>
                    <ul class="small text-muted mb-2">
                        <li>Freight - Main shipping cost</li>
                        <li>Local Charge - Port/Airport fees</li>
                        <li>Customs - Clearance fees</li>
                        <li>Trucking - Land transport</li>
                        <li>Documentation - Document fees</li>
                        <li>Service Fee - Additional services</li>
                    </ul>
                </div>
                
                <div class="mb-0">
                    <strong>Tips:</strong>
                    <ul class="small text-muted mb-0">
                        <li>Set valid until date strategically</li>
                        <li>Include all relevant charges</li>
                        <li>Use clear descriptions</li>
                        <li>Double-check calculations</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Recent Quotations -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>Recent Quotations
                </h6>
            </div>
            <div class="card-body">
                <?php
                $recent_quotations = fetchAll("
                    SELECT q.quotation_no, q.total_amount, q.currency, q.created_at,
                           c.company_name
                    FROM quotations q
                    LEFT JOIN customers c ON q.customer_id = c.id
                    ORDER BY q.created_at DESC 
                    LIMIT 5
                ");
                ?>
                
                <?php if (empty($recent_quotations)): ?>
                    <p class="text-muted small mb-0">No quotations yet. This will be your first quotation!</p>
                <?php else: ?>
                    <?php foreach ($recent_quotations as $recent): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <div class="fw-bold small"><?php echo htmlspecialchars($recent['quotation_no']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($recent['company_name']); ?></div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold small"><?php echo formatMoney($recent['total_amount'], $recent['currency']); ?></div>
                            <small class="text-muted">
                                <?php echo formatDateThai($recent['created_at'], 'd/m'); ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewModalLabel">
                    <i class="fas fa-eye me-2"></i>Quotation Preview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="previewContent">
                <!-- Preview content will be generated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printPreview()">
                    <i class="fas fa-print me-2"></i>Print Preview
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables
let itemCounter = 0;

// Generate new quotation number
function generateNewQuotationNo() {
    fetch('ajax/generate_quotation_number.php', {
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
        if (data.success && data.quotation_no) {
            document.getElementById('quotation_no').value = data.quotation_no;
        } else {
            // Fallback: generate client-side
            generateQuotationNoFallback();
        }
    })
    .catch(error => {
        console.log('Error generating quotation number:', error);
        generateQuotationNoFallback();
    });
}

// Fallback quotation number generation
function generateQuotationNoFallback() {
    const now = new Date();
    const year = now.getFullYear().toString().substr(-2);
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const seqNum = Math.floor(Date.now() / 1000) % 1000;
    const newQuotationNo = 'QT' + year + month + String(seqNum).padStart(3, '0');
    document.getElementById('quotation_no').value = newQuotationNo;
}

// Load customer information
function loadCustomerInfo() {
    const customerId = document.getElementById('customer_id').value;
    
    if (customerId) {
        fetch('ajax/get_customer_data.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                customer_id: customerId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.customer) {
                displayCustomerInfo(data.customer);
            }
        })
        .catch(error => {
            console.log('Error loading customer info:', error);
        });
    } else {
        document.getElementById('customerInfoCard').style.display = 'none';
    }
}

// Display customer information in sidebar
function displayCustomerInfo(customer) {
    const customerInfoCard = document.getElementById('customerInfoCard');
    const customerInfoBody = document.getElementById('customerInfoBody');
    
    const customerHtml = `
        <div class="mb-2">
            <strong>${customer.customer_code}</strong><br>
            <span class="text-muted">${customer.company_name}</span>
        </div>
        ${customer.contact_person ? `<div class="mb-2"><strong>Contact:</strong> ${customer.contact_person}</div>` : ''}
        ${customer.phone ? `<div class="mb-2"><strong>Phone:</strong> <a href="tel:${customer.phone}">${customer.phone}</a></div>` : ''}
        ${customer.email ? `<div class="mb-2"><strong>Email:</strong> <a href="mailto:${customer.email}">${customer.email}</a></div>` : ''}
        <div class="mb-2"><strong>Credit Term:</strong> ${customer.credit_term} days</div>
        <div class="mb-2"><strong>Credit Limit:</strong> ${formatMoney(customer.credit_limit)} THB</div>
        <div class="mb-0">
            <span class="badge ${customer.status === 'active' ? 'bg-success' : (customer.status === 'inactive' ? 'bg-warning' : 'bg-danger')}">
                ${customer.status.charAt(0).toUpperCase() + customer.status.slice(1)}
            </span>
        </div>
    `;
    
    customerInfoBody.innerHTML = customerHtml;
    customerInfoCard.style.display = 'block';
}

// Update valid until date when quotation date changes
function updateValidUntil() {
    const quotationDate = document.getElementById('quotation_date').value;
    if (quotationDate) {
        const date = new Date(quotationDate);
        date.setDate(date.getDate() + 30); // Default 30 days validity
        document.getElementById('valid_until').value = date.toISOString().split('T')[0];
    }
}

// Update currency for all items
function updateItemsCurrency() {
    const currency = document.getElementById('currency').value;
    document.getElementById('totalCurrency').textContent = currency;
    
    // Update all item currency dropdowns
    const currencySelects = document.querySelectorAll('select[name*="[currency]"]');
    currencySelects.forEach(select => {
        select.value = currency;
    });
}

// Add quotation item
function addQuotationItem() {
    const tbody = document.getElementById('quotationItemsBody');
    const currency = document.getElementById('currency').value;
    const row = document.createElement('tr');
    row.id = `item_${itemCounter}`;
    
    row.innerHTML = `
        <td>
            <select class="form-select form-select-sm" name="items[${itemCounter}][item_type]" required>
                <option value="">Select Type</option>
                <option value="freight">Freight</option>
                <option value="local_charge">Local Charge</option>
                <option value="customs">Customs</option>
                <option value="trucking">Trucking</option>
                <option value="documentation">Documentation</option>
                <option value="service_fee">Service Fee</option>
                <option value="other">Other</option>
            </select>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm" 
                   name="items[${itemCounter}][description]" 
                   placeholder="Item description" required>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm" 
                   name="items[${itemCounter}][unit]" 
                   placeholder="per shipment" 
                   value="per shipment">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm" 
                   name="items[${itemCounter}][quantity]" 
                   value="1" min="0" step="0.001" 
                   onchange="calculateItemAmount(${itemCounter})" required>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm" 
                   name="items[${itemCounter}][unit_price]" 
                   placeholder="0.00" 
                   onchange="calculateItemAmount(${itemCounter})" 
                   data-format="number" required>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm" 
                   name="items[${itemCounter}][amount]" 
                   placeholder="0.00" readonly>
        </td>
        <td>
            <select class="form-select form-select-sm" name="items[${itemCounter}][currency]">
                <option value="THB" ${currency === 'THB' ? 'selected' : ''}>THB</option>
                <option value="USD" ${currency === 'USD' ? 'selected' : ''}>USD</option>
                <option value="EUR" ${currency === 'EUR' ? 'selected' : ''}>EUR</option>
                <option value="GBP" ${currency === 'GBP' ? 'selected' : ''}>GBP</option>
                <option value="JPY" ${currency === 'JPY' ? 'selected' : ''}>JPY</option>
                <option value="CNY" ${currency === 'CNY' ? 'selected' : ''}>CNY</option>
            </select>
        </td>
        <td>
            <button type="button" class="btn btn-outline-danger btn-sm" 
                    onclick="removeQuotationItem(${itemCounter})" title="Remove">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(row);
    
    // Add number formatting to unit price field
    const unitPriceField = row.querySelector('input[data-format="number"]');
    unitPriceField.addEventListener('input', function() {
        formatNumberInput(this);
    });
    
    itemCounter++;
    
    // Add first item automatically if this is the first one
    if (tbody.children.length === 1) {
        // Focus on item type
        row.querySelector('select').focus();
    }
}

// Remove quotation item
function removeQuotationItem(index) {
    const row = document.getElementById(`item_${index}`);
    if (row) {
        row.remove();
        calculateTotalAmount();
    }
}

// Calculate item amount
function calculateItemAmount(index) {
    const row = document.getElementById(`item_${index}`);
    const quantity = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
    const unitPriceStr = row.querySelector('input[name*="[unit_price]"]').value.replace(/,/g, '');
    const unitPrice = parseFloat(unitPriceStr) || 0;
    const amount = quantity * unitPrice;
    
    row.querySelector('input[name*="[amount]"]').value = formatNumber(amount, 2);
    
    calculateTotalAmount();
}

// Calculate total amount
function calculateTotalAmount() {
    let total = 0;
    const amountFields = document.querySelectorAll('input[name*="[amount]"]');
    
    amountFields.forEach(field => {
        const value = parseFloat(field.value.replace(/,/g, '')) || 0;
        total += value;
    });
    
    document.getElementById('totalAmount').textContent = formatNumber(total, 2);
}

// Format number input with thousand separators
function formatNumberInput(input) {
    let value = input.value.replace(/[^\d.]/g, '');
    if (value) {
        const parts = value.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        input.value = parts.join('.');
    }
}

// Format number helper function
function formatNumber(number, decimals = 2) {
    return number.toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

// Format money helper function
function formatMoney(amount, currency = 'THB') {
    return formatNumber(amount, 2) + ' ' + currency;
}

// Reset form
function resetForm() {
    if (confirm('Are you sure you want to reset all fields? All entered data will be lost.')) {
        document.getElementById('quotationForm').reset();
        document.getElementById('quotationItemsBody').innerHTML = '';
        document.getElementById('customerInfoCard').style.display = 'none';
        document.getElementById('totalAmount').textContent = '0.00';
        itemCounter = 0;
        
        // Clear localStorage auto-save data
        localStorage.removeItem('form_add_quotation');
        
        // Generate new quotation number
        generateNewQuotationNo();
        
        // Reset date fields
        document.getElementById('quotation_date').value = new Date().toISOString().split('T')[0];
        updateValidUntil();
    }
}

// Preview quotation
function previewQuotation() {
    const formData = new FormData(document.getElementById('quotationForm'));
    const customer = document.getElementById('customer_id').selectedOptions[0]?.text || '';
    const quotationNo = document.getElementById('quotation_no').value;
    const quotationDate = document.getElementById('quotation_date').value;
    const validUntil = document.getElementById('valid_until').value;
    const jobType = document.getElementById('job_type').selectedOptions[0]?.text || '';
    const serviceType = document.getElementById('service_type').selectedOptions[0]?.text || '';
    const origin = document.getElementById('origin').value;
    const destination = document.getElementById('destination').value;
    const currency = document.getElementById('currency').value;
    const cargoDescription = document.getElementById('cargo_description').value;
    const remark = document.getElementById('remark').value;
    
    // Generate items table
    let itemsHtml = '';
    const rows = document.querySelectorAll('#quotationItemsBody tr');
    let totalAmount = 0;
    
    rows.forEach((row, index) => {
        const itemType = row.querySelector('select[name*="[item_type]"]').selectedOptions[0]?.text || '';
        const description = row.querySelector('input[name*="[description]"]').value;
        const unit = row.querySelector('input[name*="[unit]"]').value;
        const quantity = row.querySelector('input[name*="[quantity]"]').value;
        const unitPrice = row.querySelector('input[name*="[unit_price]"]').value;
        const amount = row.querySelector('input[name*="[amount]"]').value;
        const itemCurrency = row.querySelector('select[name*="[currency]"]').value;
        
        if (description && unitPrice) {
            itemsHtml += `
                <tr>
                    <td>${itemType}</td>
                    <td>${description}</td>
                    <td class="text-center">${unit}</td>
                    <td class="text-center">${quantity}</td>
                    <td class="text-end">${unitPrice} ${itemCurrency}</td>
                    <td class="text-end">${amount} ${itemCurrency}</td>
                </tr>
            `;
            totalAmount += parseFloat(amount.replace(/,/g, '')) || 0;
        }
    });
    
    const previewHtml = `
        <div class="quotation-preview">
            <div class="text-center mb-4">
                <h4>FREIGHT QUOTATION</h4>
                <p class="text-muted">Quotation No: ${quotationNo}</p>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6>Customer Information:</h6>
                    <p>${customer}<br>
                    Date: ${new Date(quotationDate).toLocaleDateString()}<br>
                    Valid Until: ${new Date(validUntil).toLocaleDateString()}</p>
                </div>
                <div class="col-md-6">
                    <h6>Service Details:</h6>
                    <p>Job Type: ${jobType}<br>
                    Service Type: ${serviceType}<br>
                    Route: ${origin || 'TBD'} → ${destination || 'TBD'}</p>
                </div>
            </div>
            
            ${cargoDescription ? `
            <div class="mb-4">
                <h6>Cargo Description:</h6>
                <p>${cargoDescription}</p>
            </div>
            ` : ''}
            
            <div class="mb-4">
                <h6>Quotation Items:</h6>
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Type</th>
                            <th>Description</th>
                            <th class="text-center">Unit</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHtml}
                    </tbody>
                    <tfoot>
                        <tr class="table-info">
                            <td colspan="5" class="text-end"><strong>Total Amount:</strong></td>
                            <td class="text-end"><strong>${formatNumber(totalAmount, 2)} ${currency}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            ${remark ? `
            <div class="mb-4">
                <h6>Remarks:</h6>
                <p>${remark}</p>
            </div>
            ` : ''}
            
            <div class="text-center text-muted">
                <small>This is a computer-generated quotation and does not require signature.<br>
                Valid until ${new Date(validUntil).toLocaleDateString()}</small>
            </div>
        </div>
    `;
    
    document.getElementById('previewContent').innerHTML = previewHtml;
    const modal = new bootstrap.Modal(document.getElementById('previewModal'));
    modal.show();
}

// Print preview
function printPreview() {
    const printWindow = window.open('', '_blank');
    const previewContent = document.getElementById('previewContent').innerHTML;
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Quotation Preview</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .text-center { text-align: center; }
                .text-end { text-align: right; }
                .text-muted { color: #666; }
                .table-info { background-color: #e3f2fd; }
                @media print {
                    body { margin: 0; padding: 15px; }
                }
            </style>
        </head>
        <body>
            ${previewContent}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}

// Form validation before submit
document.getElementById('quotationForm').addEventListener('submit', function(e) {
    const quotationNo = document.getElementById('quotation_no').value.trim();
    const customerId = document.getElementById('customer_id').value;
    const quotationDate = document.getElementById('quotation_date').value;
    const validUntil = document.getElementById('valid_until').value;
    const jobType = document.getElementById('job_type').value;
    const serviceType = document.getElementById('service_type').value;
    const currency = document.getElementById('currency').value;
    
    // Basic validation
    if (!quotationNo) {
        e.preventDefault();
        alert('Please enter quotation number');
        document.getElementById('quotation_no').focus();
        return false;
    }
    
    if (!customerId) {
        e.preventDefault();
        alert('Please select a customer');
        document.getElementById('customer_id').focus();
        return false;
    }
    
    if (!quotationDate || !validUntil) {
        e.preventDefault();
        alert('Please enter quotation date and valid until date');
        return false;
    }
    
    if (new Date(validUntil) <= new Date(quotationDate)) {
        e.preventDefault();
        alert('Valid until date must be after quotation date');
        document.getElementById('valid_until').focus();
        return false;
    }
    
    if (!jobType || !serviceType) {
        e.preventDefault();
        alert('Please select job type and service type');
        return false;
    }
    
    // Check if at least one item exists
    const items = document.querySelectorAll('#quotationItemsBody tr');
    if (items.length === 0) {
        e.preventDefault();
        alert('Please add at least one quotation item');
        addQuotationItem();
        return false;
    }
    
    // Validate items
    let hasValidItem = false;
    items.forEach((row, index) => {
        const description = row.querySelector('input[name*="[description]"]').value.trim();
        const unitPrice = row.querySelector('input[name*="[unit_price]"]').value.trim();
        
        if (description && unitPrice && parseFloat(unitPrice.replace(/,/g, '')) > 0) {
            hasValidItem = true;
        }
    });
    
    if (!hasValidItem) {
        e.preventDefault();
        alert('Please add at least one valid quotation item with description and unit price');
        return false;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
    submitBtn.disabled = true;
});

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Add first item automatically
    addQuotationItem();
    
    // Load customer info if pre-selected
    const customerId = document.getElementById('customer_id').value;
    if (customerId) {
        loadCustomerInfo();
    }
    
    // Add number formatting to existing price fields
    document.querySelectorAll('input[data-format="number"]').forEach(function(input) {
        input.addEventListener('input', function() {
            formatNumberInput(this);
        });
    });
    
    // Auto-focus on customer field
    document.getElementById('customer_id').focus();
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + S = Save
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        document.getElementById('quotationForm').submit();
    }
    
    // Ctrl + R = Reset
    if (e.ctrlKey && e.key === 'r') {
        e.preventDefault();
        resetForm();
    }
    
    // Ctrl + P = Preview
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        previewQuotation();
    }
    
    // Ctrl + I = Add Item
    if (e.ctrlKey && e.key === 'i') {
        e.preventDefault();
        addQuotationItem();
    }
});
</script>

<style>
/* Additional CSS for quotation form */
.table-responsive {
    max-height: 400px;
    overflow-y: auto;
}

.quotation-preview {
    font-size: 0.9rem;
}

.quotation-preview h4 {
    color: #333;
    border-bottom: 2px solid #007bff;
    padding-bottom: 10px;
}

.quotation-preview h6 {
    color: #495057;
    margin-bottom: 10px;
    font-weight: 600;
}

/* Responsive table for mobile */
@media (max-width: 768px) {
    #quotationItemsTable {
        font-size: 0.8rem;
    }
    
    #quotationItemsTable input,
    #quotationItemsTable select {
        font-size: 0.8rem;
        padding: 0.25rem;
    }
    
    #quotationItemsTable th,
    #quotationItemsTable td {
        padding: 0.5rem 0.25rem;
    }
}

/* Print styles for preview */
@media print {
    .modal-header,
    .modal-footer,
    .btn {
        display: none !important;
    }
    
    .quotation-preview {
        margin: 0;
        padding: 0;
    }
}

/* Loading state for form */
.form-loading {
    pointer-events: none;
    opacity: 0.6;
}

/* Highlight required fields */
.form-control:required:invalid,
.form-select:required:invalid {
    border-color: #dc3545;
}

.form-control:required:valid,
.form-select:required:valid {
    border-color: #28a745;
}

/* Item row hover effect */
#quotationItemsTable tbody tr:hover {
    background-color: #f8f9fa;
}

/* Currency symbol display */
.currency-symbol {
    font-weight: bold;
    color: #495057;
}

/* Total amount highlight */
#totalAmount {
    font-size: 1.1rem;
    color: #007bff;
}

/* Sticky table header */
#quotationItemsTable thead th {
    position: sticky;
    top: 0;
    background-color: #f8f9fa;
    z-index: 10;
}
</style>

<?php include 'includes/footer.php'; ?>