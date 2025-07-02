<?php
// =====================================================
// jobs_edit.php - Edit Job (Fixed Version)
// =====================================================

// Include functions first
require_once 'includes/functions.php';

// Require staff permission or higher
requirePermission('staff');

// Get job ID
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$job_id) {
    $_SESSION['error_message'] = "Job ID is required.";
    redirect('jobs.php');
    exit();
}

// Get job data
$job = fetchOne("SELECT * FROM jobs WHERE id = ?", [$job_id]);

if (!$job) {
    $_SESSION['error_message'] = "Job not found.";
    redirect('jobs.php');
    exit();
}

// Check if job can be edited
if ($job['status'] === 'completed' && !hasPermission('manager')) {
    $_SESSION['error_message'] = "Cannot edit completed jobs. Manager permission required.";
    redirect('jobs_view.php?id=' . $job_id);
    exit();
}

$errors = [];
$form_data = $job; // Initialize with existing data

// Handle form submission BEFORE any output
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and clean form data
    $form_data = [
        'job_no' => strtoupper(cleanInput($_POST['job_no'])),
        'job_type' => cleanInput($_POST['job_type']),
        'service_type' => cleanInput($_POST['service_type']),
        'shipper_id' => (int)$_POST['shipper_id'],
        'consignee_id' => (int)$_POST['consignee_id'],
        'origin' => cleanInput($_POST['origin']),
        'destination' => cleanInput($_POST['destination']),
        'vessel_flight' => cleanInput($_POST['vessel_flight']),
        'voyage_no' => cleanInput($_POST['voyage_no']),
        'etd' => cleanInput($_POST['etd']),
        'eta' => cleanInput($_POST['eta']),
        'delivery_date' => cleanInput($_POST['delivery_date']),
        'cargo_description' => cleanInput($_POST['cargo_description']),
        'packages' => (int)$_POST['packages'],
        'gross_weight' => (float)$_POST['gross_weight'],
        'volume_weight' => (float)$_POST['volume_weight'],
        'cbm' => (float)$_POST['cbm'],
        'bl_awb_no' => cleanInput($_POST['bl_awb_no']),
        'container_no' => cleanInput($_POST['container_no']),
        'status' => cleanInput($_POST['status']),
        'remark' => cleanInput($_POST['remark'])
    ];
    
    // Validation
    if (empty($form_data['job_no'])) {
        $errors['job_no'] = 'Job number is required';
    } elseif ($form_data['job_no'] !== $job['job_no']) {
        // Check if new job number already exists (only if changed)
        $existing = fetchOne("SELECT id FROM jobs WHERE job_no = ? AND id != ?", [$form_data['job_no'], $job_id]);
        if ($existing) {
            $errors['job_no'] = 'Job number already exists';
        }
    }
    
    if (empty($form_data['job_type'])) {
        $errors['job_type'] = 'Job type is required';
    }
    
    if (empty($form_data['service_type'])) {
        $errors['service_type'] = 'Service type is required';
    }
    
    if (empty($form_data['origin'])) {
        $errors['origin'] = 'Origin is required';
    }
    
    if (empty($form_data['destination'])) {
        $errors['destination'] = 'Destination is required';
    }
    
    // At least one customer (shipper or consignee) is required
    if ($form_data['shipper_id'] == 0 && $form_data['consignee_id'] == 0) {
        $errors['customer'] = 'At least one customer (shipper or consignee) is required';
    }
    
    // Validate dates
    if (!empty($form_data['etd']) && !empty($form_data['eta'])) {
        if (strtotime($form_data['etd']) > strtotime($form_data['eta'])) {
            $errors['eta'] = 'ETA must be after ETD';
        }
    }
    
    if (!empty($form_data['eta']) && !empty($form_data['delivery_date'])) {
        if (strtotime($form_data['eta']) > strtotime($form_data['delivery_date'])) {
            $errors['delivery_date'] = 'Delivery date must be after ETA';
        }
    }
    
    // Validate status change
    $valid_statuses = ['booking', 'document_preparation', 'customs_clearance', 'in_transit', 'arrived', 'delivered', 'completed'];
    if (!in_array($form_data['status'], $valid_statuses)) {
        $errors['status'] = 'Invalid status';
    }
    
    // Check status change permissions
    if ($form_data['status'] !== $job['status']) {
        $status_order = [
            'booking' => 1,
            'document_preparation' => 2,
            'customs_clearance' => 3,
            'in_transit' => 4,
            'arrived' => 5,
            'delivered' => 6,
            'completed' => 7
        ];
        
        $current_order = $status_order[$job['status']] ?? 0;
        $new_order = $status_order[$form_data['status']] ?? 0;
        
        // Only managers can move status backwards or complete jobs
        if (!hasPermission('manager')) {
            if ($new_order < $current_order) {
                $errors['status'] = 'Cannot move to previous status. Manager permission required.';
            }
            if ($form_data['status'] === 'completed') {
                $errors['status'] = 'Cannot complete job. Manager permission required.';
            }
        }
    }
    
    // Validate business rules based on status
    if ($form_data['status'] === 'in_transit' && empty($form_data['bl_awb_no'])) {
        $errors['bl_awb_no'] = 'BL/AWB number is required for in-transit status';
    }
    
    // If no errors, update database
    if (empty($errors)) {
        // Begin transaction
        beginTransaction();
        
        try {
            $sql = "UPDATE jobs SET 
                        job_no = ?, job_type = ?, service_type = ?, shipper_id = ?, consignee_id = ?,
                        origin = ?, destination = ?, vessel_flight = ?, voyage_no = ?, 
                        etd = ?, eta = ?, delivery_date = ?, cargo_description = ?,
                        packages = ?, gross_weight = ?, volume_weight = ?, cbm = ?,
                        bl_awb_no = ?, container_no = ?, status = ?, remark = ?,
                        updated_at = NOW()
                    WHERE id = ?";
            
            $params = [
                $form_data['job_no'],
                $form_data['job_type'],
                $form_data['service_type'],
                $form_data['shipper_id'] ?: null,
                $form_data['consignee_id'] ?: null,
                $form_data['origin'],
                $form_data['destination'],
                $form_data['vessel_flight'],
                $form_data['voyage_no'],
                $form_data['etd'] ?: null,
                $form_data['eta'] ?: null,
                $form_data['delivery_date'] ?: null,
                $form_data['cargo_description'],
                $form_data['packages'] ?: null,
                $form_data['gross_weight'] ?: null,
                $form_data['volume_weight'] ?: null,
                $form_data['cbm'] ?: null,
                $form_data['bl_awb_no'],
                $form_data['container_no'],
                $form_data['status'],
                $form_data['remark'],
                $job_id
            ];
            
            $result = execute($sql, $params);
            
            if (!$result) {
                throw new Exception('Failed to update job.');
            }
            
            // Log status change if status was updated
            if ($form_data['status'] !== $job['status']) {
                execute("INSERT INTO job_status_history (job_id, old_status, new_status, remark, changed_by, changed_at) 
                         VALUES (?, ?, ?, ?, ?, NOW())", 
                        [$job_id, $job['status'], $form_data['status'], 'Updated via job edit', $_SESSION['user_id']]);
            }
            
            // Commit transaction
            commit();
            
            $_SESSION['success_message'] = "Job '{$form_data['job_no']}' has been updated successfully.";
            redirect('jobs_view.php?id=' . $job_id);
            exit();
            
        } catch (Exception $e) {
            rollback();
            $errors['general'] = 'Failed to update job: ' . $e->getMessage();
        }
    }
}

// Get customers for dropdowns
$customers = fetchAll("SELECT id, customer_code, company_name, customer_type FROM customers WHERE status = 'active' ORDER BY company_name");

// Get job statistics
$job_stats = [
    'total_costs' => fetchOne("SELECT COALESCE(SUM(amount_thb), 0) as total FROM job_costs WHERE job_id = ?", [$job_id])['total'],
    'total_selling' => fetchOne("SELECT COALESCE(SUM(amount_thb), 0) as total FROM job_selling WHERE job_id = ?", [$job_id])['total'],
    'document_count' => fetchOne("SELECT COUNT(*) as count FROM documents WHERE job_id = ?", [$job_id])['count'],
    'invoice_count' => fetchOne("SELECT COUNT(*) as count FROM invoices WHERE job_id = ?", [$job_id])['count']
];

$profit_loss = $job_stats['total_selling'] - $job_stats['total_costs'];

// NOW set page variables and include header
$custom_page_title = "Edit Job - " . $job['job_no'];
$page_header = true;
$page_subtitle = "Update job information and settings";
$breadcrumb = [
    ['name' => 'Jobs Management', 'url' => 'jobs.php'],
    ['name' => $job['job_no'], 'url' => 'jobs_view.php?id=' . $job_id],
    ['name' => 'Edit']
];

include 'includes/header.php';
?>

<!-- Job Status Alert -->
<?php if ($job['status'] === 'completed'): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Warning:</strong> This job is completed. Changes should be made carefully and may require manager approval.
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <!-- Main Form -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2"></i>Edit Job Information
                    </h5>
                    <div class="text-light small">
                        Last updated: <?php echo formatDateThai($job['updated_at'], 'd/m/Y H:i'); ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $errors['general']; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors['customer'])): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $errors['customer']; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" data-autosave="edit_job_<?php echo $job_id; ?>" id="jobEditForm">
                    <!-- Job Type & Service Type -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="job_type" class="form-label">
                                Job Type <span class="text-danger">*</span>
                            </label>
                            <select class="form-select <?php echo isset($errors['job_type']) ? 'is-invalid' : ''; ?>" 
                                    id="job_type" name="job_type" required>
                                <option value="">Select Job Type</option>
                                <option value="export_air" <?php echo ($form_data['job_type'] ?? '') == 'export_air' ? 'selected' : ''; ?>>Export Air</option>
                                <option value="export_sea" <?php echo ($form_data['job_type'] ?? '') == 'export_sea' ? 'selected' : ''; ?>>Export Sea</option>
                                <option value="import_air" <?php echo ($form_data['job_type'] ?? '') == 'import_air' ? 'selected' : ''; ?>>Import Air</option>
                                <option value="import_sea" <?php echo ($form_data['job_type'] ?? '') == 'import_sea' ? 'selected' : ''; ?>>Import Sea</option>
                            </select>
                            <?php if (isset($errors['job_type'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['job_type']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="service_type" class="form-label">
                                Service Type <span class="text-danger">*</span>
                            </label>
                            <select class="form-select <?php echo isset($errors['service_type']) ? 'is-invalid' : ''; ?>" 
                                    id="service_type" name="service_type" required>
                                <option value="">Select Service Type</option>
                                <option value="customer_only" <?php echo ($form_data['service_type'] ?? '') == 'customer_only' ? 'selected' : ''; ?>>Customer Only (C)</option>
                                <option value="freight_only" <?php echo ($form_data['service_type'] ?? '') == 'freight_only' ? 'selected' : ''; ?>>Freight Only (F)</option>
                                <option value="mix" <?php echo ($form_data['service_type'] ?? '') == 'mix' ? 'selected' : ''; ?>>Mix Service (M)</option>
                            </select>
                            <?php if (isset($errors['service_type'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['service_type']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="job_no" class="form-label">
                                Job Number <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control <?php echo isset($errors['job_no']) ? 'is-invalid' : ''; ?>" 
                                   id="job_no" name="job_no" 
                                   value="<?php echo htmlspecialchars($form_data['job_no'] ?? ''); ?>"
                                   placeholder="e.g., AEC0625-0001"
                                   maxlength="20" 
                                   required>
                            <?php if (isset($errors['job_no'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['job_no']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>Original: <?php echo htmlspecialchars($job['job_no']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Customers -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="shipper_id" class="form-label">Shipper</label>
                            <select class="form-select" id="shipper_id" name="shipper_id">
                                <option value="">Select Shipper</option>
                                <?php foreach ($customers as $customer): ?>
                                    <?php if (in_array($customer['customer_type'], ['shipper', 'both'])): ?>
                                    <option value="<?php echo $customer['id']; ?>" 
                                            <?php echo ($form_data['shipper_id'] ?? '') == $customer['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['customer_code'] . ' - ' . $customer['company_name']); ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="consignee_id" class="form-label">Consignee</label>
                            <select class="form-select" id="consignee_id" name="consignee_id">
                                <option value="">Select Consignee</option>
                                <?php foreach ($customers as $customer): ?>
                                    <?php if (in_array($customer['customer_type'], ['consignee', 'both'])): ?>
                                    <option value="<?php echo $customer['id']; ?>" 
                                            <?php echo ($form_data['consignee_id'] ?? '') == $customer['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['customer_code'] . ' - ' . $customer['company_name']); ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Route Information -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="origin" class="form-label">
                                Origin <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control <?php echo isset($errors['origin']) ? 'is-invalid' : ''; ?>" 
                                   id="origin" name="origin" 
                                   value="<?php echo htmlspecialchars($form_data['origin'] ?? ''); ?>"
                                   placeholder="e.g., Bangkok, Thailand" required>
                            <?php if (isset($errors['origin'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['origin']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="destination" class="form-label">
                                Destination <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control <?php echo isset($errors['destination']) ? 'is-invalid' : ''; ?>" 
                                   id="destination" name="destination" 
                                   value="<?php echo htmlspecialchars($form_data['destination'] ?? ''); ?>"
                                   placeholder="e.g., Los Angeles, USA" required>
                            <?php if (isset($errors['destination'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['destination']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Transport Details -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="vessel_flight" class="form-label">Vessel/Flight</label>
                            <input type="text" class="form-control" id="vessel_flight" name="vessel_flight" 
                                   value="<?php echo htmlspecialchars($form_data['vessel_flight'] ?? ''); ?>"
                                   placeholder="e.g., TG917, OOCL BANGKOK">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="voyage_no" class="form-label">Voyage/Flight No</label>
                            <input type="text" class="form-control" id="voyage_no" name="voyage_no" 
                                   value="<?php echo htmlspecialchars($form_data['voyage_no'] ?? ''); ?>"
                                   placeholder="e.g., 001E, TG917">
                        </div>
                    </div>
                    
                    <!-- Dates -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="etd" class="form-label">ETD (Estimated Time of Departure)</label>
                            <input type="date" class="form-control" id="etd" name="etd" 
                                   value="<?php echo htmlspecialchars($form_data['etd'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="eta" class="form-label">ETA (Estimated Time of Arrival)</label>
                            <input type="date" class="form-control <?php echo isset($errors['eta']) ? 'is-invalid' : ''; ?>" 
                                   id="eta" name="eta" 
                                   value="<?php echo htmlspecialchars($form_data['eta'] ?? ''); ?>">
                            <?php if (isset($errors['eta'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['eta']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="delivery_date" class="form-label">Delivery Date</label>
                            <input type="date" class="form-control <?php echo isset($errors['delivery_date']) ? 'is-invalid' : ''; ?>" 
                                   id="delivery_date" name="delivery_date" 
                                   value="<?php echo htmlspecialchars($form_data['delivery_date'] ?? ''); ?>">
                            <?php if (isset($errors['delivery_date'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['delivery_date']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Cargo Information -->
                    <div class="mb-3">
                        <label for="cargo_description" class="form-label">Cargo Description</label>
                        <textarea class="form-control" id="cargo_description" name="cargo_description" rows="3"
                                  placeholder="Describe the cargo/goods being shipped"><?php echo htmlspecialchars($form_data['cargo_description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="packages" class="form-label">Packages</label>
                            <input type="number" class="form-control" id="packages" name="packages" 
                                   value="<?php echo htmlspecialchars($form_data['packages'] ?? ''); ?>"
                                   placeholder="Number of packages" min="0">
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="gross_weight" class="form-label">Gross Weight (KG)</label>
                            <input type="number" class="form-control" id="gross_weight" name="gross_weight" 
                                   value="<?php echo htmlspecialchars($form_data['gross_weight'] ?? ''); ?>"
                                   placeholder="0.000" step="0.001" min="0">
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="volume_weight" class="form-label">Volume Weight (KG)</label>
                            <input type="number" class="form-control" id="volume_weight" name="volume_weight" 
                                   value="<?php echo htmlspecialchars($form_data['volume_weight'] ?? ''); ?>"
                                   placeholder="0.000" step="0.001" min="0">
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="cbm" class="form-label">CBM</label>
                            <input type="number" class="form-control" id="cbm" name="cbm" 
                                   value="<?php echo htmlspecialchars($form_data['cbm'] ?? ''); ?>"
                                   placeholder="0.000" step="0.001" min="0">
                        </div>
                    </div>
                    
                    <!-- Document Numbers -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="bl_awb_no" class="form-label">BL/AWB Number</label>
                            <input type="text" class="form-control <?php echo isset($errors['bl_awb_no']) ? 'is-invalid' : ''; ?>" 
                                   id="bl_awb_no" name="bl_awb_no" 
                                   value="<?php echo htmlspecialchars($form_data['bl_awb_no'] ?? ''); ?>"
                                   placeholder="Bill of Lading / Airway Bill Number">
                            <?php if (isset($errors['bl_awb_no'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['bl_awb_no']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="container_no" class="form-label">Container Number</label>
                            <input type="text" class="form-control" id="container_no" name="container_no" 
                                   value="<?php echo htmlspecialchars($form_data['container_no'] ?? ''); ?>"
                                   placeholder="Container Number (for sea freight)">
                        </div>
                    </div>
                    
                    <!-- Status -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">
                                Job Status <span class="text-danger">*</span>
                            </label>
                            <select class="form-select <?php echo isset($errors['status']) ? 'is-invalid' : ''; ?>" 
                                    id="status" name="status" required>
                                <option value="booking" <?php echo ($form_data['status'] ?? '') == 'booking' ? 'selected' : ''; ?>>Booking</option>
                                <option value="document_preparation" <?php echo ($form_data['status'] ?? '') == 'document_preparation' ? 'selected' : ''; ?>>Document Preparation</option>
                                <option value="customs_clearance" <?php echo ($form_data['status'] ?? '') == 'customs_clearance' ? 'selected' : ''; ?>>Customs Clearance</option>
                                <option value="in_transit" <?php echo ($form_data['status'] ?? '') == 'in_transit' ? 'selected' : ''; ?>>In Transit</option>
                                <option value="arrived" <?php echo ($form_data['status'] ?? '') == 'arrived' ? 'selected' : ''; ?>>Arrived</option>
                                <option value="delivered" <?php echo ($form_data['status'] ?? '') == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <?php if (hasPermission('manager')): ?>
                                <option value="completed" <?php echo ($form_data['status'] ?? '') == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <?php endif; ?>
                            </select>
                            <?php if (isset($errors['status'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['status']; ?></div>
                            <?php endif; ?>
                            <?php if (!hasPermission('manager')): ?>
                                <div class="form-text">Only managers can complete jobs or move status backwards</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Remarks -->
                    <div class="mb-3">
                        <label for="remark" class="form-label">Remarks</label>
                        <textarea class="form-control" id="remark" name="remark" rows="3"
                                  placeholder="Additional notes or special instructions"><?php echo htmlspecialchars($form_data['remark'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Job
                        </button>
                        <a href="jobs_view.php?id=<?php echo $job_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="button" class="btn btn-outline-info" onclick="resetToOriginal()">
                            <i class="fas fa-undo me-2"></i>Reset to Original
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Sidebar Info -->
    <div class="col-lg-4">
        <!-- Job Statistics -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>Job Statistics
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="border-end">
                            <h4 class="text-danger mb-1"><?php echo formatMoney($job_stats['total_costs']); ?></h4>
                            <small class="text-muted">Total Costs</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <h4 class="text-info mb-1"><?php echo formatMoney($job_stats['total_selling']); ?></h4>
                        <small class="text-muted">Total Revenue</small>
                    </div>
                    <div class="col-6">
                        <div class="border-end">
                            <h4 class="text-secondary mb-1"><?php echo $job_stats['document_count']; ?></h4>
                            <small class="text-muted">Documents</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success mb-1"><?php echo $job_stats['invoice_count']; ?></h4>
                        <small class="text-muted">Invoices</small>
                    </div>
                </div>
                
                <hr>
                <div class="text-center">
                    <h4 class="<?php echo $profit_loss >= 0 ? 'text-success' : 'text-danger'; ?> mb-1">
                        <i class="fas <?php echo $profit_loss >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?> me-1"></i>
                        <?php echo formatMoney($profit_loss); ?>
                    </h4>
                    <small class="text-muted">Profit/Loss</small>
                </div>
                
                <div class="d-grid gap-2 mt-3">
                    <a href="job_costs.php?job_id=<?php echo $job_id; ?>" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-money-bill me-2"></i>Manage Costs
                    </a>
                    <a href="job_selling.php?job_id=<?php echo $job_id; ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-hand-holding-usd me-2"></i>Manage Selling
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Edit Guidelines -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>Edit Guidelines
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Job Number Format:</strong>
                    <p class="small text-muted mb-2">
                        {Type}{Service}{MMYY}-{0000}<br>
                        <strong>Example:</strong> AEC0625-0001
                    </p>
                </div>
                
                <div class="mb-3">
                    <strong>Status Changes:</strong>
                    <ul class="small text-muted mb-2">
                        <li><strong>Staff:</strong> Can move forward only</li>
                        <li><strong>Manager:</strong> Can move any direction</li>
                        <li><strong>Complete:</strong> Manager only</li>
                    </ul>
                </div>
                
                <div class="mb-3">
                    <strong>Required Fields:</strong>
                    <ul class="small text-muted mb-2">
                        <li>Job Number, Type, Service</li>
                        <li>Origin & Destination</li>
                        <li>At least one customer</li>
                        <li>BL/AWB for In-Transit status</li>
                    </ul>
                </div>
                
                <div class="mb-0">
                    <strong>Date Validation:</strong>
                    <p class="small text-muted mb-0">
                        ETD ≤ ETA ≤ Delivery Date
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Change History -->
        <?php 
        $change_history = fetchAll("
            SELECT jsh.*, u.name as changed_by_name
            FROM job_status_history jsh
            LEFT JOIN users u ON jsh.changed_by = u.id
            WHERE jsh.job_id = ?
            ORDER BY jsh.changed_at DESC
            LIMIT 5
        ", [$job_id]);
        ?>
        
        <?php if (!empty($change_history)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>Recent Changes
                </h6>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php foreach ($change_history as $history): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-info"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">
                                Status: <?php echo ucfirst(str_replace('_', ' ', $history['new_status'])); ?>
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
                
                <div class="text-center mt-3">
                    <a href="jobs_view.php?id=<?php echo $job_id; ?>#status-history" class="btn btn-outline-secondary btn-sm">
                        View Full History
                    </a>
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
                        <?php if ($job['created_by']): ?>
                            <?php 
                            $creator = fetchOne("SELECT name FROM users WHERE id = ?", [$job['created_by']]);
                            if ($creator): ?>
                                <br>by <?php echo htmlspecialchars($creator['name']); ?>
                            <?php endif; ?>
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
                
                <?php if ($job['status'] === 'completed'): ?>
                <div class="alert alert-success py-2">
                    <small>
                        <i class="fas fa-check-circle me-1"></i>
                        <strong>Completed Job</strong><br>
                        All activities have been finalized.
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="jobs_view.php?id=<?php echo $job_id; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-eye me-2"></i>View Job Details
                    </a>
                    
                    <button class="btn btn-outline-secondary" onclick="copyJobInfo()">
                        <i class="fas fa-copy me-2"></i>Copy Job Info
                    </button>
                    
                    <button class="btn btn-outline-secondary" onclick="printJobDetails()">
                        <i class="fas fa-print me-2"></i>Print Job
                    </button>
                    
                    <?php if (hasPermission('manager') && $job_stats['invoice_count'] == 0): ?>
                    <a href="jobs.php?action=delete&id=<?php echo $job_id; ?>" 
                       class="btn btn-outline-danger confirm-delete">
                        <i class="fas fa-trash me-2"></i>Delete Job
                    </a>
                    <?php endif; ?>
                </div>
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

/* Form Enhancement */
.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.is-invalid {
    border-color: #dc3545;
}

.invalid-feedback {
    display: block;
}

/* Status-based styling */
.status-booking { border-left: 4px solid #6c757d; }
.status-document_preparation { border-left: 4px solid #ffc107; }
.status-customs_clearance { border-left: 4px solid #17a2b8; }
.status-in_transit { border-left: 4px solid #007bff; }
.status-arrived { border-left: 4px solid #6f42c1; }
.status-delivered { border-left: 4px solid #28a745; }
.status-completed { border-left: 4px solid #343a40; }

/* Highlight changed fields */
.field-changed {
    border-left: 3px solid #ffc107 !important;
    background-color: #fff3cd;
}

/* Print Styles */
@media print {
    .btn, .card-header, .sidebar, .timeline::before, .timeline-marker {
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
}
</style>

<script>
// Store original form values for comparison
const originalFormData = <?php echo json_encode($job); ?>;

// Reset to original values
function resetToOriginal() {
    if (confirm('Are you sure you want to reset all fields to their original values? All changes will be lost.')) {
        // Reset all form fields to original values
        document.getElementById('job_no').value = originalFormData.job_no || '';
        document.getElementById('job_type').value = originalFormData.job_type || '';
        document.getElementById('service_type').value = originalFormData.service_type || '';
        document.getElementById('shipper_id').value = originalFormData.shipper_id || '';
        document.getElementById('consignee_id').value = originalFormData.consignee_id || '';
        document.getElementById('origin').value = originalFormData.origin || '';
        document.getElementById('destination').value = originalFormData.destination || '';
        document.getElementById('vessel_flight').value = originalFormData.vessel_flight || '';
        document.getElementById('voyage_no').value = originalFormData.voyage_no || '';
        document.getElementById('etd').value = originalFormData.etd || '';
        document.getElementById('eta').value = originalFormData.eta || '';
        document.getElementById('delivery_date').value = originalFormData.delivery_date || '';
        document.getElementById('cargo_description').value = originalFormData.cargo_description || '';
        document.getElementById('packages').value = originalFormData.packages || '';
        document.getElementById('gross_weight').value = originalFormData.gross_weight || '';
        document.getElementById('volume_weight').value = originalFormData.volume_weight || '';
        document.getElementById('cbm').value = originalFormData.cbm || '';
        document.getElementById('bl_awb_no').value = originalFormData.bl_awb_no || '';
        document.getElementById('container_no').value = originalFormData.container_no || '';
        document.getElementById('status').value = originalFormData.status || '';
        document.getElementById('remark').value = originalFormData.remark || '';
        
        // Clear localStorage auto-save data
        localStorage.removeItem('form_edit_job_<?php echo $job_id; ?>');
        
        // Remove any validation error classes
        document.querySelectorAll('.is-invalid').forEach(function(element) {
            element.classList.remove('is-invalid');
        });
        
        // Remove field change highlights
        document.querySelectorAll('.field-changed').forEach(function(element) {
            element.classList.remove('field-changed');
        });
    }
}

// Form validation before submit
document.getElementById('jobEditForm').addEventListener('submit', function(e) {
    const jobNo = document.getElementById('job_no').value.trim();
    const jobType = document.getElementById('job_type').value;
    const serviceType = document.getElementById('service_type').value;
    const origin = document.getElementById('origin').value.trim();
    const destination = document.getElementById('destination').value.trim();
    const shipperId = document.getElementById('shipper_id').value;
    const consigneeId = document.getElementById('consignee_id').value;
    const status = document.getElementById('status').value;
    const blAwbNo = document.getElementById('bl_awb_no').value.trim();
    
    // Validate required fields
    if (!jobNo) {
        e.preventDefault();
        alert('Please enter a job number');
        document.getElementById('job_no').focus();
        return false;
    }
    
    if (!jobType) {
        e.preventDefault();
        alert('Please select a job type');
        document.getElementById('job_type').focus();
        return false;
    }
    
    if (!serviceType) {
        e.preventDefault();
        alert('Please select a service type');
        document.getElementById('service_type').focus();
        return false;
    }
    
    if (!origin) {
        e.preventDefault();
        alert('Please enter the origin');
        document.getElementById('origin').focus();
        return false;
    }
    
    if (!destination) {
        e.preventDefault();
        alert('Please enter the destination');
        document.getElementById('destination').focus();
        return false;
    }
    
    // Check if at least one customer is selected
    if (!shipperId && !consigneeId) {
        e.preventDefault();
        alert('Please select at least one customer (shipper or consignee)');
        document.getElementById('shipper_id').focus();
        return false;
    }
    
    // Validate BL/AWB for in-transit status
    if (status === 'in_transit' && !blAwbNo) {
        e.preventDefault();
        alert('BL/AWB number is required for in-transit status');
        document.getElementById('bl_awb_no').focus();
        return false;
    }
    
    // Validate dates
    const etd = document.getElementById('etd').value;
    const eta = document.getElementById('eta').value;
    const deliveryDate = document.getElementById('delivery_date').value;
    
    if (etd && eta && new Date(etd) > new Date(eta)) {
        e.preventDefault();
        alert('ETA must be after ETD');
        document.getElementById('eta').focus();
        return false;
    }
    
    if (eta && deliveryDate && new Date(eta) > new Date(deliveryDate)) {
        e.preventDefault();
        alert('Delivery date must be after ETA');
        document.getElementById('delivery_date').focus();
        return false;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
    submitBtn.disabled = true;
});

// Highlight changed fields
document.querySelectorAll('input, select, textarea').forEach(function(field) {
    field.addEventListener('input', function() {
        const fieldName = this.name;
        const currentValue = this.value;
        const originalValue = originalFormData[fieldName] || '';
        
        if (currentValue !== originalValue.toString()) {
            this.classList.add('field-changed');
        } else {
            this.classList.remove('field-changed');
        }
    });
});

// Status change warning
document.getElementById('status').addEventListener('change', function() {
    const newStatus = this.value;
    const oldStatus = originalFormData.status;
    
    if (newStatus !== oldStatus) {
        const statusOrder = {
            'booking': 1,
            'document_preparation': 2,
            'customs_clearance': 3,
            'in_transit': 4,
            'arrived': 5,
            'delivered': 6,
            'completed': 7
        };
        
        const oldOrder = statusOrder[oldStatus] || 0;
        const newOrder = statusOrder[newStatus] || 0;
        
        if (newOrder < oldOrder) {
            if (!confirm('Warning: You are moving the status backwards. This may affect job tracking. Are you sure?')) {
                this.value = oldStatus;
                return false;
            }
        }
        
        if (newStatus === 'completed') {
            if (!confirm('Warning: Completing a job will finalize all activities. Are you sure?')) {
                this.value = oldStatus;
                return false;
            }
        }
    }
});

// Auto-suggest container/BL field based on job type
document.getElementById('job_type').addEventListener('change', function() {
    const blAwbField = document.getElementById('bl_awb_no');
    const containerField = document.getElementById('container_no');
    
    if (this.value.includes('air')) {
        blAwbField.placeholder = 'Airway Bill Number (e.g., 125-12345678)';
        containerField.closest('.col-md-6').style.opacity = '0.5';
        containerField.placeholder = 'Not applicable for air freight';
    } else if (this.value.includes('sea')) {
        blAwbField.placeholder = 'Bill of Lading Number';
        containerField.closest('.col-md-6').style.opacity = '1';
        containerField.placeholder = 'Container Number (e.g., ABCD1234567)';
    }
});

// Copy job information to clipboard
function copyJobInfo() {
    const jobInfo = `
Job Number: <?php echo $job['job_no']; ?>
Job Type: <?php echo strtoupper(str_replace('_', ' ', $job['job_type'])); ?>
Service Type: <?php echo strtoupper(str_replace('_', ' ', $job['service_type'])); ?>
Status: <?php echo getStatusText($job['status']); ?>
Origin: <?php echo $job['origin']; ?>
Destination: <?php echo $job['destination']; ?>
ETD: <?php echo $job['etd'] ? formatDateThai($job['etd'], 'd/m/Y') : 'Not set'; ?>
ETA: <?php echo $job['eta'] ? formatDateThai($job['eta'], 'd/m/Y') : 'Not set'; ?>
Vessel/Flight: <?php echo $job['vessel_flight'] ?: 'Not set'; ?>
BL/AWB: <?php echo $job['bl_awb_no'] ?: 'Not set'; ?>
Container: <?php echo $job['container_no'] ?: 'Not set'; ?>
    `.trim();
    
    navigator.clipboard.writeText(jobInfo).then(function() {
        alert('Job information copied to clipboard!');
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
    });
}

// Print job details
function printJobDetails() {
    window.print();
}

// Weight calculator function
function calculateWeights() {
    const length = prompt('Enter length (cm):');
    const width = prompt('Enter width (cm):');
    const height = prompt('Enter height (cm):');
    
    if (length && width && height) {
        const cbm = (length * width * height) / 1000000;
        const volumeWeight = cbm * 167; // Standard air freight calculation
        
        document.getElementById('cbm').value = cbm.toFixed(3);
        document.getElementById('volume_weight').value = volumeWeight.toFixed(3);
        
        alert(`Calculated:\nCBM: ${cbm.toFixed(3)}\nVolume Weight: ${volumeWeight.toFixed(3)} kg`);
    }
}

// Add weight calculator button
document.addEventListener('DOMContentLoaded', function() {
    const cbmField = document.getElementById('cbm');
    if (cbmField) {
        const calcButton = document.createElement('button');
        calcButton.type = 'button';
        calcButton.className = 'btn btn-outline-secondary btn-sm mt-1';
        calcButton.innerHTML = '<i class="fas fa-calculator me-1"></i>Calculate';
        calcButton.onclick = calculateWeights;
        cbmField.parentNode.appendChild(calcButton);
    }
});

// Confirm delete
document.querySelectorAll('.confirm-delete').forEach(function(element) {
    element.addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to delete this job? This action cannot be undone and will remove all related data including documents, costs, and selling prices.')) {
            e.preventDefault();
            return false;
        }
    });
});

// Auto-focus on first editable field when page loads
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('job_no').focus();
});
</script>

<?php include 'includes/footer.php'; ?>