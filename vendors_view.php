<?php
// =====================================================
// vendors_view.php - View Vendor Details
// =====================================================

// Include functions first
require_once 'includes/functions.php';

// Get vendor ID
$vendor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$vendor_id) {
    $_SESSION['error_message'] = "Vendor ID is required.";
    redirect('vendors.php');
    exit();
}

// Get vendor data
$vendor = fetchOne("
    SELECT v.*, u.name as created_by_name 
    FROM vendors v
    LEFT JOIN users u ON v.created_by = u.id
    WHERE v.id = ?
", [$vendor_id]);

if (!$vendor) {
    $_SESSION['error_message'] = "Vendor not found.";
    redirect('vendors.php');
    exit();
}

$custom_page_title = "Vendor Details - " . $vendor['company_name'];
$page_header = true;
$page_subtitle = "View complete vendor information and transaction history";
$breadcrumb = [
    ['name' => 'Vendors', 'url' => 'vendors.php'],
    ['name' => $vendor['company_name']]
];

// Page actions
$page_actions = '';
if (hasPermission('staff')) {
    $page_actions .= '<a href="vendors_edit.php?id=' . $vendor_id . '" class="btn btn-primary me-2">
                        <i class="fas fa-edit me-2"></i>Edit Vendor
                      </a>';
}

$page_actions .= '<div class="btn-group">
                    <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-plus me-2"></i>Add Cost
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="job_costs_add.php?vendor_id=' . $vendor_id . '">
                            <i class="fas fa-money-bill me-2"></i>Add Job Cost
                        </a></li>
                        <li><a class="dropdown-item" href="job_costs.php?vendor_id=' . $vendor_id . '">
                            <i class="fas fa-list me-2"></i>View All Costs
                        </a></li>
                    </ul>
                  </div>';

$page_actions .= '<button class="btn btn-outline-secondary ms-2" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print
                  </button>';

include 'includes/header.php';

// Get vendor statistics
$stats = [
    'total_costs' => fetchOne("SELECT COALESCE(SUM(amount_thb), 0) as amount FROM job_costs WHERE vendor_id = ?", [$vendor_id])['amount'],
    'pending_costs' => fetchOne("SELECT COALESCE(SUM(amount_thb), 0) as amount FROM job_costs WHERE vendor_id = ? AND payment_status = 'pending'", [$vendor_id])['amount'],
    'paid_costs' => fetchOne("SELECT COALESCE(SUM(amount_thb), 0) as amount FROM job_costs WHERE vendor_id = ? AND payment_status = 'paid'", [$vendor_id])['amount'],
    'total_jobs' => fetchOne("SELECT COUNT(DISTINCT job_id) as count FROM job_costs WHERE vendor_id = ?", [$vendor_id])['count'],
    'total_transactions' => fetchOne("SELECT COUNT(*) as count FROM job_costs WHERE vendor_id = ?", [$vendor_id])['count'],
    'avg_cost_per_job' => 0,
    'last_transaction' => fetchOne("SELECT MAX(created_at) as date FROM job_costs WHERE vendor_id = ?", [$vendor_id])['date']
];

// Calculate average cost per job
if ($stats['total_jobs'] > 0) {
    $stats['avg_cost_per_job'] = $stats['total_costs'] / $stats['total_jobs'];
}

// Calculate monthly performance
$monthly_stats = fetchAll("
    SELECT 
        YEAR(created_at) as year,
        MONTH(created_at) as month,
        COUNT(*) as transaction_count,
        SUM(amount_thb) as total_amount
    FROM job_costs 
    WHERE vendor_id = ? 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY year DESC, month DESC
    LIMIT 6
", [$vendor_id]);

// Get recent job costs
$recent_costs = fetchAll("
    SELECT jc.*, j.job_no, j.job_type, j.status as job_status,
           c1.company_name as shipper_name,
           c2.company_name as consignee_name
    FROM job_costs jc
    LEFT JOIN jobs j ON jc.job_id = j.id
    LEFT JOIN customers c1 ON j.shipper_id = c1.id
    LEFT JOIN customers c2 ON j.consignee_id = c2.id
    WHERE jc.vendor_id = ?
    ORDER BY jc.created_at DESC
    LIMIT 10
", [$vendor_id]);

// Get cost breakdown by type
$cost_breakdown = fetchAll("
    SELECT 
        cost_type,
        COUNT(*) as transaction_count,
        SUM(amount_thb) as total_amount,
        AVG(amount_thb) as avg_amount
    FROM job_costs 
    WHERE vendor_id = ?
    GROUP BY cost_type
    ORDER BY total_amount DESC
", [$vendor_id]);

// Get top jobs by cost
$top_jobs = fetchAll("
    SELECT 
        j.job_no,
        j.job_type,
        j.origin,
        j.destination,
        SUM(jc.amount_thb) as total_cost,
        COUNT(jc.id) as cost_items,
        MAX(jc.created_at) as last_cost_date
    FROM job_costs jc
    INNER JOIN jobs j ON jc.job_id = j.id
    WHERE jc.vendor_id = ?
    GROUP BY j.id
    ORDER BY total_cost DESC
    LIMIT 5
", [$vendor_id]);
?>

<!-- Vendor Status Alert -->
<?php if ($vendor['status'] != 'active'): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    This vendor is currently <strong><?php echo strtoupper($vendor['status']); ?></strong>. 
    Inactive vendors cannot be used for new job costs.
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-danger mb-2">
                    <i class="fas fa-money-bill-wave fa-2x"></i>
                </div>
                <h4 class="mb-1 text-danger"><?php echo formatNumber($stats['total_costs'], 0); ?></h4>
                <small class="text-muted">Total Costs (THB)</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-warning mb-2">
                    <i class="fas fa-clock fa-2x"></i>
                </div>
                <h4 class="mb-1 text-warning"><?php echo formatNumber($stats['pending_costs'], 0); ?></h4>
                <small class="text-muted">Pending (THB)</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-success mb-2">
                    <i class="fas fa-check-circle fa-2x"></i>
                </div>
                <h4 class="mb-1 text-success"><?php echo formatNumber($stats['paid_costs'], 0); ?></h4>
                <small class="text-muted">Paid (THB)</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-primary mb-2">
                    <i class="fas fa-briefcase fa-2x"></i>
                </div>
                <h4 class="mb-1 text-primary"><?php echo $stats['total_jobs']; ?></h4>
                <small class="text-muted">Total Jobs</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-info mb-2">
                    <i class="fas fa-list fa-2x"></i>
                </div>
                <h4 class="mb-1 text-info"><?php echo $stats['total_transactions']; ?></h4>
                <small class="text-muted">Transactions</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-secondary mb-2">
                    <i class="fas fa-calculator fa-2x"></i>
                </div>
                <h4 class="mb-1 text-secondary"><?php echo formatNumber($stats['avg_cost_per_job'], 0); ?></h4>
                <small class="text-muted">Avg/Job (THB)</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Main Content -->
    <div class="col-lg-8">
        <!-- Vendor Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-truck me-2"></i>Vendor Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" width="140">Vendor Code:</td>
                                <td class="text-primary fw-bold"><?php echo htmlspecialchars($vendor['vendor_code']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Company Name:</td>
                                <td><?php echo htmlspecialchars($vendor['company_name']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Contact Person:</td>
                                <td><?php echo htmlspecialchars($vendor['contact_person'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Vendor Type:</td>
                                <td>
                                    <?php
                                    $type_badges = [
                                        'shipping_line' => '<span class="badge bg-info"><i class="fas fa-ship me-1"></i>Shipping Line</span>',
                                        'airline' => '<span class="badge bg-warning"><i class="fas fa-plane me-1"></i>Airline</span>',
                                        'trucking' => '<span class="badge bg-purple"><i class="fas fa-truck me-1"></i>Trucking</span>',
                                        'customs_broker' => '<span class="badge bg-secondary"><i class="fas fa-file-alt me-1"></i>Customs Broker</span>',
                                        'warehouse' => '<span class="badge bg-success"><i class="fas fa-warehouse me-1"></i>Warehouse</span>',
                                        'other' => '<span class="badge bg-dark"><i class="fas fa-ellipsis-h me-1"></i>Other</span>'
                                    ];
                                    echo $type_badges[$vendor['vendor_type']] ?? '<span class="badge bg-secondary">Unknown</span>';
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Status:</td>
                                <td>
                                    <?php
                                    $status_badges = [
                                        'active' => '<span class="badge bg-success">Active</span>',
                                        'inactive' => '<span class="badge bg-danger">Inactive</span>'
                                    ];
                                    echo $status_badges[$vendor['status']] ?? '<span class="badge bg-secondary">Unknown</span>';
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
                                    <?php if ($vendor['phone']): ?>
                                        <a href="tel:<?php echo $vendor['phone']; ?>" class="text-decoration-none">
                                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($vendor['phone']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Email:</td>
                                <td>
                                    <?php if ($vendor['email']): ?>
                                        <a href="mailto:<?php echo $vendor['email']; ?>" class="text-decoration-none">
                                            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($vendor['email']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Tax ID:</td>
                                <td><?php echo htmlspecialchars($vendor['tax_id'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Payment Term:</td>
                                <td><?php echo $vendor['payment_term']; ?> days</td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Currency:</td>
                                <td>
                                    <span class="badge bg-light text-dark"><?php echo $vendor['currency']; ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($vendor['address']): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <strong>Address:</strong><br>
                        <div class="bg-light p-3 rounded">
                            <?php echo nl2br(htmlspecialchars($vendor['address'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($vendor['remark']): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <strong>Remarks:</strong><br>
                        <div class="bg-light p-3 rounded">
                            <?php echo nl2br(htmlspecialchars($vendor['remark'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-credit-card me-2"></i>Payment Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center">
                            <h3 class="text-info mb-1"><?php echo $vendor['payment_term']; ?></h3>
                            <small class="text-muted">Payment Term (Days)</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <h3 class="text-primary mb-1"><?php echo $vendor['currency']; ?></h3>
                            <small class="text-muted">Preferred Currency</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <?php 
                            $payment_ratio = $stats['total_costs'] > 0 ? ($stats['paid_costs'] / $stats['total_costs']) * 100 : 0;
                            ?>
                            <h3 class="mb-1 <?php echo $payment_ratio >= 80 ? 'text-success' : ($payment_ratio >= 50 ? 'text-warning' : 'text-danger'); ?>">
                                <?php echo number_format($payment_ratio, 1); ?>%
                            </h3>
                            <small class="text-muted">Payment Ratio</small>
                        </div>
                    </div>
                </div>
                
                <?php if ($stats['total_costs'] > 0): ?>
                <div class="mt-3">
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $payment_ratio; ?>%"></div>
                        <div class="progress-bar bg-warning" style="width: <?php echo (($stats['pending_costs'] / $stats['total_costs']) * 100); ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-success">Paid: <?php echo formatMoney($stats['paid_costs']); ?></small>
                        <small class="text-warning">Pending: <?php echo formatMoney($stats['pending_costs']); ?></small>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($stats['pending_costs'] > 0): ?>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Outstanding Amount: <?php echo formatMoney($stats['pending_costs']); ?></strong>
                    <br><small>Please review pending payments to this vendor.</small>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cost Breakdown -->
        <?php if (!empty($cost_breakdown)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>Cost Breakdown by Type
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Cost Type</th>
                                <th>Transactions</th>
                                <th>Total Amount</th>
                                <th>Average</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cost_breakdown as $breakdown): ?>
                            <?php $percentage = $stats['total_costs'] > 0 ? ($breakdown['total_amount'] / $stats['total_costs']) * 100 : 0; ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo strtoupper(str_replace('_', ' ', $breakdown['cost_type'])); ?>
                                    </span>
                                </td>
                                <td><?php echo $breakdown['transaction_count']; ?></td>
                                <td class="text-danger fw-bold"><?php echo formatMoney($breakdown['total_amount']); ?></td>
                                <td><?php echo formatMoney($breakdown['avg_amount']); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2" style="height: 20px;">
                                            <div class="progress-bar" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <small><?php echo number_format($percentage, 1); ?>%</small>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Job Costs -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>Recent Job Costs
                    </h5>
                    <?php if (count($recent_costs) > 0): ?>
                        <a href="job_costs.php?vendor_id=<?php echo $vendor_id; ?>" class="btn btn-outline-primary btn-sm">
                            View All Costs
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($recent_costs)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-money-bill fa-3x mb-3 d-block"></i>
                        <h6>No Job Costs Yet</h6>
                        <p class="mb-0">This vendor hasn't been used for any job costs yet.</p>
                        <?php if (hasPermission('staff')): ?>
                            <a href="job_costs_add.php?vendor_id=<?php echo $vendor_id; ?>" class="btn btn-primary mt-3">
                                <i class="fas fa-plus me-2"></i>Add First Cost
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Job No.</th>
                                    <th>Cost Type</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_costs as $cost): ?>
                                <tr>
                                    <td>
                                        <?php if ($cost['job_no']): ?>
                                            <a href="jobs_view.php?id=<?php echo $cost['job_id']; ?>" class="text-decoration-none fw-bold">
                                                <?php echo htmlspecialchars($cost['job_no']); ?>
                                            </a>
                                            <br><small class="text-muted"><?php echo strtoupper(str_replace('_', ' ', $cost['job_type'] ?: '')); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo strtoupper(str_replace('_', ' ', $cost['cost_type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($cost['description']); ?></td>
                                    <td>
                                        <div>
                                            <strong class="text-danger"><?php echo formatMoney($cost['amount_thb']); ?></strong>
                                            <?php if ($cost['currency'] !== 'THB'): ?>
                                                <br><small class="text-muted"><?php echo $cost['currency'] . ' ' . formatNumber($cost['amount'], 2); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $payment_badges = [
                                            'pending' => '<span class="badge bg-warning">Pending</span>',
                                            'paid' => '<span class="badge bg-success">Paid</span>',
                                            'cancelled' => '<span class="badge bg-secondary">Cancelled</span>'
                                        ];
                                        echo $payment_badges[$cost['payment_status']] ?? '<span class="badge bg-secondary">Unknown</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo formatDateThai($cost['created_at'], 'd/m/Y'); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <a href="job_costs_view.php?id=<?php echo $cost['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm" title="View Cost Details">
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

        <!-- Top Jobs by Cost -->
        <?php if (!empty($top_jobs)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-trophy me-2"></i>Top Jobs by Cost
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Job No.</th>
                                <th>Type</th>
                                <th>Route</th>
                                <th>Total Cost</th>
                                <th>Cost Items</th>
                                <th>Last Cost Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_jobs as $job): ?>
                            <tr>
                                <td>
                                    <a href="jobs_view.php?job_no=<?php echo $job['job_no']; ?>" class="text-decoration-none fw-bold">
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
                                        <?php echo htmlspecialchars($job['origin'] ?: '-'); ?> → 
                                        <?php echo htmlspecialchars($job['destination'] ?: '-'); ?>
                                    </small>
                                </td>
                                <td>
                                    <strong class="text-danger"><?php echo formatMoney($job['total_cost']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $job['cost_items']; ?> items</span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo formatDateThai($job['last_cost_date'], 'd/m/Y'); ?>
                                    </small>
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
                    <a href="vendors_edit.php?id=<?php echo $vendor_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i>Edit Vendor
                    </a>
                    
                    <a href="job_costs_add.php?vendor_id=<?php echo $vendor_id; ?>" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>Add Job Cost
                    </a>
                    
                    <a href="job_costs.php?vendor_id=<?php echo $vendor_id; ?>" class="btn btn-info">
                        <i class="fas fa-list me-2"></i>View All Costs
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($vendor['email']): ?>
                    <a href="mailto:<?php echo htmlspecialchars($vendor['email']); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-envelope me-2"></i>Send Email
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($vendor['phone']): ?>
                    <a href="tel:<?php echo htmlspecialchars($vendor['phone']); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-phone me-2"></i>Call Vendor
                    </a>
                    <?php endif; ?>
                    
                    <button class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Details
                    </button>
                    
                    <button class="btn btn-outline-secondary" onclick="copyVendorInfo()">
                        <i class="fas fa-copy me-2"></i>Copy Vendor Info
                    </button>
                </div>
            </div>
        </div>

        <!-- Performance Summary -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>Performance Summary
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Total Costs:</span>
                        <strong class="text-danger"><?php echo formatMoney($stats['total_costs']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Pending:</span>
                        <strong class="text-warning"><?php echo formatMoney($stats['pending_costs']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Paid:</span>
                        <strong class="text-success"><?php echo formatMoney($stats['paid_costs']); ?></strong>
                    </div>
                </div>
                
                <hr>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Jobs Worked:</span>
                        <strong class="text-primary"><?php echo $stats['total_jobs']; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Transactions:</span>
                        <strong class="text-info"><?php echo $stats['total_transactions']; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Avg per Job:</span>
                        <strong class="text-secondary"><?php echo formatMoney($stats['avg_cost_per_job']); ?></strong>
                    </div>
                </div>
                
                <?php if ($stats['last_transaction']): ?>
                <hr>
                <div class="mb-0">
                    <strong>Last Transaction:</strong><br>
                    <small class="text-muted"><?php echo formatDateThai($stats['last_transaction'], 'd/m/Y H:i'); ?></small>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Monthly Performance -->
        <?php if (!empty($monthly_stats)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-calendar-alt me-2"></i>Monthly Performance (Last 6 Months)
                </h6>
            </div>
            <div class="card-body">
                <?php foreach ($monthly_stats as $month): ?>
                    <?php 
                    $month_name = date('M Y', mktime(0, 0, 0, $month['month'], 1, $month['year']));
                    $percentage = $stats['total_costs'] > 0 ? ($month['total_amount'] / $stats['total_costs']) * 100 : 0;
                    ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong><?php echo $month_name; ?></strong>
                            <br><small class="text-muted"><?php echo $month['transaction_count']; ?> transactions</small>
                        </div>
                        <div class="text-end">
                            <strong class="text-danger"><?php echo formatMoney($month['total_amount']); ?></strong>
                        </div>
                    </div>
                    <div class="progress mb-3" style="height: 6px;">
                        <div class="progress-bar bg-danger" style="width: <?php echo min($percentage * 5, 100); ?>%"></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Vendor Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>Vendor Information
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Created:</strong><br>
                    <small class="text-muted">
                        <?php echo formatDateThai($vendor['created_at'], 'd/m/Y H:i'); ?>
                        <?php if ($vendor['created_by_name']): ?>
                            <br>by <?php echo htmlspecialchars($vendor['created_by_name']); ?>
                        <?php endif; ?>
                    </small>
                </div>
                
                <div class="mb-3">
                    <strong>Last Updated:</strong><br>
                    <small class="text-muted">
                        <?php echo formatDateThai($vendor['updated_at'], 'd/m/Y H:i'); ?>
                    </small>
                </div>
                
                <div class="mb-3">
                    <strong>Current Status:</strong><br>
                    <?php
                    $status_badges = [
                        'active' => '<span class="badge bg-success">Active</span>',
                        'inactive' => '<span class="badge bg-danger">Inactive</span>'
                    ];
                    echo $status_badges[$vendor['status']] ?? '<span class="badge bg-secondary">Unknown</span>';
                    ?>
                </div>
                
                <?php if ($stats['pending_costs'] > 0): ?>
                <div class="alert alert-warning py-2">
                    <small>
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Outstanding Balance:</strong><br>
                        <?php echo formatMoney($stats['pending_costs']); ?>
                    </small>
                </div>
                <?php endif; ?>
                
                <?php if ($vendor['status'] == 'inactive'): ?>
                <div class="alert alert-info py-2">
                    <small>
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Inactive Vendor</strong><br>
                        Cannot be used for new job costs.
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Related Information -->
        <?php 
        $related_vendors = fetchAll("
            SELECT id, vendor_code, company_name, vendor_type
            FROM vendors 
            WHERE vendor_type = ? 
            AND id != ? 
            AND status = 'active'
            ORDER BY company_name 
            LIMIT 5
        ", [$vendor['vendor_type'], $vendor_id]);
        ?>
        
        <?php if (!empty($related_vendors)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-link me-2"></i>Similar Vendors
                    <small class="text-muted">(<?php echo ucfirst(str_replace('_', ' ', $vendor['vendor_type'])); ?>)</small>
                </h6>
            </div>
            <div class="card-body">
                <?php foreach ($related_vendors as $related): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <a href="vendors_view.php?id=<?php echo $related['id']; ?>" class="text-decoration-none fw-bold">
                            <?php echo htmlspecialchars($related['vendor_code']); ?>
                        </a>
                        <br><small class="text-muted"><?php echo htmlspecialchars($related['company_name']); ?></small>
                    </div>
                    <div>
                        <?php echo $type_badges[$related['vendor_type']] ?? ''; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Delete Vendor (if applicable) -->
        <?php if (hasPermission('manager') && $stats['total_transactions'] == 0): ?>
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h6 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>Danger Zone
                </h6>
            </div>
            <div class="card-body">
                <p class="mb-3">
                    <small class="text-muted">
                        Delete this vendor permanently. This action cannot be undone.
                    </small>
                </p>
                <a href="vendors.php?action=delete&id=<?php echo $vendor_id; ?>" 
                   class="btn btn-outline-danger btn-sm confirm-delete">
                    <i class="fas fa-trash me-2"></i>Delete Vendor
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.text-purple {
    color: #6f42c1 !important;
}

.bg-purple {
    background-color: #6f42c1 !important;
}

/* Performance indicators */
.performance-high {
    border-left: 4px solid #dc3545;
}

.performance-medium {
    border-left: 4px solid #ffc107;
}

.performance-low {
    border-left: 4px solid #28a745;
}

.performance-new {
    border-left: 4px solid #6c757d;
}

/* Monthly chart styling */
.progress {
    background-color: #f8f9fa;
}

/* Vendor type specific colors */
.vendor-shipping { color: #17a2b8; }
.vendor-airline { color: #ffc107; }
.vendor-trucking { color: #6f42c1; }
.vendor-customs { color: #6c757d; }
.vendor-warehouse { color: #28a745; }
.vendor-other { color: #343a40; }

/* Print Styles */
@media print {
    .btn, .card-header, .sidebar, .page-actions {
        display: none !important;
    }
    
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        break-inside: avoid;
    }
    
    .main-content {
        width: 100% !important;
        max-width: none !important;
    }
    
    .badge {
        border: 1px solid #000 !important;
        color: #000 !important;
    }
}

/* Statistics cards hover effect */
.statistics-card:hover {
    transform: translateY(-2px);
    transition: transform 0.2s ease;
}

/* Table enhancements */
.table-hover tbody tr:hover {
    background-color: rgba(102, 126, 234, 0.05);
}

/* Progress bar animations */
.progress-bar {
    transition: width 0.6s ease;
}

/* Badge enhancements */
.badge {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
}

/* Card shadow enhancements */
.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: box-shadow 0.2s ease;
}

.card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}
</style>

<script>
// Copy vendor information to clipboard
function copyVendorInfo() {
    const vendorInfo = `
Vendor Code: <?php echo $vendor['vendor_code']; ?>
Company Name: <?php echo $vendor['company_name']; ?>
Vendor Type: <?php echo strtoupper(str_replace('_', ' ', $vendor['vendor_type'])); ?>
Contact: <?php echo $vendor['contact_person']; ?>
Phone: <?php echo $vendor['phone']; ?>
Email: <?php echo $vendor['email']; ?>
Payment Term: <?php echo $vendor['payment_term']; ?> days
Currency: <?php echo $vendor['currency']; ?>
Status: <?php echo ucfirst($vendor['status']); ?>
Total Costs: <?php echo formatMoney($stats['total_costs']); ?>
Pending: <?php echo formatMoney($stats['pending_costs']); ?>
Total Jobs: <?php echo $stats['total_jobs']; ?>
    `.trim();
    
    navigator.clipboard.writeText(vendorInfo).then(function() {
        // Show success message
        showToast('Vendor information copied to clipboard!', 'success');
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = vendorInfo;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToast('Vendor information copied to clipboard!', 'success');
    });
}

// Show toast notification
function showToast(message, type = 'info') {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 3000);
}

// Print vendor details
function printVendorDetails() {
    window.print();
}

// Enhanced statistics with performance indicators
document.addEventListener('DOMContentLoaded', function() {
    const totalCosts = <?php echo $stats['total_costs']; ?>;
    const totalJobs = <?php echo $stats['total_jobs']; ?>;
    
    // Add performance class to main content based on vendor activity
    const mainContent = document.querySelector('.col-lg-8');
    if (totalCosts > 1000000) { // 1M+ THB
        mainContent.classList.add('performance-high');
    } else if (totalCosts > 100000) { // 100K+ THB
        mainContent.classList.add('performance-medium');
    } else if (totalJobs > 0) {
        mainContent.classList.add('performance-low');
    } else {
        mainContent.classList.add('performance-new');
    }
    
    // Add statistics cards hover effects
    document.querySelectorAll('.statistics-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});

// Confirm delete
document.querySelectorAll('.confirm-delete').forEach(function(element) {
    element.addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to delete this vendor? This action cannot be undone and will remove all related data.')) {
            e.preventDefault();
            return false;
        }
    });
});

// Enhanced vendor analysis
function showVendorAnalysis() {
    const analysisData = {
        vendor_code: '<?php echo $vendor['vendor_code']; ?>',
        company_name: '<?php echo addslashes($vendor['company_name']); ?>',
        vendor_type: '<?php echo $vendor['vendor_type']; ?>',
        total_costs: <?php echo $stats['total_costs']; ?>,
        total_jobs: <?php echo $stats['total_jobs']; ?>,
        avg_cost_per_job: <?php echo $stats['avg_cost_per_job']; ?>,
        payment_ratio: <?php echo $stats['total_costs'] > 0 ? ($stats['paid_costs'] / $stats['total_costs']) * 100 : 0; ?>,
        pending_costs: <?php echo $stats['pending_costs']; ?>
    };
    
    let analysis = `Vendor Performance Analysis for ${analysisData.company_name}:\n\n`;
    
    // Performance rating
    if (analysisData.total_costs > 1000000) {
        analysis += '🔴 HIGH VOLUME VENDOR\n';
        analysis += 'This is a major vendor with significant transaction volume.\n\n';
    } else if (analysisData.total_costs > 100000) {
        analysis += '🟡 MEDIUM VOLUME VENDOR\n';
        analysis += 'This is a regular vendor with moderate transaction volume.\n\n';
    } else if (analysisData.total_jobs > 0) {
        analysis += '🟢 LOW VOLUME VENDOR\n';
        analysis += 'This is a minor vendor with low transaction volume.\n\n';
    } else {
        analysis += '⚪ NEW VENDOR\n';
        analysis += 'This vendor has not been used for any jobs yet.\n\n';
    }
    
    analysis += `📊 Key Metrics:\n`;
    analysis += `• Total Costs: ${formatMoney(analysisData.total_costs)}\n`;
    analysis += `• Jobs Worked: ${analysisData.total_jobs}\n`;
    analysis += `• Avg Cost/Job: ${formatMoney(analysisData.avg_cost_per_job)}\n`;
    analysis += `• Payment Ratio: ${analysisData.payment_ratio.toFixed(1)}%\n`;
    
    if (analysisData.pending_costs > 0) {
        analysis += `\n⚠️  Outstanding Payments: ${formatMoney(analysisData.pending_costs)}\n`;
    }
    
    // Recommendations
    analysis += `\n💡 Recommendations:\n`;
    if (analysisData.payment_ratio < 50) {
        analysis += `• Review payment terms - low payment ratio detected\n`;
    }
    if (analysisData.pending_costs > 100000) {
        analysis += `• Follow up on outstanding payments\n`;
    }
    if (analysisData.total_jobs > 10 && analysisData.avg_cost_per_job < 10000) {
        analysis += `• Consider negotiating better rates for frequent use\n`;
    }
    
    alert(analysis);
}

// Add analysis button to quick actions
document.addEventListener('DOMContentLoaded', function() {
    const quickActions = document.querySelector('.quick-actions .d-grid');
    if (quickActions && <?php echo $stats['total_transactions']; ?> > 0) {
        const analysisBtn = document.createElement('button');
        analysisBtn.className = 'btn btn-outline-info';
        analysisBtn.innerHTML = '<i class="fas fa-chart-line me-2"></i>Performance Analysis';
        analysisBtn.onclick = showVendorAnalysis;
        quickActions.appendChild(analysisBtn);
    }
});

// Auto-refresh statistics every 5 minutes (for real-time updates)
setTimeout(function() {
    location.reload();
}, 300000); // 5 minutes

// Format money helper function (JavaScript)
function formatMoney(amount) {
    return new Intl.NumberFormat('th-TH', {
        style: 'currency',
        currency: 'THB',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
}

// Smooth scroll for internal links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Export vendor data to JSON
function exportVendorData() {
    const vendorData = {
        vendor_info: {
            vendor_code: '<?php echo $vendor['vendor_code']; ?>',
            company_name: '<?php echo addslashes($vendor['company_name']); ?>',
            vendor_type: '<?php echo $vendor['vendor_type']; ?>',
            contact_person: '<?php echo addslashes($vendor['contact_person']); ?>',
            phone: '<?php echo $vendor['phone']; ?>',
            email: '<?php echo $vendor['email']; ?>',
            payment_term: <?php echo $vendor['payment_term']; ?>,
            currency: '<?php echo $vendor['currency']; ?>',
            status: '<?php echo $vendor['status']; ?>'
        },
        statistics: {
            total_costs: <?php echo $stats['total_costs']; ?>,
            pending_costs: <?php echo $stats['pending_costs']; ?>,
            paid_costs: <?php echo $stats['paid_costs']; ?>,
            total_jobs: <?php echo $stats['total_jobs']; ?>,
            total_transactions: <?php echo $stats['total_transactions']; ?>,
            avg_cost_per_job: <?php echo $stats['avg_cost_per_job']; ?>
        },
        meta: {
            exported_at: new Date().toISOString(),
            exported_by: '<?php echo $_SESSION['username']; ?>'
        }
    };
    
    const dataStr = JSON.stringify(vendorData, null, 2);
    const dataBlob = new Blob([dataStr], {type: 'application/json'});
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `vendor_${vendorData.vendor_info.vendor_code}_${new Date().toISOString().split('T')[0]}.json`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    
    showToast('Vendor data exported successfully!', 'success');
}

// Initialize tooltips if Bootstrap is available
document.addEventListener('DOMContentLoaded', function() {
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + E = Edit vendor
    if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
        e.preventDefault();
        <?php if (hasPermission('staff')): ?>
        window.location.href = 'vendors_edit.php?id=<?php echo $vendor_id; ?>';
        <?php endif; ?>
    }
    
    // Ctrl/Cmd + P = Print
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        window.print();
    }
    
    // Ctrl/Cmd + C = Copy vendor info
    if ((e.ctrlKey || e.metaKey) && e.key === 'c' && e.shiftKey) {
        e.preventDefault();
        copyVendorInfo();
    }
});

console.log('Vendor view initialized for:', {
    vendor_id: <?php echo $vendor_id; ?>,
    vendor_code: '<?php echo $vendor['vendor_code']; ?>',
    total_costs: <?php echo $stats['total_costs']; ?>,
    total_jobs: <?php echo $stats['total_jobs']; ?>
});
</script>

<?php include 'includes/footer.php'; ?>