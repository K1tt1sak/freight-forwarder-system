<?php
// =====================================================
// customers.php - Customer Management List (Fixed Version)
// =====================================================

// Include functions first
require_once 'includes/functions.php';

// Handle delete action BEFORE any output
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    if (hasPermission('manager')) {
        $customer_id = (int)$_GET['id'];
        
        // Check if customer has any jobs
        $job_count = fetchOne("SELECT COUNT(*) as count FROM jobs WHERE shipper_id = ? OR consignee_id = ?", 
                             [$customer_id, $customer_id])['count'];
        
        if ($job_count > 0) {
            $_SESSION['error_message'] = "Cannot delete customer with existing jobs. Please remove related jobs first.";
        } else {
            // Get customer name for success message
            $customer = fetchOne("SELECT company_name FROM customers WHERE id = ?", [$customer_id]);
            $customer_name = $customer ? $customer['company_name'] : 'Customer';
            
            if (execute("DELETE FROM customers WHERE id = ?", [$customer_id])) {
                $_SESSION['success_message'] = "Customer '{$customer_name}' has been deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to delete customer. Please try again.";
            }
        }
    } else {
        $_SESSION['error_message'] = "You don't have permission to delete customers.";
    }
    redirect('customers.php');
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
    $where_conditions[] = "(company_name LIKE ? OR customer_code LIKE ? OR contact_person LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($type_filter)) {
    $where_conditions[] = "customer_type = ?";
    $params[] = $type_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total records for pagination
$total_sql = "SELECT COUNT(*) as total FROM customers $where_clause";
$total_records = fetchOne($total_sql, $params)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get customers
$sql = "SELECT c.*, 
               (SELECT COUNT(*) FROM jobs WHERE shipper_id = c.id OR consignee_id = c.id) as job_count,
               u.name as created_by_name
        FROM customers c
        LEFT JOIN users u ON c.created_by = u.id
        $where_clause
        ORDER BY c.created_at DESC
        LIMIT $records_per_page OFFSET $offset";

$customers = fetchAll($sql, $params);

// Get statistics
$stats = [
    'total' => fetchOne("SELECT COUNT(*) as count FROM customers")['count'],
    'active' => fetchOne("SELECT COUNT(*) as count FROM customers WHERE status = 'active'")['count'],
    'inactive' => fetchOne("SELECT COUNT(*) as count FROM customers WHERE status = 'inactive'")['count'],
    'blacklist' => fetchOne("SELECT COUNT(*) as count FROM customers WHERE status = 'blacklist'")['count']
];

// NOW set page variables and include header
$custom_page_title = "Customer Management";
$page_header = true;
$page_subtitle = "Manage your customer information and contacts";
$breadcrumb = [
    ['name' => 'Customers']
];

// Page actions (top right buttons)
$page_actions = '
    <a href="customers_add.php" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>Add New Customer
    </a>
    <button class="btn btn-outline-secondary" onclick="exportTableToCSV(\'customersTable\', \'customers.csv\')">
        <i class="fas fa-download me-2"></i>Export CSV
    </button>
';

include 'includes/header.php';
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 text-primary"><?php echo $stats['total']; ?></h3>
                        <small class="text-muted">Total Customers</small>
                    </div>
                    <div class="text-primary">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 text-success"><?php echo $stats['active']; ?></h3>
                        <small class="text-muted">Active</small>
                    </div>
                    <div class="text-success">
                        <i class="fas fa-user-check fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 text-warning"><?php echo $stats['inactive']; ?></h3>
                        <small class="text-muted">Inactive</small>
                    </div>
                    <div class="text-warning">
                        <i class="fas fa-user-times fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-0 text-danger"><?php echo $stats['blacklist']; ?></h3>
                        <small class="text-muted">Blacklisted</small>
                    </div>
                    <div class="text-danger">
                        <i class="fas fa-user-slash fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="card mb-4">
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
                    <option value="blacklist" <?php echo ($status_filter == 'blacklist') ? 'selected' : ''; ?>>Blacklisted</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="type" class="form-label">Customer Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">All Types</option>
                    <option value="shipper" <?php echo ($type_filter == 'shipper') ? 'selected' : ''; ?>>Shipper Only</option>
                    <option value="consignee" <?php echo ($type_filter == 'consignee') ? 'selected' : ''; ?>>Consignee Only</option>
                    <option value="agent" <?php echo ($type_filter == 'agent') ? 'selected' : ''; ?>>Agent</option>
                    <option value="both" <?php echo ($type_filter == 'both') ? 'selected' : ''; ?>>Both</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                    <a href="customers.php" class="btn btn-outline-secondary btn-sm">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Customers Table -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>Customer List
                <span class="badge bg-secondary ms-2"><?php echo $total_records; ?> records</span>
            </h5>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" onclick="window.print()" title="Print">
                    <i class="fas fa-print"></i>
                </button>
                <button class="btn btn-outline-secondary" onclick="exportTableToCSV('customersTable', 'customers.csv')" title="Export CSV">
                    <i class="fas fa-download"></i>
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0" id="customersTable">
                <thead class="table-light">
                    <tr>
                        <th>Customer Code</th>
                        <th>Company Name</th>
                        <th>Contact Person</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Jobs</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">
                            <i class="fas fa-users fa-3x mb-3 d-block"></i>
                            <h5>No Customers Found</h5>
                            <p class="mb-0">
                                <?php if (!empty($search) || !empty($status_filter) || !empty($type_filter)): ?>
                                    No customers match your search criteria. <a href="customers.php">Clear filters</a> to see all customers.
                                <?php else: ?>
                                    Start by adding your first customer.
                                <?php endif; ?>
                            </p>
                            <?php if (empty($search) && empty($status_filter) && empty($type_filter)): ?>
                            <a href="customers_add.php" class="btn btn-primary mt-3">
                                <i class="fas fa-plus me-2"></i>Add New Customer
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td>
                            <a href="customers_view.php?id=<?php echo $customer['id']; ?>" class="text-decoration-none fw-bold">
                                <?php echo htmlspecialchars($customer['customer_code']); ?>
                            </a>
                        </td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($customer['company_name']); ?></strong>
                                <?php if ($customer['tax_id']): ?>
                                    <br><small class="text-muted">Tax ID: <?php echo htmlspecialchars($customer['tax_id']); ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($customer['contact_person'] ?: '-'); ?></td>
                        <td>
                            <?php if ($customer['email']): ?>
                                <a href="mailto:<?php echo $customer['email']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($customer['email']); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($customer['phone']): ?>
                                <a href="tel:<?php echo $customer['phone']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($customer['phone']); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $type_badges = [
                                'shipper' => '<span class="badge bg-primary">Shipper</span>',
                                'consignee' => '<span class="badge bg-info">Consignee</span>',
                                'agent' => '<span class="badge bg-warning">Agent</span>',
                                'both' => '<span class="badge bg-success">Both</span>'
                            ];
                            echo $type_badges[$customer['customer_type']] ?? '<span class="badge bg-secondary">Unknown</span>';
                            ?>
                        </td>
                        <td>
                            <?php
                            $status_badges = [
                                'active' => '<span class="badge bg-success">Active</span>',
                                'inactive' => '<span class="badge bg-warning">Inactive</span>',
                                'blacklist' => '<span class="badge bg-danger">Blacklisted</span>'
                            ];
                            echo $status_badges[$customer['status']] ?? '<span class="badge bg-secondary">Unknown</span>';
                            ?>
                        </td>
                        <td>
                            <?php if ($customer['job_count'] > 0): ?>
                                <a href="jobs.php?customer_id=<?php echo $customer['id']; ?>" class="badge bg-info text-decoration-none">
                                    <?php echo $customer['job_count']; ?> jobs
                                </a>
                            <?php else: ?>
                                <span class="text-muted">0 jobs</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo formatDateThai($customer['created_at'], 'd/m/Y'); ?><br>
                                by <?php echo htmlspecialchars($customer['created_by_name'] ?: 'System'); ?>
                            </small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="customers_view.php?id=<?php echo $customer['id']; ?>" 
                                   class="btn btn-outline-primary btn-sm" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (hasPermission('staff')): ?>
                                <a href="customers_edit.php?id=<?php echo $customer['id']; ?>" 
                                   class="btn btn-outline-warning btn-sm" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (hasPermission('manager') && $customer['job_count'] == 0): ?>
                                <a href="customers.php?action=delete&id=<?php echo $customer['id']; ?>" 
                                   class="btn btn-outline-danger btn-sm confirm-delete" 
                                   title="Delete"
                                   data-customer-name="<?php echo htmlspecialchars($customer['company_name']); ?>">
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

<script>
// Enhanced delete confirmation with customer name
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.confirm-delete').forEach(function(element) {
        element.addEventListener('click', function(e) {
            const customerName = this.getAttribute('data-customer-name');
            const confirmMessage = `Are you sure you want to delete customer "${customerName}"?\n\nThis action cannot be undone.`;
            
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
    
    csv.unshift('"Customer List Export - ' + new Date().toLocaleString() + '"');
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

// Clear search functionality
function clearSearch() {
    window.location.href = 'customers.php';
}
</script>

<?php include 'includes/footer.php'; ?>