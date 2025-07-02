<?php
// =====================================================
// invoices_add.php - Create New Invoice
// =====================================================

// เริ่มต้น session และเรียกใช้ functions
require_once 'includes/functions.php';

// ตรวจสอบสิทธิ์ - ต้องเป็น staff ขึ้นไป
requirePermission('staff');

// ตัวแปรสำหรับเก็บข้อมูล
$errors = [];
$form_data = [
    'customer_id' => '',
    'job_id' => '',
    'invoice_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+30 days')),
    'currency' => 'THB',
    'vat_rate' => 7.00,
    'remark' => ''
];

// จัดการการส่งข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับข้อมูลจากฟอร์ม
    $form_data['customer_id'] = (int)$_POST['customer_id'];
    $form_data['job_id'] = isset($_POST['job_id']) ? (int)$_POST['job_id'] : null;
    $form_data['invoice_date'] = cleanInput($_POST['invoice_date']);
    $form_data['due_date'] = cleanInput($_POST['due_date']);
    $form_data['currency'] = cleanInput($_POST['currency']);
    $form_data['vat_rate'] = (float)$_POST['vat_rate'];
    $form_data['remark'] = cleanInput($_POST['remark']);
    
    // รับข้อมูลรายการสินค้า/บริการ
    $invoice_items = [];
    if (isset($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $item) {
            if (!empty($item['description']) && !empty($item['unit_price'])) {
                $invoice_items[] = [
                    'description' => cleanInput($item['description']),
                    'quantity' => (float)$item['quantity'],
                    'unit_price' => (float)$item['unit_price'],
                    'amount' => (float)$item['quantity'] * (float)$item['unit_price']
                ];
            }
        }
    }
    
    // Validation
    if (empty($form_data['customer_id'])) {
        $errors[] = 'Please select a customer';
    }
    
    if (empty($form_data['invoice_date'])) {
        $errors[] = 'Invoice date is required';
    }
    
    if (empty($form_data['due_date'])) {
        $errors[] = 'Due date is required';
    }
    
    if (strtotime($form_data['due_date']) < strtotime($form_data['invoice_date'])) {
        $errors[] = 'Due date cannot be earlier than invoice date';
    }
    
    if (empty($invoice_items)) {
        $errors[] = 'At least one invoice item is required';
    }
    
    // ตรวจสอบว่าลูกค้ามีอยู่จริง
    if ($form_data['customer_id']) {
        $customer = fetchOne("SELECT * FROM customers WHERE id = ? AND status = 'active'", [$form_data['customer_id']]);
        if (!$customer) {
            $errors[] = 'Selected customer not found or inactive';
        }
    }
    
    // ตรวจสอบ Job (ถ้ามี)
    if ($form_data['job_id']) {
        $job = fetchOne("SELECT * FROM jobs WHERE id = ?", [$form_data['job_id']]);
        if (!$job) {
            $errors[] = 'Selected job not found';
        }
    }
    
    // หากไม่มี error ให้บันทึกข้อมูล
    if (empty($errors)) {
        try {
            beginTransaction();
            
            // คำนวณยอดรวม
            $subtotal = array_sum(array_column($invoice_items, 'amount'));
            $vat_amount = $subtotal * ($form_data['vat_rate'] / 100);
            $total_amount = $subtotal + $vat_amount;
            
            // สร้างเลขที่ใบแจ้งหนี้
            $invoice_no = generateInvoiceNumber();
            
            // บันทึกใบแจ้งหนี้
            $invoice_result = execute("
                INSERT INTO invoices 
                (invoice_no, job_id, customer_id, invoice_date, due_date, subtotal, 
                 vat_rate, vat_amount, total_amount, currency, payment_status, 
                 paid_amount, remark, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0.00, ?, ?)
            ", [
                $invoice_no, $form_data['job_id'], $form_data['customer_id'], 
                $form_data['invoice_date'], $form_data['due_date'], $subtotal,
                $form_data['vat_rate'], $vat_amount, $total_amount, $form_data['currency'],
                $form_data['remark'], $_SESSION['user_id']
            ]);
            
            if (!$invoice_result) {
                throw new Exception('Failed to create invoice');
            }
            
            $invoice_id = lastInsertId();
            
            // บันทึกรายการใบแจ้งหนี้
            foreach ($invoice_items as $item) {
                $item_result = execute("
                    INSERT INTO invoice_items 
                    (invoice_id, description, quantity, unit_price, amount)
                    VALUES (?, ?, ?, ?, ?)
                ", [
                    $invoice_id, $item['description'], $item['quantity'], 
                    $item['unit_price'], $item['amount']
                ]);
                
                if (!$item_result) {
                    throw new Exception('Failed to create invoice item');
                }
            }
            
            commit();
            
            $_SESSION['success_message'] = "Invoice {$invoice_no} created successfully";
            redirect("invoices_view.php?id={$invoice_id}");
            
        } catch (Exception $e) {
            rollback();
            $errors[] = 'Error creating invoice: ' . $e->getMessage();
        }
    }
}

// ดึงข้อมูลลูกค้า
$customers = fetchAll("
    SELECT id, customer_code, company_name, credit_term
    FROM customers 
    WHERE status = 'active' 
    ORDER BY company_name
");

// ดึงข้อมูล Jobs ที่ยังไม่มีใบแจ้งหนี้
$jobs = fetchAll("
    SELECT j.*, c.company_name as customer_name
    FROM jobs j
    LEFT JOIN customers c ON j.shipper_id = c.id
    WHERE j.status IN ('completed', 'delivered')
    AND j.id NOT IN (SELECT job_id FROM invoices WHERE job_id IS NOT NULL)
    ORDER BY j.created_at DESC
");

// ตั้งค่าหน้า
$custom_page_title = "Create New Invoice";
$breadcrumb = [
    ['name' => 'Invoices', 'url' => 'invoices.php'],
    ['name' => 'Create Invoice']
];

$page_header = true;
$page_subtitle = "Create a new invoice for customers";

$page_actions = "
    <a href='invoices.php' class='btn btn-outline-secondary'>
        <i class='fas fa-arrow-left'></i> Back to Invoices
    </a>
";

// เพิ่ม CSS สำหรับหน้านี้
$additional_css = "
<style>
.invoice-form {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.section-header {
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 10px;
    margin-bottom: 20px;
    color: #495057;
}

.invoice-items-table {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    margin: 20px 0;
}

.item-row {
    background: white;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    border: 1px solid #e9ecef;
}

.totals-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 20px;
    margin-top: 20px;
}

.form-floating {
    margin-bottom: 1rem;
}

.btn-add-item {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
    color: white;
}

.btn-remove-item {
    background: #dc3545;
    border: none;
    color: white;
}

.currency-input {
    font-family: monospace;
    font-weight: bold;
}

.preview-section {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    margin-top: 20px;
}
</style>
";

// เพิ่ม JavaScript สำหรับหน้านี้
$page_js = "
let itemCount = 1;

// เพิ่มรายการใหม่
function addInvoiceItem() {
    const container = document.getElementById('invoiceItems');
    const newItem = createItemRow(itemCount);
    container.appendChild(newItem);
    itemCount++;
    calculateTotals();
}

// สร้างแถวรายการใหม่
function createItemRow(index) {
    const div = document.createElement('div');
    div.className = 'item-row';
    div.innerHTML = `
        <div class=\"row align-items-end\">
            <div class=\"col-md-4\">
                <div class=\"form-floating\">
                    <input type=\"text\" class=\"form-control\" name=\"items[' + index + '][description]\" 
                           id=\"description_' + index + '\" placeholder=\"Description\" required>
                    <label for=\"description_' + index + '\">Description</label>
                </div>
            </div>
            <div class=\"col-md-2\">
                <div class=\"form-floating\">
                    <input type=\"number\" class=\"form-control\" name=\"items[' + index + '][quantity]\" 
                           id=\"quantity_' + index + '\" step=\"0.01\" value=\"1.00\" 
                           placeholder=\"Quantity\" onchange=\"calculateItemAmount(' + index + ')\" required>
                    <label for=\"quantity_' + index + '\">Quantity</label>
                </div>
            </div>
            <div class=\"col-md-2\">
                <div class=\"form-floating\">
                    <input type=\"number\" class=\"form-control currency-input\" name=\"items[' + index + '][unit_price]\" 
                           id=\"unit_price_' + index + '\" step=\"0.01\" 
                           placeholder=\"Unit Price\" onchange=\"calculateItemAmount(' + index + ')\" required>
                    <label for=\"unit_price_' + index + '\">Unit Price</label>
                </div>
            </div>
            <div class=\"col-md-3\">
                <div class=\"form-floating\">
                    <input type=\"number\" class=\"form-control currency-input item-amount\" 
                           id=\"amount_' + index + '\" step=\"0.01\" readonly
                           placeholder=\"Amount\">
                    <label for=\"amount_' + index + '\">Amount</label>
                </div>
            </div>
            <div class=\"col-md-1\">
                <button type=\"button\" class=\"btn btn-remove-item btn-sm\" onclick=\"removeItem(this)\" title=\"Remove Item\">
                    <i class=\"fas fa-trash\"></i>
                </button>
            </div>
        </div>
    `;
    return div;
}

// ลบรายการ
function removeItem(button) {
    if (document.querySelectorAll('.item-row').length > 1) {
        button.closest('.item-row').remove();
        calculateTotals();
    } else {
        alert('At least one item is required');
    }
}

// คำนวณยอดของแต่ละรายการ
function calculateItemAmount(index) {
    const quantity = parseFloat(document.getElementById('quantity_' + index).value) || 0;
    const unitPrice = parseFloat(document.getElementById('unit_price_' + index).value) || 0;
    const amount = quantity * unitPrice;
    
    document.getElementById('amount_' + index).value = amount.toFixed(2);
    calculateTotals();
}

// คำนวณยอดรวม
function calculateTotals() {
    const amounts = document.querySelectorAll('.item-amount');
    let subtotal = 0;
    
    amounts.forEach(input => {
        subtotal += parseFloat(input.value) || 0;
    });
    
    const vatRate = parseFloat(document.getElementById('vat_rate').value) || 0;
    const vatAmount = subtotal * (vatRate / 100);
    const totalAmount = subtotal + vatAmount;
    
    document.getElementById('subtotal_display').textContent = formatCurrency(subtotal);
    document.getElementById('vat_amount_display').textContent = formatCurrency(vatAmount);
    document.getElementById('total_amount_display').textContent = formatCurrency(totalAmount);
    
    // Update hidden inputs for form submission
    document.getElementById('calculated_subtotal').value = subtotal.toFixed(2);
    document.getElementById('calculated_vat_amount').value = vatAmount.toFixed(2);
    document.getElementById('calculated_total').value = totalAmount.toFixed(2);
}

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

// เมื่อเปลี่ยนลูกค้า
function onCustomerChange() {
    const customerId = document.getElementById('customer_id').value;
    const customerSelect = document.getElementById('customer_id');
    const selectedOption = customerSelect.options[customerSelect.selectedIndex];
    
    if (customerId && selectedOption.dataset.creditTerm) {
        const creditTerm = parseInt(selectedOption.dataset.creditTerm);
        const invoiceDate = new Date(document.getElementById('invoice_date').value);
        const dueDate = new Date(invoiceDate);
        dueDate.setDate(dueDate.getDate() + creditTerm);
        
        document.getElementById('due_date').value = dueDate.toISOString().split('T')[0];
    }
    
    // Load jobs for this customer
    loadCustomerJobs(customerId);
}

// โหลด Jobs ของลูกค้า
function loadCustomerJobs(customerId) {
    if (!customerId) {
        document.getElementById('job_id').innerHTML = '<option value=\"\">-- Select Job (Optional) --</option>';
        return;
    }
    
    fetch('ajax/get_customer_jobs.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            customer_id: customerId,
            include_completed: true,
            include_invoiced: false
        })
    })
    .then(response => response.json())
    .then(data => {
        const jobSelect = document.getElementById('job_id');
        jobSelect.innerHTML = '<option value=\"\">-- Select Job (Optional) --</option>';
        
        if (data.success && data.jobs) {
            data.jobs.forEach(job => {
                const option = document.createElement('option');
                option.value = job.id;
                option.textContent = job.job_no + ' - ' + job.route;
                jobSelect.appendChild(option);
            });
        }
    })
    .catch(error => {
        console.error('Error loading jobs:', error);
    });
}

// Copy selling items from job
function copyFromJob() {
    const jobId = document.getElementById('job_id').value;
    if (!jobId) {
        alert('Please select a job first');
        return;
    }
    
    if (confirm('This will replace all current items with job selling items. Continue?')) {
        fetch('ajax/get_job_selling.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                job_id: jobId,
                format_for_invoice: true
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.selling_items) {
                // Clear current items
                document.getElementById('invoiceItems').innerHTML = '';
                itemCount = 0;
                
                // Add items from job
                data.selling_items.forEach(item => {
                    const newItem = createItemRow(itemCount);
                    document.getElementById('invoiceItems').appendChild(newItem);
                    
                    // Fill data
                    document.getElementById('description_' + itemCount).value = item.description;
                    document.getElementById('quantity_' + itemCount).value = item.suggested_quantity || '1.00';
                    document.getElementById('unit_price_' + itemCount).value = item.suggested_unit_price || item.amount_thb;
                    
                    calculateItemAmount(itemCount);
                    itemCount++;
                });
                
                if (data.selling_items.length === 0) {
                    addInvoiceItem(); // Add at least one empty item
                    alert('No selling items found for this job');
                } else {
                    alert('Successfully copied ' + data.selling_items.length + ' items from job');
                }
            } else {
                alert('No selling items found for this job or error occurred');
                if (document.querySelectorAll('.item-row').length === 0) {
                    addInvoiceItem();
                }
            }
        })
        .catch(error => {
            console.error('Error loading job items:', error);
            alert('Error loading job items');
        });
    }
}

// Preview function
function previewInvoice() {
    alert('Preview functionality will be implemented');
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Add first item if none exists
    if (document.querySelectorAll('.item-row').length === 0) {
        addInvoiceItem();
    }
    
    // Calculate totals on load
    calculateTotals();
    
    // Set up event listeners
    document.getElementById('vat_rate').addEventListener('change', calculateTotals);
    document.getElementById('customer_id').addEventListener('change', onCustomerChange);
});
";

include 'includes/header.php';
?>

<!-- Invoice Creation Form -->
<div class="invoice-form">
    <form method="POST" id="invoiceForm">
        
        <!-- Display Errors -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <h6><i class="fas fa-exclamation-triangle"></i> Please correct the following errors:</h6>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Invoice Header Information -->
        <div class="row">
            <div class="col-md-6">
                <h5 class="section-header">
                    <i class="fas fa-user"></i> Customer Information
                </h5>
                
                <div class="form-floating mb-3">
                    <select name="customer_id" id="customer_id" class="form-select" required>
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>" 
                                    data-credit-term="<?php echo $customer['credit_term']; ?>"
                                    <?php echo $form_data['customer_id'] == $customer['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['customer_code'] . ' - ' . $customer['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="customer_id">Customer <span class="text-danger">*</span></label>
                </div>

                <div class="form-floating mb-3">
                    <select name="job_id" id="job_id" class="form-select">
                        <option value="">-- Select Job (Optional) --</option>
                        <?php foreach ($jobs as $job): ?>
                            <option value="<?php echo $job['id']; ?>"
                                    <?php echo $form_data['job_id'] == $job['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($job['job_no'] . ' - ' . $job['origin'] . ' → ' . $job['destination']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="job_id">Related Job</label>
                </div>

                <button type="button" class="btn btn-info btn-sm" onclick="copyFromJob()">
                    <i class="fas fa-copy"></i> Copy Items from Job
                </button>
            </div>

            <div class="col-md-6">
                <h5 class="section-header">
                    <i class="fas fa-calendar"></i> Invoice Details
                </h5>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="date" name="invoice_date" id="invoice_date" class="form-control" 
                                   value="<?php echo $form_data['invoice_date']; ?>" required>
                            <label for="invoice_date">Invoice Date <span class="text-danger">*</span></label>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="date" name="due_date" id="due_date" class="form-control" 
                                   value="<?php echo $form_data['due_date']; ?>" required>
                            <label for="due_date">Due Date <span class="text-danger">*</span></label>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <select name="currency" id="currency" class="form-select">
                                <option value="THB" <?php echo $form_data['currency'] == 'THB' ? 'selected' : ''; ?>>THB</option>
                                <option value="USD" <?php echo $form_data['currency'] == 'USD' ? 'selected' : ''; ?>>USD</option>
                                <option value="EUR" <?php echo $form_data['currency'] == 'EUR' ? 'selected' : ''; ?>>EUR</option>
                            </select>
                            <label for="currency">Currency</label>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="number" name="vat_rate" id="vat_rate" class="form-control" 
                                   step="0.01" value="<?php echo $form_data['vat_rate']; ?>" min="0" max="100">
                            <label for="vat_rate">VAT Rate (%)</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoice Items -->
        <div class="invoice-items-table">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="section-header mb-0">
                    <i class="fas fa-list"></i> Invoice Items
                </h5>
                <button type="button" class="btn btn-add-item btn-sm" onclick="addInvoiceItem()">
                    <i class="fas fa-plus"></i> Add Item
                </button>
            </div>

            <div id="invoiceItems">
                <!-- Items will be added dynamically -->
            </div>
        </div>

        <!-- Totals Section -->
        <div class="totals-section">
            <div class="row">
                <div class="col-md-8">
                    <div class="form-floating">
                        <textarea name="remark" id="remark" class="form-control" 
                                  placeholder="Additional notes or remarks" style="height: 100px;"><?php echo htmlspecialchars($form_data['remark']); ?></textarea>
                        <label for="remark">Remarks</label>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <table class="table table-borderless text-white">
                        <tr>
                            <td><strong>Subtotal:</strong></td>
                            <td class="text-end"><strong><span id="subtotal_display">0.00</span> <span id="currency_display">THB</span></strong></td>
                        </tr>
                        <tr>
                            <td><strong>VAT:</strong></td>
                            <td class="text-end"><strong><span id="vat_amount_display">0.00</span> <span id="currency_display2">THB</span></strong></td>
                        </tr>
                        <tr class="border-top">
                            <td><h5><strong>Total:</strong></h5></td>
                            <td class="text-end"><h5><strong><span id="total_amount_display">0.00</span> <span id="currency_display3">THB</span></strong></h5></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Hidden inputs for calculated values -->
        <input type="hidden" id="calculated_subtotal" name="calculated_subtotal">
        <input type="hidden" id="calculated_vat_amount" name="calculated_vat_amount">
        <input type="hidden" id="calculated_total" name="calculated_total">

        <!-- Submit Buttons -->
        <div class="row mt-4">
            <div class="col-md-12 text-center">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Create Invoice
                </button>
                
                <button type="button" class="btn btn-outline-secondary btn-lg ms-3" onclick="window.history.back()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                
                <button type="button" class="btn btn-info btn-lg ms-3" onclick="previewInvoice()">
                    <i class="fas fa-eye"></i> Preview
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Invoice Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="previewContent">
                    <!-- Preview content will be generated here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('invoiceForm').submit()">
                    <i class="fas fa-save"></i> Create Invoice
                </button>
            </div>
        </div>
    </div>
</div>

<?php 
include 'includes/footer.php';

// ========================================
// Helper Functions
// ========================================

/**
 * Generate invoice number
 * @return string
 */
function generateInvoiceNumber() {
    $year = date('y');
    $month = date('m');
    $prefix = "INV{$year}{$month}";
    
    // Find the latest invoice number for this month
    $last_invoice = fetchOne("
        SELECT invoice_no 
        FROM invoices 
        WHERE invoice_no LIKE ? 
        ORDER BY invoice_no DESC 
        LIMIT 1
    ", ["{$prefix}%"]);
    
    if ($last_invoice) {
        $last_number = (int)substr($last_invoice['invoice_no'], -4);
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    
    return $prefix . str_pad($new_number, 4, '0', STR_PAD_LEFT);
}

/**
 * Preview invoice function (would be implemented via AJAX)
 */
?>

<script>
// Preview function
function previewInvoice() {
    // Collect form data
    const formData = new FormData(document.getElementById('invoiceForm'));
    
    // Generate preview content
    let previewHTML = generatePreviewHTML(formData);
    
    document.getElementById('previewContent').innerHTML = previewHTML;
    new bootstrap.Modal(document.getElementById('previewModal')).show();
}

function generatePreviewHTML(formData) {
    // This would generate a preview of the invoice
    // For now, just a simple message
    return '<div class="alert alert-info">Invoice preview functionality would be implemented here</div>';
}

// Update currency displays when currency changes
document.getElementById('currency').addEventListener('change', function() {
    const currency = this.value;
    document.getElementById('currency_display').textContent = currency;
    document.getElementById('currency_display2').textContent = currency;
    document.getElementById('currency_display3').textContent = currency;
});
</script>