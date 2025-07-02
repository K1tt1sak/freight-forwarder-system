-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 30, 2025 at 12:20 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `freight_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `customer_code` varchar(20) NOT NULL,
  `company_name` varchar(200) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `fax` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `tax_id` varchar(20) DEFAULT NULL,
  `customer_type` enum('shipper','consignee','agent','both') DEFAULT 'both',
  `credit_term` int(11) DEFAULT 30 COMMENT 'วันเครดิต',
  `credit_limit` decimal(12,2) DEFAULT 0.00 COMMENT 'วงเงินเครดิต',
  `status` enum('active','inactive','blacklist') DEFAULT 'active',
  `remark` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `customer_code`, `company_name`, `contact_person`, `phone`, `email`, `fax`, `address`, `tax_id`, `customer_type`, `credit_term`, `credit_limit`, `status`, `remark`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'CUS25001', 'ABOUT THE FIX CO., LTD.', 'Kittisak Pitiphongphatthana', '+66945422933', 'kittisak@fapot.or.th', '', '59/165 The Balance Sigma Village, Moo 4, Soi 18, Bang Krathuek Subdistrict,', '1236547896541', 'shipper', 30, 50000.00, 'active', '', 1, '2025-06-30 10:11:34', '2025-06-30 10:19:11');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `job_id` int(11) DEFAULT NULL,
  `document_type` enum('bill_of_lading','airway_bill','commercial_invoice','packing_list','certificate','customs_doc','other') NOT NULL,
  `document_name` varchar(200) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL COMMENT 'ขนาดไฟล์ในไบต์',
  `is_original` tinyint(1) DEFAULT 0 COMMENT 'เอกสารต้นฉบับ',
  `expiry_date` date DEFAULT NULL COMMENT 'วันหมดอายุ',
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(20) NOT NULL,
  `job_id` int(11) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  `vat_rate` decimal(5,2) DEFAULT 7.00 COMMENT 'อัตรา VAT %',
  `vat_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'THB',
  `payment_status` enum('pending','partial','paid','overdue','cancelled') DEFAULT 'pending',
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `payment_date` date DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `job_selling_id` int(11) DEFAULT NULL COMMENT 'อ้างอิงจาก job_selling',
  `description` varchar(200) NOT NULL,
  `quantity` decimal(10,3) DEFAULT 1.000,
  `unit_price` decimal(12,2) NOT NULL,
  `amount` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int(11) NOT NULL,
  `job_no` varchar(20) NOT NULL COMMENT 'เช่น AEC0625-0001',
  `job_type` enum('export_air','export_sea','import_air','import_sea') NOT NULL,
  `service_type` enum('customer_only','freight_only','mix') NOT NULL COMMENT 'C=Customer, F=Freight, M=Mix',
  `shipper_id` int(11) DEFAULT NULL,
  `consignee_id` int(11) DEFAULT NULL,
  `origin` varchar(100) DEFAULT NULL COMMENT 'ต้นทาง',
  `destination` varchar(100) DEFAULT NULL COMMENT 'ปลายทาง',
  `vessel_flight` varchar(100) DEFAULT NULL COMMENT 'ชื่อเรือ/เที่ยวบิน',
  `voyage_no` varchar(50) DEFAULT NULL COMMENT 'เที่ยวที่',
  `etd` date DEFAULT NULL COMMENT 'วันออกเดินทาง',
  `eta` date DEFAULT NULL COMMENT 'วันถึงปลายทาง',
  `delivery_date` date DEFAULT NULL COMMENT 'วันส่งมอบ',
  `cargo_description` text DEFAULT NULL COMMENT 'รายละเอียดสินค้า',
  `packages` int(11) DEFAULT NULL COMMENT 'จำนวนชิ้น',
  `gross_weight` decimal(10,3) DEFAULT NULL COMMENT 'น้ำหนักรวม KG',
  `volume_weight` decimal(10,3) DEFAULT NULL COMMENT 'น้ำหนักปริมาตร KG',
  `cbm` decimal(10,3) DEFAULT NULL COMMENT 'ลูกบาศก์เมตร',
  `status` enum('booking','document_preparation','customs_clearance','in_transit','arrived','delivered','completed') DEFAULT 'booking',
  `bl_awb_no` varchar(100) DEFAULT NULL COMMENT 'Bill of Lading / Airway Bill Number',
  `container_no` varchar(100) DEFAULT NULL COMMENT 'หมายเลขตู้',
  `cost_total` decimal(12,2) DEFAULT 0.00 COMMENT 'ต้นทุนรวม',
  `selling_total` decimal(12,2) DEFAULT 0.00 COMMENT 'ราคาขายรวม',
  `profit_loss` decimal(12,2) DEFAULT 0.00 COMMENT 'กำไร/ขาดทุน',
  `remark` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_costs`
--

CREATE TABLE `job_costs` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `cost_type` enum('freight','local_charge','customs','trucking','documentation','other') NOT NULL,
  `description` varchar(200) NOT NULL,
  `currency` varchar(3) DEFAULT 'THB',
  `exchange_rate` decimal(8,4) DEFAULT 1.0000,
  `amount` decimal(12,2) NOT NULL,
  `amount_thb` decimal(12,2) NOT NULL COMMENT 'ยอดเป็นบาท',
  `invoice_no` varchar(100) DEFAULT NULL COMMENT 'เลขที่ใบแจ้งหนี้จาก vendor',
  `invoice_date` date DEFAULT NULL,
  `payment_status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `payment_date` date DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `job_costs`
--
DELIMITER $$
CREATE TRIGGER `update_job_cost_total` AFTER INSERT ON `job_costs` FOR EACH ROW BEGIN
    UPDATE jobs 
    SET cost_total = (
        SELECT COALESCE(SUM(amount_thb), 0) 
        FROM job_costs 
        WHERE job_id = NEW.job_id
    ),
    profit_loss = selling_total - (
        SELECT COALESCE(SUM(amount_thb), 0) 
        FROM job_costs 
        WHERE job_id = NEW.job_id
    )
    WHERE id = NEW.job_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `job_selling`
--

CREATE TABLE `job_selling` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `selling_type` enum('freight','local_charge','customs','trucking','documentation','service_fee','other') NOT NULL,
  `description` varchar(200) NOT NULL,
  `currency` varchar(3) DEFAULT 'THB',
  `exchange_rate` decimal(8,4) DEFAULT 1.0000,
  `amount` decimal(12,2) NOT NULL,
  `amount_thb` decimal(12,2) NOT NULL COMMENT 'ยอดเป็นบาท',
  `remark` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `job_selling`
--
DELIMITER $$
CREATE TRIGGER `update_job_selling_total` AFTER INSERT ON `job_selling` FOR EACH ROW BEGIN
    UPDATE jobs 
    SET selling_total = (
        SELECT COALESCE(SUM(amount_thb), 0) 
        FROM job_selling 
        WHERE job_id = NEW.job_id
    ),
    profit_loss = (
        SELECT COALESCE(SUM(amount_thb), 0) 
        FROM job_selling 
        WHERE job_id = NEW.job_id
    ) - cost_total
    WHERE id = NEW.job_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `job_status_history`
--

CREATE TABLE `job_status_history` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `remark` text DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quotations`
--

CREATE TABLE `quotations` (
  `id` int(11) NOT NULL,
  `quotation_no` varchar(20) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `quotation_date` date NOT NULL,
  `valid_until` date NOT NULL,
  `job_type` enum('export_air','export_sea','import_air','import_sea') NOT NULL,
  `service_type` enum('customer_only','freight_only','mix') NOT NULL,
  `origin` varchar(100) DEFAULT NULL,
  `destination` varchar(100) DEFAULT NULL,
  `cargo_description` text DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'THB',
  `status` enum('draft','sent','accepted','rejected','expired') DEFAULT 'draft',
  `remark` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quotation_items`
--

CREATE TABLE `quotation_items` (
  `id` int(11) NOT NULL,
  `quotation_id` int(11) NOT NULL,
  `item_type` enum('freight','local_charge','customs','trucking','documentation','service_fee','other') NOT NULL,
  `description` varchar(200) NOT NULL,
  `unit` varchar(50) DEFAULT NULL COMMENT 'หน่วย เช่น per shipment, per CBM',
  `quantity` decimal(10,3) DEFAULT 1.000,
  `unit_price` decimal(12,2) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'THB'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(200) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_by`, `updated_at`) VALUES
(1, 'company_name', 'Your Freight Company Ltd.', 'ชื่อบริษัท', NULL, '2025-06-30 06:17:00'),
(2, 'company_address', '123 Business District, Bangkok 10110', 'ที่อยู่บริษัท', NULL, '2025-06-30 06:17:00'),
(3, 'company_phone', '02-123-4567', 'เบอร์โทรบริษัท', NULL, '2025-06-30 06:17:00'),
(4, 'company_email', 'info@company.com', 'อีเมลบริษัท', NULL, '2025-06-30 06:17:00'),
(5, 'default_currency', 'THB', 'สกุลเงินหลัก', NULL, '2025-06-30 06:17:00'),
(6, 'vat_rate', '7.00', 'อัตรา VAT เริ่มต้น', NULL, '2025-06-30 06:17:00'),
(7, 'job_number_format', '{type}{service}{mmyy}-{0000}', 'รูปแบบเลข Job', NULL, '2025-06-30 06:17:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','manager','staff','viewer') DEFAULT 'staff',
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `name`, `email`, `phone`, `role`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@company.com', NULL, 'admin', 'active', '2025-06-30 06:31:44', '2025-06-30 06:17:00', '2025-06-30 06:31:44');

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `id` int(11) NOT NULL,
  `vendor_code` varchar(20) NOT NULL,
  `company_name` varchar(200) NOT NULL,
  `vendor_type` enum('shipping_line','airline','trucking','customs_broker','warehouse','other') NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `tax_id` varchar(20) DEFAULT NULL,
  `payment_term` int(11) DEFAULT 30 COMMENT 'วันเครดิตที่ได้รับ',
  `currency` varchar(3) DEFAULT 'THB',
  `status` enum('active','inactive') DEFAULT 'active',
  `remark` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_code` (`customer_code`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_customers_company` (`company_name`),
  ADD KEY `idx_customers_code` (`customer_code`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_invoices_date` (`invoice_date`),
  ADD KEY `idx_invoices_status` (`payment_status`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `job_selling_id` (`job_selling_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `job_no` (`job_no`),
  ADD KEY `shipper_id` (`shipper_id`),
  ADD KEY `consignee_id` (`consignee_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_jobs_job_no` (`job_no`),
  ADD KEY `idx_jobs_status` (`status`),
  ADD KEY `idx_jobs_date` (`created_at`);

--
-- Indexes for table `job_costs`
--
ALTER TABLE `job_costs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `job_selling`
--
ALTER TABLE `job_selling`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `job_status_history`
--
ALTER TABLE `job_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `quotations`
--
ALTER TABLE `quotations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `quotation_no` (`quotation_no`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quotation_id` (`quotation_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vendor_code` (`vendor_code`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_vendors_company` (`company_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_costs`
--
ALTER TABLE `job_costs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_selling`
--
ALTER TABLE `job_selling`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_status_history`
--
ALTER TABLE `job_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quotations`
--
ALTER TABLE `quotations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quotation_items`
--
ALTER TABLE `quotation_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`),
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `invoices_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoice_items_ibfk_2` FOREIGN KEY (`job_selling_id`) REFERENCES `job_selling` (`id`);

--
-- Constraints for table `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`shipper_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `jobs_ibfk_2` FOREIGN KEY (`consignee_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `jobs_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `job_costs`
--
ALTER TABLE `job_costs`
  ADD CONSTRAINT `job_costs_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_costs_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  ADD CONSTRAINT `job_costs_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `job_selling`
--
ALTER TABLE `job_selling`
  ADD CONSTRAINT `job_selling_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_selling_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `job_selling_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `job_status_history`
--
ALTER TABLE `job_status_history`
  ADD CONSTRAINT `job_status_history_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `quotations`
--
ALTER TABLE `quotations`
  ADD CONSTRAINT `quotations_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `quotations_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD CONSTRAINT `quotation_items_ibfk_1` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `vendors`
--
ALTER TABLE `vendors`
  ADD CONSTRAINT `vendors_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
