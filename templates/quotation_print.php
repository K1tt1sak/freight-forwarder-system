<?php
// =====================================================
// templates/quotation_print.php
// Printable Quotation Template for Freight Forwarder System
// =====================================================

// Include functions
require_once '../includes/functions.php';

// Check if user is logged in
requireLogin();

// Get quotation ID from URL
$quotation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($quotation_id <= 0) {
    die('Invalid quotation ID');
}

// Get quotation data with customer information
$quotation = fetchOne("
    SELECT q.*, c.company_name, c.contact_person, c.phone, c.email, c.address, c.tax_id,
           u.name as created_by_name
    FROM quotations q
    LEFT JOIN customers c ON q.customer_id = c.id
    LEFT JOIN users u ON q.created_by = u.id
    WHERE q.id = ?
", [$quotation_id]);

if (!$quotation) {
    die('Quotation not found');
}

// Get quotation items
$quotation_items = fetchAll("
    SELECT qi.*, 
           CASE qi.item_type
               WHEN 'freight' THEN 'Freight Charges'
               WHEN 'local_charge' THEN 'Local Charges'
               WHEN 'customs' THEN 'Customs Clearance'
               WHEN 'trucking' THEN 'Trucking Services'
               WHEN 'documentation' THEN 'Documentation'
               WHEN 'service_fee' THEN 'Service Fee'
               ELSE 'Other Services'
           END as item_type_display
    FROM quotation_items qi
    WHERE qi.quotation_id = ?
    ORDER BY qi.id
", [$quotation_id]);

// Get company settings
$company_name = getSetting('company_name', 'Your Freight Company Ltd.');
$company_address = getSetting('company_address', '123 Business District, Bangkok 10110');
$company_phone = getSetting('company_phone', '02-123-4567');
$company_email = getSetting('company_email', 'info@company.com');

// Calculate totals
$subtotal = array_sum(array_column($quotation_items, 'amount'));
$vat_rate = (float)getSetting('vat_rate', '7.00');
$vat_amount = $subtotal * ($vat_rate / 100);
$total_amount = $subtotal + $vat_amount;

// Format route display
$route_display = '';
if ($quotation['origin'] && $quotation['destination']) {
    $route_display = $quotation['origin'] . ' → ' . $quotation['destination'];
} else {
    $route_display = 'Route to be confirmed';
}

// Get current date for printing
$print_date = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation <?php echo htmlspecialchars($quotation['quotation_no']); ?></title>
    <style>
        /* Print-specific styles */
        @media print {
            @page {
                size: A4;
                margin: 1cm;
            }
            body {
                font-family: 'Arial', sans-serif;
                font-size: 12px;
                line-height: 1.4;
                color: #000;
                background: white;
            }
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-before: always;
            }
        }

        /* Screen styles */
        body {
            font-family: 'Arial', sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: #333;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        /* Header styles */
        .header {
            border-bottom: 3px solid #667eea;
            margin-bottom: 30px;
            padding-bottom: 20px;
        }

        .company-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .company-logo {
            flex: 1;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .company-details {
            font-size: 12px;
            color: #666;
            line-height: 1.6;
        }

        .quotation-title {
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin: 20px 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        /* Info sections */
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 40px;
        }

        .info-block {
            flex: 1;
        }

        .info-block h3 {
            font-size: 14px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .info-block p {
            margin: 3px 0;
            font-size: 13px;
        }

        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 100px;
            color: #555;
        }

        /* Table styles */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 12px;
        }

        .items-table th {
            background-color: #667eea;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #5a67d8;
        }

        .items-table td {
            padding: 10px 8px;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        .items-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .items-table tbody tr:hover {
            background-color: #e3f2fd;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* Totals section */
        .totals-section {
            margin-top: 30px;
            border-top: 2px solid #667eea;
            padding-top: 20px;
        }

        .totals-table {
            width: 300px;
            margin-left: auto;
            font-size: 13px;
        }

        .totals-table td {
            padding: 8px 15px;
            border-bottom: 1px solid #eee;
        }

        .totals-table .total-row {
            font-weight: bold;
            font-size: 16px;
            background-color: #f0f4ff;
            border-top: 2px solid #667eea;
        }

        /* Terms and conditions */
        .terms-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .terms-title {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 10px;
            color: #333;
        }

        .terms-list {
            font-size: 11px;
            line-height: 1.6;
            color: #555;
        }

        .terms-list li {
            margin-bottom: 5px;
        }

        /* Footer */
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 11px;
            color: #666;
        }

        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }

        .signature-block {
            text-align: center;
            width: 200px;
        }

        .signature-line {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 5px;
            font-size: 12px;
        }

        /* Status badge */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-draft { background: #6c757d; color: white; }
        .status-sent { background: #17a2b8; color: white; }
        .status-accepted { background: #28a745; color: white; }
        .status-rejected { background: #dc3545; color: white; }
        .status-expired { background: #ffc107; color: #212529; }

        /* Print button */
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .print-button:hover {
            background: #5a67d8;
        }

        /* Utility classes */
        .text-bold { font-weight: bold; }
        .text-muted { color: #666; }
        .mb-0 { margin-bottom: 0; }
        .mt-20 { margin-top: 20px; }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
                margin: 10px;
            }
            
            .company-info {
                flex-direction: column;
            }
            
            .info-section {
                flex-direction: column;
                gap: 20px;
            }
            
            .items-table {
                font-size: 11px;
            }
            
            .quotation-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Print Button -->
    <button class="print-button no-print" onclick="window.print()">
        <i class="fas fa-print"></i> Print Quotation
    </button>

    <div class="container">
        <!-- Header Section -->
        <div class="header">
            <div class="company-info">
                <div class="company-logo">
                    <div class="company-name"><?php echo htmlspecialchars($company_name); ?></div>
                    <div class="company-details">
                        <?php echo nl2br(htmlspecialchars($company_address)); ?><br>
                        Tel: <?php echo htmlspecialchars($company_phone); ?><br>
                        Email: <?php echo htmlspecialchars($company_email); ?>
                    </div>
                </div>
                <div class="quotation-status">
                    <span class="status-badge status-<?php echo $quotation['status']; ?>">
                        <?php echo ucfirst($quotation['status']); ?>
                    </span>
                </div>
            </div>
            
            <div class="quotation-title">Quotation</div>
        </div>

        <!-- Quotation and Customer Information -->
        <div class="info-section">
            <div class="info-block">
                <h3>Quotation Information</h3>
                <p><span class="info-label">Quotation No:</span> <?php echo htmlspecialchars($quotation['quotation_no']); ?></p>
                <p><span class="info-label">Date:</span> <?php echo formatDateThai($quotation['quotation_date']); ?></p>
                <p><span class="info-label">Valid Until:</span> <?php echo formatDateThai($quotation['valid_until']); ?></p>
                <p><span class="info-label">Prepared By:</span> <?php echo htmlspecialchars($quotation['created_by_name']); ?></p>
                <p><span class="info-label">Service Type:</span> <?php echo ucfirst(str_replace('_', ' ', $quotation['service_type'])); ?></p>
            </div>

            <div class="info-block">
                <h3>Customer Information</h3>
                <p><span class="info-label">Company:</span> <?php echo htmlspecialchars($quotation['company_name']); ?></p>
                <?php if ($quotation['contact_person']): ?>
                <p><span class="info-label">Contact:</span> <?php echo htmlspecialchars($quotation['contact_person']); ?></p>
                <?php endif; ?>
                <?php if ($quotation['phone']): ?>
                <p><span class="info-label">Phone:</span> <?php echo htmlspecialchars($quotation['phone']); ?></p>
                <?php endif; ?>
                <?php if ($quotation['email']): ?>
                <p><span class="info-label">Email:</span> <?php echo htmlspecialchars($quotation['email']); ?></p>
                <?php endif; ?>
                <?php if ($quotation['tax_id']): ?>
                <p><span class="info-label">Tax ID:</span> <?php echo htmlspecialchars($quotation['tax_id']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Shipment Details -->
        <div class="info-section">
            <div class="info-block">
                <h3>Shipment Details</h3>
                <p><span class="info-label">Service:</span> <?php echo ucfirst(str_replace('_', ' ', $quotation['job_type'])); ?></p>
                <p><span class="info-label">Route:</span> <?php echo htmlspecialchars($route_display); ?></p>
                <?php if ($quotation['cargo_description']): ?>
                <p><span class="info-label">Cargo:</span> <?php echo nl2br(htmlspecialchars($quotation['cargo_description'])); ?></p>
                <?php endif; ?>
            </div>

            <div class="info-block">
                <h3>Additional Information</h3>
                <p><span class="info-label">Currency:</span> <?php echo htmlspecialchars($quotation['currency']); ?></p>
                <p><span class="info-label">Printed:</span> <?php echo $print_date; ?></p>
                <?php if ($quotation['remark']): ?>
                <p><span class="info-label">Remarks:</span> <?php echo nl2br(htmlspecialchars($quotation['remark'])); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quotation Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 8%">#</th>
                    <th style="width: 20%">Service Type</th>
                    <th style="width: 35%">Description</th>
                    <th style="width: 12%">Unit</th>
                    <th style="width: 10%">Qty</th>
                    <th style="width: 15%" class="text-right">Unit Price</th>
                    <th style="width: 15%" class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($quotation_items)): ?>
                    <?php foreach ($quotation_items as $index => $item): ?>
                    <tr>
                        <td class="text-center"><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($item['item_type_display']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($item['description'])); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($item['unit'] ?: 'Per Shipment'); ?></td>
                        <td class="text-center"><?php echo formatNumber($item['quantity'], 0); ?></td>
                        <td class="text-right"><?php echo formatNumber($item['unit_price'], 2); ?></td>
                        <td class="text-right text-bold"><?php echo formatNumber($item['amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">No items found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Totals Section -->
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td>Subtotal:</td>
                    <td class="text-right"><?php echo formatNumber($subtotal, 2); ?> <?php echo $quotation['currency']; ?></td>
                </tr>
                <tr>
                    <td>VAT (<?php echo $vat_rate; ?>%):</td>
                    <td class="text-right"><?php echo formatNumber($vat_amount, 2); ?> <?php echo $quotation['currency']; ?></td>
                </tr>
                <tr class="total-row">
                    <td>Total Amount:</td>
                    <td class="text-right"><?php echo formatNumber($total_amount, 2); ?> <?php echo $quotation['currency']; ?></td>
                </tr>
            </table>
        </div>

        <!-- Terms and Conditions -->
        <div class="terms-section">
            <div class="terms-title">Terms and Conditions:</div>
            <ul class="terms-list">
                <li>This quotation is valid until <?php echo formatDateThai($quotation['valid_until']); ?></li>
                <li>All prices are quoted in <?php echo $quotation['currency']; ?> and exclude VAT unless otherwise stated</li>
                <li>Payment terms: 30 days from invoice date unless otherwise agreed</li>
                <li>Cargo must be ready for collection as per agreed schedule</li>
                <li>All documentation must be complete and accurate</li>
                <li>Rates are subject to fuel surcharge adjustments</li>
                <li>Insurance coverage is available upon request</li>
                <li>Any storage charges will be applied as per standard rates</li>
                <li>This quotation excludes any unforeseen charges or government levies</li>
                <li>Final confirmation required to proceed with booking</li>
            </ul>
        </div>

        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-block">
                <div class="signature-line">
                    Prepared By<br>
                    <?php echo htmlspecialchars($quotation['created_by_name']); ?>
                </div>
            </div>
            <div class="signature-block">
                <div class="signature-line">
                    Customer Acceptance<br>
                    Name & Signature
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong><?php echo htmlspecialchars($company_name); ?></strong></p>
            <p>Professional Freight Forwarding Services | Trusted Logistics Partner</p>
            <p class="text-muted">This is a computer-generated quotation. For inquiries, please contact us at <?php echo htmlspecialchars($company_email); ?></p>
        </div>
    </div>

    <!-- JavaScript for print functionality -->
    <script>
        // Auto-focus for print dialog
        window.addEventListener('load', function() {
            // Add print keyboard shortcut
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'p') {
                    e.preventDefault();
                    window.print();
                }
            });
        });

        // Print function
        function printQuotation() {
            window.print();
        }

        // Close after printing (optional)
        window.addEventListener('afterprint', function() {
            // Uncomment if you want to close window after printing
            // window.close();
        });
    </script>

    <!-- Font Awesome for icons (if not already included) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</body>
</html>