<?php
// =====================================================
// dashboard.php - Main Dashboard
// =====================================================

require_once 'includes/functions.php';

// Require login
requireLogin();

// Get dashboard statistics
$stats = [];

// Total active jobs
$stats['active_jobs'] = fetchOne("SELECT COUNT(*) as count FROM jobs WHERE status NOT IN ('completed', 'cancelled')")['count'];

// Jobs by status
$stats['booking'] = fetchOne("SELECT COUNT(*) as count FROM jobs WHERE status = 'booking'")['count'];
$stats['in_transit'] = fetchOne("SELECT COUNT(*) as count FROM jobs WHERE status = 'in_transit'")['count'];
$stats['arrived'] = fetchOne("SELECT COUNT(*) as count FROM jobs WHERE status = 'arrived'")['count'];

// Monthly revenue (current month)
$monthly_revenue = fetchOne("
    SELECT COALESCE(SUM(total_amount), 0) as revenue 
    FROM invoices 
    WHERE MONTH(invoice_date) = MONTH(CURRENT_DATE()) 
    AND YEAR(invoice_date) = YEAR(CURRENT_DATE())
    AND payment_status = 'paid'
")['revenue'];

// Outstanding invoices
$outstanding = fetchOne("
    SELECT COALESCE(SUM(total_amount - paid_amount), 0) as amount 
    FROM invoices 
    WHERE payment_status IN ('pending', 'partial', 'overdue')
")['amount'];

// Recent jobs (last 10)
$recent_jobs = fetchAll("
    SELECT j.*, c1.company_name as shipper_name, c2.company_name as consignee_name
    FROM jobs j
    LEFT JOIN customers c1 ON j.shipper_id = c1.id
    LEFT JOIN customers c2 ON j.consignee_id = c2.id
    ORDER BY j.created_at DESC
    LIMIT 10
");

// Pending invoices (overdue)
$overdue_invoices = fetchAll("
    SELECT i.*, c.company_name 
    FROM invoices i
    JOIN customers c ON i.customer_id = c.id
    WHERE i.due_date < CURDATE() 
    AND i.payment_status IN ('pending', 'partial')
    ORDER BY i.due_date ASC
    LIMIT 5
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Freight Pro System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: bold;
            font-size: 1.4rem;
        }
        
        .sidebar {
            min-height: calc(100vh - 56px);
            background: white;
            box-shadow: 2px 0 4px rgba(0,0,0,0.1);
            padding: 0;
        }
        
        .sidebar .nav-link {
            color: #495057;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover {
            background-color: #f8f9fa;
            color: var(--primary-color);
            padding-left: 30px;
        }
        
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .main-content {
            padding: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0;
        }
        
        .stat-card .label {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        
        .badge {
            font-size: 0.8rem;
            padding: 5px 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            border-radius: 10px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        }
        
        .quick-actions {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .quick-actions .btn {
            margin: 5px;
            border-radius: 10px;
            padding: 12px 20px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-ship me-2"></i>
                Freight Pro
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="change_password.php"><i class="fas fa-key me-2"></i>Change Password</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 sidebar">
                <div class="d-flex flex-column pt-3">
                    <ul class="nav nav-pills flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="jobs.php">
                                <i class="fas fa-shipping-fast"></i>
                                Jobs Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="customers.php">
                                <i class="fas fa-users"></i>
                                Customers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="vendors.php">
                                <i class="fas fa-truck"></i>
                                Vendors
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="quotations.php">
                                <i class="fas fa-file-invoice-dollar"></i>
                                Quotations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="invoices.php">
                                <i class="fas fa-receipt"></i>
                                Invoices
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i>
                                Reports
                            </a>
                        </li>
                        <?php if (hasPermission('admin')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog"></i>
                                Settings
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 main-content">
                <!-- Welcome Section -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-1">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
                        <p class="text-muted mb-0">Here's what's happening with your freight operations today.</p>
                    </div>
                    <div class="text-end">
                        <small class="text-muted">
                            <i class="fas fa-calendar-alt me-1"></i>
                            <?php echo date('l, F j, Y'); ?>
                        </small>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="icon bg-primary text-white">
                                <i class="fas fa-boxes"></i>
                            </div>
                            <h3 class="number text-primary"><?php echo $stats['active_jobs']; ?></h3>
                            <p class="label">Active Jobs</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="icon bg-warning text-white">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h3 class="number text-warning"><?php echo $stats['booking']; ?></h3>
                            <p class="label">Pending Bookings</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="icon bg-info text-white">
                                <i class="fas fa-ship"></i>
                            </div>
                            <h3 class="number text-info"><?php echo $stats['in_transit']; ?></h3>
                            <p class="label">In Transit</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="icon bg-success text-white">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <h3 class="number text-success"><?php echo formatNumber($monthly_revenue, 0); ?></h3>
                            <p class="label">Monthly Revenue (THB)</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="quick-actions">
                            <h5 class="mb-3"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                            <a href="jobs_add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>New Job
                            </a>
                            <a href="customers_add.php" class="btn btn-outline-primary">
                                <i class="fas fa-user-plus me-2"></i>Add Customer
                            </a>
                            <a href="quotations_add.php" class="btn btn-outline-secondary">
                                <i class="fas fa-file-plus me-2"></i>New Quotation
                            </a>
                            <a href="invoices_add.php" class="btn btn-outline-success">
                                <i class="fas fa-receipt me-2"></i>Create Invoice
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Jobs -->
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-history me-2"></i>Recent Jobs
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Job No.</th>
                                                <th>Type</th>
                                                <th>Customer</th>
                                                <th>Route</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recent_jobs)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-muted">
                                                    <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                                    No jobs found
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                            <?php foreach ($recent_jobs as $job): ?>
                                            <tr>
                                                <td>
                                                    <a href="jobs_view.php?id=<?php echo $job['id']; ?>" class="text-decoration-none">
                                                        <strong><?php echo $job['job_no']; ?></strong>
                                                    </a>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo strtoupper(str_replace('_', ' ', $job['job_type'])); ?>
                                                    </small>
                                                </td>
                                                <td><?php echo $job['shipper_name'] ?: '-'; ?></td>
                                                <td>
                                                    <small>
                                                        <?php echo $job['origin']; ?> → <?php echo $job['destination']; ?>
                                                    </small>
                                                </td>
                                                <td><?php echo getStatusBadge($job['status']); ?></td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo formatDateThai($job['created_at'], 'd/m/y'); ?>
                                                    </small>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="card-footer bg-light">
                                    <a href="jobs.php" class="btn btn-sm btn-outline-primary">
                                        View All Jobs <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Outstanding Invoices -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Overdue Invoices
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($overdue_invoices)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-check-circle fa-2x mb-2 text-success"></i><br>
                                    <small>No overdue invoices</small>
                                </div>
                                <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($overdue_invoices as $invoice): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo $invoice['invoice_no']; ?></h6>
                                                <p class="mb-1 small text-muted"><?php echo $invoice['company_name']; ?></p>
                                                <small class="text-danger">
                                                    <i class="fas fa-calendar-times me-1"></i>
                                                    Due: <?php echo formatDateThai($invoice['due_date']); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <strong class="text-danger">
                                                    <?php echo formatMoney($invoice['total_amount'] - $invoice['paid_amount']); ?>
                                                </strong>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <div class="card-footer bg-light">
                                    <a href="invoices.php?status=overdue" class="btn btn-sm btn-outline-danger">
                                        View All Overdue <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="row mt-5">
                    <div class="col-12">
                        <div class="text-center text-muted">
                            <small>
                                © <?php echo date('Y'); ?> Freight Pro System. 
                                Powered by <i class="fas fa-heart text-danger"></i>
                            </small>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-refresh dashboard every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000); // 5 minutes
        
        // Smooth scroll for internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
        
        // Tooltips initialization
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Add loading state to buttons
        document.querySelectorAll('.btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (this.href && !this.href.includes('#')) {
                    this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
                }
            });
        });
    </script>
</body>
</html>