<?php
// =====================================================
// customers_view.php - View Customer Details
// =====================================================

// Include functions first
require_once 'includes/functions.php';

// Get customer ID
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$customer_id) {
    $_SESSION['error_message'] = "Customer ID is required.";
    redirect('customers.php');
}

// Get customer data
$customer = fetchOne("
    SELECT c.*, u.name as created_by_name 
    FROM customers c
    LEFT JOIN users u ON c.created_by = u.id
    WHERE c.id = ?
", [$customer_id]);

if (!$customer) {
    $_SESSION['error_message'] = "Customer not found.";
    redirect('customers.php');
}

$custom_page_title = "Customer Details - " . $customer['company_name'];
$page_header = true;
$page_subtitle = "View complete customer information and history";
$breadcrumb = [
    ['name' => 'Customers', 'url' => 'customers.php'],
    ['name' => $customer['company_name']]
];

// Page actions
$page_actions = '';
if (hasPermission('staff')) {
    $page_actions .= '<a href="customers_edit.php?id=' . $customer_id . '" class="btn btn-primary me-2">
                        <i class="fas fa-edit me-2"></i>Edit Customer
                      </a>';
}

if ($customer['status'] == 'active') {
    $page_actions .= '<div class="btn-group">
                        <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-plus me-2"></i>Create New
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="jobs_add.php?customer_id=' . $customer_id . '">
                                <i class="fas fa-shipping-fast me-2"></i>New Job
                            </a></li>
                            <li><a class="dropdown-item" href="quotations_add.php?customer_id=' . $customer_id . '">
                                <i class="fas fa-file-invoice-dollar me-2"></i>New Quotation
                            </a></li>
                        </ul>
                      </div>';
}

include 'includes/header.php';

// Get customer statistics
$stats = [
    'total_jobs' => fetchOne("SELECT COUNT(*) as count FROM jobs WHERE shipper_id = ? OR consignee_id = ?", 
                           [$customer_id, $customer_id])['count'],
    'active_jobs' => fetchOne("SELECT COUNT(*) as count FROM jobs WHERE (shipper_id = ? OR consignee_id = ?) AND status NOT IN ('completed', 'cancelled')", 
                            [$customer_id, $customer_id])['count'],
    'completed_jobs' => fetchOne("SELECT COUNT(*) as count FROM jobs WHERE (shipper_id = ? OR consignee_id = ?) AND status = 'completed'", 
                               [$customer_id, $customer_id])['count'],
    'total_revenue' => fetchOne("SELECT COALESCE(SUM(i.total_amount), 0) as amount FROM invoices i WHERE i.customer_id = ? AND i.payment_status = 'paid'", 
                               [$customer_id])['amount'],
    'outstanding_amount' => fetchOne("SELECT COALESCE(SUM(i.total_amount - i.paid_amount), 0) as amount FROM invoices i WHERE i.customer_id = ? AND i.payment_status IN ('pending', 'partial')", 
                                   [$customer_id])['amount'],
    'overdue_amount' => fetchOne("SELECT COALESCE(SUM(i.total_amount - i.paid_amount), 0) as amount FROM invoices i WHERE i.customer_id = ? AND i.payment_status IN ('pending', 'partial') AND i.due_date < CURDATE()", 
                               [$customer_id])['amount']
];

// Get recent jobs
$recent_jobs = fetchAll("
    SELECT j.*, 
           c1.company_name as shipper_name,
           c2.company_name as consignee_name
    FROM jobs j
    LEFT JOIN customers c1 ON j.shipper_id = c1.id
    LEFT JOIN customers c2 ON j.consignee_id = c2.id
    WHERE j.shipper_id = ? OR j.consignee_id = ?
    ORDER BY j.created_at DESC
    LIMIT 10
", [$customer_id, $customer_id]);

// Get recent invoices
$recent_invoices = fetchAll("
    SELECT i.*, j.job_no
    FROM invoices i
    LEFT JOIN jobs j ON i.job_id = j.id
    WHERE i.customer_id = ?
    ORDER BY i.created_at DESC
    LIMIT 5
", [$customer_id]);
?>

<!-- Customer Status Alert -->
<?php if ($customer['status'] != 'active'): ?>
<div class="alert alert-<?php echo $customer['status'] == 'blacklist' ? 'danger' : 'warning'; ?>">
    <i class="fas fa-<?php echo $customer['status'] == 'blacklist' ? 'ban' : 'exclamation-triangle'; ?> me-2"></i>
    This customer is currently <strong><?php echo strtoupper($customer['status']); ?></strong>. 
    <?php if ($customer['status'] == 'blacklist'): ?>
        No new jobs can be created for blacklisted customers.
    <?php elseif ($customer['status'] == 'inactive'): ?>
        Customer is inactive and may affect ongoing operations.
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-primary mb-2">
                    <i class="fas fa-shipping-fast fa-2x"></i>
                </div>
                <h4 class="mb-1 text-primary"><?php echo $stats['total_jobs']; ?></h4>
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
                <h4 class="mb-1 text-warning"><?php echo $stats['active_jobs']; ?></h4>
                <small class="text-muted">Active Jobs</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-success mb-2">
                    <i class="fas fa-check-circle fa-2x"></i>
                </div>
                <h4 class="mb-1 text-success"><?php echo $stats['completed_jobs']; ?></h4>
                <small class="text-muted">Completed</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-info mb-2">
                    <i class="fas fa-dollar-sign fa-2x"></i>
                </div>
                <h4 class="mb-1 text-info"><?php echo formatNumber($stats['total_revenue'], 0); ?></h4>
                <small class="text-muted">Total Revenue</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-danger mb-2">
                    <i class="fas fa-exclamation-circle fa-2x"></i>
                </div>
                <h4 class="mb-1 text-danger"><?php echo formatNumber($stats['outstanding_amount'], 0); ?></h4>
                <small class="text-muted">Outstanding</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-dark mb-2">
                    <i class="fas fa-calendar-times fa-2x"></i>
                </div>
                <h4 class="mb-1 text-dark"><?php echo formatNumber($stats['overdue_amount'], 0); ?></h4>
                <small class="text-muted">Overdue</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Customer Information -->
    <div class="col-lg-8">
        <!-- Basic Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2"></i>Customer Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" width="140">Customer Code:</td>
                                <td><?php echo htmlspecialchars($customer['customer_code']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Company Name:</td>
                                <td><?php echo htmlspecialchars($customer['company_name']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Contact Person:</td>
                                <td><?php echo htmlspecialchars($customer['contact_person'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Customer Type:</td>
                                <td>
                                    <?php
                                    $type_badges = [
                                        'shipper' => '<span class="badge bg-primary">Shipper Only</span>',
                                        'consignee' => '<span class="badge bg-info">Consignee Only</span>',
                                        'agent' => '<span class="badge bg-warning">Agent</span>',
                                        'both' => '<span class="badge bg-success">Both Shipper & Consignee</span>'
                                    ];
                                    echo $type_badges[$customer['customer_type']] ?? '<span class="badge bg-secondary">Unknown</span>';
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Status:</td>
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
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" width="120">Phone:</td>
                                <td>
                                    <?php if ($customer['phone']): ?>
                                        <a href="tel:<?php echo $customer['phone']; ?>" class="text-decoration-none">
                                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($customer['phone']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Email:</td>
                                <td>
                                    <?php if ($customer['email']): ?>
                                        <a href="mailto:<?php echo $customer['email']; ?>" class="text-decoration-none">
                                            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($customer['email']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Fax:</td>
                                <td><?php echo htmlspecialchars($customer['fax'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Tax ID:</td>
                                <td><?php echo htmlspecialchars($customer['tax_id'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Credit Term:</td>
                                <td><?php echo $customer['credit_term']; ?> days</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($customer['address']): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <strong>Address:</strong><br>
                        <div class="bg-light p-3 rounded">
                            <?php echo nl2br(htmlspecialchars($customer['address'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($customer['remark']): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <strong>Remarks:</strong><br>
                        <div class="bg-light p-3 rounded">
                            <?php echo nl2br(htmlspecialchars($customer['remark'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Credit Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-credit-card me-2"></i>Credit Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center">
                            <h3 class="text-primary mb-1"><?php echo $customer['credit_term']; ?></h3>
                            <small class="text-muted">Credit Term (Days)</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <h3 class="text-info mb-1"><?php echo formatNumber($customer['credit_limit'], 0); ?></h3>
                            <small class="text-muted">Credit Limit (THB)</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <?php 
                            $credit_usage_percent = 0;
                            if ($customer['credit_limit'] > 0) {
                                $credit_usage_percent = ($stats['outstanding_amount'] / $customer['credit_limit']) * 100;
                            }
                            ?>
                            <h3 class="mb-1 <?php echo $credit_usage_percent > 80 ? 'text-danger' : ($credit_usage_percent > 60 ? 'text-warning' : 'text-success'); ?>">
                                <?php echo number_format($credit_usage_percent, 1); ?>%
                            </h3>
                            <small class="text-muted">Credit Usage</small>
                        </div>
                    </div>
                </div>
                
                <?php if ($customer['credit_limit'] > 0): ?>
                <div class="mt-3">
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar <?php echo $credit_usage_percent > 80 ? 'bg-danger' : ($credit_usage_percent > 60 ? 'bg-warning' : 'bg-success'); ?>" 
                             style="width: <?php echo min($credit_usage_percent, 100); ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-muted">Available: <?php echo formatMoney($customer['credit_limit'] - $stats['outstanding_amount']); ?></small>
                        <small class="text-muted">Used: <?php echo formatMoney($stats['outstanding_amount']); ?></small>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($stats['overdue_amount'] > 0): ?>
                <div class="alert alert-danger mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Overdue Amount: <?php echo formatMoney($stats['overdue_amount']); ?></strong>
                    <br><small>Please follow up on overdue payments.</small>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Jobs -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-shipping-fast me-2"></i>Recent Jobs
                    </h5>
                    <?php if (count($recent_jobs) > 0): ?>
                        <a href="jobs.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-outline-primary btn-sm">
                            View All Jobs
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($recent_jobs)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                        <h6>No Jobs Yet</h6>
                        <p class="mb-0">This customer hasn't had any jobs created yet.</p>
                        <?php if ($customer['status'] == 'active' && hasPermission('staff')): ?>
                            <a href="jobs_add.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-primary mt-3">
                                <i class="fas fa-plus me-2"></i>Create First Job
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Job No.</th>
                                    <th>Type</th>
                                    <th>Route</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_jobs as $job): ?>
                                <tr>
                                    <td>
                                        <a href="jobs_view.php?id=<?php echo $job['id']; ?>" class="text-decoration-none fw-bold">
                                            <?php echo htmlspecialchars($job['job_no']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo strtoupper(str_replace('_', ' ', $job['job_type'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo htmlspecialchars($job['origin']); ?> → 
                                            <?php echo htmlspecialchars($job['destination']); ?>
                                        </small>
                                    </td>
                                    <td><?php echo getStatusBadge($job['status']); ?></td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo formatDateThai($job['created_at'], 'd/m/Y'); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <a href="jobs_view.php?id=<?php echo $job['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm" title="View Job">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Invoices -->
        <?php if (!empty($recent_invoices)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-receipt me-2"></i>Recent Invoices
                    </h5>
                    <a href="invoices.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-outline-primary btn-sm">
                        View All Invoices
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice No.</th>
                                <th>Job No.</th>
                                <th>Amount</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_invoices as $invoice): ?>
                            <tr>
                                <td>
                                    <a href="invoices_view.php?id=<?php echo $invoice['id']; ?>" class="text-decoration-none fw-bold">
                                        <?php echo htmlspecialchars($invoice['invoice_no']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($invoice['job_no']): ?>
                                        <a href="jobs_view.php?job_no=<?php echo $invoice['job_no']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($invoice['job_no']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatMoney($invoice['total_amount']); ?></td>
                                <td>
                                    <small class="<?php echo (strtotime($invoice['due_date']) < time() && $invoice['payment_status'] != 'paid') ? 'text-danger' : 'text-muted'; ?>">
                                        <?php echo formatDateThai($invoice['due_date'], 'd/m/Y'); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php
                                    $payment_badges = [
                                        'pending' => '<span class="badge bg-warning">Pending</span>',
                                        'partial' => '<span class="badge bg-info">Partial</span>',
                                        'paid' => '<span class="badge bg-success">Paid</span>',
                                        'overdue' => '<span class="badge bg-danger">Overdue</span>',
                                        'cancelled' => '<span class="badge bg-secondary">Cancelled</span>'
                                    ];
                                    echo $payment_badges[$invoice['payment_status']] ?? '<span class="badge bg-secondary">Unknown</span>';
                                    ?>
                                </td>
                                <td>
                                    <a href="invoices_view.php?id=<?php echo $invoice['id']; ?>" 
                                       class="btn btn-outline-primary btn-sm" title="View Invoice">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Quick Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if (hasPermission('staff')): ?>
                    <a href="customers_edit.php?id=<?php echo $customer_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i>Edit Customer
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($customer['status'] == 'active'): ?>
                    <a href="jobs_add.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>Create New Job
                    </a>
                    <a href="quotations_add.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-info">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Create Quotation
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($customer['email']): ?>
                    <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-envelope me-2"></i>Send Email
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($customer['phone']): ?>
                    <a href="tel:<?php echo htmlspecialchars($customer['phone']); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-phone me-2"></i>Call Customer
                    </a>
                    <?php endif; ?>
                    
                    <button class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Details
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Account Summary -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>Account Summary
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Total Revenue:</span>
                        <strong class="text-success"><?php echo formatMoney($stats['total_revenue']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Outstanding:</span>
                        <strong class="text-warning"><?php echo formatMoney($stats['outstanding_amount']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Overdue:</span>
                        <strong class="text-danger"><?php echo formatMoney($stats['overdue_amount']); ?></strong>
                    </div>
                </div>
                
                <?php if ($customer['credit_limit'] > 0): ?>
                <hr>
                <div class="mb-2">
                    <strong>Credit Status:</strong>
                </div>
                <div class="progress mb-2" style="height: 8px;">
                    <div class="progress-bar <?php echo $credit_usage_percent > 80 ? 'bg-danger' : ($credit_usage_percent > 60 ? 'bg-warning' : 'bg-success'); ?>" 
                         style="width: <?php echo min($credit_usage_percent, 100); ?>%"></div>
                </div>
                <small class="text-muted">
                    <?php echo number_format($credit_usage_percent, 1); ?>% of <?php echo formatMoney($customer['credit_limit']); ?> used
                </small>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Customer Timeline -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>Customer Timeline
                </h6>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">Customer Created</h6>
                            <p class="timeline-text">
                                <?php echo formatDateThai($customer['created_at'], 'd/m/Y H:i'); ?>
                                <?php if ($customer['created_by_name']): ?>
                                    <br><small class="text-muted">by <?php echo htmlspecialchars($customer['created_by_name']); ?></small>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($stats['total_jobs'] > 0): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-primary"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">First Job</h6>
                            <p class="timeline-text">
                                <?php 
                                $first_job = fetchOne("SELECT created_at, job_no FROM jobs WHERE (shipper_id = ? OR consignee_id = ?) ORDER BY created_at ASC LIMIT 1", [$customer_id, $customer_id]);
                                if ($first_job): ?>
                                    <?php echo formatDateThai($first_job['created_at'], 'd/m/Y'); ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($first_job['job_no']); ?></small>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($customer['updated_at'] != $customer['created_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-info"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">Last Updated</h6>
                            <p class="timeline-text">
                                <?php echo formatDateThai($customer['updated_at'], 'd/m/Y H:i'); ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Delete Customer (if applicable) -->
        <?php if (hasPermission('manager') && $stats['total_jobs'] == 0): ?>
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h6 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>Danger Zone
                </h6>
            </div>
            <div class="card-body">
                <p class="mb-3">
                    <small class="text-muted">
                        Delete this customer permanently. This action cannot be undone.
                    </small>
                </p>
                <a href="customers.php?action=delete&id=<?php echo $customer_id; ?>" 
                   class="btn btn-outline-danger btn-sm confirm-delete">
                    <i class="fas fa-trash me-2"></i>Delete Customer
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Timeline Styles */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -25px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #e9ecef;
}

.timeline-title {
    font-size: 0.9rem;
    margin-bottom: 5px;
    font-weight: 600;
}

.timeline-text {
    font-size: 0.8rem;
    margin-bottom: 0;
    color: #6c757d;
}

/* Print Styles */
@media print {
    .btn, .card-header, .timeline::before, .timeline-marker {
        display: none !important;
    }
    
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
    }
}
</style>

<script>
// Auto-refresh statistics every 60 seconds
setInterval(function() {
    // You can add AJAX call here to refresh statistics
    // without reloading the entire page
}, 60000);

// Print functionality
function printCustomerDetails() {
    window.print();
}

// Copy customer info to clipboard
function copyCustomerInfo() {
    const customerInfo = `
Customer Code: <?php echo $customer['customer_code']; ?>
Company Name: <?php echo $customer['company_name']; ?>
Contact: <?php echo $customer['contact_person']; ?>
Phone: <?php echo $customer['phone']; ?>
Email: <?php echo $customer['email']; ?>
    `.trim();
    
    navigator.clipboard.writeText(customerInfo).then(function() {
        alert('Customer information copied to clipboard!');
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
    });
}
</script>

<?php include 'includes/footer.php'; ?>