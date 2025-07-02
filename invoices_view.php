<?php
// =====================================================
// invoices_view.php - View Invoice Details
// =====================================================

// เริ่มต้น session และเรียกใช้ functions
require_once 'includes/functions.php';

// ตรวจสอบสิทธิ์ - ต้องเป็น viewer ขึ้นไป
requirePermission('viewer');

// รับ invoice ID
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($invoice_id <= 0) {
    $_SESSION['error_message'] = 'Invalid invoice ID';
    redirect('invoices.php');
}

// จัดการการอัพเดตสถานะการจ่ายเงิน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('staff')) {
    $action = cleanInput($_POST['action']);
    
    if ($action === 'update_payment_status') {
        $new_status = cleanInput($_POST['payment_status']);
        $paid_amount = (float)$_POST['paid_amount'];
        $payment_date = cleanInput($_POST['payment_date']);
        $payment_note = cleanInput($_POST['payment_note']);
        
        // Validation
        $errors = [];
        if (!in_array($new_status, ['pending', 'partial', 'paid', 'overdue', 'cancelled'])) {
            $errors[] = 'Invalid payment status';
        }
        
        if ($new_status === 'paid' && $paid_amount <= 0) {
            $errors[] = 'Paid amount must be greater than 0 for paid status';
        }
        
        if ($new_status === 'paid' && empty($payment_date)) {
            $payment_date = date('Y-m-d');
        }
        
        if (empty($errors)) {
            $result = execute("
                UPDATE invoices 
                SET payment_status = ?, paid_amount = ?, payment_date = ?, updated_at = NOW()
                WHERE id = ?
            ", [$new_status, $paid_amount, $payment_date ?: null, $invoice_id]);
            
            if ($result) {
                // บันทึกประวัติการจ่ายเงิน
                execute("
                    INSERT INTO invoice_payment_history 
                    (invoice_id, payment_status, paid_amount, payment_date, notes, updated_by, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ", [$invoice_id, $new_status, $paid_amount, $payment_date ?: null, $payment_note, $_SESSION['user_id']]);
                
                $_SESSION['success_message'] = 'Payment status updated successfully';
            } else {
                $_SESSION['error_message'] = 'Failed to update payment status';
            }
        } else {
            $_SESSION['error_message'] = implode(', ', $errors);
        }
        
        redirect("invoices_view.php?id={$invoice_id}");
    }
}

// ดึงข้อมูลใบแจ้งหนี้
$invoice = fetchOne("
    SELECT 
        i.*,
        c.company_name as customer_name,
        c.customer_code,
        c.address as customer_address,
        c.phone as customer_phone,
        c.email as customer_email,
        c.tax_id as customer_tax_id,
        j.job_no,
        j.job_type,
        j.origin,
        j.destination,
        j.vessel_flight,
        j.etd,
        j.eta,
        u.name as created_by_name,
        (i.total_amount - i.paid_amount) as outstanding_amount,
        CASE 
            WHEN i.due_date < CURDATE() AND i.payment_status IN ('pending', 'partial') THEN 1
            ELSE 0
        END as is_overdue,
        DATEDIFF(CURDATE(), i.due_date) as days_overdue
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    LEFT JOIN jobs j ON i.job_id = j.id
    LEFT JOIN users u ON i.created_by = u.id
    WHERE i.id = ?
", [$invoice_id]);

if (!$invoice) {
    $_SESSION['error_message'] = 'Invoice not found';
    redirect('invoices.php');
}

// ดึงรายการสินค้า/บริการ
$invoice_items = fetchAll("
    SELECT * FROM invoice_items 
    WHERE invoice_id = ? 
    ORDER BY id
", [$invoice_id]);

// ดึงประวัติการจ่ายเงิน
$payment_history = fetchAll("
    SELECT iph.*, u.name as updated_by_name
    FROM invoice_payment_history iph
    LEFT JOIN users u ON iph.updated_by = u.id
    WHERE iph.invoice_id = ?
    ORDER BY iph.updated_at DESC
", [$invoice_id]);

// คำนวณข้อมูลเพิ่มเติม
$age_days = floor((time() - strtotime($invoice['invoice_date'])) / 86400);
$payment_percentage = $invoice['total_amount'] > 0 ? ($invoice['paid_amount'] / $invoice['total_amount']) * 100 : 0;

// ตั้งค่าหน้า
$custom_page_title = "Invoice Details - {$invoice['invoice_no']}";
$breadcrumb = [
    ['name' => 'Invoices', 'url' => 'invoices.php'],
    ['name' => $invoice['invoice_no']]
];

$page_header = true;
$page_subtitle = "Invoice details and payment information";

$page_actions = "
    <a href='invoices.php' class='btn btn-outline-secondary'>
        <i class='fas fa-arrow-left'></i> Back to Invoices
    </a>
    <a href='invoices_print.php?id={$invoice_id}' class='btn btn-success' target='_blank'>
        <i class='fas fa-print'></i> Print
    </a>
    <a href='invoices_export.php?id={$invoice_id}&type=pdf' class='btn btn-danger' target='_blank'>
        <i class='fas fa-file-pdf'></i> PDF
    </a>
";

if (hasPermission('staff')) {
    $page_actions .= "
        <a href='invoices_edit.php?id={$invoice_id}' class='btn btn-primary'>
            <i class='fas fa-edit'></i> Edit
        </a>
    ";
}

// เพิ่ม CSS สำหรับหน้านี้
$additional_css = "
<style>
.invoice-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
}

.invoice-status {
    position: absolute;
    top: 20px;
    right: 20px;
    font-size: 1.2rem;
}

.invoice-details-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
}

.invoice-items-table {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.payment-summary {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
}

.payment-progress {
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
    height: 20px;
    margin-top: 10px;
}

.payment-progress-bar {
    background: rgba(255,255,255,0.8);
    height: 100%;
    border-radius: 10px;
    transition: width 0.3s ease;
}

.overdue-warning {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.8; }
    100% { opacity: 1; }
}

.info-section {
    border-left: 4px solid #007bff;
    padding-left: 15px;
    margin-bottom: 20px;
}

.amount-display {
    font-family: 'Courier New', monospace;
    font-weight: bold;
    font-size: 1.1rem;
}

.history-item {
    border-left: 3px solid #e9ecef;
    padding-left: 15px;
    margin-bottom: 15px;
    position: relative;
}

.history-item::before {
    content: '';
    position: absolute;
    left: -6px;
    top: 5px;
    width: 8px;
    height: 8px;
    background: #007bff;
    border-radius: 50%;
}

.quick-actions {
    position: sticky;
    top: 20px;
}
</style>
";

// เพิ่ม JavaScript สำหรับหน้านี้
$page_js = "
// Update payment status
function updatePaymentStatus() {
    const modal = new bootstrap.Modal(document.getElementById('paymentStatusModal'));
    modal.show();
}

// Calculate outstanding amount
function calculateOutstanding() {
    const totalAmount = parseFloat(document.getElementById('total_amount_value').value);
    const paidAmount = parseFloat(document.getElementById('paid_amount').value) || 0;
    const outstanding = totalAmount - paidAmount;
    
    document.getElementById('outstanding_display').textContent = formatMoney(outstanding);
    
    // Auto-set status based on payment
    if (paidAmount >= totalAmount) {
        document.getElementById('payment_status').value = 'paid';
    } else if (paidAmount > 0) {
        document.getElementById('payment_status').value = 'partial';
    } else {
        document.getElementById('payment_status').value = 'pending';
    }
}

// Format money display
function formatMoney(amount) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

// Set payment date to today when status is paid
function onPaymentStatusChange() {
    const status = document.getElementById('payment_status').value;
    if (status === 'paid' && !document.getElementById('payment_date').value) {
        document.getElementById('payment_date').value = new Date().toISOString().split('T')[0];
    }
}

// Send invoice by email (placeholder)
function sendInvoiceByEmail() {
    if (confirm('Send this invoice to customer email?')) {
        // AJAX call to send email
        alert('Email functionality will be implemented');
    }
}

// Generate payment reminder
function sendPaymentReminder() {
    if (confirm('Send payment reminder to customer?')) {
        // AJAX call to send reminder
        alert('Payment reminder functionality will be implemented');
    }
}

// Print invoice
function printInvoice() {
    window.open('invoices_print.php?id={$invoice_id}', '_blank');
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Set up event listeners
    const paidAmountInput = document.getElementById('paid_amount');
    if (paidAmountInput) {
        paidAmountInput.addEventListener('input', calculateOutstanding);
    }
    
    const paymentStatusSelect = document.getElementById('payment_status');
    if (paymentStatusSelect) {
        paymentStatusSelect.addEventListener('change', onPaymentStatusChange);
    }
});
";

include 'includes/header.php';
?>

<!-- Overdue Warning -->
<?php if ($invoice['is_overdue']): ?>
<div class="overdue-warning">
    <div class="d-flex align-items-center">
        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
        <div>
            <h5 class="mb-1">Payment Overdue!</h5>
            <p class="mb-0">This invoice is overdue by <strong><?php echo $invoice['days_overdue']; ?> days</strong>. 
            Outstanding amount: <strong><?php echo formatMoney($invoice['outstanding_amount'], $invoice['currency']); ?></strong></p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <!-- Main Content -->
    <div class="col-lg-8">
        <!-- Invoice Header -->
        <div class="invoice-header position-relative">
            <div class="invoice-status">
                <?php echo getInvoiceStatusBadge($invoice['payment_status'], $invoice['is_overdue']); ?>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <h2 class="mb-3">Invoice <?php echo htmlspecialchars($invoice['invoice_no']); ?></h2>
                    <div class="info-section">
                        <h5><i class="fas fa-user"></i> Bill To:</h5>
                        <p class="mb-1"><strong><?php echo htmlspecialchars($invoice['customer_name']); ?></strong></p>
                        <p class="mb-1"><?php echo htmlspecialchars($invoice['customer_code']); ?></p>
                        <?php if ($invoice['customer_address']): ?>
                            <p class="mb-1"><?php echo nl2br(htmlspecialchars($invoice['customer_address'])); ?></p>
                        <?php endif; ?>
                        <?php if ($invoice['customer_tax_id']): ?>
                            <p class="mb-0">Tax ID: <?php echo htmlspecialchars($invoice['customer_tax_id']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="info-section">
                        <h5><i class="fas fa-calendar"></i> Invoice Details:</h5>
                        <p class="mb-1"><strong>Invoice Date:</strong> <?php echo formatDateThai($invoice['invoice_date']); ?></p>
                        <p class="mb-1"><strong>Due Date:</strong> <?php echo formatDateThai($invoice['due_date']); ?></p>
                        <p class="mb-1"><strong>Age:</strong> <?php echo $age_days; ?> days</p>
                        <?php if ($invoice['job_no']): ?>
                            <p class="mb-0">
                                <strong>Related Job:</strong> 
                                <a href="jobs_view.php?id=<?php echo $invoice['job_id']; ?>" class="text-white text-decoration-underline">
                                    <?php echo htmlspecialchars($invoice['job_no']); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Job Information (if applicable) -->
        <?php if ($invoice['job_no']): ?>
        <div class="invoice-details-card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-shipping-fast"></i> Related Job Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Job No:</strong> <?php echo htmlspecialchars($invoice['job_no']); ?></p>
                        <p><strong>Job Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $invoice['job_type'])); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Route:</strong> <?php echo htmlspecialchars($invoice['origin'] . ' → ' . $invoice['destination']); ?></p>
                        <p><strong>Vessel/Flight:</strong> <?php echo htmlspecialchars($invoice['vessel_flight'] ?: 'TBD'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Invoice Items -->
        <div class="invoice-items-table">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-list"></i> Invoice Items</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">#</th>
                                <th width="45%">Description</th>
                                <th width="15%" class="text-center">Quantity</th>
                                <th width="15%" class="text-end">Unit Price</th>
                                <th width="20%" class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoice_items as $index => $item): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                                <td class="text-center"><?php echo formatNumber($item['quantity'], 2); ?></td>
                                <td class="text-end amount-display"><?php echo formatMoney($item['unit_price'], $invoice['currency']); ?></td>
                                <td class="text-end amount-display"><?php echo formatMoney($item['amount'], $invoice['currency']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                <td class="text-end amount-display"><strong><?php echo formatMoney($invoice['subtotal'], $invoice['currency']); ?></strong></td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-end"><strong>VAT (<?php echo formatNumber($invoice['vat_rate'], 1); ?>%):</strong></td>
                                <td class="text-end amount-display"><strong><?php echo formatMoney($invoice['vat_amount'], $invoice['currency']); ?></strong></td>
                            </tr>
                            <tr class="table-primary">
                                <td colspan="4" class="text-end"><h5><strong>Total Amount:</strong></h5></td>
                                <td class="text-end amount-display"><h5><strong><?php echo formatMoney($invoice['total_amount'], $invoice['currency']); ?></strong></h5></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Remarks -->
        <?php if ($invoice['remark']): ?>
        <div class="invoice-details-card mt-3">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-comment"></i> Remarks</h5>
            </div>
            <div class="card-body">
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($invoice['remark'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <div class="quick-actions">
            <!-- Payment Summary -->
            <div class="payment-summary">
                <h5><i class="fas fa-money-bill-wave"></i> Payment Summary</h5>
                <div class="row text-center">
                    <div class="col-6">
                        <div class="amount-display"><?php echo formatMoney($invoice['total_amount'], $invoice['currency']); ?></div>
                        <small>Total Amount</small>
                    </div>
                    <div class="col-6">
                        <div class="amount-display"><?php echo formatMoney($invoice['paid_amount'], $invoice['currency']); ?></div>
                        <small>Paid Amount</small>
                    </div>
                </div>
                
                <div class="payment-progress">
                    <div class="payment-progress-bar" style="width: <?php echo min(100, $payment_percentage); ?>%"></div>
                </div>
                
                <div class="text-center mt-3">
                    <h4 class="amount-display"><?php echo formatMoney($invoice['outstanding_amount'], $invoice['currency']); ?></h4>
                    <small>Outstanding Amount</small>
                </div>
                
                <div class="text-center mt-2">
                    <small><?php echo number_format($payment_percentage, 1); ?>% Paid</small>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if (hasPermission('staff')): ?>
                        <button type="button" class="btn btn-primary" onclick="updatePaymentStatus()">
                            <i class="fas fa-credit-card"></i> Update Payment
                        </button>
                        <?php endif; ?>
                        
                        <button type="button" class="btn btn-success" onclick="printInvoice()">
                            <i class="fas fa-print"></i> Print Invoice
                        </button>
                        
                        <button type="button" class="btn btn-info" onclick="sendInvoiceByEmail()">
                            <i class="fas fa-envelope"></i> Email Invoice
                        </button>
                        
                        <?php if ($invoice['outstanding_amount'] > 0): ?>
                        <button type="button" class="btn btn-warning" onclick="sendPaymentReminder()">
                            <i class="fas fa-bell"></i> Send Reminder
                        </button>
                        <?php endif; ?>
                        
                        <a href="invoices_export.php?id=<?php echo $invoice_id; ?>&type=pdf" class="btn btn-danger" target="_blank">
                            <i class="fas fa-file-pdf"></i> Download PDF
                        </a>
                    </div>
                </div>
            </div>

            <!-- Payment History -->
            <?php if (!empty($payment_history)): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history"></i> Payment History</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($payment_history as $history): ?>
                    <div class="history-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong><?php echo ucfirst($history['payment_status']); ?></strong>
                                <?php if ($history['paid_amount'] > 0): ?>
                                    <br><span class="amount-display"><?php echo formatMoney($history['paid_amount'], $invoice['currency']); ?></span>
                                <?php endif; ?>
                                <?php if ($history['notes']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($history['notes']); ?></small>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">
                                <?php echo formatDateThai($history['updated_at'], 'd/m/Y H:i'); ?>
                                <br>by <?php echo htmlspecialchars($history['updated_by_name']); ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Invoice Info -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Invoice Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Created:</strong><br><?php echo formatDateThai($invoice['created_at'], 'd/m/Y H:i'); ?></p>
                    <p><strong>Created by:</strong><br><?php echo htmlspecialchars($invoice['created_by_name']); ?></p>
                    <p><strong>Last Updated:</strong><br><?php echo formatDateThai($invoice['updated_at'], 'd/m/Y H:i'); ?></p>
                    <p class="mb-0"><strong>Currency:</strong><br><?php echo $invoice['currency']; ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Status Update Modal -->
<?php if (hasPermission('staff')): ?>
<div class="modal fade" id="paymentStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_payment_status">
                <input type="hidden" id="total_amount_value" value="<?php echo $invoice['total_amount']; ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title">Update Payment Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Payment Status</label>
                                <select name="payment_status" id="payment_status" class="form-select" required>
                                    <option value="pending" <?php echo $invoice['payment_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="partial" <?php echo $invoice['payment_status'] == 'partial' ? 'selected' : ''; ?>>Partial Paid</option>
                                    <option value="paid" <?php echo $invoice['payment_status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="overdue" <?php echo $invoice['payment_status'] == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                    <option value="cancelled" <?php echo $invoice['payment_status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Paid Amount</label>
                                <input type="number" name="paid_amount" id="paid_amount" class="form-control" 
                                       step="0.01" value="<?php echo $invoice['paid_amount']; ?>" min="0" 
                                       max="<?php echo $invoice['total_amount']; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Date</label>
                        <input type="date" name="payment_date" id="payment_date" class="form-control" 
                               value="<?php echo $invoice['payment_date']; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="payment_note" class="form-control" rows="3" 
                                  placeholder="Optional payment notes..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Outstanding Amount:</strong> 
                        <span id="outstanding_display" class="amount-display">
                            <?php echo formatMoney($invoice['outstanding_amount'], $invoice['currency']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php 
include 'includes/footer.php';

// ========================================
// Helper Functions
// ========================================

/**
 * Get invoice status badge
 * @param string $status
 * @param bool $is_overdue
 * @return string
 */
function getInvoiceStatusBadge($status, $is_overdue = false) {
    if ($is_overdue && in_array($status, ['pending', 'partial'])) {
        return '<span class="badge bg-danger fs-6">Overdue</span>';
    }
    
    $badges = [
        'pending' => '<span class="badge bg-warning fs-6">Pending</span>',
        'partial' => '<span class="badge bg-info fs-6">Partial Paid</span>',
        'paid' => '<span class="badge bg-success fs-6">Paid</span>',
        'overdue' => '<span class="badge bg-danger fs-6">Overdue</span>',
        'cancelled' => '<span class="badge bg-dark fs-6">Cancelled</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge bg-secondary fs-6">Unknown</span>';
}
?>