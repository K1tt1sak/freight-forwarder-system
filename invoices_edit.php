<?php
// =====================================================
// invoices_edit.php - Edit Invoice
// =====================================================

// Include header และ functions
$custom_page_title = 'Edit Invoice';
$page_header = true;

// Set breadcrumb
$breadcrumb = [
    ['name' => 'Invoices', 'url' => 'invoices.php'],
    ['name' => 'Edit Invoice']
];

$additional_css = "
<style>
.invoice-header {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
}

.invoice-section {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.item-row {
    border-bottom: 1px solid #e9ecef;
    padding: 0.75rem 0;
}

.item-row:last-child {
    border-bottom: none;
}

.calculation-section {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 1rem;
    border: 2px dashed #dee2e6;
}

.total-amount {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary-color);
}

.payment-status-badge {
    font-size: 0.9rem;
    padding: 0.5rem 1rem;
}

.required-field {
    border-left: 4px solid #dc3545;
    padding-left: 0.5rem;
}

.form-floating > label {
    color: #6c757d;
}

.btn-save {
    background: linear-gradient(135deg, #28a745, #20c997);
    border: none;
    color: white;
    padding: 0.75rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
}

.alert-changes {
    border-left: 4px solid #ffc107;
    background: #fff3cd;
    border-color: #ffeaa7;
}
</style>
";

include 'includes/header.php';

// Check permissions
requirePermission('staff');

// Get invoice ID
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($invoice_id <= 0) {
    $_SESSION['error_message'] = 'Invalid invoice ID.';
    redirect('invoices.php');
}

// Get invoice data
$invoice = fetchOne("
    SELECT i.*, 
           c.company_name, c.customer_code, c.address as customer_address,
           c.tax_id as customer_tax_id, c.credit_term,
           j.job_no, j.origin, j.destination
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    LEFT JOIN jobs j ON i.job_id = j.id
    WHERE i.id = ?
", [$invoice_id]);

if (!$invoice) {
    $_SESSION['error_message'] = 'Invoice not found.';
    redirect('invoices.php');
}

// Check if invoice can be edited
$editable_statuses = ['pending', 'partial'];
if (!in_array($invoice['payment_status'], $editable_statuses) && !hasPermission('manager')) {
    $_SESSION['error_message'] = 'This invoice cannot be edited. Manager permission required for paid invoices.';
    redirect('invoices_view.php?id=' . $invoice_id);
}

// Get invoice items
$invoice_items = fetchAll("
    SELECT ii.*, js.selling_type
    FROM invoice_items ii
    LEFT JOIN job_selling js ON ii.job_selling_id = js.id
    WHERE ii.invoice_id = ?
    ORDER BY ii.id
", [$invoice_id]);

// Get available customers
$customers = fetchAll("
    SELECT id, customer_code, company_name 
    FROM customers 
    WHERE status = 'active' 
    ORDER BY company_name
");

// Get available jobs (if changing job reference)
$jobs = fetchAll("
    SELECT id, job_no, origin, destination, shipper_id, consignee_id
    FROM jobs 
    WHERE status IN ('delivered', 'completed')
    AND id NOT IN (
        SELECT DISTINCT job_id FROM invoices 
        WHERE job_id IS NOT NULL AND id != ?
    )
    ORDER BY created_at DESC
    LIMIT 50
", [$invoice_id]);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate and sanitize input
        $customer_id = (int)$_POST['customer_id'];
        $job_id = !empty($_POST['job_id']) ? (int)$_POST['job_id'] : null;
        $invoice_date = cleanInput($_POST['invoice_date']);
        $due_date = cleanInput($_POST['due_date']);
        $subtotal = (float)str_replace(',', '', $_POST['subtotal']);
        $vat_rate = (float)$_POST['vat_rate'];
        $vat_amount = (float)str_replace(',', '', $_POST['vat_amount']);
        $total_amount = (float)str_replace(',', '', $_POST['total_amount']);
        $currency = cleanInput($_POST['currency']);
        $payment_status = cleanInput($_POST['payment_status']);
        $paid_amount = (float)str_replace(',', '', $_POST['paid_amount']);
        $payment_date = !empty($_POST['payment_date']) ? cleanInput($_POST['payment_date']) : null;
        $remark = cleanInput($_POST['remark']);
        
        // Get items data
        $items = [];
        if (isset($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (!empty($item['description']) && $item['quantity'] > 0 && $item['unit_price'] > 0) {
                    $items[] = [
                        'id' => isset($item['id']) ? (int)$item['id'] : 0,
                        'job_selling_id' => !empty($item['job_selling_id']) ? (int)$item['job_selling_id'] : null,
                        'description' => cleanInput($item['description']),
                        'quantity' => (float)$item['quantity'],
                        'unit_price' => (float)str_replace(',', '', $item['unit_price']),
                        'amount' => (float)str_replace(',', '', $item['amount'])
                    ];
                }
            }
        }
        
        // Validation
        $errors = [];
        
        if ($customer_id <= 0) {
            $errors[] = 'Please select a customer.';
        }
        
        if (!$invoice_date || !strtotime($invoice_date)) {
            $errors[] = 'Please enter a valid invoice date.';
        }
        
        if (!$due_date || !strtotime($due_date)) {
            $errors[] = 'Please enter a valid due date.';
        }
        
        if (strtotime($due_date) < strtotime($invoice_date)) {
            $errors[] = 'Due date cannot be earlier than invoice date.';
        }
        
        if (empty($items)) {
            $errors[] = 'Please add at least one invoice item.';
        }
        
        if ($total_amount <= 0) {
            $errors[] = 'Total amount must be greater than zero.';
        }
        
        if (!in_array($payment_status, ['pending', 'partial', 'paid', 'overdue', 'cancelled'])) {
            $errors[] = 'Invalid payment status.';
        }
        
        if ($payment_status === 'paid' && $paid_amount != $total_amount) {
            $errors[] = 'Paid amount must equal total amount for paid invoices.';
        }
        
        if ($payment_status === 'partial' && ($paid_amount <= 0 || $paid_amount >= $total_amount)) {
            $errors[] = 'Paid amount must be between 0 and total amount for partial payments.';
        }
        
        if (($payment_status === 'paid' || $payment_status === 'partial') && !$payment_date) {
            $errors[] = 'Payment date is required for paid/partial invoices.';
        }
        
        // Business rule validations
        if ($payment_status === 'paid' && !hasPermission('manager')) {
            $errors[] = 'Manager permission required to mark invoice as paid.';
        }
        
        // Check if customer exists and is active
        $customer_check = fetchOne("SELECT status FROM customers WHERE id = ?", [$customer_id]);
        if (!$customer_check || $customer_check['status'] !== 'active') {
            $errors[] = 'Selected customer is not active.';
        }
        
        if (!empty($errors)) {
            throw new Exception(implode('<br>', $errors));
        }
        
        // Begin transaction
        beginTransaction();
        
        // Update invoice
        $update_result = execute("
            UPDATE invoices SET
                customer_id = ?,
                job_id = ?,
                invoice_date = ?,
                due_date = ?,
                subtotal = ?,
                vat_rate = ?,
                vat_amount = ?,
                total_amount = ?,
                currency = ?,
                payment_status = ?,
                paid_amount = ?,
                payment_date = ?,
                remark = ?,
                updated_at = NOW()
            WHERE id = ?
        ", [
            $customer_id, $job_id, $invoice_date, $due_date,
            $subtotal, $vat_rate, $vat_amount, $total_amount,
            $currency, $payment_status, $paid_amount, $payment_date,
            $remark, $invoice_id
        ]);
        
        if (!$update_result) {
            throw new Exception('Failed to update invoice.');
        }
        
        // Delete existing items
        execute("DELETE FROM invoice_items WHERE invoice_id = ?", [$invoice_id]);
        
        // Insert updated items
        foreach ($items as $item) {
            execute("
                INSERT INTO invoice_items (invoice_id, job_selling_id, description, quantity, unit_price, amount)
                VALUES (?, ?, ?, ?, ?, ?)
            ", [
                $invoice_id,
                $item['job_selling_id'],
                $item['description'],
                $item['quantity'],
                $item['unit_price'],
                $item['amount']
            ]);
        }
        
        // Update payment status to overdue if past due date
        if ($payment_status === 'pending' && strtotime($due_date) < time()) {
            execute("UPDATE invoices SET payment_status = 'overdue' WHERE id = ?", [$invoice_id]);
        }
        
        commit();
        
        $_SESSION['success_message'] = "Invoice {$invoice['invoice_no']} updated successfully.";
        redirect('invoices_view.php?id=' . $invoice_id);
        
    } catch (Exception $e) {
        rollback();
        $error_message = $e->getMessage();
    }
}

$page_subtitle = 'Edit Invoice ' . $invoice['invoice_no'];
?>

<div class="container-fluid">
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Invoice Header -->
    <div class="invoice-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h3 class="mb-1">
                    <i class="fas fa-edit me-2"></i>
                    Edit Invoice: <?php echo $invoice['invoice_no']; ?>
                </h3>
                <p class="mb-0 opacity-75">
                    Customer: <?php echo htmlspecialchars($invoice['company_name']); ?>
                    <?php if ($invoice['job_no']): ?>
                        | Job: <?php echo $invoice['job_no']; ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="payment-status-badge badge bg-<?php 
                    echo match($invoice['payment_status']) {
                        'paid' => 'success',
                        'partial' => 'info',
                        'pending' => 'warning',
                        'overdue' => 'danger',
                        'cancelled' => 'secondary',
                        default => 'secondary'
                    };
                ?>">
                    <?php echo ucfirst($invoice['payment_status']); ?>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" id="invoiceForm">
        <div class="row">
            <!-- Invoice Details -->
            <div class="col-lg-8">
                <!-- Basic Information -->
                <div class="invoice-section">
                    <h5 class="mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Invoice Information
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3 required-field">
                                <select class="form-select" id="customer_id" name="customer_id" required onchange="loadCustomerInfo()">
                                    <option value="">Select Customer</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo $customer['id']; ?>" 
                                                <?php echo $customer['id'] == $invoice['customer_id'] ? 'selected' : ''; ?>>
                                            <?php echo $customer['customer_code'] . ' - ' . $customer['company_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="customer_id">Customer *</label>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="job_id" name="job_id">
                                    <option value="">No Job Reference</option>
                                    <?php foreach ($jobs as $job): ?>
                                        <option value="<?php echo $job['id']; ?>" 
                                                <?php echo $job['id'] == $invoice['job_id'] ? 'selected' : ''; ?>>
                                            <?php echo $job['job_no']; ?>
                                            <?php if ($job['origin'] && $job['destination']): ?>
                                                - <?php echo $job['origin'] . ' → ' . $job['destination']; ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="job_id">Job Reference</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-floating mb-3 required-field">
                                <input type="date" class="form-control" id="invoice_date" name="invoice_date" 
                                       value="<?php echo $invoice['invoice_date']; ?>" required>
                                <label for="invoice_date">Invoice Date *</label>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-floating mb-3 required-field">
                                <input type="date" class="form-control" id="due_date" name="due_date" 
                                       value="<?php echo $invoice['due_date']; ?>" required>
                                <label for="due_date">Due Date *</label>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="currency" name="currency">
                                    <option value="THB" <?php echo $invoice['currency'] == 'THB' ? 'selected' : ''; ?>>THB - Thai Baht</option>
                                    <option value="USD" <?php echo $invoice['currency'] == 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                    <option value="EUR" <?php echo $invoice['currency'] == 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                                </select>
                                <label for="currency">Currency</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Invoice Items -->
                <div class="invoice-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Invoice Items
                        </h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItem()">
                            <i class="fas fa-plus me-1"></i>Add Item
                        </button>
                    </div>
                    
                    <div id="itemsContainer">
                        <?php foreach ($invoice_items as $index => $item): ?>
                        <div class="item-row" data-index="<?php echo $index; ?>">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <input type="hidden" name="items[<?php echo $index; ?>][id]" value="<?php echo $item['id']; ?>">
                                    <input type="hidden" name="items[<?php echo $index; ?>][job_selling_id]" value="<?php echo $item['job_selling_id']; ?>">
                                    <label class="form-label">Description</label>
                                    <input type="text" class="form-control" name="items[<?php echo $index; ?>][description]" 
                                           value="<?php echo htmlspecialchars($item['description']); ?>" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Quantity</label>
                                    <input type="number" step="0.001" class="form-control item-quantity" 
                                           name="items[<?php echo $index; ?>][quantity]" 
                                           value="<?php echo $item['quantity']; ?>" 
                                           onchange="calculateItem(<?php echo $index; ?>)" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Unit Price</label>
                                    <input type="number" step="0.01" class="form-control item-unit-price" 
                                           name="items[<?php echo $index; ?>][unit_price]" 
                                           value="<?php echo $item['unit_price']; ?>" 
                                           onchange="calculateItem(<?php echo $index; ?>)" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Amount</label>
                                    <input type="number" step="0.01" class="form-control item-amount" 
                                           name="items[<?php echo $index; ?>][amount]" 
                                           value="<?php echo $item['amount']; ?>" readonly>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="removeItem(this)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Payment Information -->
                <div class="invoice-section">
                    <h5 class="mb-3">
                        <i class="fas fa-credit-card me-2"></i>
                        Payment Information
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="payment_status" name="payment_status" 
                                        onchange="handlePaymentStatusChange()">
                                    <option value="pending" <?php echo $invoice['payment_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="partial" <?php echo $invoice['payment_status'] == 'partial' ? 'selected' : ''; ?>>Partial Payment</option>
                                    <option value="paid" <?php echo $invoice['payment_status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="overdue" <?php echo $invoice['payment_status'] == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                    <?php if (hasPermission('manager')): ?>
                                    <option value="cancelled" <?php echo $invoice['payment_status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <?php endif; ?>
                                </select>
                                <label for="payment_status">Payment Status</label>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-floating mb-3">
                                <input type="number" step="0.01" class="form-control" id="paid_amount" name="paid_amount" 
                                       value="<?php echo $invoice['paid_amount']; ?>" onchange="validatePaidAmount()">
                                <label for="paid_amount">Paid Amount</label>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-floating mb-3">
                                <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                       value="<?php echo $invoice['payment_date']; ?>">
                                <label for="payment_date">Payment Date</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <textarea class="form-control" id="remark" name="remark" style="height: 80px;"><?php echo htmlspecialchars($invoice['remark']); ?></textarea>
                        <label for="remark">Remark</label>
                    </div>
                </div>
            </div>

            <!-- Calculation Summary -->
            <div class="col-lg-4">
                <div class="invoice-section calculation-section">
                    <h5 class="mb-3">
                        <i class="fas fa-calculator me-2"></i>
                        Invoice Summary
                    </h5>
                    
                    <div class="mb-3">
                        <label class="form-label">Subtotal</label>
                        <input type="number" step="0.01" class="form-control" id="subtotal" name="subtotal" 
                               value="<?php echo $invoice['subtotal']; ?>" readonly>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <label class="form-label">VAT Rate (%)</label>
                            <input type="number" step="0.01" class="form-control" id="vat_rate" name="vat_rate" 
                                   value="<?php echo $invoice['vat_rate']; ?>" onchange="calculateTotals()">
                        </div>
                        <div class="col-6">
                            <label class="form-label">VAT Amount</label>
                            <input type="number" step="0.01" class="form-control" id="vat_amount" name="vat_amount" 
                                   value="<?php echo $invoice['vat_amount']; ?>" readonly>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <label class="form-label">Total Amount</label>
                        <input type="number" step="0.01" class="form-control total-amount" id="total_amount" name="total_amount" 
                               value="<?php echo $invoice['total_amount']; ?>" readonly>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            Outstanding: <strong id="outstanding_amount"><?php echo formatMoney($invoice['total_amount'] - $invoice['paid_amount']); ?></strong>
                        </small>
                    </div>
                </div>

                <!-- Change History -->
                <?php if ($invoice['updated_at'] != $invoice['created_at']): ?>
                <div class="invoice-section">
                    <h6>
                        <i class="fas fa-history me-2"></i>
                        Last Modified
                    </h6>
                    <small class="text-muted">
                        <?php echo formatDateThai($invoice['updated_at'], 'd/m/Y H:i'); ?>
                    </small>
                </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="invoice-section">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-save">
                            <i class="fas fa-save me-2"></i>
                            Save Changes
                        </button>
                        
                        <a href="invoices_view.php?id=<?php echo $invoice_id; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-eye me-2"></i>
                            View Invoice
                        </a>
                        
                        <a href="invoices.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Back to List
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let itemIndex = <?php echo count($invoice_items); ?>;

// Add new item
function addItem() {
    const container = document.getElementById('itemsContainer');
    const newItem = document.createElement('div');
    newItem.className = 'item-row';
    newItem.setAttribute('data-index', itemIndex);
    
    newItem.innerHTML = `
        <div class="row align-items-center">
            <div class="col-md-4">
                <input type="hidden" name="items[${itemIndex}][id]" value="0">
                <input type="hidden" name="items[${itemIndex}][job_selling_id]" value="">
                <label class="form-label">Description</label>
                <input type="text" class="form-control" name="items[${itemIndex}][description]" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Quantity</label>
                <input type="number" step="0.001" class="form-control item-quantity" 
                       name="items[${itemIndex}][quantity]" value="1" 
                       onchange="calculateItem(${itemIndex})" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Unit Price</label>
                <input type="number" step="0.01" class="form-control item-unit-price" 
                       name="items[${itemIndex}][unit_price]" value="0" 
                       onchange="calculateItem(${itemIndex})" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Amount</label>
                <input type="number" step="0.01" class="form-control item-amount" 
                       name="items[${itemIndex}][amount]" value="0" readonly>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(newItem);
    itemIndex++;
}

// Remove item
function removeItem(button) {
    if (document.querySelectorAll('.item-row').length > 1) {
        button.closest('.item-row').remove();
        calculateTotals();
    } else {
        alert('At least one item is required.');
    }
}

// Calculate item amount
function calculateItem(index) {
    const row = document.querySelector(`[data-index="${index}"]`);
    const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
    const unitPrice = parseFloat(row.querySelector('.item-unit-price').value) || 0;
    const amount = quantity * unitPrice;
    
    row.querySelector('.item-amount').value = amount.toFixed(2);
    calculateTotals();
}

// Calculate totals
function calculateTotals() {
    let subtotal = 0;
    
    document.querySelectorAll('.item-amount').forEach(input => {
        subtotal += parseFloat(input.value) || 0;
    });
    
    const vatRate = parseFloat(document.getElementById('vat_rate').value) || 0;
    const vatAmount = subtotal * (vatRate / 100);
    const totalAmount = subtotal + vatAmount;
    
    document.getElementById('subtotal').value = subtotal.toFixed(2);
    document.getElementById('vat_amount').value = vatAmount.toFixed(2);
    document.getElementById('total_amount').value = totalAmount.toFixed(2);
    
    updateOutstandingAmount();
}

// Update outstanding amount
function updateOutstandingAmount() {
    const totalAmount = parseFloat(document.getElementById('total_amount').value) || 0;
    const paidAmount = parseFloat(document.getElementById('paid_amount').value) || 0;
    const outstanding = totalAmount - paidAmount;
    
    document.getElementById('outstanding_amount').textContent = formatMoney(outstanding);
}

// Handle payment status change
function handlePaymentStatusChange() {
    const status = document.getElementById('payment_status').value;
    const paidAmountField = document.getElementById('paid_amount');
    const paymentDateField = document.getElementById('payment_date');
    const totalAmount = parseFloat(document.getElementById('total_amount').value) || 0;
    
    switch(status) {
        case 'paid':
            paidAmountField.value = totalAmount.toFixed(2);
            paymentDateField.required = true;
            if (!paymentDateField.value) {
                paymentDateField.value = new Date().toISOString().split('T')[0];
            }
            break;
        case 'pending':
            paidAmountField.value = '0.00';
            paymentDateField.required = false;
            paymentDateField.value = '';
            break;
        case 'partial':
            paymentDateField.required = true;
            if (!paymentDateField.value) {
                paymentDateField.value = new Date().toISOString().split('T')[0];
            }
            break;
        case 'overdue':
            paymentDateField.required = false;
            break;
        case 'cancelled':
            paidAmountField.value = '0.00';
            paymentDateField.required = false;
            break;
    }
    
    updateOutstandingAmount();
}

// Validate paid amount
function validatePaidAmount() {
    const totalAmount = parseFloat(document.getElementById('total_amount').value) || 0;
    const paidAmount = parseFloat(document.getElementById('paid_amount').value) || 0;
    const status = document.getElementById('payment_status').value;
    
    if (paidAmount > totalAmount) {
        alert('Paid amount cannot exceed total amount.');
        document.getElementById('paid_amount').value = totalAmount.toFixed(2);
    }
    
    if (paidAmount < 0) {
        alert('Paid amount cannot be negative.');
        document.getElementById('paid_amount').value = '0.00';
    }
    
    // Auto-update payment status based on paid amount
    if (paidAmount === 0) {
        if (status !== 'cancelled' && status !== 'overdue') {
            document.getElementById('payment_status').value = 'pending';
        }
    } else if (paidAmount === totalAmount) {
        document.getElementById('payment_status').value = 'paid';
    } else if (paidAmount > 0 && paidAmount < totalAmount) {
        document.getElementById('payment_status').value = 'partial';
    }
    
    updateOutstandingAmount();
}

// Load customer info (for future enhancement)
function loadCustomerInfo() {
    const customerId = document.getElementById('customer_id').value;
    if (customerId) {
        // Could load customer details like credit terms, tax info, etc.
        // For now, just calculate due date based on standard terms
        const invoiceDate = document.getElementById('invoice_date').value;
        if (invoiceDate) {
            const date = new Date(invoiceDate);
            date.setDate(date.getDate() + 30); // Default 30 days credit term
            document.getElementById('due_date').value = date.toISOString().split('T')[0];
        }
    }
}

// Format money display
function formatMoney(amount) {
    return new Intl.NumberFormat('th-TH', {
        style: 'currency',
        currency: 'THB',
        minimumFractionDigits: 2
    }).format(amount);
}

// Auto-calculate due date when invoice date changes
document.getElementById('invoice_date').addEventListener('change', function() {
    const invoiceDate = this.value;
    const dueDateField = document.getElementById('due_date');
    
    if (invoiceDate && !dueDateField.value) {
        const date = new Date(invoiceDate);
        date.setDate(date.getDate() + 30); // Default 30 days
        dueDateField.value = date.toISOString().split('T')[0];
    }
});

// Form validation before submit
document.getElementById('invoiceForm').addEventListener('submit', function(e) {
    const items = document.querySelectorAll('.item-row');
    let hasValidItems = false;
    
    items.forEach(item => {
        const description = item.querySelector('input[name*="[description]"]').value.trim();
        const quantity = parseFloat(item.querySelector('input[name*="[quantity]"]').value) || 0;
        const unitPrice = parseFloat(item.querySelector('input[name*="[unit_price]"]').value) || 0;
        
        if (description && quantity > 0 && unitPrice > 0) {
            hasValidItems = true;
        }
    });
    
    if (!hasValidItems) {
        e.preventDefault();
        alert('Please add at least one valid invoice item with description, quantity, and unit price.');
        return false;
    }
    
    const totalAmount = parseFloat(document.getElementById('total_amount').value) || 0;
    if (totalAmount <= 0) {
        e.preventDefault();
        alert('Total amount must be greater than zero.');
        return false;
    }
    
    const paymentStatus = document.getElementById('payment_status').value;
    const paidAmount = parseFloat(document.getElementById('paid_amount').value) || 0;
    
    if (paymentStatus === 'paid' && paidAmount !== totalAmount) {
        e.preventDefault();
        alert('Paid amount must equal total amount for paid invoices.');
        return false;
    }
    
    if (paymentStatus === 'partial' && (paidAmount <= 0 || paidAmount >= totalAmount)) {
        e.preventDefault();
        alert('Paid amount must be between 0 and total amount for partial payments.');
        return false;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
    submitBtn.disabled = true;
    
    // Re-enable if form submission fails
    setTimeout(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 10000);
});

// Initialize calculations on page load
document.addEventListener('DOMContentLoaded', function() {
    calculateTotals();
    handlePaymentStatusChange();
    
    // Add change listeners to paid amount field
    document.getElementById('paid_amount').addEventListener('input', updateOutstandingAmount);
    
    // Add change listeners to all existing items
    document.querySelectorAll('.item-quantity, .item-unit-price').forEach(input => {
        input.addEventListener('change', function() {
            const row = this.closest('.item-row');
            const index = row.getAttribute('data-index');
            calculateItem(index);
        });
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+S to save
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        document.getElementById('invoiceForm').submit();
    }
    
    // Ctrl+N to add new item
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        addItem();
    }
});

// Warn about unsaved changes
let formChanged = false;

document.querySelectorAll('input, select, textarea').forEach(element => {
    element.addEventListener('change', function() {
        formChanged = true;
    });
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        const message = 'You have unsaved changes. Are you sure you want to leave?';
        e.returnValue = message;
        return message;
    }
});

// Clear warning on form submit
document.getElementById('invoiceForm').addEventListener('submit', function() {
    formChanged = false;
});

// Auto-save draft functionality (could be enhanced)
function autoSaveDraft() {
    if (formChanged) {
        const formData = new FormData(document.getElementById('invoiceForm'));
        const data = Object.fromEntries(formData.entries());
        
        // Save to localStorage as backup
        localStorage.setItem('invoice_edit_draft_<?php echo $invoice_id; ?>', JSON.stringify(data));
        
        console.log('Draft auto-saved');
    }
}

// Auto-save every 2 minutes
setInterval(autoSaveDraft, 120000);

// Load draft on page load if available
document.addEventListener('DOMContentLoaded', function() {
    const draftKey = 'invoice_edit_draft_<?php echo $invoice_id; ?>';
    const savedDraft = localStorage.getItem(draftKey);
    
    if (savedDraft) {
        const confirmed = confirm('A draft of your changes was found. Would you like to restore it?');
        if (confirmed) {
            try {
                const draftData = JSON.parse(savedDraft);
                
                // Restore form values (implementation would go here)
                console.log('Draft restored');
                
                // Clear the draft
                localStorage.removeItem(draftKey);
            } catch (e) {
                console.error('Error restoring draft:', e);
            }
        } else {
            localStorage.removeItem(draftKey);
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>