<?php
// =====================================================
// jobs_add.php - Create New Job
// =====================================================

// Include functions first
require_once 'includes/functions.php';

// Require staff permission or higher
requirePermission('staff');

$errors = [];
$form_data = [
    'job_no' => '',
    'job_type' => '',
    'service_type' => '',
    'shipper_id' => '',
    'consignee_id' => '',
    'origin' => '',
    'destination' => '',
    'vessel_flight' => '',
    'voyage_no' => '',
    'etd' => '',
    'eta' => '',
    'delivery_date' => '',
    'cargo_description' => '',
    'packages' => '',
    'gross_weight' => '',
    'volume_weight' => '',
    'cbm' => '',
    'bl_awb_no' => '',
    'container_no' => '',
    'status' => 'booking',
    'remark' => ''    
];

// Function to generate next job number (sequential)
function generateNextJobNumber($job_type, $service_type) {
    // Job type mapping
    $type_map = [
        'export_air' => 'AE',
        'export_sea' => 'SE', 
        'import_air' => 'AI',
        'import_sea' => 'SI'
    ];
    
    // Service type mapping
    $service_map = [
        'customer_only' => 'C',
        'freight_only' => 'F',
        'mix' => 'M'
    ];
    
    $type_code = $type_map[$job_type] ?? 'XX';
    $service_code = $service_map[$service_type] ?? 'X';
    $mmyy = date('my'); // เดือนปี เช่น 0625
    
    $prefix = $type_code . $service_code . $mmyy;
    
    // หาเลขรันนิ่งล่าสุดของเดือนนี้ สำหรับ prefix นี้
    $last_job = fetchOne("
        SELECT job_no 
        FROM jobs 
        WHERE job_no LIKE ? 
        ORDER BY job_no DESC 
        LIMIT 1
    ", ["{$prefix}-%"]);
    
    if ($last_job && isset($last_job['job_no'])) {
        // ดึงเลข 4 หลักสุดท้าย เช่น AEC0625-0001 -> 0001
        $last_number = (int)substr($last_job['job_no'], -4);
        $new_number = $last_number + 1;
    } else {
        // Job แรกของ prefix นี้
        $new_number = 1;
    }
    
    // Format เป็น 4 หลักด้วย leading zeros
    return $prefix . '-' . str_pad($new_number, 4, '0', STR_PAD_LEFT);
}

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
    } else {
        // Check if job number already exists
        $existing = fetchOne("SELECT id FROM jobs WHERE job_no = ?", [$form_data['job_no']]);
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
    
    // Validate status
    $valid_statuses = ['booking', 'document_preparation', 'customs_clearance', 'in_transit', 'arrived', 'delivered', 'completed'];
    if (!in_array($form_data['status'], $valid_statuses)) {
        $errors['status'] = 'Invalid status';
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        $sql = "INSERT INTO jobs (
                    job_no, job_type, service_type, shipper_id, consignee_id,
                    origin, destination, vessel_flight, voyage_no, etd, eta, delivery_date,
                    cargo_description, packages, gross_weight, volume_weight, cbm,
                    bl_awb_no, container_no, status, remark, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
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
            $_SESSION['user_id']
        ];
        
        if (execute($sql, $params)) {
            $job_id = lastInsertId();
            $_SESSION['success_message'] = "Job '{$form_data['job_no']}' has been created successfully.";
            redirect('jobs_view.php?id=' . $job_id);
            exit();
        } else {
            $errors['general'] = 'Failed to save job. Please try again.';
        }
    }
}

// Pre-fill customer if coming from customer page
$pre_customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
if ($pre_customer_id > 0) {
    $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$pre_customer_id]);
    if ($customer) {
        // Determine if this should be shipper or consignee based on customer type
        if (in_array($customer['customer_type'], ['shipper', 'both'])) {
            $form_data['shipper_id'] = $pre_customer_id;
        } else {
            $form_data['consignee_id'] = $pre_customer_id;
        }
    }
}

// Auto-generate job number when job_type and service_type are available
if (!empty($form_data['job_type']) && !empty($form_data['service_type']) && empty($form_data['job_no'])) {
    $form_data['job_no'] = generateNextJobNumber($form_data['job_type'], $form_data['service_type']);
}

// Get customers for dropdowns
$customers = fetchAll("SELECT id, customer_code, company_name, customer_type FROM customers WHERE status = 'active' ORDER BY company_name");

// NOW set page variables and include header
$custom_page_title = "Create New Job";
$page_header = true;
$page_subtitle = "Create a new freight forwarding job";
$breadcrumb = [
    ['name' => 'Jobs Management', 'url' => 'jobs.php'],
    ['name' => 'Create New Job']
];

include 'includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <!-- Main Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-plus me-2"></i>Job Information
                </h5>
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
                
                <form method="POST" action="" data-autosave="add_job" id="jobForm">
                    <!-- Job Type & Service Type -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="job_type" class="form-label">
                                Job Type <span class="text-danger">*</span>
                            </label>
                            <select class="form-select <?php echo isset($errors['job_type']) ? 'is-invalid' : ''; ?>" 
                                    id="job_type" name="job_type" required onchange="generateJobNumber()">
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
                                    id="service_type" name="service_type" required onchange="generateJobNumber()">
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
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control <?php echo isset($errors['job_no']) ? 'is-invalid' : ''; ?>" 
                                       id="job_no" name="job_no" 
                                       value="<?php echo htmlspecialchars($form_data['job_no'] ?? ''); ?>"
                                       placeholder="Auto-generated"
                                       maxlength="20" 
                                       readonly
                                       required>
                                <button type="button" class="btn btn-outline-secondary" onclick="generateJobNumber()" title="Generate New Job Number">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                            <?php if (isset($errors['job_no'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['job_no']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">
                                <i class="fas fa-lock me-1"></i>Auto-generated sequential number
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
                            <input type="date" class="form-control" id="delivery_date" name="delivery_date" 
                                   value="<?php echo htmlspecialchars($form_data['delivery_date'] ?? ''); ?>">
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
                            <input type="text" class="form-control" id="bl_awb_no" name="bl_awb_no" 
                                   value="<?php echo htmlspecialchars($form_data['bl_awb_no'] ?? ''); ?>"
                                   placeholder="Bill of Lading / Airway Bill Number">
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
                            <label for="status" class="form-label">Initial Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="booking" <?php echo ($form_data['status'] ?? 'booking') == 'booking' ? 'selected' : ''; ?>>Booking</option>
                                <option value="document_preparation" <?php echo ($form_data['status'] ?? '') == 'document_preparation' ? 'selected' : ''; ?>>Document Preparation</option>
                                <option value="customs_clearance" <?php echo ($form_data['status'] ?? '') == 'customs_clearance' ? 'selected' : ''; ?>>Customs Clearance</option>
                            </select>
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
                            <i class="fas fa-save me-2"></i>Create Job
                        </button>
                        <a href="jobs.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="reset" class="btn btn-outline-warning" onclick="resetForm()">
                            <i class="fas fa-undo me-2"></i>Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Sidebar Info -->
    <div class="col-lg-4">
        <!-- Job Number Guide -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>Job Number Format
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Format: {Type}{Service}{MMYY}-{0001}</strong>
                    <hr>
                </div>
                
                <div class="mb-3">
                    <strong>Job Types:</strong>
                    <ul class="small mb-2">
                        <li><strong>AE</strong> = Export Air</li>
                        <li><strong>SE</strong> = Export Sea</li>
                        <li><strong>AI</strong> = Import Air</li>
                        <li><strong>SI</strong> = Import Sea</li>
                    </ul>
                </div>
                
                <div class="mb-3">
                    <strong>Service Types:</strong>
                    <ul class="small mb-2">
                        <li><strong>C</strong> = Customer Only</li>
                        <li><strong>F</strong> = Freight Only</li>
                        <li><strong>M</strong> = Mix Service</li>
                    </ul>
                </div>
                
                <div class="mb-0">
                    <strong>Examples:</strong>
                    <ul class="small mb-0">
                        <li>AEC0625-0001 → AEC0625-0002 → AEC0625-0003</li>
                        <li>SIF0625-0001 → SIF0625-0002 → SIF0625-0003</li>
                        <li>AIM0625-0001 → AIM0625-0002 → AIM0625-0003</li>
                    </ul>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Numbers run sequentially for each prefix
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Status Guide -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-tasks me-2"></i>Job Status Flow
                </h6>
            </div>
            <div class="card-body">
                <div class="status-flow">
                    <div class="status-item">
                        <span class="badge bg-secondary">1. Booking</span>
                        <small class="d-block text-muted">Job created and confirmed</small>
                    </div>
                    <div class="status-arrow">↓</div>
                    <div class="status-item">
                        <span class="badge bg-warning">2. Document Prep</span>
                        <small class="d-block text-muted">Preparing documents</small>
                    </div>
                    <div class="status-arrow">↓</div>
                    <div class="status-item">
                        <span class="badge bg-info">3. Customs</span>
                        <small class="d-block text-muted">Customs clearance</small>
                    </div>
                    <div class="status-arrow">↓</div>
                    <div class="status-item">
                        <span class="badge bg-primary">4. In Transit</span>
                        <small class="d-block text-muted">Goods in transport</small>
                    </div>
                    <div class="status-arrow">↓</div>
                    <div class="status-item">
                        <span class="badge bg-purple">5. Arrived</span>
                        <small class="d-block text-muted">Goods arrived at destination</small>
                    </div>
                    <div class="status-arrow">↓</div>
                    <div class="status-item">
                        <span class="badge bg-success">6. Delivered</span>
                        <small class="d-block text-muted">Goods delivered to customer</small>
                    </div>
                    <div class="status-arrow">↓</div>
                    <div class="status-item">
                        <span class="badge bg-dark">7. Completed</span>
                        <small class="d-block text-muted">Job completed and closed</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Customer Add -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-users me-2"></i>Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="customers_add.php" class="btn btn-outline-primary btn-sm" target="_blank">
                        <i class="fas fa-user-plus me-2"></i>Add New Customer
                    </a>
                    <a href="customers.php" class="btn btn-outline-info btn-sm" target="_blank">
                        <i class="fas fa-search me-2"></i>Search Customers
                    </a>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="calculateWeights()">
                        <i class="fas fa-calculator me-2"></i>Weight Calculator
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.status-flow {
    text-align: center;
}

.status-item {
    margin: 10px 0;
}

.status-arrow {
    color: #6c757d;
    font-size: 18px;
    margin: 5px 0;
}

.bg-purple {
    background-color: #6f42c1 !important;
}
</style>

<script>
// Generate job number based on job type and service type
function generateJobNumber() {
    const jobType = document.getElementById('job_type').value;
    const serviceType = document.getElementById('service_type').value;
    
    if (!jobType || !serviceType) {
        return;
    }
    
    // Make AJAX request to generate job number
    fetch('ajax/generate_job_number.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            job_type: jobType,
            service_type: serviceType
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.job_no) {
            document.getElementById('job_no').value = data.job_no;
        } else {
            // Fallback: generate client-side
            generateJobNumberFallback(jobType, serviceType);
        }
    })
    .catch(error => {
        console.log('Error generating job number:', error);
        generateJobNumberFallback(jobType, serviceType);
    });
}

// Fallback function for client-side job number generation (sequential)
function generateJobNumberFallback(jobType, serviceType) {
    const typeMap = {
        'export_air': 'AE',
        'export_sea': 'SE',
        'import_air': 'AI',
        'import_sea': 'SI'
    };
    
    const serviceMap = {
        'customer_only': 'C',
        'freight_only': 'F',
        'mix': 'M'
    };
    
    const typeCode = typeMap[jobType] || 'XX';
    const serviceCode = serviceMap[serviceType] || 'X';
    const mmyy = String(new Date().getMonth() + 1).padStart(2, '0') + 
                 new Date().getFullYear().toString().substr(-2);
    
    // For fallback, use a simple incrementing number based on current time
    // This is not perfect but better than random
    const now = new Date();
    const seqNum = (now.getHours() * 3600 + now.getMinutes() * 60 + now.getSeconds()) % 10000;
    const jobNo = typeCode + serviceCode + mmyy + '-' + seqNum.toString().padStart(4, '0');
    
    document.getElementById('job_no').value = jobNo;
}

// Auto-generate job number when both selects change
document.getElementById('job_type').addEventListener('change', generateJobNumber);
document.getElementById('service_type').addEventListener('change', generateJobNumber);

// Add visual styling to readonly field
document.addEventListener('DOMContentLoaded', function() {
    const jobNoField = document.getElementById('job_no');
    jobNoField.style.backgroundColor = '#f8f9fa';
    jobNoField.style.cursor = 'not-allowed';
});

// Form validation before submit
document.getElementById('jobForm').addEventListener('submit', function(e) {
    const jobType = document.getElementById('job_type').value;
    const serviceType = document.getElementById('service_type').value;
    const jobNo = document.getElementById('job_no').value.trim();
    const origin = document.getElementById('origin').value.trim();
    const destination = document.getElementById('destination').value.trim();
    const shipperId = document.getElementById('shipper_id').value;
    const consigneeId = document.getElementById('consignee_id').value;
    
    // Validate required fields
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
    
    if (!jobNo) {
        e.preventDefault();
        alert('Please generate or enter a job number');
        document.getElementById('job_no').focus();
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
    
    // Validate dates
    const etd = document.getElementById('etd').value;
    const eta = document.getElementById('eta').value;
    
    if (etd && eta && new Date(etd) > new Date(eta)) {
        e.preventDefault();
        alert('ETA must be after ETD');
        document.getElementById('eta').focus();
        return false;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Job...';
    submitBtn.disabled = true;
});

// Reset form function
function resetForm() {
    if (confirm('Are you sure you want to reset all fields? All entered data will be lost.')) {
        document.getElementById('jobForm').reset();
        localStorage.removeItem('form_add_job');
        // Clear job number
        document.getElementById('job_no').value = '';
    }
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

// Auto-fill customer data when coming from customer page
document.addEventListener('DOMContentLoaded', function() {
    // If we have pre-selected customer, generate job number
    const jobType = document.getElementById('job_type').value;
    const serviceType = document.getElementById('service_type').value;
    
    if (jobType && serviceType) {
        generateJobNumber();
    }
    
    // Focus on first empty required field
    const requiredFields = ['job_type', 'service_type', 'origin', 'destination'];
    for (let field of requiredFields) {
        const element = document.getElementById(field);
        if (!element.value) {
            element.focus();
            break;
        }
    }
});

// Smart destination suggestion based on job type
document.getElementById('job_type').addEventListener('change', function() {
    const jobType = this.value;
    const originField = document.getElementById('origin');
    const destField = document.getElementById('destination');
    
    // Auto-suggest based on job type
    if (jobType.includes('export')) {
        if (!originField.value) {
            originField.value = 'Bangkok, Thailand';
        }
    } else if (jobType.includes('import')) {
        if (!destField.value) {
            destField.value = 'Bangkok, Thailand';
        }
    }
});

// Container number format validation (for sea freight)
document.getElementById('container_no').addEventListener('input', function() {
    let value = this.value.toUpperCase();
    // Basic container number format validation could be added here
    this.value = value;
});

// BL/AWB number format helper
document.getElementById('job_type').addEventListener('change', function() {
    const blAwbField = document.getElementById('bl_awb_no');
    const containerField = document.getElementById('container_no');
    
    if (this.value.includes('air')) {
        blAwbField.placeholder = 'Airway Bill Number (e.g., 125-12345678)';
        containerField.closest('.col-md-6').style.display = 'none';
    } else if (this.value.includes('sea')) {
        blAwbField.placeholder = 'Bill of Lading Number';
        containerField.closest('.col-md-6').style.display = 'block';
        containerField.placeholder = 'Container Number (e.g., ABCD1234567)';
    }
});
</script>

<?php include 'includes/footer.php'; ?>