-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 31, 2025 at 03:36 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fintrack`
--

CREATE DATABASE IF NOT EXISTS `fintrack` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `fintrack`;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email_address` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `verification_code` varchar(100) DEFAULT NULL,
  `account_type` enum('Admin','Employee') NOT NULL DEFAULT 'Employee',
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email_address` (`email_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `company_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `account_titles`
--

CREATE TABLE `account_titles` (
  `title_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `title_name` varchar(100) NOT NULL,
  `type` enum('expense','sale') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`title_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `account_titles_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `title_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `particulars` varchar(255) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`sale_id`),
  KEY `idx_company_date` (`company_id`, `date`),
  KEY `idx_title` (`title_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`title_id`) REFERENCES `account_titles` (`title_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_companies`
--

CREATE TABLE `user_companies` (
  `user_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`company_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `user_companies_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `user_companies_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `expense_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `particulars` text NOT NULL,
  `category` varchar(50) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`expense_id`),
  KEY `user_id` (`user_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trial_balance`
--

CREATE TABLE `trial_balance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `classification` ENUM(
    'Cash', 'Cash Total', 
    'Trade and other receivables', 'Trade and other receivables Total',
    'Prepayments and other current assets', 'Prepayments and other current assets Total',
    'Inventories', 'Inventories Total',
    'Trade and other payables', 'Trade and other payables Total',
    'Deferred tax assets', 'Deferred tax assets Total',
    'Property and equipment – net', 'Property and equipment – net Total',
    'Income Tax Payable', 'Income Tax Payable Total',
    'Output VAT 12%-Goods Total',
    'RETIREMENT BENEFIT OBLIGATIONS', 'RETIREMENT BENEFIT OBLIGATIONS Total',
    'Share capital', 'Share capital Total',
    'Retained earnings', 'Retained earnings Total',
    'Revenues', 'Revenues Total',
    'Cost of sales and services', 'Cost of sales and services Total',
    'Marketing expenses', 'Marketing expenses Total',
    'Administrative expenses', 'Administrative expenses Total',
    'Income Tax Expense', 'Income Tax Expense Total',
    'Other income', 'Other income Total',
    'Dividend Payable', 'Dividend Payable Total',
    'Loans payable-net of current portion', 'Loans payable-net of current portion Total'
  ) NOT NULL,
  `category` ENUM(
    'Cash on hand', 'Cash in banks', 'Cash Equivalents',
    'Outside parties', 'Other Receivable', 'Factory receivables',
    'Advances to suppliers', 'Advances to officers and employees',
    'Insurance and warranty claims', 'Passenger cars', 'Commercial vehicle',
    'Parts, accessories and supplies', 'Input VAT', 'Creditable VAT',
    'Prepaid tax', 'Prepaid expenses', 'Deferred Tax Asset', 'Security deposits',
    'Land', 'Building and improvements', 'Machineries and tools',
    'Transportation equipment', 'Computer equipment and peripherals',
    'Office equipment', 'Accumulated Depreciation', 'Construction in Progress',
    'Customer deposit', 'Government payables', 'Output Vat Payable',
    'Withholding tax payable', 'Accrued expenses', 'RETIREMENT BENEFIT OBLIGATIONS',
    'Share capital', 'Retained earnings', 'Sale of vehicles - net of discount',
    'Sale of parts, net of discount', 'Sale of accessories and chemicals - net of discount',
    'Sale of services', 'Cost of vehicles', 'Cost of parts',
    'Cost of accessories and chemicals', 'Cost of services', 'Salaries and wages',
    'Commission expense', 'Advertising expense', 'Rentals', 'Warranty',
    'Government contributions', 'Employee benefits', 'Events', 'Utilities',
    'Contractual', 'Contractual expense', 'Transportation and travel',
    'Communications', 'Office supplies', 'Representation',
    'Repairs and maintenance', 'Depreciation', 'Professional fees',
    'Insurance', 'Taxes and licenses', 'Subscription dues',
    'Income Tax Expense', 'Bank charges', 'Miscellaneous', 'Others', 'Other income'
  ) DEFAULT NULL,
  `account_code_sap` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  `ending_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `idx_year` (`year`),
  KEY `idx_classification` (`classification`),
  CONSTRAINT `trial_balance_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE,
  CONSTRAINT `check_total_category` CHECK (
    (classification LIKE '%Total' AND category IS NULL) OR
    (classification NOT LIKE '%Total' AND category IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Insert default admin user
--

INSERT INTO `users` (`first_name`, `last_name`, `email_address`, `password`, `account_type`, `is_verified`) VALUES
('Admin', 'User', 'admin@fintrack.com', '$2y$10$YourHashedPasswordHere', 'Admin', 1);

--
-- Insert default company for admin
--

INSERT INTO `companies` (`company_name`, `address`, `contact_number`) VALUES
('Default Company', 'Default Address', '12345678');

--
-- Default account titles for expenses and sales
--

INSERT INTO `account_titles` (`company_id`, `title_name`, `type`) VALUES
(1, 'PROF FEE', 'expense'),
(1, 'DONATION', 'expense'),
(1, 'VEHICLE', 'expense'),
(1, 'SUPPLIES', 'expense'),
(1, 'EMPLOYEES BENEFIT', 'expense'),
(1, 'MEDICAL SUPPLIES', 'expense'),
(1, 'BONUS', 'expense'),
(1, 'TAXES AND LICENSES', 'expense'),
(1, 'MATERIALS', 'expense'),
(1, 'COM/LIGHT/WATER', 'expense'),
(1, 'REP & MAINTENANCE', 'expense'),
(1, 'OTHER EXPENSES', 'expense'),
(1, 'EQUIPMENT', 'expense'),
(1, 'CA', 'expense'),
(1, 'TRAINORS FEE', 'expense'),
(1, 'CONSTRUCTION FEE', 'expense'),
(1, 'CONSTRUCTION MATERIALS', 'expense'),
(1, 'MEALS', 'expense'),
(1, 'TRANSPORTATION', 'expense'),
(1, 'FUEL AND OIL', 'expense'),
(1, 'DIRECTORS FEE', 'expense'),
(1, 'TUTORIAL FEE', 'expense'),
(1, 'GIFTS', 'expense'),
(1, 'SALARY', 'expense'),
(1, 'ALLOWANCE', 'expense'),
(1, 'SSS/HMDF/PHEALTH', 'expense'),
(1, 'SERVICE', 'expense'),
(1, 'UNIFORM', 'expense'),
(1, 'LOAN AMORTIZATION', 'expense'),
(1, 'PRODUCT SALES', 'sale'),
(1, 'SERVICE REVENUE', 'sale'),
(1, 'CONSULTING FEES', 'sale'),
(1, 'COMMISSION INCOME', 'sale'),
(1, 'RENTAL INCOME', 'sale'),
(1, 'INTEREST INCOME', 'sale'),
(1, 'MISCELLANEOUS INCOME', 'sale');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
