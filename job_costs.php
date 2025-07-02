<?php
// =====================================================
// job_costs.php - Job Cost Management
// =====================================================

// เริ่มต้น session และเรียกใช้ functions
require_once 'includes/functions.php';

// ตรวจสอบสิทธิ์ - ต้องเป็น staff ขึ้นไป
requirePermission('staff');

// ตรวจสอบว่ามี job_id หรือไม่
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

if ($job_id <= 0) {
    $_SESSION['error_message'] = 'Invalid job ID';
    redirect('jobs.php');
}

// ดึงข้อมูล job
$job = fetchOne("
    SELECT j.*, 
           c1.company_name as shipper_name,
           c2.company_name as consignee_name
    FROM jobs j
    LEFT JOIN customers c1 ON j.shipper_id = c1.id
    LEFT JOIN customers c2 ON j.consignee_id = c2.id
    WHERE j.id = ?
", [$job_id]);

if (!$job) {
    $_SESSION['error_message'] = 'Job not found';
    redirect('jobs.php');
}

// จัดการการส่งข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = cleanInput($_POST['action']);
    
    if ($action === 'add_cost') {
        // เพิ่มรายการต้นทุนใหม่
        $vendor_id = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null;
        $cost_type = cleanInput($_POST['cost_type']);
        $description = cleanInput($_POST['description']);
        $currency = cleanInput($_POST['currency']);
        $exchange_rate = (float)$_POST['exchange_rate'];
        $amount = (float)$_POST['amount'];
        $invoice_no = cleanInput($_POST['invoice_no']);
        $invoice_date = cleanInput($_POST['invoice_date']);
        $remark = cleanInput($_POST['remark']);
        
        // คำนวณยอดเป็นบาท
        $amount_thb = $amount * $exchange_rate;
        
        // Validation
        $errors = [];
        if (empty($cost_type)) $errors[] = 'Cost type is required';
        if (empty($description)) $errors[] = 'Description is required';
        if ($amount <= 0) $errors[] = 'Amount must be greater than 0';
        if ($exchange_rate <= 0) $errors[] = 'Exchange rate must be greater than 0';
        
        if (empty($errors)) {
            $result = execute("
                INSERT INTO job_costs 
                (job_id, vendor_id, cost_type, description, currency, exchange_rate, 
                 amount, amount_thb, invoice_no, invoice_date, remark, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $job_id, $vendor_id, $cost_type, $description, $currency, 
                $exchange_rate, $amount, $amount_thb, $invoice_no, 
                $invoice_date ?: null, $remark, $_SESSION['user_id']
            ]);
            
            if ($result) {
                $_SESSION['success_message'] = 'Cost item added successfully';
            } else {
                $_SESSION['error_message'] = 'Failed to add cost item';
            }
        } else {
            $_SESSION['error_message'] = implode(', ', $errors);
        }
        
        redirect("job_costs.php?job_id={$job_id}");
    }
    
    elseif ($action === 'update_cost') {
        // แก้ไขรายการต้นทุน
        $cost_id = (int)$_POST['cost_id'];
        $vendor_id = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null;
        $cost_type = cleanInput($_POST['cost_type']);
        $description = cleanInput($_POST['description']);
        $currency = cleanInput($_POST['currency']);
        $exchange_rate = (float)$_POST['exchange_rate'];
        $amount = (float)$_POST['amount'];
        $invoice_no = cleanInput($_POST['invoice_no']);
        $invoice_date = cleanInput($_POST['invoice_date']);
        $payment_status = cleanInput($_POST['payment_status']);
        $payment_date = cleanInput($_POST['payment_date']);
        $remark = cleanInput($_POST['remark']);
        
        $amount_thb = $amount * $exchange_rate;
        
        $result = execute("
            UPDATE job_costs SET
                vendor_id = ?, cost_type = ?, description = ?, currency = ?,
                exchange_rate = ?, amount = ?, amount_thb = ?, invoice_no = ?,
                invoice_date = ?, payment_status = ?, payment_date = ?, 
                remark = ?, updated_at = NOW()
            WHERE id = ? AND job_id = ?
        ", [
            $vendor_id, $cost_type, $description, $currency, $exchange_rate,
            $amount, $amount_thb, $invoice_no, $invoice_date ?: null,
            $payment_status, $payment_date ?: null, $remark, $cost_id, $job_id
        ]);
        
        if ($result) {
            $_SESSION['success_message'] = 'Cost item updated successfully';
        } else {
            $_SESSION['error_message'] = 'Failed to update cost item';
        }
        
        redirect("job_costs.php?job_id={$job_id}");
    }
    
    elseif ($action === 'delete_cost') {
        // ลบรายการต้นทุน
        $cost_id = (int)$_POST['cost_id'];
        
        // ตรวจสอบสิทธิ์ - เฉพาะ manager ขึ้นไปถึงลบได้
        if (!hasPermission('manager')) {
            $_SESSION['error_message'] = 'Manager permission required to delete cost items';
            redirect("job_costs.php?job_id={$job_id}");
        }
        
        $result = execute("DELETE FROM job_costs WHERE id = ? AND job_id = ?", [$cost_id, $job_id]);
        
        if ($result) {
            $_SESSION['success_message'] = 'Cost item deleted successfully';
        } else {
            $_SESSION['error_message'] = 'Failed to delete cost item';
        }
        
        redirect("job_costs.php?job_id={$job_id}");
    }
}

// ดึงรายการต้นทุนของ job นี้
$job_costs = fetchAll("
    SELECT jc.*, v.company_name as vendor_name, v.vendor_code
    FROM job_costs jc
    LEFT JOIN vendors v ON jc.vendor_id = v.id
    WHERE jc.job_id = ?
    ORDER BY jc.created_at DESC
", [$job_id]);

// คำนวณยอดรวม
$total_cost_thb = array_sum(array_column($job_costs, 'amount_thb'));

// ดึงรายการ vendors สำหรับ dropdown
$vendors = fetchAll("
    SELECT id, vendor_code, company_name 
    FROM vendors 
    WHERE status = 'active' 
    ORDER BY company_name
");

// ตั้งค่าหน้า
$custom_page_title = "Job Costs - {$job['job_no']}";
$breadcrumb = [
    ['name' => 'Jobs', 'url' => 'jobs.php'],
    ['name' => $job['job_no'], 'url' => "jobs_view.php?id={$job_id}"],
    ['name' => 'Costs']
];

$page_header = true;
$page_subtitle = "Manage costs for job {$job['job_no']} - {$job['shipper_name']} to {$job['consignee_name']}";

$page_actions = "
    <a href='jobs_view.php?id={$job_id}' class='btn btn-outline-secondary'>
        <i class='fas fa-arrow-left'></i> Back to Job
    </a>
    <a href='job_selling.php?job_id={$job_id}' class='btn btn-success'>
        <i class='fas fa-dollar-sign'></i> Manage Selling
    </a>
";

// เพิ่ม CSS สำหรับหน้านี้
$additional_css = "
<style>
.cost-summary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 30px;
}
.cost-item-card {
    border: 1px solid #e9ecef;
    border-radius: 10px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}
.cost-item-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.cost-type-badge {
    font-size: 0.8rem;
    padding: 4px 8px;
}
.payment-status {
    font-weight: bold;
}
.currency-display {
    font-family: monospace;
    font-weight: bold;
}
</style>
";

// เพิ่ม JavaScript สำหรับหน้านี้
$page_js = "
// ตั้งค่า exchange rate เริ่มต้น
function setCurrencyRate(currency) {
    const rates = {
        'THB': 1.0000,
        'USD': 35.0000,
        'EUR': 38.0000,
        'CNY': 5.0000,
        'JPY': 0.2500
    };
    
    document.getElementById('exchange_rate').value = rates[currency] || 1.0000;
    calculateTotalTHB();
}

// คำนวณยอดเป็นบาท
function calculateTotalTHB() {
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const rate = parseFloat(document.getElementById('exchange_rate').value) || 1;
    const totalTHB = amount * rate;
    
    document.getElementById('amount_thb_display').textContent = formatNumber(totalTHB) + ' THB';
}

// Format ตัวเลข
function formatNumber(num) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(num);
}

// เปิด modal สำหรับแก้ไข
function editCost(costData) {
    document.getElementById('edit_cost_id').value = costData.id;
    document.getElementById('edit_vendor_id').value = costData.vendor_id || '';
    document.getElementById('edit_cost_type').value = costData.cost_type;
    document.getElementById('edit_description').value = costData.description;
    document.getElementById('edit_currency').value = costData.currency;
    document.getElementById('edit_exchange_rate').value = costData.exchange_rate;
    document.getElementById('edit_amount').value = costData.amount;
    document.getElementById('edit_invoice_no').value = costData.invoice_no || '';
    document.getElementById('edit_invoice_date').value = costData.invoice_date || '';
    document.getElementById('edit_payment_status').value = costData.payment_status;
    document.getElementById('edit_payment_date').value = costData.payment_date || '';
    document.getElementById('edit_remark').value = costData.remark || '';
    
    new bootstrap.Modal(document.getElementById('editCostModal')).show();
}

// ยืนยันการลบ
function confirmDelete(costId, description) {
    if (confirm('Are you sure you want to delete this cost item?\\n\\n' + description + '\\n\\nThis action cannot be undone.')) {
        document.getElementById('delete_cost_id').value = costId;
        document.getElementById('deleteCostForm').submit();
    }
}
";

include 'includes/header.php';
?>

<!-- Cost Summary -->
<div class="cost-summary">
    <div class="row">
        <div class="col-md-8">
            <h5><i class="fas fa-calculator me-2"></i>Cost Summary for Job: <?php echo htmlspecialchars($job['job_no']); ?></h5>
            <p class="mb-2">
                <strong>Route:</strong> <?php echo htmlspecialchars($job['origin'] . ' → ' . $job['destination']); ?><br>
                <strong>Service:</strong> <?php echo ucfirst(str_replace('_', ' ', $job['job_type'])); ?> 
                (<?php echo ucfirst(str_replace('_', ' ', $job['service_type'])); ?>)
            </p>
        </div>
        <div class="col-md-4 text-md-end">
            <h3 class="mb-0"><?php echo formatMoney($total_cost_thb, 'THB'); ?></h3>
            <small>Total Cost</small><br>
            <small class="opacity-75"><?php echo count($job_costs); ?> cost items</small>
        </div>
    </div>
</div>

<!-- Add New Cost Button -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Cost Items</h4>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCostModal">
        <i class="fas fa-plus"></i> Add Cost Item
    </button>
</div>

<!-- Cost Items List -->
<div class="row">
    <?php if (empty($job_costs)): ?>
        <div class="col-12">
            <div class="card text-center">
                <div class="card-body py-5">
                    <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Cost Items Yet</h5>
                    <p class="text-muted">Start by adding cost items for this job.</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCostModal">
                        <i class="fas fa-plus"></i> Add First Cost Item
                    </button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($job_costs as $cost): ?>
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="cost-item-card card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge cost-type-badge bg-<?php echo getCostTypeBadgeColor($cost['cost_type']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $cost['cost_type'])); ?>
                            </span>
                            <span class="payment-status text-<?php echo getPaymentStatusColor($cost['payment_status']); ?>">
                                <?php echo ucfirst($cost['payment_status']); ?>
                            </span>
                        </div>
                        
                        <h6 class="card-title"><?php echo htmlspecialchars($cost['description']); ?></h6>
                        
                        <?php if ($cost['vendor_name']): ?>
                            <p class="text-muted mb-2">
                                <i class="fas fa-truck"></i> <?php echo htmlspecialchars($cost['vendor_name']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="currency-display mb-2">
                            <?php echo formatMoney($cost['amount'], $cost['currency']); ?>
                            <?php if ($cost['currency'] !== 'THB'): ?>
                                <br><small class="text-muted">
                                    @ <?php echo number_format($cost['exchange_rate'], 4); ?> = 
                                    <?php echo formatMoney($cost['amount_thb'], 'THB'); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($cost['invoice_no']): ?>
                            <p class="mb-2">
                                <small><i class="fas fa-file-invoice"></i> <?php echo htmlspecialchars($cost['invoice_no']); ?></small>
                                <?php if ($cost['invoice_date']): ?>
                                    <br><small class="text-muted"><?php echo formatDateThai($cost['invoice_date']); ?></small>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="mt-auto">
                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                    onclick="editCost(<?php echo htmlspecialchars(json_encode($cost)); ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            
                            <?php if (hasPermission('manager')): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                        onclick="confirmDelete(<?php echo $cost['id']; ?>, '<?php echo htmlspecialchars($cost['description']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-light">
                        <small class="text-muted">
                            <i class="fas fa-clock"></i> <?php echo formatDateThai($cost['created_at'], 'd/m/Y H:i'); ?>
                        </small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Add Cost Modal -->
<div class="modal fade" id="addCostModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_cost">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add Cost Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Vendor</label>
                                <select name="vendor_id" class="form-select">
                                    <option value="">-- Select Vendor --</option>
                                    <?php foreach ($vendors as $vendor): ?>
                                        <option value="<?php echo $vendor['id']; ?>">
                                            <?php echo htmlspecialchars($vendor['vendor_code'] . ' - ' . $vendor['company_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Cost Type <span class="text-danger">*</span></label>
                                <select name="cost_type" class="form-select" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="freight">Freight</option>
                                    <option value="local_charge">Local Charge</option>
                                    <option value="customs">Customs</option>
                                    <option value="trucking">Trucking</option>
                                    <option value="documentation">Documentation</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <input type="text" name="description" class="form-control" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Currency <span class="text-danger">*</span></label>
                                <select name="currency" id="currency" class="form-select" required onchange="setCurrencyRate(this.value)">
                                    <option value="THB">THB</option>
                                    <option value="USD">USD</option>
                                    <option value="EUR">EUR</option>
                                    <option value="CNY">CNY</option>
                                    <option value="JPY">JPY</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Exchange Rate <span class="text-danger">*</span></label>
                                <input type="number" name="exchange_rate" id="exchange_rate" class="form-control" 
                                       step="0.0001" value="1.0000" required onchange="calculateTotalTHB()">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Amount <span class="text-danger">*</span></label>
                                <input type="number" name="amount" id="amount" class="form-control" 
                                       step="0.01" required onchange="calculateTotalTHB()">
                                <small class="text-muted">
                                    Total: <span id="amount_thb_display">0.00 THB</span>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Invoice No.</label>
                                <input type="text" name="invoice_no" class="form-control">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Invoice Date</label>
                                <input type="date" name="invoice_date" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Remark</label>
                        <textarea name="remark" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Cost Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Cost Modal -->
<div class="modal fade" id="editCostModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_cost">
                <input type="hidden" name="cost_id" id="edit_cost_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Edit Cost Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Vendor</label>
                                <select name="vendor_id" id="edit_vendor_id" class="form-select">
                                    <option value="">-- Select Vendor --</option>
                                    <?php foreach ($vendors as $vendor): ?>
                                        <option value="<?php echo $vendor['id']; ?>">
                                            <?php echo htmlspecialchars($vendor['vendor_code'] . ' - ' . $vendor['company_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Cost Type <span class="text-danger">*</span></label>
                                <select name="cost_type" id="edit_cost_type" class="form-select" required>
                                    <option value="freight">Freight</option>
                                    <option value="local_charge">Local Charge</option>
                                    <option value="customs">Customs</option>
                                    <option value="trucking">Trucking</option>
                                    <option value="documentation">Documentation</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <input type="text" name="description" id="edit_description" class="form-control" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Currency <span class="text-danger">*</span></label>
                                <select name="currency" id="edit_currency" class="form-select" required>
                                    <option value="THB">THB</option>
                                    <option value="USD">USD</option>
                                    <option value="EUR">EUR</option>
                                    <option value="CNY">CNY</option>
                                    <option value="JPY">JPY</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Exchange Rate <span class="text-danger">*</span></label>
                                <input type="number" name="exchange_rate" id="edit_exchange_rate" class="form-control" 
                                       step="0.0001" required>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Amount <span class="text-danger">*</span></label>
                                <input type="number" name="amount" id="edit_amount" class="form-control" 
                                       step="0.01" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Invoice No.</label>
                                <input type="text" name="invoice_no" id="edit_invoice_no" class="form-control">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Invoice Date</label>
                                <input type="date" name="invoice_date" id="edit_invoice_date" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Payment Status</label>
                                <select name="payment_status" id="edit_payment_status" class="form-select">
                                    <option value="pending">Pending</option>
                                    <option value="paid">Paid</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Payment Date</label>
                                <input type="date" name="payment_date" id="edit_payment_date" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Remark</label>
                        <textarea name="remark" id="edit_remark" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Cost Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form (Hidden) -->
<form id="deleteCostForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_cost">
    <input type="hidden" name="cost_id" id="delete_cost_id">
</form>

<?php 
include 'includes/footer.php';

// ========================================
// Helper Functions
// ========================================

function getCostTypeBadgeColor($cost_type) {
    $colors = [
        'freight' => 'primary',
        'local_charge' => 'info',
        'customs' => 'warning',
        'trucking' => 'success',
        'documentation' => 'secondary',
        'other' => 'dark'
    ];
    
    return $colors[$cost_type] ?? 'secondary';
}

function getPaymentStatusColor($payment_status) {
    $colors = [
        'pending' => 'warning',
        'paid' => 'success',
        'cancelled' => 'danger'
    ];
    
    return $colors[$payment_status] ?? 'secondary';
}
?>