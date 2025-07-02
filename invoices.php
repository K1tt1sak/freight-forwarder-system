<?php
// =====================================================
// invoices.php - Invoice Management
// =====================================================

// เริ่มต้น session และเรียกใช้ functions
require_once 'includes/functions.php';

// ตรวจสอบสิทธิ์ - ต้องเป็น staff ขึ้นไป
requirePermission('staff');

// รับพารามิเตอร์สำหรับการค้นหาและกรอง
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? cleanInput($_GET['status']) : '';
$customer_filter = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$date_from = isset($_GET['date_from']) ? cleanInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? cleanInput($_GET['date_to']) : '';
$sort_by = isset($_GET['sort_by']) ? cleanInput($_GET['sort_by']) : 'created_at';
$sort_order = isset($_GET['sort_order']) ? cleanInput($_GET['sort_order']) : 'DESC';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;

// จัดการ bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulk_action = cleanInput($_POST['bulk_action']);
    $selected_invoices = isset($_POST['selected_invoices']) ? $_POST['selected_invoices'] : [];
    
    if (!empty($selected_invoices) && hasPermission('manager')) {
        switch ($bulk_action) {
            case 'mark_sent':
                $updated = 0;
                foreach ($selected_invoices as $invoice_id) {
                    if (execute("UPDATE invoices SET payment_status = 'pending' WHERE id = ? AND payment_status = 'draft'", [$invoice_id])) {
                        $updated++;
                    }
                }
                $_SESSION['success_message'] = "Marked {$updated} invoices as sent";
                break;
                
            case 'mark_overdue':
                $updated = 0;
                foreach ($selected_invoices as $invoice_id) {
                    if (execute("UPDATE invoices SET payment_status = 'overdue' WHERE id = ? AND payment_status IN ('pending', 'partial') AND due_date < CURDATE()", [$invoice_id])) {
                        $updated++;
                    }
                }
                $_SESSION['success_message'] = "Marked {$updated} invoices as overdue";
                break;
                
            case 'export_pdf':
                // Redirect to bulk PDF export
                $invoice_ids = implode(',', $selected_invoices);
                redirect("invoices_export.php?type=bulk_pdf&ids={$invoice_ids}");
                break;
        }
    }
    
    redirect('invoices.php?' . $_SERVER['QUERY_STRING']);
}

// สร้าง WHERE clause สำหรับการค้นหา
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(i.invoice_no LIKE ? OR c.company_name LIKE ? OR j.job_no LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($status_filter)) {
    $where_conditions[] = "i.payment_status = ?";
    $params[] = $status_filter;
}

if (!empty($customer_filter)) {
    $where_conditions[] = "i.customer_id = ?";
    $params[] = $customer_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "i.invoice_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "i.invoice_date <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// นับจำนวนรายการทั้งหมด
$total_sql = "
    SELECT COUNT(*) as total
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    LEFT JOIN jobs j ON i.job_id = j.id
    {$where_clause}
";

$total_result = fetchOne($total_sql, $params);
$total_records = $total_result['total'];
$total_pages = ceil($total_records / $per_page);

// คำนวณ OFFSET
$offset = ($page - 1) * $per_page;

// ดึงข้อมูลใบแจ้งหนี้
$valid_sort_columns = [
    'invoice_no' => 'i.invoice_no',
    'invoice_date' => 'i.invoice_date',
    'due_date' => 'i.due_date',
    'customer_name' => 'c.company_name',
    'total_amount' => 'i.total_amount',
    'payment_status' => 'i.payment_status',
    'created_at' => 'i.created_at'
];

$sort_column = isset($valid_sort_columns[$sort_by]) ? $valid_sort_columns[$sort_by] : 'i.created_at';
$sort_direction = in_array(strtoupper($sort_order), ['ASC', 'DESC']) ? strtoupper($sort_order) : 'DESC';

$invoices_sql = "
    SELECT 
        i.*,
        c.company_name as customer_name,
        c.customer_code,
        j.job_no,
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
    {$where_clause}
    ORDER BY {$sort_column} {$sort_direction}
    LIMIT {$per_page} OFFSET {$offset}
";

$invoices = fetchAll($invoices_sql, $params);

// ดึงข้อมูลสำหรับ dropdown filters
$customers = fetchAll("
    SELECT DISTINCT c.id, c.customer_code, c.company_name
    FROM customers c
    INNER JOIN invoices i ON c.id = i.customer_id
    ORDER BY c.company_name
");

// คำนวณสถิติสำหรับ dashboard
$stats = fetchOne("
    SELECT 
        COUNT(*) as total_invoices,
        SUM(total_amount) as total_value,
        SUM(paid_amount) as total_paid,
        SUM(total_amount - paid_amount) as total_outstanding,
        COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN payment_status = 'overdue' OR (due_date < CURDATE() AND payment_status IN ('pending', 'partial')) THEN 1 END) as overdue_count,
        COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_count
    FROM invoices i
    {$where_clause}
", $params);

// ตั้งค่าหน้า
$custom_page_title = "Invoice Management";
$breadcrumb = [
    ['name' => 'Invoices']
];

$page_header = true;
$page_subtitle = "Manage and track all invoices";

$page_actions = "
    <a href='invoices_add.php' class='btn btn-primary'>
        <i class='fas fa-plus'></i> Create Invoice
    </a>
    <div class='btn-group ms-2'>
        <button type='button' class='btn btn-outline-secondary dropdown-toggle' data-bs-toggle='dropdown'>
            <i class='fas fa-download'></i> Export
        </button>
        <ul class='dropdown-menu'>
            <li><a class='dropdown-item' href='invoices_export.php?type=excel'><i class='fas fa-file-excel'></i> Export to Excel</a></li>
            <li><a class='dropdown-item' href='invoices_export.php?type=pdf'><i class='fas fa-file-pdf'></i> Export to PDF</a></li>
            <li><a class='dropdown-item' href='invoices_export.php?type=csv'><i class='fas fa-file-csv'></i> Export to CSV</a></li>
        </ul>
    </div>
";

// เพิ่ม CSS สำหรับหน้านี้
$additional_css = "
<style>
.invoice-stats {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 30px;
}

.stat-card {
    text-align: center;
    padding: 10px;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.invoice-filters {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}

.overdue-row {
    background-color: #fff5f5 !important;
    border-left: 4px solid #dc3545;
}

.partial-payment-row {
    background-color: #fff8e1 !important;
    border-left: 4px solid #ff9800;
}

.paid-row {
    background-color: #f3e5f5 !important;
    border-left: 4px solid #4caf50;
}

.invoice-amount {
    font-family: monospace;
    font-weight: bold;
}

.days-overdue {
    font-size: 0.8rem;
    color: #dc3545;
    font-weight: bold;
}

.bulk-actions {
    background: #e9ecef;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 20px;
    display: none;
}

.table-responsive {
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}
</style>
";

// เพิ่ม JavaScript สำหรับหน้านี้
$page_js = "
// Bulk actions functionality
function toggleBulkActions() {
    const checkboxes = document.querySelectorAll('input[name=\"selected_invoices[]\"]');
    const bulkActions = document.getElementById('bulkActions');
    const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
    
    if (checkedCount > 0) {
        bulkActions.style.display = 'block';
        document.getElementById('selectedCount').textContent = checkedCount;
    } else {
        bulkActions.style.display = 'none';
    }
}

// Select all checkboxes
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('input[name=\"selected_invoices[]\"]');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    
    toggleBulkActions();
}

// Clear filters
function clearFilters() {
    document.getElementById('search').value = '';
    document.getElementById('status_filter').value = '';
    document.getElementById('customer_filter').value = '';
    document.getElementById('date_from').value = '';
    document.getElementById('date_to').value = '';
    document.getElementById('filterForm').submit();
}

// Auto-submit form on filter change
function autoSubmitFilter() {
    document.getElementById('filterForm').submit();
}

// Confirm bulk action
function confirmBulkAction() {
    const action = document.getElementById('bulkActionSelect').value;
    const count = document.getElementById('selectedCount').textContent;
    
    if (!action) {
        alert('Please select an action');
        return false;
    }
    
    const messages = {
        'mark_sent': 'mark as sent',
        'mark_overdue': 'mark as overdue',
        'export_pdf': 'export to PDF'
    };
    
    return confirm('Are you sure you want to ' + messages[action] + ' ' + count + ' selected invoices?');
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to checkboxes
    document.querySelectorAll('input[name=\"selected_invoices[]\"]').forEach(checkbox => {
        checkbox.addEventListener('change', toggleBulkActions);
    });
    
    // Initialize DataTable-like features
    initializeTable();
});

function initializeTable() {
    // Add row hover effects and click handlers
    document.querySelectorAll('.invoice-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (e.target.type !== 'checkbox') {
                const invoiceId = this.dataset.invoiceId;
                window.location.href = 'invoices_view.php?id=' + invoiceId;
            }
        });
    });
}
";

include 'includes/header.php';
?>

<!-- Invoice Statistics -->
<div class="invoice-stats">
    <div class="row">
        <div class="col-md-2 stat-card">
            <div class="stat-value"><?php echo number_format($stats['total_invoices']); ?></div>
            <div>Total Invoices</div>
        </div>
        <div class="col-md-2 stat-card">
            <div class="stat-value"><?php echo formatMoney($stats['total_value'], 'THB'); ?></div>
            <div>Total Value</div>
        </div>
        <div class="col-md-2 stat-card">
            <div class="stat-value"><?php echo formatMoney($stats['total_paid'], 'THB'); ?></div>
            <div>Total Paid</div>
        </div>
        <div class="col-md-2 stat-card">
            <div class="stat-value text-warning"><?php echo formatMoney($stats['total_outstanding'], 'THB'); ?></div>
            <div>Outstanding</div>
        </div>
        <div class="col-md-2 stat-card">
            <div class="stat-value text-info"><?php echo number_format($stats['pending_count']); ?></div>
            <div>Pending</div>
        </div>
        <div class="col-md-2 stat-card">
            <div class="stat-value text-danger"><?php echo number_format($stats['overdue_count']); ?></div>
            <div>Overdue</div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="invoice-filters">
    <form method="GET" id="filterForm" class="row g-3">
        <div class="col-md-3">
            <label class="form-label">Search</label>
            <input type="text" name="search" id="search" class="form-control" 
                   placeholder="Invoice No, Customer, Job No..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        
        <div class="col-md-2">
            <label class="form-label">Status</label>
            <select name="status" id="status_filter" class="form-select" onchange="autoSubmitFilter()">
                <option value="">All Status</option>
                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="partial" <?php echo $status_filter == 'partial' ? 'selected' : ''; ?>>Partial Paid</option>
                <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                <option value="overdue" <?php echo $status_filter == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>
        
        <div class="col-md-2">
            <label class="form-label">Customer</label>
            <select name="customer_id" id="customer_filter" class="form-select" onchange="autoSubmitFilter()">
                <option value="">All Customers</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo $customer['id']; ?>" <?php echo $customer_filter == $customer['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($customer['customer_code'] . ' - ' . $customer['company_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-2">
            <label class="form-label">Date From</label>
            <input type="date" name="date_from" id="date_from" class="form-control" 
                   value="<?php echo $date_from; ?>" onchange="autoSubmitFilter()">
        </div>
        
        <div class="col-md-2">
            <label class="form-label">Date To</label>
            <input type="date" name="date_to" id="date_to" class="form-control" 
                   value="<?php echo $date_to; ?>" onchange="autoSubmitFilter()">
        </div>
        
        <div class="col-md-1">
            <label class="form-label">&nbsp;</label>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        
        <div class="col-md-12">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearFilters()">
                <i class="fas fa-times"></i> Clear Filters
            </button>
            <span class="ms-3 text-muted">
                Showing <?php echo number_format($total_records); ?> invoice(s)
            </span>
        </div>
    </form>
</div>

<!-- Bulk Actions -->
<?php if (hasPermission('manager')): ?>
<div id="bulkActions" class="bulk-actions">
    <form method="POST" onsubmit="return confirmBulkAction()">
        <div class="row align-items-center">
            <div class="col-md-4">
                <strong><span id="selectedCount">0</span> invoices selected</strong>
            </div>
            <div class="col-md-4">
                <select name="bulk_action" id="bulkActionSelect" class="form-select">
                    <option value="">Choose Action...</option>
                    <option value="mark_sent">Mark as Sent</option>
                    <option value="mark_overdue">Mark as Overdue</option>
                    <option value="export_pdf">Export to PDF</option>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">Apply Action</button>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Invoices Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <?php if (hasPermission('manager')): ?>
                        <th width="40">
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                        </th>
                        <?php endif; ?>
                        
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'invoice_no', 'sort_order' => $sort_by == 'invoice_no' && $sort_order == 'ASC' ? 'DESC' : 'ASC'])); ?>" 
                               class="text-decoration-none text-dark">
                                Invoice No
                                <?php if ($sort_by == 'invoice_no'): ?>
                                    <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'customer_name', 'sort_order' => $sort_by == 'customer_name' && $sort_order == 'ASC' ? 'DESC' : 'ASC'])); ?>" 
                               class="text-decoration-none text-dark">
                                Customer
                                <?php if ($sort_by == 'customer_name'): ?>
                                    <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        
                        <th>Job No</th>
                        
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'invoice_date', 'sort_order' => $sort_by == 'invoice_date' && $sort_order == 'ASC' ? 'DESC' : 'ASC'])); ?>" 
                               class="text-decoration-none text-dark">
                                Invoice Date
                                <?php if ($sort_by == 'invoice_date'): ?>
                                    <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'due_date', 'sort_order' => $sort_by == 'due_date' && $sort_order == 'ASC' ? 'DESC' : 'ASC'])); ?>" 
                               class="text-decoration-none text-dark">
                                Due Date
                                <?php if ($sort_by == 'due_date'): ?>
                                    <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        
                        <th class="text-end">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'total_amount', 'sort_order' => $sort_by == 'total_amount' && $sort_order == 'ASC' ? 'DESC' : 'ASC'])); ?>" 
                               class="text-decoration-none text-dark">
                                Amount
                                <?php if ($sort_by == 'total_amount'): ?>
                                    <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        
                        <th class="text-end">Outstanding</th>
                        
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'payment_status', 'sort_order' => $sort_by == 'payment_status' && $sort_order == 'ASC' ? 'DESC' : 'ASC'])); ?>" 
                               class="text-decoration-none text-dark">
                                Status
                                <?php if ($sort_by == 'payment_status'): ?>
                                    <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        
                        <th width="120">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="<?php echo hasPermission('manager') ? '10' : '9'; ?>" class="text-center py-4">
                                <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Invoices Found</h5>
                                <p class="text-muted">No invoices match your search criteria.</p>
                                <a href="invoices_add.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Create First Invoice
                                </a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $invoice): ?>
                            <?php
                            $row_class = '';
                            if ($invoice['is_overdue']) {
                                $row_class = 'overdue-row';
                            } elseif ($invoice['payment_status'] == 'partial') {
                                $row_class = 'partial-payment-row';
                            } elseif ($invoice['payment_status'] == 'paid') {
                                $row_class = 'paid-row';
                            }
                            ?>
                            <tr class="invoice-row <?php echo $row_class; ?>" data-invoice-id="<?php echo $invoice['id']; ?>">
                                <?php if (hasPermission('manager')): ?>
                                <td>
                                    <input type="checkbox" name="selected_invoices[]" value="<?php echo $invoice['id']; ?>">
                                </td>
                                <?php endif; ?>
                                
                                <td>
                                    <strong><?php echo htmlspecialchars($invoice['invoice_no']); ?></strong>
                                    <?php if ($invoice['is_overdue']): ?>
                                        <br><span class="days-overdue">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <?php echo $invoice['days_overdue']; ?> days overdue
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($invoice['customer_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($invoice['customer_code']); ?></small>
                                    </div>
                                </td>
                                
                                <td>
                                    <?php if ($invoice['job_no']): ?>
                                        <a href="jobs_view.php?id=<?php echo $invoice['job_id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($invoice['job_no']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td><?php echo formatDateThai($invoice['invoice_date'], 'd/m/Y'); ?></td>
                                
                                <td>
                                    <?php echo formatDateThai($invoice['due_date'], 'd/m/Y'); ?>
                                    <?php if ($invoice['is_overdue']): ?>
                                        <i class="fas fa-exclamation-circle text-danger ms-1" title="Overdue"></i>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="text-end">
                                    <div class="invoice-amount">
                                        <?php echo formatMoney($invoice['total_amount'], $invoice['currency']); ?>
                                    </div>
                                    <?php if ($invoice['paid_amount'] > 0): ?>
                                        <small class="text-success">
                                            Paid: <?php echo formatMoney($invoice['paid_amount'], $invoice['currency']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="text-end">
                                    <div class="invoice-amount">
                                        <?php if ($invoice['outstanding_amount'] > 0): ?>
                                            <span class="text-danger">
                                                <?php echo formatMoney($invoice['outstanding_amount'], $invoice['currency']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-success">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <td>
                                    <?php echo getInvoiceStatusBadge($invoice['payment_status'], $invoice['is_overdue']); ?>
                                </td>
                                
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="invoices_view.php?id=<?php echo $invoice['id']; ?>" 
                                           class="btn btn-outline-primary" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if (hasPermission('staff')): ?>
                                        <a href="invoices_edit.php?id=<?php echo $invoice['id']; ?>" 
                                           class="btn btn-outline-secondary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <a href="invoices_print.php?id=<?php echo $invoice['id']; ?>" 
                                           class="btn btn-outline-success" target="_blank" title="Print">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<nav aria-label="Invoice pagination" class="mt-4">
    <ul class="pagination justify-content-center">
        <?php
        // สร้าง URL parameters สำหรับ pagination
        $url_params = $_GET;
        unset($url_params['page']);
        $base_url = '?' . http_build_query($url_params);
        $base_url = $base_url === '?' ? '?page=' : $base_url . '&page=';
        ?>
        
        <!-- Previous Page -->
        <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="<?php echo $base_url . ($page - 1); ?>">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            </li>
        <?php endif; ?>
        
        <!-- Page Numbers -->
        <?php
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        
        if ($start_page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="<?php echo $base_url . '1'; ?>">1</a>
            </li>
            <?php if ($start_page > 2): ?>
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="<?php echo $base_url . $i; ?>"><?php echo $i; ?></a>
            </li>
        <?php endfor; ?>
        
        <?php if ($end_page < $total_pages): ?>
            <?php if ($end_page < $total_pages - 1): ?>
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
            <?php endif; ?>
            <li class="page-item">
                <a class="page-link" href="<?php echo $base_url . $total_pages; ?>"><?php echo $total_pages; ?></a>
            </li>
        <?php endif; ?>
        
        <!-- Next Page -->
        <?php if ($page < $total_pages): ?>
            <li class="page-item">
                <a class="page-link" href="<?php echo $base_url . ($page + 1); ?>">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        <?php endif; ?>
    </ul>
    
    <div class="text-center mt-3">
        <small class="text-muted">
            Showing <?php echo (($page - 1) * $per_page) + 1; ?> to <?php echo min($page * $per_page, $total_records); ?>
            of <?php echo number_format($total_records); ?> invoices
            (Page <?php echo $page; ?> of <?php echo $total_pages; ?>)
        </small>
    </div>
</nav>
<?php endif; ?>

<!-- Quick Actions Modal -->
<div class="modal fade" id="quickActionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Quick Actions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary" onclick="location.href='invoices_add.php'">
                        <i class="fas fa-plus"></i> Create New Invoice
                    </button>
                    
                    <button type="button" class="btn btn-info" onclick="location.href='invoices_import.php'">
                        <i class="fas fa-upload"></i> Import Invoices
                    </button>
                    
                    <button type="button" class="btn btn-success" onclick="location.href='invoices_export.php'">
                        <i class="fas fa-download"></i> Export All Invoices
                    </button>
                    
                    <button type="button" class="btn btn-warning" onclick="showOverdueReport()">
                        <i class="fas fa-exclamation-triangle"></i> View Overdue Report
                    </button>
                    
                    <button type="button" class="btn btn-secondary" onclick="location.href='invoices_settings.php'">
                        <i class="fas fa-cog"></i> Invoice Settings
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Action Button -->
<div class="position-fixed" style="bottom: 20px; right: 20px; z-index: 1000;">
    <div class="btn-group-vertical">
        <button type="button" class="btn btn-primary btn-lg rounded-circle" 
                data-bs-toggle="modal" data-bs-target="#quickActionsModal"
                title="Quick Actions">
            <i class="fas fa-plus"></i>
        </button>
    </div>
</div>

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
        return '<span class="badge bg-danger">Overdue</span>';
    }
    
    $badges = [
        'pending' => '<span class="badge bg-warning">Pending</span>',
        'partial' => '<span class="badge bg-info">Partial Paid</span>',
        'paid' => '<span class="badge bg-success">Paid</span>',
        'overdue' => '<span class="badge bg-danger">Overdue</span>',
        'cancelled' => '<span class="badge bg-dark">Cancelled</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge bg-secondary">Unknown</span>';
}

/**
 * Calculate invoice aging
 * @param string $due_date
 * @param string $payment_status
 * @return array
 */
function calculateInvoiceAging($due_date, $payment_status) {
    if ($payment_status === 'paid') {
        return ['days' => 0, 'category' => 'paid'];
    }
    
    $days_overdue = (time() - strtotime($due_date)) / 86400;
    
    if ($days_overdue <= 0) {
        return ['days' => 0, 'category' => 'current'];
    } elseif ($days_overdue <= 30) {
        return ['days' => $days_overdue, 'category' => '1-30_days'];
    } elseif ($days_overdue <= 60) {
        return ['days' => $days_overdue, 'category' => '31-60_days'];
    } elseif ($days_overdue <= 90) {
        return ['days' => $days_overdue, 'category' => '61-90_days'];
    } else {
        return ['days' => $days_overdue, 'category' => 'over_90_days'];
    }
}

/**
 * Generate invoice summary report
 * @param array $filters
 * @return array
 */
function generateInvoiceSummary($filters = []) {
    $where_conditions = [];
    $params = [];
    
    // Apply filters
    if (!empty($filters['date_from'])) {
        $where_conditions[] = "invoice_date >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where_conditions[] = "invoice_date <= ?";
        $params[] = $filters['date_to'];
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    return fetchOne("
        SELECT 
            COUNT(*) as total_invoices,
            SUM(total_amount) as total_amount,
            SUM(paid_amount) as total_paid,
            SUM(total_amount - paid_amount) as total_outstanding,
            AVG(total_amount) as average_amount,
            COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_count,
            COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN payment_status = 'overdue' OR (due_date < CURDATE() AND payment_status IN ('pending', 'partial')) THEN 1 END) as overdue_count,
            (COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) / COUNT(*)) * 100 as payment_rate
        FROM invoices
        {$where_clause}
    ", $params);
}

/**
 * Get top customers by invoice value
 * @param int $limit
 * @return array
 */
function getTopCustomersByInvoiceValue($limit = 10) {
    return fetchAll("
        SELECT 
            c.company_name,
            c.customer_code,
            COUNT(i.id) as invoice_count,
            SUM(i.total_amount) as total_value,
            SUM(i.paid_amount) as total_paid,
            SUM(i.total_amount - i.paid_amount) as outstanding_amount,
            AVG(i.total_amount) as average_invoice
        FROM customers c
        INNER JOIN invoices i ON c.id = i.customer_id
        GROUP BY c.id, c.company_name, c.customer_code
        ORDER BY total_value DESC
        LIMIT ?
    ", [$limit]);
}

/**
 * Get invoice payment trends
 * @param int $months
 * @return array
 */
function getInvoicePaymentTrends($months = 12) {
    return fetchAll("
        SELECT 
            DATE_FORMAT(invoice_date, '%Y-%m') as month,
            COUNT(*) as invoice_count,
            SUM(total_amount) as total_invoiced,
            SUM(paid_amount) as total_paid,
            (SUM(paid_amount) / SUM(total_amount)) * 100 as payment_rate
        FROM invoices
        WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
        GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
        ORDER BY month DESC
    ", [$months]);
}

/**
 * Calculate collection efficiency
 * @return array
 */
function calculateCollectionEfficiency() {
    $data = fetchOne("
        SELECT 
            SUM(CASE WHEN payment_status = 'paid' AND DATEDIFF(payment_date, due_date) <= 0 THEN 1 ELSE 0 END) as on_time_payments,
            SUM(CASE WHEN payment_status = 'paid' AND DATEDIFF(payment_date, due_date) > 0 THEN 1 ELSE 0 END) as late_payments,
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as total_paid,
            COUNT(*) as total_invoices,
            AVG(CASE WHEN payment_status = 'paid' THEN DATEDIFF(payment_date, invoice_date) END) as avg_collection_days
        FROM invoices
        WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    ");
    
    if ($data && $data['total_paid'] > 0) {
        $data['on_time_rate'] = ($data['on_time_payments'] / $data['total_paid']) * 100;
        $data['late_rate'] = ($data['late_payments'] / $data['total_paid']) * 100;
        $data['collection_rate'] = ($data['total_paid'] / $data['total_invoices']) * 100;
    }
    
    return $data;
}

/**
 * Get overdue invoice summary
 * @return array
 */
function getOverdueInvoiceSummary() {
    return fetchAll("
        SELECT 
            CASE 
                WHEN DATEDIFF(CURDATE(), due_date) <= 30 THEN '1-30 days'
                WHEN DATEDIFF(CURDATE(), due_date) <= 60 THEN '31-60 days'
                WHEN DATEDIFF(CURDATE(), due_date) <= 90 THEN '61-90 days'
                ELSE 'Over 90 days'
            END as aging_category,
            COUNT(*) as invoice_count,
            SUM(total_amount - paid_amount) as outstanding_amount
        FROM invoices
        WHERE due_date < CURDATE() 
        AND payment_status IN ('pending', 'partial')
        GROUP BY aging_category
        ORDER BY 
            CASE aging_category
                WHEN '1-30 days' THEN 1
                WHEN '31-60 days' THEN 2
                WHEN '61-90 days' THEN 3
                WHEN 'Over 90 days' THEN 4
            END
    ");
}
?>