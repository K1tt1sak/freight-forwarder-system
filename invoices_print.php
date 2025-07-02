<?php
// =====================================================
// invoices_print.php - Print Invoice Layout
// =====================================================

// เริ่มต้น session และเรียกใช้ functions
require_once 'includes/functions.php';

// ตรวจสอบสิทธิ์ - ต้องเป็น viewer ขึ้นไป
requirePermission('viewer');

// รับ invoice ID
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($invoice_id <= 0) {
    die('Invalid invoice ID');
}

// ดึงข้อมูลใบแจ้งหนี้
$invoice = fetchOne("
    SELECT 
        i.*,
        c.company_name as customer_name,
        c.customer_code,
        c.address as customer_address,
        c.phone as customer_phone,
        c.email as customer_email,
        c.tax_id as customer_tax_id,
        j.job_no,
        j.job_type,
        j.origin,
        j.destination,
        j.vessel_flight,
        j.etd,
        j.eta,
        u.name as created_by_name,
        (i.total_amount - i.paid_amount) as outstanding_amount
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    LEFT JOIN jobs j ON i.job_id = j.id
    LEFT JOIN users u ON i.created_by = u.id
    WHERE i.id = ?
", [$invoice_id]);

if (!$invoice) {
    die('Invoice not found');
}

// ดึงรายการสินค้า/บริการ
$invoice_items = fetchAll("
    SELECT * FROM invoice_items 
    WHERE invoice_id = ? 
    ORDER BY id
", [$invoice_id]);

// ดึงข้อมูลบริษัท
$company_info = [
    'name' => getSetting('company_name', 'Your Freight Company Ltd.'),
    'address' => getSetting('company_address', '123 Business District, Bangkok 10110'),
    'phone' => getSetting('company_phone', '02-123-4567'),
    'email' => getSetting('company_email', 'info@company.com'),
    'tax_id' => getSetting('company_tax_id', '0123456789012'),
    'website' => getSetting('company_website', 'www.company.com')
];

// สร้างข้อความสถานะการจ่าย
function getPaymentStatusText($status) {
    $status_map = [
        'pending' => 'PENDING PAYMENT',
        'partial' => 'PARTIALLY PAID',
        'paid' => 'PAID',
        'overdue' => 'OVERDUE',
        'cancelled' => 'CANCELLED'
    ];
    
    return $status_map[$status] ?? 'UNKNOWN';
}

// แปลงตัวเลขเป็นตัวอักษร (สำหรับ amount in words)
function numberToWords($number) {
    // Simple implementation for Thai Baht
    $number = number_format($number, 2, '.', '');
    $parts = explode('.', $number);
    $baht = (int)$parts[0];
    $satang = (int)$parts[1];
    
    if ($baht == 0 && $satang == 0) {
        return 'Zero Baht Only';
    }
    
    // For simplicity, return formatted number
    return number_format($baht, 0) . ' Baht' . ($satang > 0 ? ' and ' . $satang . ' Satang' : ' Only');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo htmlspecialchars($invoice['invoice_no']); ?> - Print</title>
    
    <!-- Bootstrap CSS for styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        /* Print-specific styles */
        @page {
            size: A4;
            margin: 1cm;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            
            .page-break {
                page-break-after: always;
            }
            
            .print-header {
                border-bottom: 3px solid #333;
                margin-bottom: 20px;
            }
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 14px;
            line-height: 1.4;
            color: #333;
        }
        
        .invoice-container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 20px;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .company-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .company-logo {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
        }
        
        .invoice-title {
            font-size: 36px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            color: #333;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .invoice-number {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        
        .info-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .info-section h6 {
            color: #667eea;
            font-weight: bold;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .items-table {
            margin: 30px 0;
        }
        
        .items-table th {
            background: #667eea;
            color: white;
            font-weight: bold;
            text-align: center;
            padding: 12px 8px;
            border: none;
        }
        
        .items-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .items-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .amount-cell {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }
        
        .total-section {
            background: #667eea;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .grand-total {
            font-size: 24px;
            font-weight: bold;
            border-top: 2px solid rgba(255,255,255,0.5);
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .payment-status {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: bold;
            font-size: 18px;
            transform: rotate(15deg);
        }
        
        .status-paid {
            background: #28a745;
            color: white;
        }
        
        .status-pending {
            background: #ffc107;
            color: #333;
        }
        
        .status-overdue {
            background: #dc3545;
            color: white;
        }
        
        .status-partial {
            background: #17a2b8;
            color: white;
        }
        
        .terms-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
            border: 1px solid #dee2e6;
        }
        
        .qr-code {
            width: 100px;
            height: 100px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 12px;
            text-align: center;
        }
        
        .signature-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        
        .signature-box {
            text-align: center;
            padding: 40px 20px 20px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: #f8f9fa;
        }
        
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 72px;
            color: rgba(220, 53, 69, 0.1);
            font-weight: bold;
            z-index: -1;
            pointer-events: none;
        }
        
        .print-buttons {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .footer-info {
            text-align: center;
            font-size: 12px;
            color: #6c757d;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <!-- Print Buttons (hidden when printing) -->
    <div class="print-buttons no-print">
        <button onclick="window.print()" class="btn btn-primary btn-lg me-3">
            <i class="fas fa-print"></i> Print Invoice
        </button>
        <button onclick="window.close()" class="btn btn-secondary btn-lg">
            <i class="fas fa-times"></i> Close
        </button>
    </div>

    <!-- Invoice Container -->
    <div class="invoice-container">
        <!-- Payment Status Watermark -->
        <?php if ($invoice['payment_status'] === 'overdue'): ?>
            <div class="watermark">OVERDUE</div>
        <?php elseif ($invoice['payment_status'] === 'cancelled'): ?>
            <div class="watermark">CANCELLED</div>
        <?php endif; ?>
        
        <!-- Payment Status Badge -->
        <div class="payment-status status-<?php echo $invoice['payment_status']; ?>">
            <?php echo getPaymentStatusText($invoice['payment_status']); ?>
        </div>

        <!-- Company Header -->
        <div class="company-header">
            <div class="row align-items-center">
                <div class="col-2">
                    <div class="company-logo">
                        <i class="fas fa-ship"></i>
                    </div>
                </div>
                <div class="col-7">
                    <h3 class="mb-1"><?php echo htmlspecialchars($company_info['name']); ?></h3>
                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($company_info['address'])); ?></p>
                    <p class="mb-0">
                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($company_info['phone']); ?> | 
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($company_info['email']); ?>
                    </p>
                </div>
                <div class="col-3 text-end">
                    <div class="qr-code">
                        QR CODE
                        <br><small>for payment</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoice Title -->
        <div class="invoice-title">
            TAX INVOICE
        </div>

        <!-- Invoice Info Row -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="info-section">
                    <h6><i class="fas fa-file-invoice"></i> Invoice Information</h6>
                    <p class="mb-1"><strong>Invoice No:</strong> <span class="invoice-number"><?php echo htmlspecialchars($invoice['invoice_no']); ?></span></p>
                    <p class="mb-1"><strong>Invoice Date:</strong> <?php echo formatDateThai($invoice['invoice_date']); ?></p>
                    <p class="mb-1"><strong>Due Date:</strong> <?php echo formatDateThai($invoice['due_date']); ?></p>
                    <p class="mb-0"><strong>Currency:</strong> <?php echo $invoice['currency']; ?></p>
                </div>
            </div>
            
            <div class="col-md-6">
                <?php if ($invoice['job_no']): ?>
                <div class="info-section">
                    <h6><i class="fas fa-shipping-fast"></i> Shipment Information</h6>
                    <p class="mb-1"><strong>Job No:</strong> <?php echo htmlspecialchars($invoice['job_no']); ?></p>
                    <p class="mb-1"><strong>Service:</strong> <?php echo ucfirst(str_replace('_', ' ', $invoice['job_type'])); ?></p>
                    <p class="mb-1"><strong>Route:</strong> <?php echo htmlspecialchars($invoice['origin'] . ' → ' . $invoice['destination']); ?></p>
                    <p class="mb-0"><strong>Vessel/Flight:</strong> <?php echo htmlspecialchars($invoice['vessel_flight'] ?: 'TBD'); ?></p>
                </div>
                <?php else: ?>
                <div class="info-section">
                    <h6><i class="fas fa-info-circle"></i> Additional Information</h6>
                    <p class="mb-1"><strong>Terms:</strong> Net <?php echo $invoice['due_date'] ? ceil((strtotime($invoice['due_date']) - strtotime($invoice['invoice_date'])) / 86400) : '30'; ?> Days</p>
                    <p class="mb-0"><strong>Tax ID:</strong> <?php echo htmlspecialchars($company_info['tax_id']); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bill To Section -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="info-section">
                    <h6><i class="fas fa-user"></i> Bill To</h6>
                    <h5 class="mb-2"><?php echo htmlspecialchars($invoice['customer_name']); ?></h5>
                    <div class="row">
                        <div class="col-md-8">
                            <?php if ($invoice['customer_address']): ?>
                                <p class="mb-1"><?php echo nl2br(htmlspecialchars($invoice['customer_address'])); ?></p>
                            <?php endif; ?>
                            <?php if ($invoice['customer_phone']): ?>
                                <p class="mb-1"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($invoice['customer_phone']); ?></p>
                            <?php endif; ?>
                            <?php if ($invoice['customer_email']): ?>
                                <p class="mb-0"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($invoice['customer_email']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-end">
                            <p class="mb-1"><strong>Customer Code:</strong><br><?php echo htmlspecialchars($invoice['customer_code']); ?></p>
                            <?php if ($invoice['customer_tax_id']): ?>
                                <p class="mb-0"><strong>Tax ID:</strong><br><?php echo htmlspecialchars($invoice['customer_tax_id']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoice Items Table -->
        <div class="items-table">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th width="5%" style="text-align: center;">#</th>
                        <th width="45%">Description</th>
                        <th width="10%" style="text-align: center;">Qty</th>
                        <th width="15%" style="text-align: right;">Unit Price</th>
                        <th width="15%" style="text-align: right;">Amount</th>
                        <th width="10%" style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoice_items as $index => $item): ?>
                    <tr>
                        <td style="text-align: center;"><?php echo $index + 1; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($item['description']); ?></strong>
                            <?php if ($invoice['job_no']): ?>
                                <br><small class="text-muted">Job: <?php echo htmlspecialchars($invoice['job_no']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;"><?php echo formatNumber($item['quantity'], 2); ?></td>
                        <td class="amount-cell"><?php echo formatNumber($item['unit_price'], 2); ?></td>
                        <td class="amount-cell"><?php echo formatNumber($item['amount'], 2); ?></td>
                        <td class="amount-cell"><?php echo formatNumber($item['amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Add empty rows if less than 5 items (for consistent layout) -->
                    <?php for ($i = count($invoice_items); $i < 5; $i++): ?>
                    <tr>
                        <td style="text-align: center;">&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <!-- Total Section -->
        <div class="row">
            <div class="col-md-6">
                <div class="info-section">
                    <h6><i class="fas fa-comment"></i> Amount in Words</h6>
                    <p class="mb-0"><strong><?php echo numberToWords($invoice['total_amount']); ?></strong></p>
                </div>
                
                <?php if ($invoice['remark']): ?>
                <div class="info-section mt-3">
                    <h6><i class="fas fa-sticky-note"></i> Remarks</h6>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($invoice['remark'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-6">
                <div class="total-section">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span class="amount-cell"><?php echo formatMoney($invoice['subtotal'], $invoice['currency']); ?></span>
                    </div>
                    
                    <div class="total-row">
                        <span>VAT (<?php echo formatNumber($invoice['vat_rate'], 1); ?>%):</span>
                        <span class="amount-cell"><?php echo formatMoney($invoice['vat_amount'], $invoice['currency']); ?></span>
                    </div>
                    
                    <div class="total-row grand-total">
                        <span>TOTAL AMOUNT:</span>
                        <span class="amount-cell"><?php echo formatMoney($invoice['total_amount'], $invoice['currency']); ?></span>
                    </div>
                    
                    <?php if ($invoice['paid_amount'] > 0): ?>
                    <div class="total-row" style="opacity: 0.8;">
                        <span>Paid Amount:</span>
                        <span class="amount-cell"><?php echo formatMoney($invoice['paid_amount'], $invoice['currency']); ?></span>
                    </div>
                    
                    <div class="total-row" style="border-top: 1px solid rgba(255,255,255,0.5); padding-top: 10px;">
                        <span>Outstanding:</span>
                        <span class="amount-cell"><?php echo formatMoney($invoice['outstanding_amount'], $invoice['currency']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Payment Terms -->
        <div class="terms-section">
            <div class="row">
                <div class="col-md-8">
                    <h6><i class="fas fa-exclamation-circle"></i> Payment Terms & Conditions</h6>
                    <ul class="mb-0">
                        <li>Payment is due within <?php echo $invoice['due_date'] ? ceil((strtotime($invoice['due_date']) - strtotime($invoice['invoice_date'])) / 86400) : '30'; ?> days from invoice date</li>
                        <li>Late payment may be subject to 1.5% monthly service charge</li>
                        <li>All payments should be made in <?php echo $invoice['currency']; ?></li>
                        <li>Please include invoice number in payment reference</li>
                        <li>Any discrepancies should be reported within 7 days</li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6><i class="fas fa-university"></i> Bank Information</h6>
                    <p class="mb-1"><strong>Bank:</strong> Bangkok Bank</p>
                    <p class="mb-1"><strong>Account:</strong> 123-456-7890</p>
                    <p class="mb-0"><strong>Swift:</strong> BKKBTHBK</p>
                </div>
            </div>
        </div>

        <!-- Signature Section -->
        <div class="signature-section">
            <div class="row">
                <div class="col-md-6">
                    <div class="signature-box">
                        <div style="height: 60px;"></div>
                        <strong>Customer Signature</strong>
                        <br><small>Date: _______________</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="signature-box">
                        <div style="height: 60px;"></div>
                        <strong>Authorized Signature</strong>
                        <br><small><?php echo htmlspecialchars($company_info['name']); ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer-info">
            <p class="mb-1">This is a computer generated invoice and does not require signature</p>
            <p class="mb-1">Invoice created by: <?php echo htmlspecialchars($invoice['created_by_name']); ?> on <?php echo formatDateThai($invoice['created_at'], 'd/m/Y H:i'); ?></p>
            <p class="mb-0">Printed on: <?php echo date('d/m/Y H:i'); ?> | Page 1 of 1</p>
        </div>
    </div>

    <!-- Auto-print script (optional) -->
    <script>
        // Uncomment the line below to auto-print when page loads
        // window.onload = function() { window.print(); }
        
        // Print function
        function printInvoice() {
            window.print();
        }
        
        // Close function
        function closeWindow() {
            window.close();
        }
        
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
            if (e.key === 'Escape') {
                window.close();
            }
        });
    </script>
</body>
</html>