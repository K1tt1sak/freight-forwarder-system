<?php
// =====================================================
// jobs_view.php - View Job Details
// =====================================================

// Include functions first
require_once 'includes/functions.php';

// Get job ID
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$job_id) {
    $_SESSION['error_message'] = "Job ID is required.";
    redirect('jobs.php');
    exit();
}

// Get job data with related information
$job = fetchOne("
    SELECT j.*, 
           c1.company_name as shipper_name, c1.customer_code as shipper_code,
           c1.contact_person as shipper_contact, c1.phone as shipper_phone, c1.email as shipper_email,
           c2.company_name as consignee_name, c2.customer_code as consignee_code,
           c2.contact_person as consignee_contact, c2.phone as consignee_phone, c2.email as consignee_email,
           u.name as created_by_name
    FROM jobs j
    LEFT JOIN customers c1 ON j.shipper_id = c1.id
    LEFT JOIN customers c2 ON j.consignee_id = c2.id
    LEFT JOIN users u ON j.created_by = u.id
    WHERE j.id = ?
", [$job_id]);

if (!$job) {
    $_SESSION['error_message'] = "Job not found.";
    redirect('jobs.php');
    exit();
}

// Get job costs
$job_costs = fetchAll("
    SELECT jc.*, v.company_name as vendor_name, v.vendor_code
    FROM job_costs jc
    LEFT JOIN vendors v ON jc.vendor_id = v.id
    WHERE jc.job_id = ?
    ORDER BY jc.created_at DESC
", [$job_id]);

// Get job selling
$job_selling = fetchAll("
    SELECT js.*, c.company_name as customer_name, c.customer_code
    FROM job_selling js
    LEFT JOIN customers c ON js.customer_id = c.id
    WHERE js.job_id = ?
    ORDER BY js.created_at DESC
", [$job_id]);

// Get job documents
$job_documents = fetchAll("
    SELECT d.*, u.name as uploaded_by_name
    FROM documents d
    LEFT JOIN users u ON d.uploaded_by = u.id
    WHERE d.job_id = ?
    ORDER BY d.uploaded_at DESC
", [$job_id]);

// Get invoices for this job
$job_invoices = fetchAll("
    SELECT i.*, c.company_name as customer_name
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    WHERE i.job_id = ?
    ORDER BY i.created_at DESC
", [$job_id]);

// Get status history
$status_history = fetchAll("
    SELECT jsh.*, u.name as changed_by_name
    FROM job_status_history jsh
    LEFT JOIN users u ON jsh.changed_by = u.id
    WHERE jsh.job_id = ?
    ORDER BY jsh.changed_at DESC
", [$job_id]);

// Calculate totals
$total_costs = array_sum(array_column($job_costs, 'amount_thb'));
$total_selling = array_sum(array_column($job_selling, 'amount_thb'));
$profit_loss = $total_selling - $total_costs;
$profit_margin = $total_selling > 0 ? ($profit_loss / $total_selling) * 100 : 0;

// Set page variables
$custom_page_title = "Job Details - " . $job['job_no'];
$page_header = true;
$page_subtitle = "Complete job information and tracking";
$breadcrumb = [
    ['name' => 'Jobs Management', 'url' => 'jobs.php'],
    ['name' => $job['job_no']]
];

// Page actions
$page_actions = '';
if (hasPermission('staff')) {
    $page_actions .= '<a href="jobs_edit.php?id=' . $job_id . '" class="btn btn-primary me-2">
                        <i class="fas fa-edit me-2"></i>Edit Job
                      </a>';
}

if ($job['status'] != 'completed') {
    $page_actions .= '<div class="btn-group me-2">
                        <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-tasks me-2"></i>Update Status
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="updateStatus(\'document_preparation\')">Document Preparation</a></li>
                            <li><a class="dropdown-item" href="#" onclick="updateStatus(\'customs_clearance\')">Customs Clearance</a></li>
                            <li><a class="dropdown-item" href="#" onclick="updateStatus(\'in_transit\')">In Transit</a></li>
                            <li><a class="dropdown-item" href="#" onclick="updateStatus(\'arrived\')">Arrived</a></li>
                            <li><a class="dropdown-item" href="#" onclick="updateStatus(\'delivered\')">Delivered</a></li>
                            <li><a class="dropdown-item" href="#" onclick="updateStatus(\'completed\')">Completed</a></li>
                        </ul>
                      </div>';
}

$page_actions .= '<button class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print
                  </button>';

include 'includes/header.php';
?>

<!-- Job Status Alert -->
<?php if ($job['status'] == 'completed'): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle me-2"></i>
    This job has been <strong>COMPLETED</strong>. All activities are finalized.
</div>
<?php elseif ($job['status'] == 'cancelled'): ?>
<div class="alert alert-danger">
    <i class="fas fa-times-circle me-2"></i>
    This job has been <strong>CANCELLED</strong>.
</div>
<?php endif; ?>

<div class="row">
    <!-- Main Content -->
    <div class="col-lg-8">
        <!-- Job Overview -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-shipping-fast me-2"></i>Job Overview
                    </h5>
                    <div>
                        <?php echo getStatusBadge($job['status']); ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" width="140">Job Number:</td>
                                <td class="text-primary fw-bold"><?php echo htmlspecialchars($job['job_no']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Job Type:</td>
                                <td>
                                    <?php
                                    $job_type_badges = [
                                        'export_air' => '<span class="badge bg-primary">Export Air</span>',
                                        'export_sea' => '<span class="badge bg-info">Export Sea</span>',
                                        'import_air' => '<span class="badge bg-warning">Import Air</span>',
                                        'import_sea' => '<span class="badge bg-success">Import Sea</span>'
                                    ];
                                    echo $job_type_badges[$job['job_type']] ?? '<span class="badge bg-secondary">Unknown</span>';
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Service Type:</td>
                                <td><?php echo strtoupper(str_replace('_', ' ', $job['service_type'])); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Origin:</td>
                                <td><?php echo htmlspecialchars($job['origin'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Destination:</td>
                                <td><?php echo htmlspecialchars($job['destination'] ?: '-'); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" width="120">ETD:</td>
                                <td><?php echo $job['etd'] ? formatDateThai($job['etd'], 'd/m/Y') : '-'; ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">ETA:</td>
                                <td><?php echo $job['eta'] ? formatDateThai($job['eta'], 'd/m/Y') : '-'; ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Delivery Date:</td>
                                <td><?php echo $job['delivery_date'] ? formatDateThai($job['delivery_date'], 'd/m/Y') : '-'; ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Vessel/Flight:</td>
                                <td><?php echo htmlspecialchars($job['vessel_flight'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Voyage No:</td>
                                <td><?php echo htmlspecialchars($job['voyage_no'] ?: '-'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php if ($job['cargo_description']): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <strong>Cargo Description:</strong><br>
                        <div class="bg-light p-3 rounded mt-2">
                            <?php echo nl2br(htmlspecialchars($job['cargo_description'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Cargo Details -->
                <div class="row mt-3">
                    <div class="col-md-3">
                        <div class="text-center">
                            <h4 class="text-primary mb-1"><?php echo $job['packages'] ?: '0'; ?></h4>
                            <small class="text-muted">Packages</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h4 class="text-info mb-1"><?php echo formatNumber($job['gross_weight'] ?: 0, 3); ?></h4>
                            <small class="text-muted">Gross Weight (KG)</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h4 class="text-warning mb-1"><?php echo formatNumber($job['volume_weight'] ?: 0, 3); ?></h4>
                            <small class="text-muted">Volume Weight (KG)</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h4 class="text-success mb-1"><?php echo formatNumber($job['cbm'] ?: 0, 3); ?></h4>
                            <small class="text-muted">CBM</small>
                        </div>
                    </div>
                </div>

                <!-- Document Numbers -->
                <?php if ($job['bl_awb_no'] || $job['container_no']): ?>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <strong>BL/AWB Number:</strong><br>
                        <span class="text-primary"><?php echo htmlspecialchars($job['bl_awb_no'] ?: '-'); ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Container Number:</strong><br>
                        <span class="text-info"><?php echo htmlspecialchars($job['container_no'] ?: '-'); ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($job['remark']): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <strong>Remarks:</strong><br>
                        <div class="bg-light p-3 rounded mt-2">
                            <?php echo nl2br(htmlspecialchars($job['remark'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Customers Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>Customers Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Shipper -->
                    <div class="col-md-6">
                        <h6 class="text-primary">
                            <i class="fas fa-arrow-up me-2"></i>Shipper
                        </h6>
                        <?php if ($job['shipper_name']): ?>
                            <div class="border p-3 rounded">
                                <div class="fw-bold"><?php echo htmlspecialchars($job['shipper_name']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($job['shipper_code']); ?></div>
                                <?php if ($job['shipper_contact']): ?>
                                    <div class="mt-2">
                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($job['shipper_contact']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($job['shipper_phone']): ?>
                                    <div>
                                        <i class="fas fa-phone me-1"></i>
                                        <a href="tel:<?php echo $job['shipper_phone']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($job['shipper_phone']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if ($job['shipper_email']): ?>
                                    <div>
                                        <i class="fas fa-envelope me-1"></i>
                                        <a href="mailto:<?php echo $job['shipper_email']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($job['shipper_email']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-2">
                                    <a href="customers_view.php?id=<?php echo $job['shipper_id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-eye me-1"></i>View Details
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-muted text-center py-3">
                                <i class="fas fa-minus-circle me-2"></i>No shipper assigned
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Consignee -->
                    <div class="col-md-6">
                        <h6 class="text-success">
                            <i class="fas fa-arrow-down me-2"></i>Consignee
                        </h6>
                        <?php if ($job['consignee_name']): ?>
                            <div class="border p-3 rounded">
                                <div class="fw-bold"><?php echo htmlspecialchars($job['consignee_name']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($job['consignee_code']); ?></div>
                                <?php if ($job['consignee_contact']): ?>
                                    <div class="mt-2">
                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($job['consignee_contact']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($job['consignee_phone']): ?>
                                    <div>
                                        <i class="fas fa-phone me-1"></i>
                                        <a href="tel:<?php echo $job['consignee_phone']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($job['consignee_phone']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if ($job['consignee_email']): ?>
                                    <div>
                                        <i class="fas fa-envelope me-1"></i>
                                        <a href="mailto:<?php echo $job['consignee_email']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($job['consignee_email']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-2">
                                    <a href="customers_view.php?id=<?php echo $job['consignee_id']; ?>" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-eye me-1"></i>View Details
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-muted text-center py-3">
                                <i class="fas fa-minus-circle me-2"></i>No consignee assigned
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calculator me-2"></i>Financial Summary
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3 mb-3">
                        <div class="border-end">
                            <h4 class="text-danger mb-1"><?php echo formatMoney($total_costs); ?></h4>
                            <small class="text-muted">Total Costs</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="border-end">
                            <h4 class="text-info mb-1"><?php echo formatMoney($total_selling); ?></h4>
                            <small class="text-muted">Total Revenue</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="border-end">
                            <h4 class="<?php echo $profit_loss >= 0 ? 'text-success' : 'text-danger'; ?> mb-1">
                                <i class="fas <?php echo $profit_loss >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?> me-1"></i>
                                <?php echo formatMoney($profit_loss); ?>
                            </h4>
                            <small class="text-muted">Profit/Loss</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <h4 class="<?php echo $profit_margin >= 0 ? 'text-success' : 'text-danger'; ?> mb-1">
                            <?php echo number_format($profit_margin, 1); ?>%
                        </h4>
                        <small class="text-muted">Profit Margin</small>
                    </div>
                </div>

                <?php if ($total_selling > 0): ?>
                <div class="mt-3">
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-danger" style="width: <?php echo ($total_costs / $total_selling) * 100; ?>%"></div>
                        <div class="progress-bar <?php echo $profit_loss >= 0 ? 'bg-success' : 'bg-warning'; ?>" 
                             style="width: <?php echo abs($profit_loss / $total_selling) * 100; ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-danger">Costs: <?php echo number_format(($total_costs / $total_selling) * 100, 1); ?>%</small>
                        <small class="<?php echo $profit_loss >= 0 ? 'text-success' : 'text-warning'; ?>">
                            <?php echo $profit_loss >= 0 ? 'Profit' : 'Loss'; ?>: <?php echo number_format(abs($profit_loss / $total_selling) * 100, 1); ?>%
                        </small>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Job Costs -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-money-bill-wave me-2"></i>Job Costs
                        <span class="badge bg-danger ms-2"><?php echo count($job_costs); ?> items</span>
                    </h5>
                    <?php if (hasPermission('staff')): ?>
                        <a href="job_costs.php?job_id=<?php echo $job_id; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-plus me-1"></i>Manage Costs
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($job_costs)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-money-bill fa-3x mb-3 d-block"></i>
                        <h6>No Costs Recorded</h6>
                        <p class="mb-0">No costs have been added to this job yet.</p>
                        <?php if (hasPermission('staff')): ?>
                            <a href="job_costs.php?job_id=<?php echo $job_id; ?>" class="btn btn-primary mt-3">
                                <i class="fas fa-plus me-2"></i>Add First Cost
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Vendor</th>
                                    <th>Amount</th>
                                    <th>Currency</th>
                                    <th>Amount (THB)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($job_costs as $cost): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo strtoupper(str_replace('_', ' ', $cost['cost_type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($cost['description']); ?></td>
                                    <td>
                                        <?php if ($cost['vendor_name']): ?>
                                            <?php echo htmlspecialchars($cost['vendor_name']); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($cost['vendor_code']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?php echo formatNumber($cost['amount'], 2); ?></td>
                                    <td><?php echo $cost['currency']; ?></td>
                                    <td class="text-end fw-bold text-danger"><?php echo formatNumber($cost['amount_thb'], 2); ?></td>
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
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-danger">
                                    <td colspan="5" class="text-end fw-bold">Total Costs:</td>
                                    <td class="text-end fw-bold"><?php echo formatNumber($total_costs, 2); ?></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Job Selling -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-hand-holding-usd me-2"></i>Job Selling Prices
                        <span class="badge bg-info ms-2"><?php echo count($job_selling); ?> items</span>
                    </h5>
                    <?php if (hasPermission('staff')): ?>
                        <a href="job_selling.php?job_id=<?php echo $job_id; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-plus me-1"></i>Manage Selling
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($job_selling)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-hand-holding-usd fa-3x mb-3 d-block"></i>
                        <h6>No Selling Prices Set</h6>
                        <p class="mb-0">No selling prices have been configured for this job yet.</p>
                        <?php if (hasPermission('staff')): ?>
                            <a href="job_selling.php?job_id=<?php echo $job_id; ?>" class="btn btn-info mt-3">
                                <i class="fas fa-plus me-2"></i>Add Selling Price
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Currency</th>
                                    <th>Amount (THB)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($job_selling as $selling): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo strtoupper(str_replace('_', ' ', $selling['selling_type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($selling['description']); ?></td>
                                    <td>
                                        <?php if ($selling['customer_name']): ?>
                                            <?php echo htmlspecialchars($selling['customer_name']); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($selling['customer_code']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?php echo formatNumber($selling['amount'], 2); ?></td>
                                    <td><?php echo $selling['currency']; ?></td>
                                    <td class="text-end fw-bold text-info"><?php echo formatNumber($selling['amount_thb'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-info">
                                    <td colspan="5" class="text-end fw-bold">Total Revenue:</td>
                                    <td class="text-end fw-bold"><?php echo formatNumber($total_selling, 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Documents -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-file-alt me-2"></i>Documents
                        <span class="badge bg-secondary ms-2"><?php echo count($job_documents); ?> files</span>
                    </h5>
                    <?php if (hasPermission('staff')): ?>
                        <button class="btn btn-outline-primary btn-sm" onclick="uploadDocument()">
                            <i class="fas fa-upload me-1"></i>Upload Document
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($job_documents)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-file-alt fa-3x mb-3 d-block"></i>
                        <h6>No Documents Uploaded</h6>
                        <p class="mb-0">No documents have been uploaded for this job yet.</p>
                        <?php if (hasPermission('staff')): ?>
                            <button class="btn btn-secondary mt-3" onclick="uploadDocument()">
                                <i class="fas fa-upload me-2"></i>Upload First Document
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($job_documents as $doc): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card border">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <i class="fas fa-file me-2"></i>
                                                <?php echo htmlspecialchars($doc['document_name']); ?>
                                            </h6>
                                            <div class="text-muted small">
                                                <span class="badge bg-info">
                                                    <?php echo strtoupper(str_replace('_', ' ', $doc['document_type'])); ?>
                                                </span>
                                                <?php if ($doc['is_original']): ?>
                                                    <span class="badge bg-warning">Original</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-muted small mt-1">
                                                Uploaded: <?php echo formatDateThai($doc['uploaded_at'], 'd/m/Y H:i'); ?>
                                                <br>by <?php echo htmlspecialchars($doc['uploaded_by_name'] ?: 'System'); ?>
                                                <?php if ($doc['file_size']): ?>
                                                    <br>Size: <?php echo formatFileSize($doc['file_size']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <a href="document_download.php?id=<?php echo $doc['id']; ?>" 
                                               class="btn btn-outline-primary" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <?php if (hasPermission('staff')): ?>
                                            <a href="#" class="btn btn-outline-danger" 
                                               onclick="deleteDocument(<?php echo $doc['id']; ?>)" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Invoices -->
        <?php if (!empty($job_invoices)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-receipt me-2"></i>Related Invoices
                        <span class="badge bg-success ms-2"><?php echo count($job_invoices); ?> invoices</span>
                    </h5>
                    <?php if (hasPermission('staff')): ?>
                        <a href="invoices_add.php?job_id=<?php echo $job_id; ?>" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-plus me-1"></i>Create Invoice
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice No.</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Due Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($job_invoices as $invoice): ?>
                            <tr>
                                <td>
                                    <a href="invoices_view.php?id=<?php echo $invoice['id']; ?>" class="text-decoration-none fw-bold">
                                        <?php echo htmlspecialchars($invoice['invoice_no']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                                <td><?php echo formatDateThai($invoice['invoice_date'], 'd/m/Y'); ?></td>
                                <td>
                                    <span class="<?php echo (strtotime($invoice['due_date']) < time() && $invoice['payment_status'] != 'paid') ? 'text-danger' : 'text-muted'; ?>">
                                        <?php echo formatDateThai($invoice['due_date'], 'd/m/Y'); ?>
                                    </span>
                                </td>
                                <td class="text-end"><?php echo formatMoney($invoice['total_amount']); ?></td>
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
                    <a href="jobs_edit.php?id=<?php echo $job_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i>Edit Job Details
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($job['status'] != 'completed'): ?>
                    <button class="btn btn-success" onclick="showStatusModal()">
                        <i class="fas fa-tasks me-2"></i>Update Status
                    </button>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('staff')): ?>
                    <a href="job_costs.php?job_id=<?php echo $job_id; ?>" class="btn btn-outline-danger">
                        <i class="fas fa-money-bill me-2"></i>Manage Costs
                    </a>
                    <a href="job_selling.php?job_id=<?php echo $job_id; ?>" class="btn btn-outline-info">
                        <i class="fas fa-hand-holding-usd me-2"></i>Manage Selling
                    </a>
                    <a href="invoices_add.php?job_id=<?php echo $job_id; ?>" class="btn btn-outline-success">
                        <i class="fas fa-receipt me-2"></i>Create Invoice
                    </a>
                    <?php endif; ?>
                    
                    <button class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Job Details
                    </button>
                    
                    <button class="btn btn-outline-secondary" onclick="copyJobInfo()">
                        <i class="fas fa-copy me-2"></i>Copy Job Info
                    </button>
                </div>
            </div>
        </div>

        <!-- Job Progress -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>Job Progress
                </h6>
            </div>
            <div class="card-body">
                <div class="job-progress">
                    <?php
                    $statuses = [
                        'booking' => 'Booking',
                        'document_preparation' => 'Document Prep',
                        'customs_clearance' => 'Customs',
                        'in_transit' => 'In Transit',
                        'arrived' => 'Arrived',
                        'delivered' => 'Delivered',
                        'completed' => 'Completed'
                    ];
                    
                    $current_index = array_search($job['status'], array_keys($statuses));
                    $status_count = count($statuses);
                    $progress_percentage = $current_index !== false ? (($current_index + 1) / $status_count) * 100 : 0;
                    ?>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Progress</small>
                            <small class="text-muted"><?php echo number_format($progress_percentage, 0); ?>%</small>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-primary" style="width: <?php echo $progress_percentage; ?>%"></div>
                        </div>
                    </div>
                    
                    <?php foreach ($statuses as $status_key => $status_name): ?>
                        <?php 
                        $is_current = ($status_key == $job['status']);
                        $is_completed = (array_search($status_key, array_keys($statuses)) < $current_index);
                        ?>
                        <div class="status-item d-flex align-items-center mb-2">
                            <div class="status-icon me-3">
                                <?php if ($is_completed): ?>
                                    <i class="fas fa-check-circle text-success"></i>
                                <?php elseif ($is_current): ?>
                                    <i class="fas fa-circle text-primary"></i>
                                <?php else: ?>
                                    <i class="fas fa-circle text-muted"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <div class="<?php echo $is_current ? 'fw-bold text-primary' : ($is_completed ? 'text-success' : 'text-muted'); ?>">
                                    <?php echo $status_name; ?>
                                </div>
                            </div>
                            <?php if ($is_current): ?>
                                <span class="badge bg-primary">Current</span>
                            <?php elseif ($is_completed): ?>
                                <i class="fas fa-check text-success"></i>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Status History -->
        <?php if (!empty($status_history)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>Status History
                </h6>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php foreach ($status_history as $history): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-primary"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">
                                <?php echo getStatusText($history['new_status']); ?>
                            </h6>
                            <p class="timeline-text">
                                <?php echo formatDateThai($history['changed_at'], 'd/m/Y H:i'); ?>
                                <br><small class="text-muted">by <?php echo htmlspecialchars($history['changed_by_name'] ?: 'System'); ?></small>
                                <?php if ($history['remark']): ?>
                                    <br><small class="text-info"><?php echo htmlspecialchars($history['remark']); ?></small>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Job Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>Job Information
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Created:</strong><br>
                    <small class="text-muted">
                        <?php echo formatDateThai($job['created_at'], 'd/m/Y H:i'); ?>
                        <?php if ($job['created_by_name']): ?>
                            <br>by <?php echo htmlspecialchars($job['created_by_name']); ?>
                        <?php endif; ?>
                    </small>
                </div>
                
                <div class="mb-3">
                    <strong>Last Updated:</strong><br>
                    <small class="text-muted">
                        <?php echo formatDateThai($job['updated_at'], 'd/m/Y H:i'); ?>
                    </small>
                </div>
                
                <div class="mb-3">
                    <strong>Current Status:</strong><br>
                    <?php echo getStatusBadge($job['status']); ?>
                </div>
                
                <?php if ($profit_loss < 0): ?>
                <div class="alert alert-warning py-2">
                    <small>
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Loss Alert:</strong><br>
                        This job is currently showing a loss of <?php echo formatMoney(abs($profit_loss)); ?>
                    </small>
                </div>
                <?php endif; ?>
                
                <?php if ($job['status'] == 'completed'): ?>
                <div class="alert alert-success py-2">
                    <small>
                        <i class="fas fa-check-circle me-1"></i>
                        <strong>Job Completed</strong><br>
                        All activities for this job have been finalized.
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Related Jobs (if any) -->
        <?php 
        $related_jobs = fetchAll("
            SELECT id, job_no, job_type, status 
            FROM jobs 
            WHERE (shipper_id = ? OR consignee_id = ?) 
            AND id != ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ", [$job['shipper_id'], $job['consignee_id'], $job_id]);
        ?>
        
        <?php if (!empty($related_jobs)): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-link me-2"></i>Related Jobs
                </h6>
            </div>
            <div class="card-body">
                <?php foreach ($related_jobs as $related): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <a href="jobs_view.php?id=<?php echo $related['id']; ?>" class="text-decoration-none fw-bold">
                            <?php echo htmlspecialchars($related['job_no']); ?>
                        </a>
                        <br><small class="text-muted"><?php echo strtoupper(str_replace('_', ' ', $related['job_type'])); ?></small>
                    </div>
                    <div>
                        <?php echo getStatusBadge($related['status']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Job Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="statusForm">
                    <div class="mb-3">
                        <label for="new_status" class="form-label">New Status</label>
                        <select class="form-select" id="new_status" name="new_status" required>
                            <option value="">Select New Status</option>
                            <option value="booking">Booking</option>
                            <option value="document_preparation">Document Preparation</option>
                            <option value="customs_clearance">Customs Clearance</option>
                            <option value="in_transit">In Transit</option>
                            <option value="arrived">Arrived</option>
                            <option value="delivered">Delivered</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="status_remark" class="form-label">Remarks (Optional)</label>
                        <textarea class="form-control" id="status_remark" name="status_remark" rows="3"
                                  placeholder="Add any notes about this status change"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitStatusUpdate()">Update Status</button>
            </div>
        </div>
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

/* Job Progress Styles */
.job-progress .status-item {
    transition: all 0.3s ease;
}

.job-progress .status-item:hover {
    background-color: #f8f9fa;
    border-radius: 5px;
    padding: 5px;
}

/* Print Styles */
@media print {
    .btn, .card-header, .timeline::before, .timeline-marker, .modal {
        display: none !important;
    }
    
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        break-inside: avoid;
    }
    
    .page-actions, .sidebar {
        display: none !important;
    }
    
    .main-content {
        width: 100% !important;
        max-width: none !important;
    }
}
</style>

<script>
// Show status update modal
function showStatusModal() {
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
}

// Submit status update
function submitStatusUpdate() {
    const newStatus = document.getElementById('new_status').value;
    const remark = document.getElementById('status_remark').value;
    
    if (!newStatus) {
        alert('Please select a new status');
        return;
    }
    
    // Show loading
    const submitBtn = document.querySelector('#statusModal .btn-primary');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
    submitBtn.disabled = true;
    
    // Make AJAX request
    fetch('ajax/update_job_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            job_id: <?php echo $job_id; ?>,
            new_status: newStatus,
            remark: remark
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload(); // Reload page to show updated status
        } else {
            alert('Error updating status: ' + data.message);
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating status. Please try again.');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// Quick status update function
function updateStatus(status) {
    if (confirm(`Are you sure you want to update the job status to "${status.replace('_', ' ').toUpperCase()}"?`)) {
        fetch('ajax/update_job_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                job_id: <?php echo $job_id; ?>,
                new_status: status,
                remark: 'Quick status update'
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
            alert('Error updating status. Please try again.');
        });
    }
}

// Copy job information to clipboard
function copyJobInfo() {
    const jobInfo = `
Job Number: <?php echo $job['job_no']; ?>
Job Type: <?php echo strtoupper(str_replace('_', ' ', $job['job_type'])); ?>
Status: <?php echo getStatusText($job['status']); ?>
Origin: <?php echo $job['origin']; ?>
Destination: <?php echo $job['destination']; ?>
Shipper: <?php echo $job['shipper_name'] ?: 'Not assigned'; ?>
Consignee: <?php echo $job['consignee_name'] ?: 'Not assigned'; ?>
Total Cost: <?php echo formatMoney($total_costs); ?>
Total Revenue: <?php echo formatMoney($total_selling); ?>
Profit/Loss: <?php echo formatMoney($profit_loss); ?>
    `.trim();
    
    navigator.clipboard.writeText(jobInfo).then(function() {
        alert('Job information copied to clipboard!');
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
    });
}

// Upload document function
function uploadDocument() {
    // This would open a file upload modal or redirect to upload page
    window.location.href = 'document_upload.php?job_id=<?php echo $job_id; ?>';
}

// Delete document function
function deleteDocument(documentId) {
    if (confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
        fetch('ajax/delete_document.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                document_id: documentId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting document: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting document. Please try again.');
        });
    }
}

// Auto-refresh page every 5 minutes (for real-time updates)
setTimeout(function() {
    location.reload();
}, 300000); // 5 minutes

// Format file size helper function
<?php
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.1f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
}
?>

// Print functionality
function printJobDetails() {
    window.print();
}

// Smooth scroll for internal anchors
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
</script>

<?php include 'includes/footer.php'; ?>