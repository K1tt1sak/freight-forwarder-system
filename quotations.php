<?php
// =====================================================
// quotations.php - Quotation Management List
// =====================================================

// Include functions first
require_once 'includes/functions.php';

// Handle delete action BEFORE any output
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    if (hasPermission('manager')) {
        $quotation_id = (int)$_GET['id'];
        
        // Check if quotation is already accepted (shouldn't delete accepted quotations)
        $quotation = fetchOne("SELECT q.*, c.company_name FROM quotations q LEFT JOIN customers c ON q.customer_id = c.id WHERE q.id = ?", [$quotation_id]);
        
        if ($quotation) {
            if ($quotation['status'] == 'accepted') {
                $_SESSION['error_message'] = "Cannot delete accepted quotations. Only draft, sent, rejected or expired quotations can be deleted.";
            } else {
                // Begin transaction
                beginTransaction();
                
                try {
                    // Delete quotation items first
                    execute("DELETE FROM quotation_items WHERE quotation_id = ?", [$quotation_id]);
                    
                    // Delete quotation
                    if (execute("DELETE FROM quotations WHERE id = ?", [$quotation_id])) {
                        commit();
                        $_SESSION['success_message'] = "Quotation '{$quotation['quotation_no']}' for {$quotation['company_name']} has been deleted successfully.";
                    } else {
                        rollback();
                        $_SESSION['error_message'] = "Failed to delete quotation. Please try again.";
                    }
                } catch (Exception $e) {
                    rollback();
                    $_SESSION['error_message'] = "Error deleting quotation: " . $e->getMessage();
                }
            }
        } else {
            $_SESSION['error_message'] = "Quotation not found.";
        }
    } else {
        $_SESSION['error_message'] = "You don't have permission to delete quotations.";
    }
    redirect('quotations.php');
    exit();
}

// Search and filter parameters
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? cleanInput($_GET['status']) : '';
$customer_filter = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$job_type_filter = isset($_GET['job_type']) ? cleanInput($_GET['job_type']) : '';
$date_from = isset($_GET['date_from']) ? cleanInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? cleanInput($_GET['date_to']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 15;
$offset = ($page - 1) * $records_per_page;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(q.quotation_no LIKE ? OR c.company_name LIKE ? OR q.origin LIKE ? OR q.destination LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "q.status = ?";
    $params[] = $status_filter;
}

if ($customer_filter > 0) {
    $where_conditions[] = "q.customer_id = ?";
    $params[] = $customer_filter;
}

if (!empty($job_type_filter)) {
    $where_conditions[] = "q.job_type = ?";
    $params[] = $job_type_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "q.quotation_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "q.quotation_date <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total records for pagination
$total_sql = "SELECT COUNT(*) as total FROM quotations q LEFT JOIN customers c ON q.customer_id = c.id $where_clause";
$total_records = fetchOne($total_sql, $params)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get quotations
$sql = "SELECT q.*, 
               c.company_name, c.customer_code,
               u.name as created_by_name,
               (CASE 
                   WHEN q.valid_until < CURDATE() AND q.status IN ('draft', 'sent') THEN 'expired'
                   ELSE q.status 
                END) as display_status
        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        LEFT JOIN users u ON q.created_by = u.id
        $where_clause
        ORDER BY q.created_at DESC
        LIMIT $records_per_page OFFSET $offset";

$quotations = fetchAll($sql, $params);

// Get statistics
$stats = [
    'total' => fetchOne("SELECT COUNT(*) as count FROM quotations")['count'],
    'draft' => fetchOne("SELECT COUNT(*) as count FROM quotations WHERE status = 'draft'")['count'],
    'sent' => fetchOne("SELECT COUNT(*) as count FROM quotations WHERE status = 'sent'")['count'],
    'accepted' => fetchOne("SELECT COUNT(*) as count FROM quotations WHERE status = 'accepted'")['count'],
    'rejected' => fetchOne("SELECT COUNT(*) as count FROM quotations WHERE status = 'rejected'")['count'],
    'expired' => fetchOne("SELECT COUNT(*) as count FROM quotations WHERE valid_until < CURDATE() AND status IN ('draft', 'sent')")['count'],
    'total_value' => fetchOne("SELECT COALESCE(SUM(total_amount), 0) as amount FROM quotations WHERE status = 'accepted'")['amount']
];

// Get customers for filter dropdown
$customers = fetchAll("SELECT id, customer_code, company_name FROM customers WHERE status = 'active' ORDER BY company_name");

// NOW set page variables and include header
$custom_page_title = "Quotations Management";
$page_header = true;
$page_subtitle = "Manage quotations and price proposals for customers";
$breadcrumb = [
    ['name' => 'Quotations']
];

// Page actions (top right buttons)
$page_actions = '
    <a href="quotations_add.php" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>Create Quotation
    </a>
    <button class="btn btn-outline-secondary" onclick="exportTableToCSV(\'quotationsTable\', \'quotations.csv\')">
        <i class="fas fa-download me-2"></i>Export CSV
    </button>
';

include 'includes/header.php';
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 text-primary"><?php echo $stats['total']; ?></h3>
                        <small class="text-muted">Total</small>
                    </div>
                    <div class="text-primary">
                        <i class="fas fa-file-invoice-dollar fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 text-secondary"><?php echo $stats['draft']; ?></h3>
                        <small class="text-muted">Draft</small>
                    </div>
                    <div class="text-secondary">
                        <i class="fas fa-edit fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 text-info"><?php echo $stats['sent']; ?></h3>
                        <small class="text-muted">Sent</small>
                    </div>
                    <div class="text-info">
                        <i class="fas fa-paper-plane fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 text-success"><?php echo $stats['accepted']; ?></h3>
                        <small class="text-muted">Accepted</small>
                    </div>
                    <div class="text-success">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 text-danger"><?php echo $stats['rejected']; ?></h3>
                        <small class="text-muted">Rejected</small>
                    </div>
                    <div class="text-danger">
                        <i class="fas fa-times-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 text-warning"><?php echo $stats['expired']; ?></h3>
                        <small class="text-muted">Expired</small>
                    </div>
                    <div class="text-warning">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Total Value Card -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="card-body text-white text-center">
                <h2 class="mb-1"><?php echo formatMoney($stats['total_value']); ?></h2>
                <p class="mb-0"><i class="fas fa-chart-line me-2"></i>Total Value of Accepted Quotations</p>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Quotation No., Customer, Route..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="draft" <?php echo ($status_filter == 'draft') ? 'selected' : ''; ?>>Draft</option>
                    <option value="sent" <?php echo ($status_filter == 'sent') ? 'selected' : ''; ?>>Sent</option>
                    <option value="accepted" <?php echo ($status_filter == 'accepted') ? 'selected' : ''; ?>>Accepted</option>
                    <option value="rejected" <?php echo ($status_filter == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                    <option value="expired" <?php echo ($status_filter == 'expired') ? 'selected' : ''; ?>>Expired</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="customer_id" class="form-label">Customer</label>
                <select class="form-select" id="customer_id" name="customer_id">
                    <option value="">All Customers</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo $customer['id']; ?>" <?php echo ($customer_filter == $customer['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($customer['customer_code'] . ' - ' . $customer['company_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="job_type" class="form-label">Job Type</label>
                <select class="form-select" id="job_type" name="job_type">
                    <option value="">All Types</option>
                    <option value="export_air" <?php echo ($job_type_filter == 'export_air') ? 'selected' : ''; ?>>Export Air</option>
                    <option value="export_sea" <?php echo ($job_type_filter == 'export_sea') ? 'selected' : ''; ?>>Export Sea</option>
                    <option value="import_air" <?php echo ($job_type_filter == 'import_air') ? 'selected' : ''; ?>>Import Air</option>
                    <option value="import_sea" <?php echo ($job_type_filter == 'import_sea') ? 'selected' : ''; ?>>Import Sea</option>
                </select>
            </div>
            <div class="col-md-1">
                <label for="date_from" class="form-label">From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-1">
                <label for="date_to" class="form-label">To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" 
                       value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid gap-1">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="quotations.php" class="btn btn-outline-secondary btn-sm">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Quotations Table -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>Quotations List
                <span class="badge bg-secondary ms-2"><?php echo $total_records; ?> records</span>
            </h5>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" onclick="window.print()" title="Print">
                    <i class="fas fa-print"></i>
                </button>
                <button class="btn btn-outline-secondary" onclick="exportTableToCSV('quotationsTable', 'quotations.csv')" title="Export CSV">
                    <i class="fas fa-download"></i>
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0" id="quotationsTable">
                <thead class="table-light">
                    <tr>
                        <th>Quotation No.</th>
                        <th>Customer</th>
                        <th>Job Type</th>
                        <th>Route</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Valid Until</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($quotations)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="fas fa-file-invoice-dollar fa-3x mb-3 d-block"></i>
                            <h5>No Quotations Found</h5>
                            <p class="mb-0">
                                <?php if (!empty($search) || !empty($status_filter) || $customer_filter > 0 || !empty($job_type_filter)): ?>
                                    No quotations match your search criteria. <a href="quotations.php">Clear filters</a> to see all quotations.
                                <?php else: ?>
                                    Start by creating your first quotation.
                                <?php endif; ?>
                            </p>
                            <?php if (empty($search) && empty($status_filter) && $customer_filter == 0 && empty($job_type_filter)): ?>
                            <a href="quotations_add.php" class="btn btn-primary mt-3">
                                <i class="fas fa-plus me-2"></i>Create First Quotation
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($quotations as $quotation): ?>
                    <tr>
                        <td>
                            <a href="quotations_view.php?id=<?php echo $quotation['id']; ?>" class="text-decoration-none fw-bold">
                                <?php echo htmlspecialchars($quotation['quotation_no']); ?>
                            </a>
                        </td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($quotation['company_name']); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($quotation['customer_code']); ?></small>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-info">
                                <?php echo strtoupper(str_replace('_', ' ', $quotation['job_type'])); ?>
                            </span>
                            <br><small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $quotation['service_type'])); ?></small>
                        </td>
                        <td>
                            <?php if ($quotation['origin'] || $quotation['destination']): ?>
                                <small>
                                    <?php echo htmlspecialchars($quotation['origin'] ?: 'TBD'); ?> 
                                    <i class="fas fa-arrow-right mx-1"></i> 
                                    <?php echo htmlspecialchars($quotation['destination'] ?: 'TBD'); ?>
                                </small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo formatMoney($quotation['total_amount'], $quotation['currency']); ?></strong>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo formatDateThai($quotation['quotation_date'], 'd/m/Y'); ?>
                                <br>by <?php echo htmlspecialchars($quotation['created_by_name'] ?: 'System'); ?>
                            </small>
                        </td>
                        <td>
                            <small class="<?php echo (strtotime($quotation['valid_until']) < time()) ? 'text-danger' : 'text-muted'; ?>">
                                <?php echo formatDateThai($quotation['valid_until'], 'd/m/Y'); ?>
                                <?php if (strtotime($quotation['valid_until']) < time() && in_array($quotation['status'], ['draft', 'sent'])): ?>
                                    <br><span class="badge bg-danger">Expired</span>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td>
                            <?php
                            $status = $quotation['display_status'];
                            $status_badges = [
                                'draft' => '<span class="badge bg-secondary">Draft</span>',
                                'sent' => '<span class="badge bg-info">Sent</span>',
                                'accepted' => '<span class="badge bg-success">Accepted</span>',
                                'rejected' => '<span class="badge bg-danger">Rejected</span>',
                                'expired' => '<span class="badge bg-warning">Expired</span>'
                            ];
                            echo $status_badges[$status] ?? '<span class="badge bg-secondary">Unknown</span>';
                            ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="quotations_view.php?id=<?php echo $quotation['id']; ?>" 
                                   class="btn btn-outline-primary btn-sm" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (hasPermission('staff') && in_array($quotation['status'], ['draft', 'sent'])): ?>
                                <a href="quotations_edit.php?id=<?php echo $quotation['id']; ?>" 
                                   class="btn btn-outline-warning btn-sm" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($quotation['status'] == 'accepted' && hasPermission('staff')): ?>
                                <a href="jobs_add.php?quotation_id=<?php echo $quotation['id']; ?>" 
                                   class="btn btn-outline-success btn-sm" title="Convert to Job">
                                    <i class="fas fa-shipping-fast"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (hasPermission('manager') && !in_array($quotation['status'], ['accepted'])): ?>
                                <a href="quotations.php?action=delete&id=<?php echo $quotation['id']; ?>" 
                                   class="btn btn-outline-danger btn-sm confirm-delete" 
                                   title="Delete"
                                   data-quotation-no="<?php echo htmlspecialchars($quotation['quotation_no']); ?>"
                                   data-customer-name="<?php echo htmlspecialchars($quotation['company_name']); ?>">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="card-footer">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <small class="text-muted">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> 
                    of <?php echo $total_records; ?> records
                </small>
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $search ? "&search=$search" : ''; ?><?php echo $status_filter ? "&status=$status_filter" : ''; ?><?php echo $customer_filter ? "&customer_id=$customer_filter" : ''; ?><?php echo $job_type_filter ? "&job_type=$job_type_filter" : ''; ?><?php echo $date_from ? "&date_from=$date_from" : ''; ?><?php echo $date_to ? "&date_to=$date_to" : ''; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? "&search=$search" : ''; ?><?php echo $status_filter ? "&status=$status_filter" : ''; ?><?php echo $customer_filter ? "&customer_id=$customer_filter" : ''; ?><?php echo $job_type_filter ? "&job_type=$job_type_filter" : ''; ?><?php echo $date_from ? "&date_from=$date_from" : ''; ?><?php echo $date_to ? "&date_to=$date_to" : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $search ? "&search=$search" : ''; ?><?php echo $status_filter ? "&status=$status_filter" : ''; ?><?php echo $customer_filter ? "&customer_id=$customer_filter" : ''; ?><?php echo $job_type_filter ? "&job_type=$job_type_filter" : ''; ?><?php echo $date_from ? "&date_from=$date_from" : ''; ?><?php echo $date_to ? "&date_to=$date_to" : ''; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Enhanced delete confirmation with quotation details
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.confirm-delete').forEach(function(element) {
        element.addEventListener('click', function(e) {
            const quotationNo = this.getAttribute('data-quotation-no');
            const customerName = this.getAttribute('data-customer-name');
            const confirmMessage = `Are you sure you want to delete quotation "${quotationNo}" for ${customerName}?\n\nThis action cannot be undone.`;
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            this.classList.add('disabled');
        });
    });
});

// Enhanced table export with current filters
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length - 1; j++) { // Exclude actions column
            let text = cols[j].innerText.replace(/\s+/g, ' ').trim();
            text = text.replace(/"/g, '""'); // Escape quotes
            row.push('"' + text + '"');
        }
        
        if (row.length > 0) {
            csv.push(row.join(','));
        }
    }
    
    // Add filters info as header
    const searchInfo = [];
    <?php if (!empty($search)): ?>
        searchInfo.push('Search: "<?php echo addslashes($search); ?>"');
    <?php endif; ?>
    <?php if (!empty($status_filter)): ?>
        searchInfo.push('Status: "<?php echo addslashes($status_filter); ?>"');
    <?php endif; ?>
    <?php if ($customer_filter > 0): ?>
        searchInfo.push('Customer ID: "<?php echo $customer_filter; ?>"');
    <?php endif; ?>
    <?php if (!empty($job_type_filter)): ?>
        searchInfo.push('Job Type: "<?php echo addslashes($job_type_filter); ?>"');
    <?php endif; ?>
    <?php if (!empty($date_from)): ?>
        searchInfo.push('Date From: "<?php echo addslashes($date_from); ?>"');
    <?php endif; ?>
    <?php if (!empty($date_to)): ?>
        searchInfo.push('Date To: "<?php echo addslashes($date_to); ?>"');
    <?php endif; ?>
    
    if (searchInfo.length > 0) {
        csv.unshift('"Filters: ' + searchInfo.join(', ') + '"');
        csv.unshift(''); // Empty line
    }
    
    csv.unshift('"Quotations List Export - ' + new Date().toLocaleString() + '"');
    csv.unshift(''); // Empty line
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// Search form enhancement
document.getElementById('search').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        this.closest('form').submit();
    }
});

// Auto-submit form when date filters change (optional)
document.querySelectorAll('#status, #customer_id, #job_type').forEach(function(select) {
    select.addEventListener('change', function() {
        // Uncomment the line below to auto-submit on filter change
        // this.closest('form').submit();
    });
});

// Highlight expired quotations
document.addEventListener('DOMContentLoaded', function() {
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(function(row) {
        const statusCell = row.querySelector('td:nth-child(8)'); // Status column
        if (statusCell && statusCell.textContent.includes('Expired')) {
            row.classList.add('table-warning');
        }
    });
});

// Quick status update (future enhancement)
function updateQuotationStatus(quotationId, newStatus) {
    if (confirm(`Are you sure you want to change status to "${newStatus}"?`)) {
        // AJAX call to update status
        fetch('ajax/update_quotation_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                quotation_id: quotationId,
                new_status: newStatus
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error updating status: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating status');
        });
    }
}

// Clear search functionality
function clearSearch() {
    window.location.href = 'quotations.php';
}

// Bulk actions (future enhancement)
function handleBulkAction() {
    const selectedQuotations = document.querySelectorAll('input[name="selected_quotations[]"]:checked');
    const action = document.getElementById('bulk_action').value;
    
    if (selectedQuotations.length === 0) {
        alert('Please select at least one quotation');
        return false;
    }
    
    if (!action) {
        alert('Please select an action');
        return false;
    }
    
    if (confirm(`Are you sure you want to ${action} ${selectedQuotations.length} quotation(s)?`)) {
        // Implement bulk action logic
        console.log('Bulk action:', action, 'for quotations:', selectedQuotations);
    }
}

// Print functionality with better formatting
function printQuotationsList() {
    const printWindow = window.open('', '_blank');
    const tableHTML = document.getElementById('quotationsTable').outerHTML;
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Quotations List</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .btn, .badge { display: none; }
                @media print {
                    .btn, .badge { display: none !important; }
                }
            </style>
        </head>
        <body>
            <h2>Quotations List - ${new Date().toLocaleDateString()}</h2>
            ${tableHTML}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + N = New Quotation
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        window.location.href = 'quotations_add.php';
    }
    
    // Ctrl + F = Focus search
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        document.getElementById('search').focus();
    }
    
    // Ctrl + E = Export
    if (e.ctrlKey && e.key === 'e') {
        e.preventDefault();
        exportTableToCSV('quotationsTable', 'quotations.csv');
    }
});

// Auto-refresh data every 5 minutes (for status updates)
setInterval(function() {
    // Only refresh if user is not actively filtering/searching
    const hasFilters = '<?php echo !empty($search) || !empty($status_filter) || $customer_filter > 0 || !empty($job_type_filter) || !empty($date_from) || !empty($date_to) ? "true" : "false"; ?>';
    
    if (hasFilters === 'false' && document.visibilityState === 'visible') {
        // Subtle page refresh without losing position
        const currentScroll = window.pageYOffset;
        location.reload();
        // Note: The scroll position restoration would need additional handling
    }
}, 300000); // 5 minutes

// Tooltip initialization for truncated text
document.addEventListener('DOMContentLoaded', function() {
    // Add tooltips to long customer names or other truncated content
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(element) {
        new bootstrap.Tooltip(element);
    });
});
</script>

<style>
/* Additional CSS for quotations list */
.table td {
    vertical-align: middle;
}

.table-warning {
    background-color: rgba(255, 193, 7, 0.1) !important;
}

.card-header .badge {
    font-size: 0.8rem;
}

/* Status badges */
.badge {
    font-size: 0.75rem;
    padding: 0.25em 0.6em;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.9rem;
    }
    
    .btn-group-sm .btn {
        padding: 0.25rem 0.4rem;
        font-size: 0.75rem;
    }
    
    .statistics-cards .card-body {
        padding: 1rem 0.5rem;
    }
}

/* Print styles */
@media print {
    .btn, .dropdown, .pagination, .card-header .btn-group {
        display: none !important;
    }
    
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
    }
    
    .table-striped > tbody > tr:nth-of-type(odd) > td {
        background-color: #f9f9f9 !important;
    }
}

/* Loading state for buttons */
.btn.loading {
    pointer-events: none;
    opacity: 0.6;
}

/* Highlight search terms */
.search-highlight {
    background-color: yellow;
    font-weight: bold;
}

/* Expired quotations visual indicator */
.expired-quotation {
    opacity: 0.7;
    background-color: rgba(255, 193, 7, 0.1);
}

/* Quick action buttons */
.quick-actions {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
}

.quick-actions .btn {
    margin-bottom: 10px;
    border-radius: 50px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

/* Custom scrollbar for table */
.table-responsive::-webkit-scrollbar {
    height: 8px;
}

.table-responsive::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.table-responsive::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

.table-responsive::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>

<?php include 'includes/footer.php'; ?>