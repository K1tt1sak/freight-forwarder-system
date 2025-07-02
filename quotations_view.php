<?php
// =====================================================
// quotations_view.php - View Quotation Details
// =====================================================

// Include functions first
require_once 'includes/functions.php';

// Get quotation ID
$quotation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$quotation_id) {
    $_SESSION['error_message'] = "Quotation ID is required.";
    redirect('quotations.php');
}

// Get quotation data with related information
$quotation = fetchOne("
    SELECT q.*, 
           c.customer_code, c.company_name, c.contact_person, c.phone, c.email, 
           c.address, c.tax_id, c.credit_term, c.credit_limit,
           u.name as created_by_name
    FROM quotations q
    LEFT JOIN customers c ON q.customer_id = c.id
    LEFT JOIN users u ON q.created_by = u.id
    WHERE q.id = ?
", [$quotation_id]);

if (!$quotation) {
    $_SESSION['error_message'] = "Quotation not found.";
    redirect('quotations.php');
}

// Get quotation items
$quotation_items = fetchAll("
    SELECT * FROM quotation_items 
    WHERE quotation_id = ? 
    ORDER BY id
", [$quotation_id]);

// Check if quotation has expired
$is_expired = strtotime($quotation['valid_until']) < time() && in_array($quotation['status'], ['draft', 'sent']);
$display_status = $is_expired ? 'expired' : $quotation['status'];

// Get related data
$related_jobs = fetchAll("
    SELECT j.id, j.job_no, j.status, j.created_at
    FROM jobs j
    WHERE j.quotation_id = ? OR (j.customer_id = ? AND j.created_at >= ?)
    ORDER BY j.created_at DESC
    LIMIT 5
", [$quotation_id, $quotation['customer_id'], $quotation['quotation_date']]);

// Calculate quotation summary
$quotation_summary = [
    'subtotal' => array_sum(array_column($quotation_items, 'amount')),
    'item_count' => count($quotation_items),
    'avg_item_value' => count($quotation_items) > 0 ? array_sum(array_column($quotation_items, 'amount')) / count($quotation_items) : 0,
    'days_valid' => max(0, floor((strtotime($quotation['valid_until']) - time()) / 86400)),
    'age_days' => floor((time() - strtotime($quotation['quotation_date'])) / 86400)
];

$custom_page_title = "Quotation Details - " . $quotation['quotation_no'];
$page_header = true;
$page_subtitle = "View complete quotation information and status";
$breadcrumb = [
    ['name' => 'Quotations', 'url' => 'quotations.php'],
    ['name' => $quotation['quotation_no']]
];

// Page actions based on status and permissions
$page_actions = '';

if (hasPermission('staff')) {
    // Edit action (only for draft and sent status)
    if (in_array($quotation['status'], ['draft', 'sent'])) {
        $page_actions .= '<a href="quotations_edit.php?id=' . $quotation_id . '" class="btn btn-primary me-2">
                            <i class="fas fa-edit me-2"></i>Edit Quotation
                          </a>';
    }
    
    // Convert to Job action (only for accepted quotations)
    if ($quotation['status'] == 'accepted') {
        $page_actions .= '<a href="jobs_add.php?quotation_id=' . $quotation_id . '" class="btn btn-success me-2">
                            <i class="fas fa-shipping-fast me-2"></i>Convert to Job
                          </a>';
    }
    
    // Duplicate action
    $page_actions .= '<a href="quotations_add.php?duplicate_from=' . $quotation_id . '" class="btn btn-outline-info me-2">
                        <i class="fas fa-copy me-2"></i>Duplicate
                      </a>';
}

// Print and export actions
$page_actions .= '<div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-print me-2"></i>Print & Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="printQuotation()">
                            <i class="fas fa-print me-2"></i>Print Quotation
                        </a></li>
                        <li><a class="dropdown-item" href="templates/quotation_pdf.php?id=<?php echo $quotation_id; ?>" target="_blank">
                            <i class="fas fa-file-pdf me-2"></i>Download PDF
                        </a></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="emailQuotation()">
                            <i class="fas fa-envelope me-2"></i>Email to Customer
                        </a></li>
                    </ul>
                  </div>';

include 'includes/header.php';
?>

<!-- Status Alert -->
<?php if ($is_expired): ?>
<div class="alert alert-warning">
    <i class="fas fa-clock me-2"></i>
    This quotation has <strong>expired</strong> on <?php echo formatDateThai($quotation['valid_until'], 'd/m/Y'); ?>. 
    <?php if (hasPermission('staff')): ?>
        <a href="quotations_edit.php?id=<?php echo $quotation_id; ?>" class="alert-link">Update validity period</a> or 
        <a href="quotations_add.php?duplicate_from=<?php echo $quotation_id; ?>" class="alert-link">create new quotation</a>.
    <?php endif; ?>
</div>
<?php elseif ($quotation['status'] == 'rejected'): ?>
<div class="alert alert-danger">
    <i class="fas fa-times-circle me-2"></i>
    This quotation has been <strong>rejected</strong> by the customer.
</div>
<?php elseif ($quotation['status'] == 'accepted'): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle me-2"></i>
    This quotation has been <strong>accepted</strong> by the customer.
    <?php if (hasPermission('staff')): ?>
        <a href="jobs_add.php?quotation_id=<?php echo $quotation_id; ?>" class="alert-link">Convert to job now</a>.
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Quotation Summary Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-primary mb-2">
                    <i class="fas fa-dollar-sign fa-2x"></i>
                </div>
                <h4 class="mb-1 text-primary"><?php echo formatMoney($quotation_summary['subtotal'], $quotation['currency']); ?></h4>
                <small class="text-muted">Total Amount</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-info mb-2">
                    <i class="fas fa-list fa-2x"></i>
                </div>
                <h4 class="mb-1 text-info"><?php echo $quotation_summary['item_count']; ?></h4>
                <small class="text-muted">Items</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="<?php echo $quotation_summary['days_valid'] > 7 ? 'text-success' : ($quotation_summary['days_valid'] > 0 ? 'text-warning' : 'text-danger'); ?> mb-2">
                    <i class="fas fa-calendar-alt fa-2x"></i>
                </div>
                <h4 class="mb-1 <?php echo $quotation_summary['days_valid'] > 7 ? 'text-success' : ($quotation_summary['days_valid'] > 0 ? 'text-warning' : 'text-danger'); ?>">
                    <?php echo $quotation_summary['days_valid']; ?>
                </h4>
                <small class="text-muted">Days Remaining</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-secondary mb-2">
                    <i class="fas fa-history fa-2x"></i>
                </div>
                <h4 class="mb-1 text-secondary"><?php echo $quotation_summary['age_days']; ?></h4>
                <small class="text-muted">Days Old</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Main Content -->
    <div class="col-lg-8">
        <!-- Quotation Information -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Quotation Information
                    </h5>
                    <div>
                        <?php
                        $status_badges = [
                            'draft' => '<span class="badge bg-secondary fs-6">Draft</span>',
                            'sent' => '<span class="badge bg-info fs-6">Sent</span>',
                            'accepted' => '<span class="badge bg-success fs-6">Accepted</span>',
                            'rejected' => '<span class="badge bg-danger fs-6">Rejected</span>',
                            'expired' => '<span class="badge bg-warning fs-6">Expired</span>'
                        ];
                        echo $status_badges[$display_status] ?? '<span class="badge bg-secondary fs-6">Unknown</span>';
                        ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" width="140">Quotation No:</td>
                                <td><?php echo htmlspecialchars($quotation['quotation_no']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Customer:</td>
                                <td>
                                    <a href="customers_view.php?id=<?php echo $quotation['customer_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($quotation['customer_code'] . ' - ' . $quotation['company_name']); ?>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Job Type:</td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo strtoupper(str_replace('_', ' ', $quotation['job_type'])); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Service Type:</td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $quotation['service_type'])); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Currency:</td>
                                <td><strong><?php echo $quotation['currency']; ?></strong></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" width="120">Date:</td>
                                <td><?php echo formatDateThai($quotation['quotation_date'], 'd/m/Y'); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Valid Until:</td>
                                <td>
                                    <span class="<?php echo $is_expired ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo formatDateThai($quotation['valid_until'], 'd/m/Y'); ?>
                                        <?php if ($is_expired): ?>
                                            <i class="fas fa-exclamation-triangle ms-1"></i>
                                        <?php endif; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Route:</td>
                                <td>
                                    <?php if ($quotation['origin'] || $quotation['destination']): ?>
                                        <?php echo htmlspecialchars($quotation['origin'] ?: 'TBD'); ?> 
                                        <i class="fas fa-arrow-right mx-2"></i> 
                                        <?php echo htmlspecialchars($quotation['destination'] ?: 'TBD'); ?>
                                    <?php else: ?>
                                        <span class="text-muted">To be determined</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Created By:</td>
                                <td><?php echo htmlspecialchars($quotation['created_by_name'] ?: 'System'); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Created:</td>
                                <td><?php echo formatDateThai($quotation['created_at'], 'd/m/Y H:i'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($quotation['cargo_description']): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <strong>Cargo Description:</strong><br>
                        <div class="bg-light p-3 rounded mt-2">
                            <?php echo nl2br(htmlspecialchars($quotation['cargo_description'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quotation Items -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>Quotation Items
                    <span class="badge bg-secondary ms-2"><?php echo count($quotation_items); ?> items</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="15%">Type</th>
                                <th width="35%">Description</th>
                                <th width="10%" class="text-center">Unit</th>
                                <th width="10%" class="text-center">Qty</th>
                                <th width="15%" class="text-end">Unit Price</th>
                                <th width="15%" class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($quotation_items)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                    No items found in this quotation
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($quotation_items as $item): ?>
                            <tr>
                                <td>
                                    <?php
                                    $type_badges = [
                                        'freight' => '<span class="badge bg-primary">Freight</span>',
                                        'local_charge' => '<span class="badge bg-info">Local Charge</span>',
                                        'customs' => '<span class="badge bg-warning">Customs</span>',
                                        'trucking' => '<span class="badge bg-success">Trucking</span>',
                                        'documentation' => '<span class="badge bg-secondary">Documentation</span>',
                                        'service_fee' => '<span class="badge bg-dark">Service Fee</span>',
                                        'other' => '<span class="badge bg-light text-dark">Other</span>'
                                    ];
                                    echo $type_badges[$item['item_type']] ?? '<span class="badge bg-secondary">Unknown</span>';
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                                <td class="text-center">
                                    <small class="text-muted"><?php echo htmlspecialchars($item['unit'] ?: 'per shipment'); ?></small>
                                </td>
                                <td class="text-center"><?php echo formatNumber($item['quantity'], 0); ?></td>
                                <td class="text-end"><?php echo formatMoney($item['unit_price'], $item['currency']); ?></td>
                                <td class="text-end"><strong><?php echo formatMoney($item['amount'], $item['currency']); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($quotation_items)): ?>
                        <tfoot class="table-info">
                            <tr>
                                <td colspan="5" class="text-end"><strong>Total Amount:</strong></td>
                                <td class="text-end">
                                    <h5 class="mb-0 text-primary">
                                        <?php echo formatMoney($quotation['total_amount'], $quotation['currency']); ?>
                                    </h5>
                                </td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Customer Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-building me-2"></i>Customer Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td class="fw-bold" width="120">Company:</td>
                                <td><?php echo htmlspecialchars($quotation['company_name']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Contact:</td>
                                <td><?php echo htmlspecialchars($quotation['contact_person'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Phone:</td>
                                <td>
                                    <?php if ($quotation['phone']): ?>
                                        <a href="tel:<?php echo $quotation['phone']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($quotation['phone']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Email:</td>
                                <td>
                                    <?php if ($quotation['email']): ?>
                                        <a href="mailto:<?php echo $quotation['email']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($quotation['email']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td class="fw-bold" width="120">Tax ID:</td>
                                <td><?php echo htmlspecialchars($quotation['tax_id'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Credit Term:</td>
                                <td><?php echo $quotation['credit_term']; ?> days</td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Credit Limit:</td>
                                <td><?php echo formatMoney($quotation['credit_limit']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">View Details:</td>
                                <td>
                                    <a href="customers_view.php?id=<?php echo $quotation['customer_id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-user me-1"></i>Customer Profile
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($quotation['address']): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <strong>Address:</strong><br>
                        <div class="bg-light p-3 rounded mt-2">
                            <?php echo nl2br(htmlspecialchars($quotation['address'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($quotation['remark']): ?>
        <!-- Remarks -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-comment me-2"></i>Remarks
                </h5>
            </div>
            <div class="card-body">
                <div class="bg-light p-3 rounded">
                    <?php echo nl2br(htmlspecialchars($quotation['remark'])); ?>
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
                    <?php if (hasPermission('staff') && in_array($quotation['status'], ['draft', 'sent'])): ?>
                    <a href="quotations_edit.php?id=<?php echo $quotation_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i>Edit Quotation
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('staff') && $quotation['status'] == 'accepted'): ?>
                    <a href="jobs_add.php?quotation_id=<?php echo $quotation_id; ?>" class="btn btn-success">
                        <i class="fas fa-shipping-fast me-2"></i>Convert to Job
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('staff')): ?>
                    <a href="quotations_add.php?duplicate_from=<?php echo $quotation_id; ?>" class="btn btn-info">
                        <i class="fas fa-copy me-2"></i>Duplicate Quotation
                    </a>
                    
                    <?php if ($quotation['status'] == 'draft'): ?>
                    <button class="btn btn-outline-primary" onclick="markAsSent()">
                        <i class="fas fa-paper-plane me-2"></i>Mark as Sent
                    </button>
                    <?php endif; ?>
                    
                    <?php if (in_array($quotation['status'], ['sent'])): ?>
                    <div class="btn-group w-100">
                        <button class="btn btn-outline-success" onclick="markAsAccepted()">
                            <i class="fas fa-check me-1"></i>Accept
                        </button>
                        <button class="btn btn-outline-danger" onclick="markAsRejected()">
                            <i class="fas fa-times me-1"></i>Reject
                        </button>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <button class="btn btn-outline-secondary" onclick="printQuotation()">
                        <i class="fas fa-print me-2"></i>Print Quotation
                    </button>
                    
                    <?php if ($quotation['email']): ?>
                    <button class="btn btn-outline-info" onclick="emailQuotation()">
                        <i class="fas fa-envelope me-2"></i>Email to Customer
                    </button>
                    <?php endif; ?>
                    
                    <a href="quotations.php" class="btn btn-outline-secondary">
                        <i class="fas fa-list me-2"></i>Back to List
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Quotation Summary -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>Summary
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Total Items:</span>
                        <strong><?php echo $quotation_summary['item_count']; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Average Item Value:</span>
                        <strong><?php echo formatMoney($quotation_summary['avg_item_value'], $quotation['currency']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Currency:</span>
                        <strong><?php echo $quotation['currency']; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Age:</span>
                        <strong><?php echo $quotation_summary['age_days']; ?> days</strong>
                    </div>
                </div>
                
                <hr>
                
                <div class="mb-2">
                    <strong>Validity Status:</strong>
                </div>
                <?php if ($is_expired): ?>
                    <div class="alert alert-danger py-2 mb-0">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <small><strong>Expired</strong><br>
                        Expired <?php echo abs($quotation_summary['days_valid']); ?> days ago</small>
                    </div>
                <?php elseif ($quotation_summary['days_valid'] <= 7): ?>
                    <div class="alert alert-warning py-2 mb-0">
                        <i class="fas fa-clock me-1"></i>
                        <small><strong>Expiring Soon</strong><br>
                        <?php echo $quotation_summary['days_valid']; ?> days remaining</small>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success py-2 mb-0">
                        <i class="fas fa-check-circle me-1"></i>
                        <small><strong>Valid</strong><br>
                        <?php echo $quotation_summary['days_valid']; ?> days remaining</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Related Jobs -->
        <?php if (!empty($related_jobs)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-shipping-fast me-2"></i>Related Jobs
                </h6>
            </div>
            <div class="card-body">
                <?php foreach ($related_jobs as $job): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <a href="jobs_view.php?id=<?php echo $job['id']; ?>" class="text-decoration-none fw-bold">
                            <?php echo htmlspecialchars($job['job_no']); ?>
                        </a>
                        <br><small class="text-muted"><?php echo formatDateThai($job['created_at'], 'd/m/Y'); ?></small>
                    </div>
                    <div>
                        <?php echo getStatusBadge($job['status']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="mt-3">
                    <a href="jobs.php?customer_id=<?php echo $quotation['customer_id']; ?>" class="btn btn-outline-primary btn-sm w-100">
                        <i class="fas fa-list me-2"></i>View All Customer Jobs
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Quotation Timeline -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>Quotation Timeline
                </h6>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">Quotation Created</h6>
                            <p class="timeline-text">
                                <?php echo formatDateThai($quotation['created_at'], 'd/m/Y H:i'); ?>
                                <br><small class="text-muted">by <?php echo htmlspecialchars($quotation['created_by_name'] ?: 'System'); ?></small>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($quotation['status'] != 'draft'): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-info"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">Status Updated</h6>
                            <p class="timeline-text">
                                Current status: <strong><?php echo ucfirst($quotation['status']); ?></strong>
                                <br><small class="text-muted"><?php echo formatDateThai($quotation['updated_at'], 'd/m/Y H:i'); ?></small>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($is_expired): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-warning"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">Quotation Expired</h6>
                            <p class="timeline-text">
                                <?php echo formatDateThai($quotation['valid_until'], 'd/m/Y'); ?>
                                <br><small class="text-muted">Validity period ended</small>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($quotation['status'] == 'accepted'): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">Quotation Accepted</h6>
                            <p class="timeline-text">
                                Customer accepted the quotation
                                <br><small class="text-muted">Ready for job conversion</small>
                            </p>
                        </div>
                    </div>
                    <?php elseif ($quotation['status'] == 'rejected'): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-danger"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">Quotation Rejected</h6>
                            <p class="timeline-text">
                                Customer rejected the quotation
                                <br><small class="text-muted">Consider creating new quotation</small>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Email Modal -->
<div class="modal fade" id="emailModal" tabindex="-1" aria-labelledby="emailModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="emailModalLabel">
                    <i class="fas fa-envelope me-2"></i>Email Quotation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="emailForm">
                    <div class="mb-3">
                        <label for="email_to" class="form-label">To:</label>
                        <input type="email" class="form-control" id="email_to" value="<?php echo htmlspecialchars($quotation['email'] ?: ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email_cc" class="form-label">CC:</label>
                        <input type="email" class="form-control" id="email_cc" placeholder="Additional recipients (optional)">
                    </div>
                    <div class="mb-3">
                        <label for="email_subject" class="form-label">Subject:</label>
                        <input type="text" class="form-control" id="email_subject" value="Quotation <?php echo htmlspecialchars($quotation['quotation_no']); ?> - <?php echo htmlspecialchars($quotation['company_name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email_message" class="form-label">Message:</label>
                        <textarea class="form-control" id="email_message" rows="5" required>Dear <?php echo htmlspecialchars($quotation['contact_person'] ?: 'Valued Customer'); ?>,

Please find attached our quotation <?php echo htmlspecialchars($quotation['quotation_no']); ?> for your freight requirements.

This quotation is valid until <?php echo formatDateThai($quotation['valid_until'], 'd/m/Y'); ?>.

Should you have any questions, please don't hesitate to contact us.

Best regards,
<?php echo htmlspecialchars($_SESSION['user_name']); ?></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="sendEmail()">
                    <i class="fas fa-paper-plane me-2"></i>Send Email
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Print quotation
function printQuotation() {
    const printWindow = window.open('', '_blank');
    const quotationContent = generatePrintContent();
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Quotation ${<?php echo json_encode($quotation['quotation_no']); ?>}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; line-height: 1.4; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
                .company-info { margin-bottom: 20px; }
                .quotation-info { background: #f8f9fa; padding: 15px; margin: 20px 0; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f8f9fa; font-weight: bold; }
                .text-right { text-align: right; }
                .text-center { text-align: center; }
                .total-row { background-color: #e3f2fd; font-weight: bold; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
                @media print {
                    body { margin: 0; padding: 15px; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            ${quotationContent}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}

// Generate print content
function generatePrintContent() {
    const quotationData = <?php echo json_encode([
        'quotation_no' => $quotation['quotation_no'],
        'company_name' => $quotation['company_name'],
        'customer_code' => $quotation['customer_code'],
        'contact_person' => $quotation['contact_person'],
        'phone' => $quotation['phone'],
        'email' => $quotation['email'],
        'address' => $quotation['address'],
        'quotation_date' => formatDateThai($quotation['quotation_date'], 'd/m/Y'),
        'valid_until' => formatDateThai($quotation['valid_until'], 'd/m/Y'),
        'job_type' => strtoupper(str_replace('_', ' ', $quotation['job_type'])),
        'service_type' => ucfirst(str_replace('_', ' ', $quotation['service_type'])),
        'origin' => $quotation['origin'],
        'destination' => $quotation['destination'],
        'cargo_description' => $quotation['cargo_description'],
        'total_amount' => $quotation['total_amount'],
        'currency' => $quotation['currency'],
        'remark' => $quotation['remark']
    ]); ?>;
    
    const items = <?php echo json_encode(array_map(function($item) {
        return [
            'item_type' => ucfirst(str_replace('_', ' ', $item['item_type'])),
            'description' => $item['description'],
            'unit' => $item['unit'] ?: 'per shipment',
            'quantity' => formatNumber($item['quantity'], 0),
            'unit_price' => formatMoney($item['unit_price'], $item['currency']),
            'amount' => formatMoney($item['amount'], $item['currency'])
        ];
    }, $quotation_items)); ?>;
    
    let itemsHtml = '';
    items.forEach(item => {
        itemsHtml += `
            <tr>
                <td>${item.item_type}</td>
                <td>${item.description}</td>
                <td class="text-center">${item.unit}</td>
                <td class="text-center">${item.quantity}</td>
                <td class="text-right">${item.unit_price}</td>
                <td class="text-right">${item.amount}</td>
            </tr>
        `;
    });
    
    return `
        <div class="header">
            <h1>FREIGHT QUOTATION</h1>
            <h2>${quotationData.quotation_no}</h2>
        </div>
        
        <div class="quotation-info">
            <div style="display: flex; justify-content: space-between;">
                <div>
                    <strong>Date:</strong> ${quotationData.quotation_date}<br>
                    <strong>Valid Until:</strong> ${quotationData.valid_until}<br>
                    <strong>Job Type:</strong> ${quotationData.job_type}<br>
                    <strong>Service Type:</strong> ${quotationData.service_type}
                </div>
                <div style="text-align: right;">
                    <strong>Route:</strong> ${quotationData.origin || 'TBD'} → ${quotationData.destination || 'TBD'}<br>
                    <strong>Currency:</strong> ${quotationData.currency}
                </div>
            </div>
        </div>
        
        <div class="company-info">
            <h3>Customer Information:</h3>
            <strong>${quotationData.company_name}</strong> (${quotationData.customer_code})<br>
            ${quotationData.contact_person ? `Contact: ${quotationData.contact_person}<br>` : ''}
            ${quotationData.phone ? `Phone: ${quotationData.phone}<br>` : ''}
            ${quotationData.email ? `Email: ${quotationData.email}<br>` : ''}
            ${quotationData.address ? `<br>${quotationData.address.replace(/\n/g, '<br>')}` : ''}
        </div>
        
        ${quotationData.cargo_description ? `
        <div>
            <h3>Cargo Description:</h3>
            <p>${quotationData.cargo_description.replace(/\n/g, '<br>')}</p>
        </div>
        ` : ''}
        
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Description</th>
                    <th class="text-center">Unit</th>
                    <th class="text-center">Qty</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                ${itemsHtml}
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="5" class="text-right"><strong>TOTAL AMOUNT:</strong></td>
                    <td class="text-right"><strong>${formatMoney(quotationData.total_amount, quotationData.currency)}</strong></td>
                </tr>
            </tfoot>
        </table>
        
        ${quotationData.remark ? `
        <div>
            <h3>Remarks:</h3>
            <p>${quotationData.remark.replace(/\n/g, '<br>')}</p>
        </div>
        ` : ''}
        
        <div class="footer">
            <p>This quotation is valid until ${quotationData.valid_until}</p>
            <p>Thank you for your business!</p>
        </div>
    `;
}

// Email quotation
function emailQuotation() {
    const modal = new bootstrap.Modal(document.getElementById('emailModal'));
    modal.show();
}

// Send email
function sendEmail() {
    const emailData = {
        quotation_id: <?php echo $quotation_id; ?>,
        to: document.getElementById('email_to').value,
        cc: document.getElementById('email_cc').value,
        subject: document.getElementById('email_subject').value,
        message: document.getElementById('email_message').value
    };
    
    if (!emailData.to || !emailData.subject || !emailData.message) {
        alert('Please fill in all required fields');
        return;
    }
    
    // Show loading state
    const sendBtn = document.querySelector('#emailModal .btn-primary');
    const originalText = sendBtn.innerHTML;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
    sendBtn.disabled = true;
    
    fetch('ajax/send_quotation_email.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(emailData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Email sent successfully!');
            const modal = bootstrap.Modal.getInstance(document.getElementById('emailModal'));
            modal.hide();
        } else {
            alert('Error sending email: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while sending email');
    })
    .finally(() => {
        sendBtn.innerHTML = originalText;
        sendBtn.disabled = false;
    });
}

// Update quotation status
function updateQuotationStatus(newStatus) {
    if (confirm(`Are you sure you want to mark this quotation as "${newStatus}"?`)) {
        fetch('ajax/update_quotation_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                quotation_id: <?php echo $quotation_id; ?>,
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

// Status update functions
function markAsSent() {
    updateQuotationStatus('sent');
}

function markAsAccepted() {
    updateQuotationStatus('accepted');
}

function markAsRejected() {
    updateQuotationStatus('rejected');
}

// Export PDF (placeholder - would need actual PDF generation)
function exportQuotationPDF() {
    // This would typically call a PDF generation service
    alert('PDF export functionality would be implemented here');
}

// Format money helper function
function formatMoney(amount, currency = 'THB') {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount) + ' ' + currency;
}

// Format number helper function
function formatNumber(number, decimals = 2) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(number);
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

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

/* Status badge styles */
.badge.fs-6 {
    font-size: 0.9rem !important;
    padding: 0.5em 0.8em;
}

/* Print styles */
@media print {
    .btn, .card-header .btn-group, .sidebar, .breadcrumb {
        display: none !important;
    }
    
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        margin-bottom: 20px !important;
    }
    
    .row {
        margin: 0 !important;
    }
    
    .col-lg-8 {
        width: 100% !important;
        padding: 0 !important;
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.9rem;
    }
    
    .timeline {
        padding-left: 20px;
    }
    
    .timeline-marker {
        left: -20px;
    }
}

/* Alert enhancements */
.alert {
    border-left: 4px solid;
}

.alert-warning {
    border-left-color: #ffc107;
}

.alert-danger {
    border-left-color: #dc3545;
}

.alert-success {
    border-left-color: #28a745;
}

/* Card hover effects */
.card {
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
}

/* Table enhancements */
.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
    background-color: #f8f9fa;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.03);
}

/* Badge spacing */
.badge {
    font-size: 0.75rem;
    padding: 0.25em 0.6em;
}

/* Button group responsiveness */
@media (max-width: 576px) {
    .btn-group.w-100 .btn {
        font-size: 0.8rem;
        padding: 0.5rem;
    }
}
</style>

<?php include 'includes/footer.php'; ?>