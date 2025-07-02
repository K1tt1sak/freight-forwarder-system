<?php
// =====================================================
// templates/quotation_pdf.php
// Professional PDF Generator for Quotations
// =====================================================

// Include required files
require_once '../includes/functions.php';
require_once '../vendor/autoload.php';
// Check authentication
requireLogin();

// Get quotation ID
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

// Create TCPDF instance
class QuotationPDF extends TCPDF {
    private $quotation_data;
    private $company_data;
    
    public function setQuotationData($quotation, $company) {
        $this->quotation_data = $quotation;
        $this->company_data = $company;
    }
    
    // Header
    public function Header() {
        // Company logo area (you can add actual logo here)
        $this->SetFont('helvetica', 'B', 20);
        $this->SetTextColor(51, 102, 204); // Blue color
        $this->Cell(0, 15, $this->company_data['name'], 0, 1, 'L');
        
        // Company details
        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 4, $this->company_data['address'], 0, 1, 'L');
        $this->Cell(0, 4, 'Tel: ' . $this->company_data['phone'] . ' | Email: ' . $this->company_data['email'], 0, 1, 'L');
        
        // Quotation title
        $this->Ln(10);
        $this->SetFont('helvetica', 'B', 24);
        $this->SetTextColor(51, 102, 204);
        $this->Cell(0, 12, 'FREIGHT QUOTATION', 0, 1, 'C');
        
        // Status badge
        if ($this->quotation_data) {
            $this->Ln(5);
            $status = strtoupper($this->quotation_data['status']);
            $this->SetFont('helvetica', 'B', 10);
            
            // Status colors
            switch($this->quotation_data['status']) {
                case 'draft': $this->SetTextColor(108, 117, 125); break;
                case 'sent': $this->SetTextColor(23, 162, 184); break;
                case 'accepted': $this->SetTextColor(40, 167, 69); break;
                case 'rejected': $this->SetTextColor(220, 53, 69); break;
                case 'expired': $this->SetTextColor(255, 193, 7); break;
                default: $this->SetTextColor(108, 117, 125);
            }
            
            $this->Cell(0, 6, 'STATUS: ' . $status, 0, 1, 'C');
        }
        
        $this->Ln(5);
        
        // Line separator
        $this->SetDrawColor(51, 102, 204);
        $this->SetLineWidth(0.5);
        $this->Line(15, $this->GetY(), $this->getPageWidth() - 15, $this->GetY());
        $this->Ln(8);
    }
    
    // Footer
    public function Footer() {
        $this->SetY(-25);
        
        // Line separator
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.3);
        $this->Line(15, $this->GetY(), $this->getPageWidth() - 15, $this->GetY());
        $this->Ln(3);
        
        // Footer text
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 4, $this->company_data['name'] . ' - Professional Freight Forwarding Services', 0, 1, 'C');
        $this->Cell(0, 4, 'This quotation is computer generated and valid until ' . 
                    ($this->quotation_data ? formatDateThai($this->quotation_data['valid_until'], 'd/m/Y') : ''), 0, 1, 'C');
        
        // Page number
        $this->Cell(0, 4, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Initialize PDF
$pdf = new QuotationPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set quotation data for header/footer
$pdf->setQuotationData($quotation, [
    'name' => $company_name,
    'address' => $company_address,
    'phone' => $company_phone,
    'email' => $company_email
]);

// Set document information
$pdf->SetCreator('Freight Pro System');
$pdf->SetAuthor($company_name);
$pdf->SetTitle('Quotation ' . $quotation['quotation_no']);
$pdf->SetSubject('Freight Quotation');
$pdf->SetKeywords('quotation, freight, shipping, logistics');

// Set margins
$pdf->SetMargins(15, 60, 15); // left, top, right
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 25);

// Add page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// Quotation Information Section
$info_y = $pdf->GetY();

// Left column - Quotation Details
$pdf->SetXY(15, $info_y);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(248, 249, 250);
$pdf->Cell(85, 8, 'QUOTATION INFORMATION', 1, 1, 'L', true);

$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor(255, 255, 255);

$info_data = [
    ['Quotation No:', $quotation['quotation_no']],
    ['Date:', formatDateThai($quotation['quotation_date'], 'd/m/Y')],
    ['Valid Until:', formatDateThai($quotation['valid_until'], 'd/m/Y')],
    ['Job Type:', strtoupper(str_replace('_', ' ', $quotation['job_type']))],
    ['Service Type:', ucfirst(str_replace('_', ' ', $quotation['service_type']))],
    ['Currency:', $quotation['currency']],
    ['Prepared By:', $quotation['created_by_name'] ?: 'System']
];

foreach ($info_data as $row) {
    $pdf->Cell(30, 6, $row[0], 1, 0, 'L', true);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(55, 6, $row[1], 1, 1, 'L', true);
    $pdf->SetFont('helvetica', '', 9);
}

// Right column - Customer Information
$pdf->SetXY(105, $info_y);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(248, 249, 250);
$pdf->Cell(85, 8, 'CUSTOMER INFORMATION', 1, 1, 'L', true);

$pdf->SetXY(105, $info_y + 8);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor(255, 255, 255);

$customer_data = [
    ['Company:', $quotation['company_name']],
    ['Contact Person:', $quotation['contact_person'] ?: '-'],
    ['Phone:', $quotation['phone'] ?: '-'],
    ['Email:', $quotation['email'] ?: '-'],
    ['Tax ID:', $quotation['tax_id'] ?: '-'],
    ['Route:', $route_display],
    ['', ''] // Empty row to match left column height
];

foreach ($customer_data as $row) {
    if ($row[0] === '' && $row[1] === '') continue;
    
    $pdf->SetX(105);
    $pdf->Cell(30, 6, $row[0], 1, 0, 'L', true);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(55, 6, $row[1], 1, 1, 'L', true);
    $pdf->SetFont('helvetica', '', 9);
}

$pdf->Ln(8);

// Customer Address (if available)
if ($quotation['address']) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'CUSTOMER ADDRESS:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(248, 249, 250);
    $pdf->MultiCell(0, 5, $quotation['address'], 1, 'L', true);
    $pdf->Ln(5);
}

// Cargo Description (if available)
if ($quotation['cargo_description']) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'CARGO DESCRIPTION:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(248, 249, 250);
    $pdf->MultiCell(0, 5, $quotation['cargo_description'], 1, 'L', true);
    $pdf->Ln(5);
}

// Quotation Items Table
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 8, 'QUOTATION ITEMS', 0, 1, 'L');

// Table header
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(51, 102, 204);
$pdf->SetTextColor(255, 255, 255);

$header_widths = [15, 40, 55, 20, 15, 25, 25];
$headers = ['No.', 'Service Type', 'Description', 'Unit', 'Qty', 'Unit Price', 'Amount'];

for ($i = 0; $i < count($headers); $i++) {
    $align = ($i >= 4) ? 'R' : 'L'; // Right align for numeric columns
    if ($i == 0) $align = 'C'; // Center align for No.
    $pdf->Cell($header_widths[$i], 8, $headers[$i], 1, 0, $align, true);
}
$pdf->Ln();

// Table data
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(255, 255, 255);

$row_num = 1;
$item_total = 0;

if (!empty($quotation_items)) {
    foreach ($quotation_items as $item) {
        // Alternate row colors
        $fill = ($row_num % 2 == 0);
        if ($fill) {
            $pdf->SetFillColor(248, 249, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        // Check if we need a new page
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
            
            // Repeat table header on new page
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(51, 102, 204);
            $pdf->SetTextColor(255, 255, 255);
            
            for ($i = 0; $i < count($headers); $i++) {
                $align = ($i >= 4) ? 'R' : 'L';
                if ($i == 0) $align = 'C';
                $pdf->Cell($header_widths[$i], 8, $headers[$i], 1, 0, $align, true);
            }
            $pdf->Ln();
            
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(0, 0, 0);
        }
        
        $y_before = $pdf->GetY();
        
        // Calculate row height for description
        $pdf->SetXY(15 + $header_widths[0] + $header_widths[1], $y_before);
        $description_height = $pdf->getStringHeight($header_widths[2], $item['description']);
        $row_height = max(6, $description_height);
        
        // Print row data
        $pdf->SetXY(15, $y_before);
        
        // No.
        $pdf->Cell($header_widths[0], $row_height, $row_num, 1, 0, 'C', $fill);
        
        // Service Type
        $pdf->Cell($header_widths[1], $row_height, $item['item_type_display'], 1, 0, 'L', $fill);
        
        // Description (multiCell for long text)
        $x_desc = $pdf->GetX();
        $y_desc = $pdf->GetY();
        $pdf->MultiCell($header_widths[2], $row_height, $item['description'], 1, 'L', $fill);
        
        // Continue with other columns
        $pdf->SetXY($x_desc + $header_widths[2], $y_desc);
        $pdf->Cell($header_widths[3], $row_height, $item['unit'] ?: 'per shipment', 1, 0, 'L', $fill);
        $pdf->Cell($header_widths[4], $row_height, formatNumber($item['quantity'], 0), 1, 0, 'R', $fill);
        $pdf->Cell($header_widths[5], $row_height, formatNumber($item['unit_price'], 2), 1, 0, 'R', $fill);
        $pdf->Cell($header_widths[6], $row_height, formatNumber($item['amount'], 2), 1, 0, 'R', $fill);
        
        $pdf->Ln();
        $row_num++;
        $item_total += $item['amount'];
    }
} else {
    // No items message
    $pdf->SetFillColor(248, 249, 250);
    $pdf->Cell(array_sum($header_widths), 15, 'No items found in this quotation', 1, 1, 'C', true);
}

// Totals section
$pdf->Ln(5);
$total_start_x = 15 + array_sum($header_widths) - 60; // Right align totals

// Subtotal
$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY($total_start_x, $pdf->GetY());
$pdf->Cell(35, 6, 'Subtotal:', 1, 0, 'L', true);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(25, 6, formatNumber($subtotal, 2) . ' ' . $quotation['currency'], 1, 1, 'R', true);

// VAT
$pdf->SetFont('helvetica', '', 9);
$pdf->SetX($total_start_x);
$pdf->Cell(35, 6, 'VAT (' . $vat_rate . '%):', 1, 0, 'L', true);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(25, 6, formatNumber($vat_amount, 2) . ' ' . $quotation['currency'], 1, 1, 'R', true);

// Total
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(51, 102, 204);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetX($total_start_x);
$pdf->Cell(35, 8, 'TOTAL AMOUNT:', 1, 0, 'L', true);
$pdf->Cell(25, 8, formatNumber($total_amount, 2) . ' ' . $quotation['currency'], 1, 1, 'R', true);

$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(10);

// Terms and Conditions
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, 'TERMS AND CONDITIONS:', 0, 1, 'L');

$pdf->SetFont('helvetica', '', 9);
$terms = [
    '1. This quotation is valid until ' . formatDateThai($quotation['valid_until'], 'd/m/Y'),
    '2. All prices are quoted in ' . $quotation['currency'] . ' and exclude VAT unless otherwise stated',
    '3. Payment terms: 30 days from invoice date unless otherwise agreed',
    '4. Cargo must be ready for collection as per agreed schedule',
    '5. All documentation must be complete and accurate',
    '6. Rates are subject to fuel surcharge adjustments',
    '7. Insurance coverage is available upon request at additional cost',
    '8. Any storage charges will be applied as per standard rates',
    '9. This quotation excludes any unforeseen charges or government levies',
    '10. Final confirmation required to proceed with booking'
];

foreach ($terms as $term) {
    $pdf->Cell(0, 5, $term, 0, 1, 'L');
}

// Remarks (if any)
if ($quotation['remark']) {
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, 'REMARKS:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(255, 255, 200);
    $pdf->MultiCell(0, 5, $quotation['remark'], 1, 'L', true);
}

// Signature section
$pdf->Ln(15);
$pdf->SetFont('helvetica', '', 9);

// Left signature
$pdf->SetXY(30, $pdf->GetY());
$pdf->Cell(60, 4, 'Prepared By:', 0, 1, 'L');
$pdf->SetX(30);
$pdf->Cell(60, 15, '', 1, 0, 'C'); // Signature box
$pdf->SetXY(30, $pdf->GetY() + 15);
$pdf->Cell(60, 4, $quotation['created_by_name'] ?: 'System', 0, 1, 'C');

// Right signature
$pdf->SetXY(125, $pdf->GetY() - 23);
$pdf->Cell(60, 4, 'Customer Acceptance:', 0, 1, 'L');
$pdf->SetX(125);
$pdf->Cell(60, 15, '', 1, 0, 'C'); // Signature box
$pdf->SetXY(125, $pdf->GetY() + 15);
$pdf->Cell(60, 4, 'Name & Signature', 0, 1, 'C');

// Security watermark (optional)
if ($quotation['status'] == 'draft') {
    $pdf->SetAlpha(0.3);
    $pdf->SetFont('helvetica', 'B', 50);
    $pdf->SetTextColor(200, 200, 200);
    $pdf->SetXY(50, 150);
    $pdf->Rotate(45);
    $pdf->Cell(100, 20, 'DRAFT', 0, 0, 'C');
    $pdf->Rotate(0);
    $pdf->SetAlpha(1);
}

// Generate filename
$filename = 'Quotation_' . $quotation['quotation_no'] . '_' . date('Ymd') . '.pdf';

// Output PDF
$pdf->Output($filename, 'I'); // 'I' = inline display, 'D' = download, 'F' = save to file

// Log PDF generation
error_log("PDF Generated - Quotation: {$quotation['quotation_no']}, User: {$_SESSION['username']}, Time: " . date('Y-m-d H:i:s'));
?>