<?php
// =====================================================
// job_selling.php - Job Selling Price Management
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
    
    if ($action === 'add_selling') {
        // เพิ่มรายการราคาขายใหม่
        $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $selling_type = cleanInput($_POST['selling_type']);
        $description = cleanInput($_POST['description']);
        $currency = cleanInput($_POST['currency']);
        $exchange_rate = (float)$_POST['exchange_rate'];
        $amount = (float)$_POST['amount'];
        $remark = cleanInput($_POST['remark']);
        
        // คำนวณยอดเป็นบาท
        $amount_thb = $amount * $exchange_rate;
        
        // Validation
        $errors = [];
        if (empty($selling_type)) $errors[] = 'Selling type is required';
        if (empty($description)) $errors[] = 'Description is required';
        if ($amount <= 0) $errors[] = 'Amount must be greater than 0';
        if ($exchange_rate <= 0) $errors[] = 'Exchange rate must be greater than 0';
        
        if (empty($errors)) {
            $result = execute("
                INSERT INTO job_selling 
                (job_id, customer_id, selling_type, description, currency, exchange_rate, 
                 amount, amount_thb, remark, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $job_id, $customer_id, $selling_type, $description, $currency, 
                $exchange_rate, $amount, $amount_thb, $remark, $_SESSION['user_id']
            ]);
            
            if ($result) {
                $_SESSION['success_message'] = 'Selling item added successfully';
            } else {
                $_SESSION['error_message'] = 'Failed to add selling item';
            }
        } else {
            $_SESSION['error_message'] = implode(', ', $errors);
        }
        
        redirect("job_selling.php?job_id={$job_id}");
    }
    
    elseif ($action === 'update_selling') {
        // แก้ไขรายการราคาขาย
        $selling_id = (int)$_POST['selling_id'];
        $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $selling_type = cleanInput($_POST['selling_type']);
        $description = cleanInput($_POST['description']);
        $currency = cleanInput($_POST['currency']);
        $exchange_rate = (float)$_POST['exchange_rate'];
        $amount = (float)$_POST['amount'];
        $remark = cleanInput($_POST['remark']);
        
        $amount_thb = $amount * $exchange_rate;
        
        $result = execute("
            UPDATE job_selling SET
                customer_id = ?, selling_type = ?, description = ?, currency = ?,
                exchange_rate = ?, amount = ?, amount_thb = ?, 
                remark = ?, updated_at = NOW()
            WHERE id = ? AND job_id = ?
        ", [
            $customer_id, $selling_type, $description, $currency, $exchange_rate,
            $amount, $amount_thb, $remark, $selling_id, $job_id
        ]);
        
        if ($result) {
            $_SESSION['success_message'] = 'Selling item updated successfully';
        } else {
            $_SESSION['error_message'] = 'Failed to update selling item';
        }
        
        redirect("job_selling.php?job_id={$job_id}");
    }
    
    elseif ($action === 'delete_selling') {
        // ลบรายการราคาขาย
        $selling_id = (int)$_POST['selling_id'];
        
        // ตรวจสอบสิทธิ์ - เฉพาะ manager ขึ้นไปถึงลบได้
        if (!hasPermission('manager')) {
            $_SESSION['error_message'] = 'Manager permission required to delete selling items';
            redirect("job_selling.php?job_id={$job_id}");
        }
        
        $result = execute("DELETE FROM job_selling WHERE id = ? AND job_id = ?", [$selling_id, $job_id]);
        
        if ($result) {
            $_SESSION['success_message'] = 'Selling item deleted successfully';
        } else {
            $_SESSION['error_message'] = 'Failed to delete selling item';
        }
        
        redirect("job_selling.php?job_id={$job_id}");
    }
    
    elseif ($action === 'copy_from_quotation') {
        // คัดลอกรายการจากใบเสนอราคา
        $quotation_id = (int)$_POST['quotation_id'];
        
        // ดึงรายการจากใบเสนอราคา
        $quotation_items = fetchAll("
            SELECT * FROM quotation_items 
            WHERE quotation_id = ?
            ORDER BY id
        ", [$quotation_id]);
        
        $copied_count = 0;
        foreach ($quotation_items as $item) {
            $result = execute("
                INSERT INTO job_selling 
                (job_id, customer_id, selling_type, description, currency, exchange_rate, 
                 amount, amount_thb, remark, created_by)
                VALUES (?, ?, ?, ?, ?, 1.0000, ?, ?, ?, ?)
            ", [
                $job_id, $job['shipper_id'], $item['item_type'], $item['description'], 
                $item['currency'], $item['amount'], $item['amount'], 
                'Copied from quotation', $_SESSION['user_id']
            ]);
            
            if ($result) $copied_count++;
        }
        
        if ($copied_count > 0) {
            $_SESSION['success_message'] = "Copied {$copied_count} items from quotation successfully";
        } else {
            $_SESSION['error_message'] = 'Failed to copy items from quotation';
        }
        
        redirect("job_selling.php?job_id={$job_id}");
    }
}

// ดึงรายการราคาขายของ job นี้
$job_selling = fetchAll("
    SELECT js.*, c.company_name as customer_name, c.customer_code
    FROM job_selling js
    LEFT JOIN customers c ON js.customer_id = c.id
    WHERE js.job_id = ?
    ORDER BY js.created_at DESC
", [$job_id]);

// คำนวณยอดรวม
$total_selling_thb = array_sum(array_column($job_selling, 'amount_thb'));
$total_cost_thb = (float)$job['cost_total'];
$profit_loss = $total_selling_thb - $total_cost_thb;
$profit_margin = $total_selling_thb > 0 ? ($profit_loss / $total_selling_thb) * 100 : 0;

// ดึงรายการลูกค้าสำหรับ dropdown
$customers = fetchAll("
    SELECT id, customer_code, company_name 
    FROM customers 
    WHERE status = 'active' 
    ORDER BY company_name
");

// ดึงใบเสนอราคาที่เกี่ยวข้องกับลูกค้านี้
$related_quotations = fetchAll("
    SELECT id, quotation_no, total_amount, currency, status
    FROM quotations 
    WHERE customer_id IN (?, ?) AND status = 'accepted'
    ORDER BY created_at DESC
", [$job['shipper_id'], $job['consignee_id']]);

// ตั้งค่าหน้า
$custom_page_title = "Job Selling - {$job['job_no']}";
$breadcrumb = [
    ['name' => 'Jobs', 'url' => 'jobs.php'],
    ['name' => $job['job_no'], 'url' => "jobs_view.php?id={$job_id}"],
    ['name' => 'Selling']
];

$page_header = true;
$page_subtitle = "Manage selling prices for job {$job['job_no']} - {$job['shipper_name']} to {$job['consignee_name']}";

$page_actions = "
    <a href='jobs_view.php?id={$job_id}' class='btn btn-outline-secondary'>
        <i class='fas fa-arrow-left'></i> Back to Job
    </a>
    <a href='job_costs.php?job_id={$job_id}' class='btn btn-warning'>
        <i class='fas fa-calculator'></i> Manage Costs
    </a>
";

// เพิ่ม CSS สำหรับหน้านี้
$additional_css = "
<style>
.selling-summary {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 30px;
}
.profit-summary {
    background: linear-gradient(135deg, #007bff 0%, #6f42c1 100%);
    color: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 30px;
}
.selling-item-card {
    border: 1px solid #e9ecef;
    border-radius: 10px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}
.selling-item-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.selling-type-badge {
    font-size: 0.8rem;
    padding: 4px 8px;
}
.currency-display {
    font-family: monospace;
    font-weight: bold;
}
.profit-positive {
    color: #28a745;
    font-weight: bold;
}
.profit-negative {
    color: #dc3545;
    font-weight: bold;
}
.profit-neutral {
    color: #6c757d;
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
function editSelling(sellingData) {
    document.getElementById('edit_selling_id').value = sellingData.id;
    document.getElementById('edit_customer_id').value = sellingData.customer_id || '';
    document.getElementById('edit_selling_type').value = sellingData.selling_type;
    document.getElementById('edit_description').value = sellingData.description;
    document.getElementById('edit_currency').value = sellingData.currency;
    document.getElementById('edit_exchange_rate').value = sellingData.exchange_rate;
    document.getElementById('edit_amount').value = sellingData.amount;
    document.getElementById('edit_remark').value = sellingData.remark || '';
    
    new bootstrap.Modal(document.getElementById('editSellingModal')).show();
}

// ยืนยันการลบ
function confirmDelete(sellingId, description) {
    if (confirm('Are you sure you want to delete this selling item?\\n\\n' + description + '\\n\\nThis action cannot be undone.')) {
        document.getElementById('delete_selling_id').value = sellingId;
        document.getElementById('deleteSellingForm').submit();
    }
}

// คัดลอกจากใบเสนอราคา
function copyFromQuotation() {
    const quotationId = document.getElementById('quotation_select').value;
    if (!quotationId) {
        alert('Please select a quotation first');
        return;
    }
    
    if (confirm('This will copy all items from the selected quotation. Continue?')) {
        document.getElementById('copy_quotation_id').value = quotationId;
        document.getElementById('copyQuotationForm').submit();
    }
}
";

include 'includes/header.php';
?>

<!-- Selling Summary -->
<div class="selling-summary">
    <div class="row">
        <div class="col-md-8">
            <h5><i class="fas fa-dollar-sign me-2"></i>Selling Summary for Job: <?php echo htmlspecialchars($job['job_no']); ?></h5>
            <p class="mb-2">
                <strong>Route:</strong> <?php echo htmlspecialchars($job['origin'] . ' → ' . $job['destination']); ?><br>
                <strong>Service:</strong> <?php echo ucfirst(str_replace('_', ' ', $job['job_type'])); ?> 
                (<?php echo ucfirst(str_replace('_', ' ', $job['service_type'])); ?>)
            </p>
        </div>
        <div class="col-md-4 text-md-end">
            <h3 class="mb-0"><?php echo formatMoney($total_selling_thb, 'THB'); ?></h3>
            <small>Total Selling</small><br>
            <small class="opacity-75"><?php echo count($job_selling); ?> selling items</small>
        </div>
    </div>
</div>

<!-- Profit/Loss Summary -->
<div class="profit-summary">
    <div class="row">
        <div class="col-md-3">
            <h6>Total Cost</h6>
            <h5><?php echo formatMoney($total_cost_thb, 'THB'); ?></h5>
        </div>
        <div class="col-md-3">
            <h6>Total Selling</h6>
            <h5><?php echo formatMoney($total_selling_thb, 'THB'); ?></h5>
        </div>
        <div class="col-md-3">
            <h6>Profit/Loss</h6>
            <h4 class="<?php echo $profit_loss > 0 ? 'profit-positive' : ($profit_loss < 0 ? 'profit-negative' : 'profit-neutral'); ?>">
                <?php echo formatMoney($profit_loss, 'THB'); ?>
            </h4>
        </div>
        <div class="col-md-3">
            <h6>Profit Margin</h6>
            <h4 class="<?php echo $profit_margin > 0 ? 'profit-positive' : ($profit_margin < 0 ? 'profit-negative' : 'profit-neutral'); ?>">
                <?php echo number_format($profit_margin, 1); ?>%
            </h4>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Selling Items</h4>
    <div>
        <?php if (!empty($related_quotations)): ?>
            <div class="btn-group me-2">
                <select id="quotation_select" class="form-select" style="max-width: 200px;">
                    <option value="">Select Quotation</option>
                    <?php foreach ($related_quotations as $quotation): ?>
                        <option value="<?php echo $quotation['id']; ?>">
                            <?php echo $quotation['quotation_no']; ?> 
                            (<?php echo formatMoney($quotation['total_amount'], $quotation['currency']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="btn btn-info me-2" onclick="copyFromQuotation()">
                <i class="fas fa-copy"></i> Copy from Quotation
            </button>
        <?php endif; ?>
        
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSellingModal">
            <i class="fas fa-plus"></i> Add Selling Item
        </button>
    </div>
</div>

<!-- Selling Items List -->
<div class="row">
    <?php if (empty($job_selling)): ?>
        <div class="col-12">
            <div class="card text-center">
                <div class="card-body py-5">
                    <i class="fas fa-dollar-sign fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Selling Items Yet</h5>
                    <p class="text-muted">Start by adding selling items for this job.</p>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSellingModal">
                        <i class="fas fa-plus"></i> Add First Selling Item
                    </button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($job_selling as $selling): ?>
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="selling-item-card card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge selling-type-badge bg-<?php echo getSellingTypeBadgeColor($selling['selling_type']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $selling['selling_type'])); ?>
                            </span>
                            <?php if ($selling['customer_name']): ?>
                                <small class="text-muted">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($selling['customer_name']); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        
                        <h6 class="card-title"><?php echo htmlspecialchars($selling['description']); ?></h6>
                        
                        <div class="currency-display mb-2">
                            <?php echo formatMoney($selling['amount'], $selling['currency']); ?>
                            <?php if ($selling['currency'] !== 'THB'): ?>
                                <br><small class="text-muted">
                                    @ <?php echo number_format($selling['exchange_rate'], 4); ?> = 
                                    <?php echo formatMoney($selling['amount_thb'], 'THB'); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($selling['remark']): ?>
                            <p class="text-muted mb-2">
                                <small><i class="fas fa-comment"></i> <?php echo htmlspecialchars($selling['remark']); ?></small>
                            </p>
                        <?php endif; ?>
                        
                        <div class="mt-auto">
                            <button type="button" class="btn btn-sm btn-outline-success" 
                                    onclick="editSelling(<?php echo htmlspecialchars(json_encode($selling)); ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            
                            <?php if (hasPermission('manager')): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                        onclick="confirmDelete(<?php echo $selling['id']; ?>, '<?php echo htmlspecialchars($selling['description']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-light">
                        <small class="text-muted">
                            <i class="fas fa-clock"></i> <?php echo formatDateThai($selling['created_at'], 'd/m/Y H:i'); ?>
                        </small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Add Selling Modal -->
<div class="modal fade" id="addSellingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_selling">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add Selling Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Customer</label>
                                <select name="customer_id" class="form-select">
                                    <option value="">-- Select Customer --</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo $customer['id']; ?>" 
                                                <?php echo ($customer['id'] == $job['shipper_id'] || $customer['id'] == $job['consignee_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($customer['customer_code'] . ' - ' . $customer['company_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Selling Type <span class="text-danger">*</span></label>
                                <select name="selling_type" class="form-select" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="freight">Freight</option>
                                    <option value="local_charge">Local Charge</option>
                                    <option value="customs">Customs</option>
                                    <option value="trucking">Trucking</option>
                                    <option value="documentation">Documentation</option>
                                    <option value="service_fee">Service Fee</option>
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
                    
                    <div class="mb-3">
                        <label class="form-label">Remark</label>
                        <textarea name="remark" id="edit_remark" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Selling Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Forms -->
<form id="deleteSellingForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_selling">
    <input type="hidden" name="selling_id" id="delete_selling_id">
</form>

<form id="copyQuotationForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="copy_from_quotation">
    <input type="hidden" name="quotation_id" id="copy_quotation_id">
</form>

<?php 
include 'includes/footer.php';

// ========================================
// Helper Functions
// ========================================

function getSellingTypeBadgeColor($selling_type) {
    $colors = [
        'freight' => 'primary',
        'local_charge' => 'info',
        'customs' => 'warning',
        'trucking' => 'success',
        'documentation' => 'secondary',
        'service_fee' => 'purple',
        'other' => 'dark'
    ];
    
    return $colors[$selling_type] ?? 'secondary';
}
?>
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
                    
                    <div class="mb-3">
                        <label class="form-label">Remark</label>
                        <textarea name="remark" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Selling Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Selling Modal -->
<div class="modal fade" id="editSellingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_selling">
                <input type="hidden" name="selling_id" id="edit_selling_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Edit Selling Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Customer</label>
                                <select name="customer_id" id="edit_customer_id" class="form-select">
                                    <option value="">-- Select Customer --</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo $customer['id']; ?>">
                                            <?php echo htmlspecialchars($customer['customer_code'] . ' - ' . $customer['company_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Selling Type <span class="text-danger">*</span></label>
                                <select name="selling_type" id="edit_selling_type" class="form-select" required>
                                    <option value="freight">Freight</option>
                                    <option value="local_charge">Local Charge</option>
                                    <option value="customs">Customs</option>
                                    <option value="trucking">Trucking</option>
                                    <option value="documentation">Documentation</option>
                                    <option value="service_fee">Service Fee</option>
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