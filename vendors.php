<?php
// =====================================================
// vendors.php - Vendor Management List (Fixed Version)
// =====================================================

// Include functions first
require_once 'includes/functions.php';

// Handle delete action BEFORE any output
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    if (hasPermission('manager')) {
        $vendor_id = (int)$_GET['id'];
        
        // Check if vendor has any job costs
        $cost_count = fetchOne("SELECT COUNT(*) as count FROM job_costs WHERE vendor_id = ?", [$vendor_id])['count'];
        
        if ($cost_count > 0) {
            $_SESSION['error_message'] = "Cannot delete vendor with existing job costs. Please remove related costs first.";
        } else {
            // Get vendor name for success message
            $vendor = fetchOne("SELECT company_name FROM vendors WHERE id = ?", [$vendor_id]);
            $vendor_name = $vendor ? $vendor['company_name'] : 'Vendor';
            
            if (execute("DELETE FROM vendors WHERE id = ?", [$vendor_id])) {
                $_SESSION['success_message'] = "Vendor '{$vendor_name}' has been deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to delete vendor. Please try again.";
            }
        }
    } else {
        $_SESSION['error_message'] = "You don't have permission to delete vendors.";
    }
    redirect('vendors.php');
    exit(); // Make sure script stops here
}

// Search and filter parameters
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? cleanInput($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? cleanInput($_GET['type']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 15;
$offset = ($page - 1) * $records_per_page;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(company_name LIKE ? OR vendor_code LIKE ? OR contact_person LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($type_filter)) {
    $where_conditions[] = "vendor_type = ?";
    $params[] = $type_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total records for pagination
$total_sql = "SELECT COUNT(*) as total FROM vendors $where_clause";
$total_records = fetchOne($total_sql, $params)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get vendors
$sql = "SELECT v.*, 
               (SELECT COUNT(*) FROM job_costs WHERE vendor_id = v.id) as cost_count,
               (SELECT COALESCE(SUM(amount_thb), 0) FROM job_costs WHERE vendor_id = v.id) as total_costs,
               u.name as created_by_name
        FROM vendors v
        LEFT JOIN users u ON v.created_by = u.id
        $where_clause
        ORDER BY v.created_at DESC
        LIMIT $records_per_page OFFSET $offset";

$vendors = fetchAll($sql, $params);

// Get statistics
$stats = [
    'total' => fetchOne("SELECT COUNT(*) as count FROM vendors")['count'],
    'active' => fetchOne("SELECT COUNT(*) as count FROM vendors WHERE status = 'active'")['count'],
    'inactive' => fetchOne("SELECT COUNT(*) as count FROM vendors WHERE status = 'inactive'")['count'],
    'shipping_line' => fetchOne("SELECT COUNT(*) as count FROM vendors WHERE vendor_type = 'shipping_line'")['count'],
    'airline' => fetchOne("SELECT COUNT(*) as count FROM vendors WHERE vendor_type = 'airline'")['count'],
    'trucking' => fetchOne("SELECT COUNT(*) as count FROM vendors WHERE vendor_type = 'trucking'")['count'],
    'customs_broker' => fetchOne("SELECT COUNT(*) as count FROM vendors WHERE vendor_type = 'customs_broker'")['count']
];

// NOW set page variables and include header
$custom_page_title = "Vendor Management";
$page_header = true;
$page_subtitle = "Manage your service providers and suppliers";
$breadcrumb = [
    ['name' => 'Vendors']
];

// Page actions (top right buttons)
$page_actions = '';
if (hasPermission('staff')) {
    $page_actions .= '<a href="vendors_add.php" class="btn btn-primary me-2">
                        <i class="fas fa-plus me-2"></i>Add New Vendor
                      </a>';
}
$page_actions .= '<button class="btn btn-outline-secondary" onclick="exportTableToCSV(\'vendorsTable\', \'vendors.csv\')">
                    <i class="fas fa-download me-2"></i>Export CSV
                  </button>';

include 'includes/header.php';
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 text-primary"><?php echo $stats['total']; ?></h3>
                        <small class="text-muted">Total Vendors</small>
                    </div>
                    <div class="text-primary">
                        <i class="fas fa-truck fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 text-success"><?php echo $stats['active']; ?></h3>
                        <small class="text-muted">Active</small>
                    </div>
                    <div class="text-success">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 text-info"><?php echo $stats['shipping_line']; ?></h3>
                        <small class="text-muted">Shipping Lines</small>
                    </div>
                    <div class="text-info">
                        <i class="fas fa-ship fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 text-warning"><?php echo $stats['airline']; ?></h3>
                        <small class="text-muted">Airlines</small>
                    </div>
                    <div class="text-warning">
                        <i class="fas fa-plane fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Stats Row -->
<div class="row mb-4">
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 text-purple"><?php echo $stats['trucking']; ?></h3>
                        <small class="text-muted">Trucking</small>
                    </div>
                    <div class="text-purple">
                        <i class="fas fa-truck-moving fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 text-secondary"><?php echo $stats['customs_broker']; ?></h3>
                        <small class="text-muted">Customs Brokers</small>
                    </div>
                    <div class="text-secondary">
                        <i class="fas fa-file-alt fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 text-danger"><?php echo $stats['inactive']; ?></h3>
                        <small class="text-muted">Inactive</small>
                    </div>
                    <div class="text-danger">
                        <i class="fas fa-times-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="fas fa-filter me-2"></i>Search & Filter Vendors
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Company name, code, contact..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="type" class="form-label">Vendor Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">All Types</option>
                    <option value="shipping_line" <?php echo ($type_filter == 'shipping_line') ? 'selected' : ''; ?>>Shipping Line</option>
                    <option value="airline" <?php echo ($type_filter == 'airline') ? 'selected' : ''; ?>>Airline</option>
                    <option value="trucking" <?php echo ($type_filter == 'trucking') ? 'selected' : ''; ?>>Trucking</option>
                    <option value="customs_broker" <?php echo ($type_filter == 'customs_broker') ? 'selected' : ''; ?>>Customs Broker</option>
                    <option value="warehouse" <?php echo ($type_filter == 'warehouse') ? 'selected' : ''; ?>>Warehouse</option>
                    <option value="other" <?php echo ($type_filter == 'other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                    <a href="vendors.php" class="btn btn-outline-secondary btn-sm">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Vendors Table -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>Vendor List
                <span class="badge bg-secondary ms-2"><?php echo $total_records; ?> records</span>
            </h5>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" onclick="window.print()" title="Print">
                    <i class="fas fa-print"></i>
                </button>
                <button class="btn btn-outline-secondary" onclick="exportTableToCSV('vendorsTable', 'vendors.csv')" title="Export CSV">
                    <i class="fas fa-download"></i>
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0" id="vendorsTable">
                <thead class="table-light">
                    <tr>
                        <th>Vendor Code</th>
                        <th>Company Name</th>
                        <th>Type</th>
                        <th>Contact Person</th>
                        <th>Contact Info</th>
                        <th>Payment Term</th>
                        <th>Total Costs</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vendors)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">
                            <i class="fas fa-truck fa-3x mb-3 d-block"></i>
                            <h5>No Vendors Found</h5>
                            <p class="mb-0">
                                <?php if (!empty($search) || !empty($status_filter) || !empty($type_filter)): ?>
                                    No vendors match your search criteria. <a href="vendors.php">Clear filters</a> to see all vendors.
                                <?php else: ?>
                                    Start by adding your first vendor.
                                <?php endif; ?>
                            </p>
                            <?php if (hasPermission('staff') && empty($search) && empty($status_filter) && empty($type_filter)): ?>
                            <a href="vendors_add.php" class="btn btn-primary mt-3">
                                <i class="fas fa-plus me-2"></i>Add New Vendor
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($vendors as $vendor): ?>
                    <tr>
                        <td>
                            <a href="vendors_view.php?id=<?php echo $vendor['id']; ?>" class="text-decoration-none fw-bold">
                                <?php echo htmlspecialchars($vendor['vendor_code']); ?>
                            </a>
                        </td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($vendor['company_name']); ?></strong>
                                <?php if ($vendor['tax_id']): ?>
                                    <br><small class="text-muted">Tax ID: <?php echo htmlspecialchars($vendor['tax_id']); ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php
                            $type_badges = [
                                'shipping_line' => '<span class="badge bg-info"><i class="fas fa-ship me-1"></i>Shipping Line</span>',
                                'airline' => '<span class="badge bg-warning"><i class="fas fa-plane me-1"></i>Airline</span>',
                                'trucking' => '<span class="badge bg-purple"><i class="fas fa-truck me-1"></i>Trucking</span>',
                                'customs_broker' => '<span class="badge bg-secondary"><i class="fas fa-file-alt me-1"></i>Customs</span>',
                                'warehouse' => '<span class="badge bg-success"><i class="fas fa-warehouse me-1"></i>Warehouse</span>',
                                'other' => '<span class="badge bg-dark"><i class="fas fa-ellipsis-h me-1"></i>Other</span>'
                            ];
                            echo $type_badges[$vendor['vendor_type']] ?? '<span class="badge bg-secondary">Unknown</span>';
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($vendor['contact_person'] ?: '-'); ?></td>
                        <td>
                            <?php if ($vendor['phone']): ?>
                                <div>
                                    <i class="fas fa-phone me-1"></i>
                                    <a href="tel:<?php echo $vendor['phone']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($vendor['phone']); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php if ($vendor['email']): ?>
                                <div>
                                    <i class="fas fa-envelope me-1"></i>
                                    <a href="mailto:<?php echo $vendor['email']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($vendor['email']); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php if (!$vendor['phone'] && !$vendor['email']): ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="fw-bold"><?php echo $vendor['payment_term']; ?></span> days
                            <br><small class="text-muted"><?php echo strtoupper($vendor['currency']); ?></small>
                        </td>
                        <td>
                            <?php if ($vendor['cost_count'] > 0): ?>
                                <div>
                                    <strong class="text-danger"><?php echo formatMoney($vendor['total_costs']); ?></strong>
                                    <br><small class="text-muted"><?php echo $vendor['cost_count']; ?> transactions</small>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">No costs yet</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $status_badges = [
                                'active' => '<span class="badge bg-success">Active</span>',
                                'inactive' => '<span class="badge bg-danger">Inactive</span>'
                            ];
                            echo $status_badges[$vendor['status']] ?? '<span class="badge bg-secondary">Unknown</span>';
                            ?>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo formatDateThai($vendor['created_at'], 'd/m/Y'); ?><br>
                                by <?php echo htmlspecialchars($vendor['created_by_name'] ?: 'System'); ?>
                            </small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="vendors_view.php?id=<?php echo $vendor['id']; ?>" 
                                   class="btn btn-outline-primary btn-sm" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (hasPermission('staff')): ?>
                                <a href="vendors_edit.php?id=<?php echo $vendor['id']; ?>" 
                                   class="btn btn-outline-warning btn-sm" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (hasPermission('manager') && $vendor['cost_count'] == 0): ?>
                                <a href="vendors.php?action=delete&id=<?php echo $vendor['id']; ?>" 
                                   class="btn btn-outline-danger btn-sm confirm-delete" 
                                   title="Delete"
                                   data-vendor-name="<?php echo htmlspecialchars($vendor['company_name']); ?>">
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
                        <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $search ? "&search=$search" : ''; ?><?php echo $status_filter ? "&status=$status_filter" : ''; ?><?php echo $type_filter ? "&type=$type_filter" : ''; ?>">
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
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? "&search=$search" : ''; ?><?php echo $status_filter ? "&status=$status_filter" : ''; ?><?php echo $type_filter ? "&type=$type_filter" : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $search ? "&search=$search" : ''; ?><?php echo $status_filter ? "&status=$status_filter" : ''; ?><?php echo $type_filter ? "&type=$type_filter" : ''; ?>">
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

.bg-purple {
    background-color: #6f42c1 !important;
}

/* Type-specific styling */
.vendor-shipping { border-left: 4px solid #17a2b8; }
.vendor-airline { border-left: 4px solid #ffc107; }
.vendor-trucking { border-left: 4px solid #6f42c1; }
.vendor-customs { border-left: 4px solid #6c757d; }
.vendor-warehouse { border-left: 4px solid #28a745; }
.vendor-other { border-left: 4px solid #343a40; }

/* Hover effects for vendor type cards */
.vendor-type-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
}
</style>

<script>
// Enhanced delete confirmation with vendor name
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.confirm-delete').forEach(function(element) {
        element.addEventListener('click', function(e) {
            const vendorName = this.getAttribute('data-vendor-name');
            const confirmMessage = `Are you sure you want to delete vendor "${vendorName}"?\n\nThis action cannot be undone.`;
            
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

// Auto-submit search form when filters change (optional)
document.querySelectorAll('#status, #type').forEach(function(select) {
    select.addEventListener('change', function() {
        // Uncomment the line below to auto-submit on filter change
        // this.closest('form').submit();
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
    <?php if (!empty($type_filter)): ?>
        searchInfo.push('Type: "<?php echo addslashes($type_filter); ?>"');
    <?php endif; ?>
    
    if (searchInfo.length > 0) {
        csv.unshift('"Filters: ' + searchInfo.join(', ') + '"');
        csv.unshift(''); // Empty line
    }
    
    csv.unshift('"Vendor List Export - ' + new Date().toLocaleString() + '"');
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

// Quick filter by vendor type
function filterByType(type) {
    const url = new URL(window.location.href);
    if (type) {
        url.searchParams.set('type', type);
    } else {
        url.searchParams.delete('type');
    }
    url.searchParams.delete('page'); // Reset pagination
    window.location.href = url.toString();
}

// Quick filter by status
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

// Add click handlers to stats cards for quick filtering
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to stat cards
    document.querySelectorAll('.card').forEach(function(card) {
        if (card.querySelector('h3')) {
            card.classList.add('vendor-type-card');
            card.style.cursor = 'pointer';
            
            // Add click handlers for filtering
            const iconElement = card.querySelector('i');
            if (iconElement) {
                if (iconElement.classList.contains('fa-ship')) {
                    card.addEventListener('click', () => filterByType('shipping_line'));
                } else if (iconElement.classList.contains('fa-plane')) {
                    card.addEventListener('click', () => filterByType('airline'));
                } else if (iconElement.classList.contains('fa-truck-moving')) {
                    card.addEventListener('click', () => filterByType('trucking'));
                } else if (iconElement.classList.contains('fa-file-alt')) {
                    card.addEventListener('click', () => filterByType('customs_broker'));
                } else if (iconElement.classList.contains('fa-check-circle')) {
                    card.addEventListener('click', () => filterByStatus('active'));
                } else if (iconElement.classList.contains('fa-times-circle')) {
                    card.addEventListener('click', () => filterByStatus('inactive'));
                } else if (iconElement.classList.contains('fa-truck') && !iconElement.classList.contains('fa-truck-moving')) {
                    card.addEventListener('click', () => filterByType(''));
                }
            }
        }
    });
});

// Vendor performance analytics (placeholder for future enhancement)
function showVendorAnalytics() {
    // Future: Show vendor performance charts, cost analysis, etc.
    alert('Vendor analytics feature coming soon!');
}

// Add vendor performance indicators
document.addEventListener('DOMContentLoaded', function() {
    // Add performance indicators to vendor rows
    document.querySelectorAll('tbody tr').forEach(function(row, index) {
        if (row.children.length > 5) { // Skip empty state row
            const totalCostsCell = row.children[6]; // Total costs column
            const costsText = totalCostsCell.innerText;
            
            // Add performance indicators based on cost volume
            if (costsText.includes('No costs yet')) {
                row.classList.add('vendor-new');
            } else {
                const costValue = parseFloat(costsText.replace(/[^\d.]/g, ''));
                if (costValue > 1000000) { // 1M+ THB
                    row.classList.add('vendor-major');
                } else if (costValue > 100000) { // 100K+ THB
                    row.classList.add('vendor-regular');
                } else {
                    row.classList.add('vendor-minor');
                }
            }
        }
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + N = New vendor
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        <?php if (hasPermission('staff')): ?>
        window.location.href = 'vendors_add.php';
        <?php endif; ?>
    }
    
    // Ctrl/Cmd + F = Focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        document.getElementById('search').focus();
    }
    
    // Escape = Clear search
    if (e.key === 'Escape') {
        const searchField = document.getElementById('search');
        if (searchField === document.activeElement) {
            searchField.value = '';
            searchField.blur();
        }
    }
});

// Add tooltips to action buttons
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips if available
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
});

// Auto-refresh vendor data every 5 minutes (optional)
// Uncomment the following to enable auto-refresh
/*
setInterval(function() {
    // Only refresh if user is not actively interacting
    if (document.hidden === false && Date.now() - lastUserActivity > 60000) {
        location.reload();
    }
}, 300000); // 5 minutes

let lastUserActivity = Date.now();
document.addEventListener('mousemove', () => lastUserActivity = Date.now());
document.addEventListener('keypress', () => lastUserActivity = Date.now());
*/
</script>

<!-- Additional CSS for vendor performance indicators -->
<style>
/* Vendor performance indicators */
.vendor-new {
    border-left: 3px solid #6c757d;
}

.vendor-minor {
    border-left: 3px solid #28a745;
}

.vendor-regular {
    border-left: 3px solid #ffc107;
}

.vendor-major {
    border-left: 3px solid #dc3545;
}

/* Enhanced hover effects */
.vendor-type-card {
    transition: all 0.3s ease;
    cursor: pointer;
}

.vendor-type-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
}

/* Statistics cards click feedback */
.vendor-type-card:active {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

/* Search highlight */
#search:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

/* Action buttons enhancement */
.btn-group .btn {
    transition: all 0.2s ease;
}

.btn-group .btn:hover {
    transform: translateY(-1px);
}

/* Table row hover enhancement */
tbody tr:hover {
    background-color: rgba(102, 126, 234, 0.05);
    transform: translateX(2px);
    transition: all 0.2s ease;
}

/* Responsive table improvements */
@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .btn-group-sm .btn {
        padding: 0.25rem 0.4rem;
        font-size: 0.75rem;
    }
}

/* Print optimizations */
@media print {
    .card-header .btn-group,
    .page-actions,
    .pagination,
    .btn-group {
        display: none !important;
    }
    
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        break-inside: avoid;
    }
    
    .badge {
        border: 1px solid #000 !important;
        color: #000 !important;
    }
}

/* Loading state for buttons */
.btn.disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Enhanced pagination */
.pagination .page-link {
    border-radius: 6px;
    margin: 0 2px;
    border: 1px solid #dee2e6;
}

.pagination .page-item.active .page-link {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: #667eea;
}

/* Vendor type icons in badges */
.badge i {
    font-size: 0.8em;
}

/* Empty state styling */
.table tbody tr td i.fa-3x {
    opacity: 0.3;
}

/* Form control focus states */
.form-control:focus,
.form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}
</style>

<?php include 'includes/footer.php'; ?>