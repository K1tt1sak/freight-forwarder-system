<?php
// =====================================================
// reports.php - Reports Dashboard
// รายงานและการวิเคราะห์ข้อมูลระบบ Freight Forwarder
// =====================================================

// Include header และ functions
$custom_page_title = 'Reports & Analytics';
$page_header = true;
$page_subtitle = 'Business Intelligence and Performance Analysis';

// Set breadcrumb
$breadcrumb = [
    ['name' => 'Reports']
];

$additional_css = "
<style>
.report-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    cursor: pointer;
    border: none;
    background: white;
    border-radius: 15px;
    overflow: hidden;
}

.report-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.report-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.8;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-item {
    background: white;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary-color);
}

.stat-label {
    color: #6c757d;
    font-size: 0.9rem;
    margin-top: 0.5rem;
}

.quick-filter {
    background: white;
    padding: 1.5rem;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.chart-container {
    background: white;
    padding: 1.5rem;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.filter-chip {
    display: inline-block;
    padding: 0.4rem 0.8rem;
    background: var(--primary-color);
    color: white;
    border-radius: 20px;
    font-size: 0.8rem;
    margin: 0.2rem;
}

.trend-up {
    color: #28a745;
}

.trend-down {
    color: #dc3545;
}

.trend-neutral {
    color: #6c757d;
}
</style>
";

include 'includes/header.php';

// Get date range for default filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today
$job_type_filter = isset($_GET['job_type']) ? $_GET['job_type'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Get quick statistics
$total_jobs = fetchOne("SELECT COUNT(*) as count FROM jobs WHERE created_at BETWEEN ? AND ?", [$start_date . ' 00:00:00', $end_date . ' 23:59:59'])['count'];
$completed_jobs = fetchOne("SELECT COUNT(*) as count FROM jobs WHERE status = 'completed' AND created_at BETWEEN ? AND ?", [$start_date . ' 00:00:00', $end_date . ' 23:59:59'])['count'];
$active_jobs = fetchOne("SELECT COUNT(*) as count FROM jobs WHERE status NOT IN ('completed', 'cancelled') AND created_at BETWEEN ? AND ?", [$start_date . ' 00:00:00', $end_date . ' 23:59:59'])['count'];

$total_revenue = fetchOne("SELECT COALESCE(SUM(selling_total), 0) as total FROM jobs WHERE created_at BETWEEN ? AND ?", [$start_date . ' 00:00:00', $end_date . ' 23:59:59'])['total'];
$total_cost = fetchOne("SELECT COALESCE(SUM(cost_total), 0) as total FROM jobs WHERE created_at BETWEEN ? AND ?", [$start_date . ' 00:00:00', $end_date . ' 23:59:59'])['total'];
$total_profit = $total_revenue - $total_cost;

$pending_invoices = fetchOne("SELECT COUNT(*) as count FROM invoices WHERE payment_status IN ('pending', 'partial')")['count'];
$overdue_invoices = fetchOne("SELECT COUNT(*) as count FROM invoices WHERE payment_status IN ('pending', 'partial') AND due_date < CURDATE()")['count'];

// Calculate trends (compare with previous period)
$previous_start = date('Y-m-d', strtotime($start_date . ' -1 month'));
$previous_end = date('Y-m-d', strtotime($end_date . ' -1 month'));
$previous_revenue = fetchOne("SELECT COALESCE(SUM(selling_total), 0) as total FROM jobs WHERE created_at BETWEEN ? AND ?", [$previous_start . ' 00:00:00', $previous_end . ' 23:59:59'])['total'];
$revenue_trend = $previous_revenue > 0 ? (($total_revenue - $previous_revenue) / $previous_revenue) * 100 : 0;

// Get job type distribution
$job_types = fetchAll("
    SELECT 
        job_type,
        COUNT(*) as count,
        SUM(selling_total) as revenue,
        SUM(profit_loss) as profit
    FROM jobs 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY job_type
    ORDER BY count DESC
", [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);

// Get top customers
$top_customers = fetchAll("
    SELECT 
        c.company_name,
        COUNT(j.id) as job_count,
        SUM(j.selling_total) as total_revenue,
        SUM(j.profit_loss) as total_profit
    FROM customers c
    LEFT JOIN jobs j ON (j.shipper_id = c.id OR j.consignee_id = c.id)
    WHERE j.created_at BETWEEN ? AND ?
    GROUP BY c.id, c.company_name
    HAVING job_count > 0
    ORDER BY total_revenue DESC
    LIMIT 10
", [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);

// Get monthly revenue trend for chart
$monthly_data = fetchAll("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as job_count,
        SUM(selling_total) as revenue,
        SUM(cost_total) as cost,
        SUM(profit_loss) as profit
    FROM jobs 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
");
?>

<div class="container-fluid">
    <!-- Quick Filters -->
    <div class="quick-filter">
        <form method="GET" id="filterForm">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Job Type</label>
                    <select class="form-select" name="job_type">
                        <option value="">All Types</option>
                        <option value="export_air" <?php echo $job_type_filter == 'export_air' ? 'selected' : ''; ?>>Export Air</option>
                        <option value="export_sea" <?php echo $job_type_filter == 'export_sea' ? 'selected' : ''; ?>>Export Sea</option>
                        <option value="import_air" <?php echo $job_type_filter == 'import_air' ? 'selected' : ''; ?>>Import Air</option>
                        <option value="import_sea" <?php echo $job_type_filter == 'import_sea' ? 'selected' : ''; ?>>Import Sea</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="in_transit" <?php echo $status_filter == 'in_transit' ? 'selected' : ''; ?>>In Transit</option>
                        <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Apply Filter
                    </button>
                </div>
            </div>
        </form>
        
        <!-- Active Filters Display -->
        <?php if ($start_date != date('Y-m-01') || $end_date != date('Y-m-d') || $job_type_filter || $status_filter): ?>
        <div class="mt-3">
            <small class="text-muted">Active Filters:</small>
            <?php if ($start_date != date('Y-m-01') || $end_date != date('Y-m-d')): ?>
                <span class="filter-chip"><?php echo formatDateThai($start_date) . ' - ' . formatDateThai($end_date); ?></span>
            <?php endif; ?>
            <?php if ($job_type_filter): ?>
                <span class="filter-chip"><?php echo ucfirst(str_replace('_', ' ', $job_type_filter)); ?></span>
            <?php endif; ?>
            <?php if ($status_filter): ?>
                <span class="filter-chip"><?php echo ucfirst($status_filter); ?></span>
            <?php endif; ?>
            <a href="reports.php" class="btn btn-sm btn-outline-secondary ms-2">Clear All</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Statistics -->
    <div class="stats-grid">
        <div class="stat-item">
            <div class="stat-value"><?php echo number_format($total_jobs); ?></div>
            <div class="stat-label">Total Jobs</div>
            <small class="text-muted">
                <?php echo number_format($completed_jobs); ?> completed, 
                <?php echo number_format($active_jobs); ?> active
            </small>
        </div>
        
        <div class="stat-item">
            <div class="stat-value"><?php echo formatMoney($total_revenue, 'THB'); ?></div>
            <div class="stat-label">Total Revenue</div>
            <small class="<?php echo $revenue_trend >= 0 ? 'trend-up' : 'trend-down'; ?>">
                <i class="fas fa-<?php echo $revenue_trend >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                <?php echo number_format(abs($revenue_trend), 1); ?>% vs previous period
            </small>
        </div>
        
        <div class="stat-item">
            <div class="stat-value"><?php echo formatMoney($total_profit, 'THB'); ?></div>
            <div class="stat-label">Total Profit</div>
            <small class="text-muted">
                Margin: <?php echo $total_revenue > 0 ? number_format(($total_profit / $total_revenue) * 100, 1) : 0; ?>%
            </small>
        </div>
        
        <div class="stat-item">
            <div class="stat-value text-warning"><?php echo number_format($pending_invoices); ?></div>
            <div class="stat-label">Pending Invoices</div>
            <small class="text-danger">
                <?php echo number_format($overdue_invoices); ?> overdue
            </small>
        </div>
    </div>

    <!-- Charts and Analysis -->
    <div class="row">
        <!-- Monthly Revenue Trend -->
        <div class="col-lg-8">
            <div class="chart-container">
                <h5><i class="fas fa-chart-line me-2"></i>Monthly Revenue Trend</h5>
                <div style="position: relative; height: 400px;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Job Type Distribution -->
        <div class="col-lg-4">
            <div class="chart-container">
                <h5><i class="fas fa-chart-pie me-2"></i>Job Type Distribution</h5>
                <div style="position: relative; height: 400px;">
                    <canvas id="jobTypeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Reports Cards -->
    <div class="row">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="report-card card h-100 text-center p-4" onclick="window.location.href='reports/job_summary.php'">
                <div class="report-icon">
                    <i class="fas fa-shipping-fast text-primary"></i>
                </div>
                <h5>Job Summary Report</h5>
                <p class="text-muted">Detailed analysis of all jobs including performance metrics and status breakdown</p>
                <div class="mt-auto">
                    <span class="badge bg-primary"><?php echo number_format($total_jobs); ?> Jobs</span>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="report-card card h-100 text-center p-4" onclick="window.location.href='reports/profit_loss.php'">
                <div class="report-icon">
                    <i class="fas fa-chart-bar text-success"></i>
                </div>
                <h5>Profit & Loss Report</h5>
                <p class="text-muted">Financial analysis with cost breakdown and profitability by job, customer, and service type</p>
                <div class="mt-auto">
                    <span class="badge bg-success"><?php echo formatMoney($total_profit, 'THB'); ?></span>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="report-card card h-100 text-center p-4" onclick="window.location.href='reports/customer_analysis.php'">
                <div class="report-icon">
                    <i class="fas fa-users text-info"></i>
                </div>
                <h5>Customer Analysis</h5>
                <p class="text-muted">Customer performance, revenue contribution, and business relationship analysis</p>
                <div class="mt-auto">
                    <span class="badge bg-info"><?php echo count($top_customers); ?> Active Customers</span>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="report-card card h-100 text-center p-4" onclick="window.location.href='reports/vendor_performance.php'">
                <div class="report-icon">
                    <i class="fas fa-truck text-warning"></i>
                </div>
                <h5>Vendor Performance</h5>
                <p class="text-muted">Vendor cost analysis, payment terms, and service quality evaluation</p>
                <div class="mt-auto">
                    <?php $vendor_count = fetchOne("SELECT COUNT(*) as count FROM vendors WHERE status = 'active'")['count']; ?>
                    <span class="badge bg-warning"><?php echo number_format($vendor_count); ?> Active Vendors</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Report Options -->
    <div class="row">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="report-card card h-100 text-center p-4" onclick="window.location.href='reports/invoice_analysis.php'">
                <div class="report-icon">
                    <i class="fas fa-file-invoice-dollar text-primary"></i>
                </div>
                <h5>Invoice Analysis</h5>
                <p class="text-muted">Payment status, aging analysis, and cash flow reports</p>
                <div class="mt-auto">
                    <span class="badge bg-danger"><?php echo number_format($overdue_invoices); ?> Overdue</span>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="report-card card h-100 text-center p-4" onclick="window.location.href='reports/operational_metrics.php'">
                <div class="report-icon">
                    <i class="fas fa-tachometer-alt text-success"></i>
                </div>
                <h5>Operational Metrics</h5>
                <p class="text-muted">Efficiency metrics, turnaround times, and operational KPIs</p>
                <div class="mt-auto">
                    <?php 
                    $avg_completion = fetchOne("SELECT AVG(DATEDIFF(delivery_date, created_at)) as avg_days FROM jobs WHERE delivery_date IS NOT NULL AND created_at BETWEEN ? AND ?", [$start_date . ' 00:00:00', $end_date . ' 23:59:59'])['avg_days'];
                    ?>
                    <span class="badge bg-success"><?php echo round($avg_completion ?: 0); ?> Avg Days</span>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="report-card card h-100 text-center p-4" onclick="window.location.href='reports/quotation_analysis.php'">
                <div class="report-icon">
                    <i class="fas fa-file-contract text-info"></i>
                </div>
                <h5>Quotation Analysis</h5>
                <p class="text-muted">Quote-to-job conversion rates and sales pipeline analysis</p>
                <div class="mt-auto">
                    <?php 
                    $quote_count = fetchOne("SELECT COUNT(*) as count FROM quotations WHERE quotation_date BETWEEN ? AND ?", [$start_date, $end_date])['count'];
                    ?>
                    <span class="badge bg-info"><?php echo number_format($quote_count); ?> Quotes</span>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="report-card card h-100 text-center p-4" onclick="exportAllData()">
                <div class="report-icon">
                    <i class="fas fa-download text-secondary"></i>
                </div>
                <h5>Export Data</h5>
                <p class="text-muted">Export filtered data to Excel for detailed analysis and external reporting</p>
                <div class="mt-auto">
                    <span class="badge bg-secondary">Excel Export</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Customers Table -->
    <?php if (!empty($top_customers)): ?>
    <div class="chart-container">
        <h5><i class="fas fa-crown me-2"></i>Top Customers by Revenue</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Customer Name</th>
                        <th>Jobs</th>
                        <th>Revenue</th>
                        <th>Profit</th>
                        <th>Profit Margin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_customers as $index => $customer): ?>
                    <tr>
                        <td><span class="badge bg-primary">#<?php echo $index + 1; ?></span></td>
                        <td>
                            <strong><?php echo htmlspecialchars($customer['company_name']); ?></strong>
                        </td>
                        <td><?php echo number_format($customer['job_count']); ?></td>
                        <td><?php echo formatMoney($customer['total_revenue'], 'THB'); ?></td>
                        <td class="<?php echo $customer['total_profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo formatMoney($customer['total_profit'], 'THB'); ?>
                        </td>
                        <td>
                            <?php 
                            $margin = $customer['total_revenue'] > 0 ? ($customer['total_profit'] / $customer['total_revenue']) * 100 : 0;
                            echo number_format($margin, 1) . '%';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Chart.js Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Revenue Trend Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const revenueChart = new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: [<?php echo "'" . implode("','", array_column($monthly_data, 'month')) . "'"; ?>],
        datasets: [{
            label: 'Revenue',
            data: [<?php echo implode(',', array_column($monthly_data, 'revenue')); ?>],
            borderColor: 'rgb(102, 126, 234)',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Profit',
            data: [<?php echo implode(',', array_column($monthly_data, 'profit')); ?>],
            borderColor: 'rgb(40, 167, 69)',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            intersect: false,
            mode: 'index'
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'THB ' + value.toLocaleString();
                    }
                },
                grid: {
                    color: 'rgba(0,0,0,0.1)'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': THB ' + context.parsed.y.toLocaleString();
                    }
                }
            },
            legend: {
                display: true,
                position: 'top'
            }
        }
    }
});

// Job Type Distribution Chart
const jobTypeCtx = document.getElementById('jobTypeChart').getContext('2d');
const jobTypeData = <?php echo json_encode($job_types); ?>;
const jobTypeChart = new Chart(jobTypeCtx, {
    type: 'doughnut',
    data: {
        labels: jobTypeData.map(item => item.job_type.replace('_', ' ').toUpperCase()),
        datasets: [{
            data: jobTypeData.map(item => item.count),
            backgroundColor: [
                'rgba(102, 126, 234, 0.8)',
                'rgba(40, 167, 69, 0.8)',
                'rgba(255, 193, 7, 0.8)',
                'rgba(220, 53, 69, 0.8)'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    usePointStyle: true,
                    boxWidth: 12
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                    }
                }
            }
        },
        layout: {
            padding: {
                top: 10,
                bottom: 10,
                left: 10,
                right: 10
            }
        }
    }
});

// Export Function
// แทนที่ฟังก์ชัน exportAllData() เดิม
function exportAllData() {
    // ไปหน้า export selection พร้อม filters
    const params = new URLSearchParams();
    params.set('start_date', document.querySelector('input[name="start_date"]').value);
    params.set('end_date', document.querySelector('input[name="end_date"]').value);
    params.set('job_type', document.querySelector('select[name="job_type"]').value);
    params.set('status', document.querySelector('select[name="status"]').value);
    
    window.location.href = 'reports/export_selection.php?' + params.toString();
}
// Auto-refresh data every 5 minutes
setInterval(() => {
    location.reload();
}, 300000);

// Quick date range buttons
document.addEventListener('DOMContentLoaded', function() {
    // Add quick date range buttons
    const quickRanges = document.createElement('div');
    quickRanges.className = 'mt-2';
    quickRanges.innerHTML = `
        <small class="text-muted me-2">Quick ranges:</small>
        <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="setDateRange('today')">Today</button>
        <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="setDateRange('week')">This Week</button>
        <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="setDateRange('month')">This Month</button>
        <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="setDateRange('quarter')">This Quarter</button>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="setDateRange('year')">This Year</button>
    `;
    
    document.querySelector('.quick-filter form').appendChild(quickRanges);
});

function setDateRange(range) {
    const startDateInput = document.querySelector('input[name="start_date"]');
    const endDateInput = document.querySelector('input[name="end_date"]');
    const today = new Date();
    
    switch(range) {
        case 'today':
            startDateInput.value = today.toISOString().split('T')[0];
            endDateInput.value = today.toISOString().split('T')[0];
            break;
        case 'week':
            const weekStart = new Date(today.setDate(today.getDate() - today.getDay()));
            startDateInput.value = weekStart.toISOString().split('T')[0];
            endDateInput.value = new Date().toISOString().split('T')[0];
            break;
        case 'month':
            startDateInput.value = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            endDateInput.value = new Date().toISOString().split('T')[0];
            break;
        case 'quarter':
            const quarterStart = new Date(today.getFullYear(), Math.floor(today.getMonth() / 3) * 3, 1);
            startDateInput.value = quarterStart.toISOString().split('T')[0];
            endDateInput.value = new Date().toISOString().split('T')[0];
            break;
        case 'year':
            startDateInput.value = new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0];
            endDateInput.value = new Date().toISOString().split('T')[0];
            break;
    }
    
    document.getElementById('filterForm').submit();
}
</script>

<?php include 'includes/footer.php'; ?>