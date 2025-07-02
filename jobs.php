<?php
// =====================================================
// jobs.php - Jobs Management List
// =====================================================

// Include functions first
require_once 'includes/functions.php';

// Handle delete action BEFORE any output
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    if (hasPermission('manager')) {
        $job_id = (int)$_GET['id'];
        
        // Check if job has any invoices
        $invoice_count = fetchOne("SELECT COUNT(*) as count FROM invoices WHERE job_id = ?", [$job_id])['count'];
        
        if ($invoice_count > 0) {
            $_SESSION['error_message'] = "Cannot delete job with existing invoices. Please remove related invoices first.";
        } else {
            // Get job info for success message
            $job = fetchOne("SELECT job_no FROM jobs WHERE id = ?", [$job_id]);
            $job_no = $job ? $job['job_no'] : 'Job';
            
            if (execute("DELETE FROM jobs WHERE id = ?", [$job_id])) {
                $_SESSION['success_message'] = "Job '{$job_no}' has been deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to delete job. Please try again.";
            }
        }
    } else {
        $_SESSION['error_message'] = "You don't have permission to delete jobs.";
    }
    redirect('jobs.php');
    exit();
}

// Search and filter parameters
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? cleanInput($_GET['status']) : '';
$job_type_filter = isset($_GET['job_type']) ? cleanInput($_GET['job_type']) : '';
$customer_id_filter = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$date_from = isset($_GET['date_from']) ? cleanInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? cleanInput($_GET['date_to']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(j.job_no LIKE ? OR j.cargo_description LIKE ? OR j.bl_awb_no LIKE ? OR j.container_no LIKE ? OR c1.company_name LIKE ? OR c2.company_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "j.status = ?";
    $params[] = $status_filter;
}

if (!empty($job_type_filter)) {
    $where_conditions[] = "j.job_type = ?";
    $params[] = $job_type_filter;
}

if ($customer_id_filter > 0) {
    $where_conditions[] = "(j.shipper_id = ? OR j.consignee_id = ?)";
    $params[] = $customer_id_filter;
    $params[] = $customer_id_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(j.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(j.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total records for pagination
$total_sql = "SELECT COUNT(*) as total FROM jobs j 
              LEFT JOIN customers c1 ON j.shipper_id = c1.id
              LEFT JOIN customers c2 ON j.consignee_id = c2.id
              $where_clause";
$total_records = fetchOne($total_sql, $params)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get jobs
$sql = "SELECT j.*, 
               c1.company_name as shipper_name,
               c1.customer_code as shipper_code,
               c2.company_name as consignee_name,
               c2.customer_code as consignee_code,
               u.name as created_by_name,
               (SELECT COUNT(*) FROM invoices WHERE job_id = j.id) as invoice_count
        FROM jobs j
        LEFT JOIN customers c1 ON j.shipper_id = c1.id
        LEFT JOIN customers c2 ON j.consignee_id = c2.id
        LEFT JOIN users u ON j.created_by = u.id
        $where_clause
        ORDER BY j.created_at DESC
        LIMIT $records_per_page OFFSET $offset";

$jobs = fetchAll($sql, $params);

// Get statistics
$stats = [
    'total' => fetchOne("SELECT COUNT(*) as count FROM jobs")['count'],
    'booking' => fetchOne("SELECT COUNT(*) as count FROM jobs WHERE status = 'booking'")['count'],
    'in_transit' => fetchOne("SELECT COUNT(*) as count FROM jobs WHERE status = 'in_transit'")['count'],
    'arrived' => fetchOne("SELECT COUNT(*) as count FROM jobs WHERE status = 'arrived'")['count'],
    'completed' => fetchOne("SELECT COUNT(*) as count FROM jobs WHERE status = 'completed'")['count'],
    'active' => fetchOne("SELECT COUNT(*) as count FROM jobs WHERE status NOT IN ('completed', 'cancelled')")['count']
];

// Get current month revenue
$current_month_revenue = fetchOne("
    SELECT COALESCE(SUM(j.selling_total), 0) as revenue 
    FROM jobs j 
    WHERE MONTH(j.created_at) = MONTH(CURRENT_DATE()) 
    AND YEAR(j.created_at) = YEAR(CURRENT_DATE())
")['revenue'];

// NOW set page variables and include header
$custom_page_title = "Jobs Management";
$page_header = true;
$page_subtitle = "Manage and track all freight forwarding jobs";
$breadcrumb = [
    ['name' => 'Jobs Management']
];

// Page actions (top right buttons)
$page_actions = '';
if (hasPermission('staff')) {
    $page_actions .= '<a href="jobs_add.php" class="btn btn-primary me-2">
                        <i class="fas fa-plus me-2"></i>Create New Job
                      </a>';
}
$page_actions .= '<button class="btn btn-outline-secondary" onclick="exportTableToCSV(\'jobsTable\', \'jobs.csv\')">
                    <i class="fas fa-download me-2"></i>Export CSV
                  </button>';

include 'includes/header.php';
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-primary mb-2">
                    <i class="fas fa-boxes fa-2x"></i>
                </div>
                <h4 class="mb-1 text-primary"><?php echo $stats['total']; ?></h4>
                <small class="text-muted">Total Jobs</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-warning mb-2">
                    <i class="fas fa-clock fa-2x"></i>
                </div>
                <h4 class="mb-1 text-warning"><?php echo $stats['active']; ?></h4>
                <small class="text-muted">Active Jobs</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-info mb-2">
                    <i class="fas fa-ship fa-2x"></i>
                </div>
                <h4 class="mb-1 text-info"><?php echo $stats['in_transit']; ?></h4>
                <small class="text-muted">In Transit</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-purple mb-2">
                    <i class="fas fa-map-marker-alt fa-2x"></i>
                </div>
                <h4 class="mb-1 text-purple"><?php echo $stats['arrived']; ?></h4>
                <small class="text-muted">Arrived</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-success mb-2">
                    <i class="fas fa-check-circle fa-2x"></i>
                </div>
                <h4 class="mb-1 text-success"><?php echo $stats['completed']; ?></h4>
                <small class="text-muted">Completed</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-dark mb-2">
                    <i class="fas fa-dollar-sign fa-2x"></i>
                </div>
                <h4 class="mb-1 text-dark"><?php echo formatNumber($current_month_revenue, 0); ?></h4>
                <small class="text-muted">Month Revenue</small>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="fas fa-filter me-2"></i>Search & Filter Jobs
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Job No, BL/AWB, Container..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="booking" <?php echo ($status_filter == 'booking') ? 'selected' : ''; ?>>Booking</option>
                    <option value="document_preparation" <?php echo ($status_filter == 'document_preparation') ? 'selected' : ''; ?>>Doc Preparation</option>
                    <option value="customs_clearance" <?php echo ($status_filter == 'customs_clearance') ? 'selected' : ''; ?>>Customs</option>
                    <option value="in_transit" <?php echo ($status_filter == 'in_transit') ? 'selected' : ''; ?>>In Transit</option>
                    <option value="arrived" <?php echo ($status_filter == 'arrived') ? 'selected' : ''; ?>>Arrived</option>
                    <option value="delivered" <?php echo ($status_filter == 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                    <option value="completed" <?php echo ($status_filter == 'completed') ? 'selected' : ''; ?>>Completed</option>
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
            <div class="col-md-2">
                <label for="date_from" class="form-label">Date From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">Date To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" 
                       value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid gap-1">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="jobs.php" class="btn btn-outline-secondary btn-sm">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Jobs Table -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>Jobs List
                <span class="badge bg-secondary ms-2"><?php echo $total_records; ?> records</span>
            </h5>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" onclick="window.print()" title="Print">
                    <i class="fas fa-print"></i>
                </button>
                <button class="btn btn-outline-secondary" onclick="exportTableToCSV('jobsTable', 'jobs.csv')" title="Export CSV">
                    <i class="fas fa-download"></i>
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0" id="jobsTable">
                <thead class="table-light">
                    <tr>
                        <th>Job No.</th>
                        <th>Type/Service</th>
                        <th>Shipper</th>
                        <th>Consignee</th>
                        <th>Route</th>
                        <th>Status</th>
                        <th>Revenue</th>
                        <th>Profit</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jobs)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">
                            <i class="fas fa-boxes fa-3x mb-3 d-block"></i>
                            <h5>No Jobs Found</h5>
                            <p class="mb-0">
                                <?php if (!empty($search) || !empty($status_filter) || !empty($job_type_filter)): ?>
                                    No jobs match your search criteria. <a href="jobs.php">Clear filters</a> to see all jobs.
                                <?php else: ?>
                                    Start by creating your first job.
                                <?php endif; ?>
                            </p>
                            <?php if (hasPermission('staff') && empty($search) && empty($status_filter) && empty($job_type_filter)): ?>
                            <a href="jobs_add.php" class="btn btn-primary mt-3">
                                <i class="fas fa-plus me-2"></i>Create New Job
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($jobs as $job): ?>
                    <tr>
                        <td>
                            <a href="jobs_view.php?id=<?php echo $job['id']; ?>" class="text-decoration-none fw-bold">
                                <?php echo htmlspecialchars($job['job_no']); ?>
                            </a>
                            <?php if ($job['bl_awb_no']): ?>
                                <br><small class="text-muted">BL/AWB: <?php echo htmlspecialchars($job['bl_awb_no']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div>
                                <?php
                                $job_type_badges = [
                                    'export_air' => '<span class="badge bg-primary">Export Air</span>',
                                    'export_sea' => '<span class="badge bg-info">Export Sea</span>',
                                    'import_air' => '<span class="badge bg-warning">Import Air</span>',
                                    'import_sea' => '<span class="badge bg-success">Import Sea</span>'
                                ];
                                echo $job_type_badges[$job['job_type']] ?? '<span class="badge bg-secondary">Unknown</span>';
                                ?>
                                <br>
                                <small class="text-muted"><?php echo strtoupper($job['service_type']); ?></small>
                            </div>
                        </td>
                        <td>
                            <?php if ($job['shipper_name']): ?>
                                <a href="customers_view.php?id=<?php echo $job['shipper_id']; ?>" class="text-decoration-none">
                                    <strong><?php echo htmlspecialchars($job['shipper_name']); ?></strong>
                                </a>
                                <br><small class="text-muted"><?php echo htmlspecialchars($job['shipper_code']); ?></small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($job['consignee_name']): ?>
                                <a href="customers_view.php?id=<?php echo $job['consignee_id']; ?>" class="text-decoration-none">
                                    <strong><?php echo htmlspecialchars($job['consignee_name']); ?></strong>
                                </a>
                                <br><small class="text-muted"><?php echo htmlspecialchars($job['consignee_code']); ?></small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small>
                                <strong><?php echo htmlspecialchars($job['origin'] ?: '-'); ?></strong>
                                <br>↓<br>
                                <strong><?php echo htmlspecialchars($job['destination'] ?: '-'); ?></strong>
                            </small>
                        </td>
                        <td><?php echo getStatusBadge($job['status']); ?></td>
                        <td>
                            <strong class="text-info"><?php echo formatMoney($job['selling_total']); ?></strong>
                            <?php if ($job['cost_total'] > 0): ?>
                                <br><small class="text-muted">Cost: <?php echo formatMoney($job['cost_total']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $profit = $job['profit_loss'];
                            $profit_class = $profit >= 0 ? 'text-success' : 'text-danger';
                            $profit_icon = $profit >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                            ?>
                            <strong class="<?php echo $profit_class; ?>">
                                <i class="fas <?php echo $profit_icon; ?> me-1"></i>
                                <?php echo formatMoney($profit); ?>
                            </strong>
                            <?php if ($job['selling_total'] > 0): ?>
                                <?php $margin = ($profit / $job['selling_total']) * 100; ?>
                                <br><small class="<?php echo $profit_class; ?>"><?php echo number_format($margin, 1); ?>%</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo formatDateThai($job['created_at'], 'd/m/Y'); ?><br>
                                by <?php echo htmlspecialchars($job['created_by_name'] ?: 'System'); ?>
                            </small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="jobs_view.php?id=<?php echo $job['id']; ?>" 
                                   class="btn btn-outline-primary btn-sm" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (hasPermission('staff')): ?>
                                <a href="jobs_edit.php?id=<?php echo $job['id']; ?>" 
                                   class="btn btn-outline-warning btn-sm" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (hasPermission('manager') && $job['invoice_count'] == 0): ?>
                                <a href="jobs.php?action=delete&id=<?php echo $job['id']; ?>" 
                                   class="btn btn-outline-danger btn-sm confirm-delete" 
                                   title="Delete"
                                   data-job-no="<?php echo htmlspecialchars($job['job_no']); ?>">
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
                        <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo http_build_query(array_filter($_GET, function($k) { return $k != 'page'; }, ARRAY_FILTER_USE_KEY)) ? '&' . http_build_query(array_filter($_GET, function($k) { return $k != 'page'; }, ARRAY_FILTER_USE_KEY)) : ''; ?>">
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
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo http_build_query(array_filter($_GET, function($k) { return $k != 'page'; }, ARRAY_FILTER_USE_KEY)) ? '&' . http_build_query(array_filter($_GET, function($k) { return $k != 'page'; }, ARRAY_FILTER_USE_KEY)) : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo http_build_query(array_filter($_GET, function($k) { return $k != 'page'; }, ARRAY_FILTER_USE_KEY)) ? '&' . http_build_query(array_filter($_GET, function($k) { return $k != 'page'; }, ARRAY_FILTER_USE_KEY)) : ''; ?>">
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

<style>
.text-purple {
    color: #6f42c1 !important;
}
</style>

<script>
// Enhanced delete confirmation with job number
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.confirm-delete').forEach(function(element) {
        element.addEventListener('click', function(e) {
            const jobNo = this.getAttribute('data-job-no');
            const confirmMessage = `Are you sure you want to delete job "${jobNo}"?\n\nThis action cannot be undone.`;
            
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
    <?php if (!empty($job_type_filter)): ?>
        searchInfo.push('Type: "<?php echo addslashes($job_type_filter); ?>"');
    <?php endif; ?>
    
    if (searchInfo.length > 0) {
        csv.unshift('"Filters: ' + searchInfo.join(', ') + '"');
        csv.unshift(''); // Empty line
    }
    
    csv.unshift('"Jobs List Export - ' + new Date().toLocaleString() + '"');
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

// Quick status filter
function filterByStatus(status) {
    const url = new URL(window.location.href);
    if (status) {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    url.searchParams.delete('page'); // Reset pagination
    window.location.href = url.toString();
}

// Search form enhancement
document.getElementById('search').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        this.closest('form').submit();
    }
});
</script>

<?php include 'includes/footer.php'; ?>