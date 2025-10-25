# SVP Web Application Suite

A comprehensive PHP-based web application suite for charity shop management, combining Point of Sale reconciliation, financial reporting, and a Progressive Web App inventory scanner.

**Live in production** managing daily operations for a local charity shop of an international organization.

---

## 📋 Table of Contents
- [Overview](#overview)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Application Components](#application-components)
- [Mobile Scanner Setup](#mobile-scanner-setup)
- [Configuration](#configuration)
- [Security Notes](#security-notes)
- [License](#license)

---

## 🎯 Overview

This application suite provides:
- **Financial Reconciliation**: End-of-day cash counting and POS reconciliation
- **Reporting Dashboard**: Real-time business intelligence from MySQL and MSSQL databases
- **Inventory Management**: PWA for smartphone-based barcode scanning
- **Automated Notifications**: Daily email reports with Excel attachments

**Tech Stack**: PHP 8.1+, MySQL 8+, MSSQL Server, Apache2, Service Workers, QuaggaJS

---

## 🚀 Features

### Financial Management
- ✅ Drag-and-drop cash counting interface (`cash_count_v3.php`)
- ✅ End-of-day reconciliation with POS system integration
- ✅ Multi-currency support (notes and coins)
- ✅ Automated daily email reports (`send_daily.php`)
- ✅ Excel export functionality

### Business Intelligence
- ✅ MySQL-based reporting dashboard (`mysql_reports.php`)
- ✅ MSSQL integration for legacy POS data (`mssql_reports.php`)
- ✅ Real-time inventory status (`inventory_dashboard.php`)
- ✅ Sales analytics and stock movement tracking

### Inventory Scanner (PWA)
- ✅ **Progressive Web App** - Works offline after first load
- ✅ **Barcode scanning** - Code 39, Code 128, EAN-13, UPC support via QuaggaJS
- ✅ **Offline mode** - Scans saved locally, synced when online
- ✅ **Bulk entry** - Scan once, enter quantity for multiple items
- ✅ **Camera flashlight** toggle for low-light scanning
- ✅ **Audio feedback** - Beep on successful scan
- ✅ **Service Worker** caching for offline functionality ⚠️ Removed - iPhone glitch

---

## 🧰 Requirements

### Server Requirements
- **PHP**: 8.1 or higher
- **MySQL**: 8.0 or higher
- **Apache2**: With mod_php and mod_rewrite enabled
- **HTTPS**: Required for Service Workers (PWA functionality)

### PHP Extensions
```bash
php-mysqli
php-pdo
php-pdo-mysql
php-sqlsrv  # For MSSQL integration
php-mbstring
php-curl
```

### Optional
- **Docker & Docker Compose**: For containerized deployment
- **DigitalOcean Droplet**: Tested and compatible

---

## ⚙️ Installation

### Option 1: Local LAMP Deployment
```bash
# Clone repository
git clone https://github.com/simondlandau/svp-webapp.git
cd svp-webapp

# Copy to Apache web root
sudo cp -r . /var/www/html/svp

# Set permissions
sudo chown -R www-data:www-data /var/www/html/svp
sudo chmod -R 755 /var/www/html/svp

# Create MySQL database
mysql -u root -p
CREATE DATABASE svp;
source schema/database.sql;
```

### Option 2: Docker Deployment
```bash
# Using Docker Compose
docker-compose up -d

# Access at https://localhost:9443/svp/
```

### Option 3: XAMPP (Development/Testing)
```bash
# Copy to XAMPP htdocs
cp -r . /opt/lampp/htdocs/svp

# Start XAMPP
sudo /opt/lampp/lampp start

# Access at http://localhost/svp/
```

---

## 📦 Application Components

### Core Applications

| File | Purpose | Database |
|------|---------|----------|
| `inventory_dashboard.php` | Real-time inventory status and scan history | MySQL |
| `inventory_scanner.html` | **PWA barcode scanner** for mobile devices | MySQL API |
| `cash_count_v3.php` | End-of-day cash reconciliation interface | MySQL |
| `send_daily.php` | Automated daily email with Excel reports | MySQL + SMTP |
| `mysql_reports.php` | Custom reporting from MySQL data | MySQL |
| `mssql_reports.php` | Legacy POS system reports | MSSQL |

### API Endpoints

| File | Purpose |
|------|---------|
| `inventory_api.php` | Scanner API - handles scan, lookup, sync operations |
| `config.php` | Database connections (MySQL + MSSQL) |

### Support Files

| File | Purpose |
|------|---------|
| `header.php` | Common page header with navigation |
| `footer.php` | Common page footer |

---

## 📱 Mobile Scanner Setup

The inventory scanner is a **Progressive Web App** that works on any smartphone with a camera.

### Quick Start

1. **Visit scanner URL** from smartphone:
```
   https://your-domain.com/svp/inventory_scanner.html
```

2. **Install the app** (optional but recommended):
   - **Android Chrome**: Tap "Install app" banner
   - **iOS Safari**: Share → Add to Home Screen

3. **Grant permissions**:
   - Allow camera access
   - Enable notifications (optional)

### Features

#### Single Item Mode (Default)
- Scan barcodes one at a time
- Each scan records 1 item
- Auto-continues scanning

#### Bulk Entry Mode
- Scan once, enter quantity
- Perfect for receiving stock
- Example: Scan → Enter "24" → All 24 items recorded

#### Offline Capability ⚠️ Removed as unforeseen iPhone service file glitch
- Works without internet after first load
- Scans saved to device storage
- Syncs automatically when online
- Shows "Sync (X)" button with pending count

### Technical Details

**Barcode Support**:
- Code 39 (primary format)
- Code 128
- EAN-13, EAN-8
- UPC-A, UPC-E

**Browser Requirements**:
- HTTPS connection (required for camera access)
- Modern browser with getUserMedia API
- Service Worker support

For detailed scanner documentation, see [docs/SCANNER_README.md](docs/SCANNER_README.md)

---

## 🔧 Configuration

### 1. Database Setup 

Edit `config.example.php` with your credentials and rename to `config.php`:
<?php
/**
 * config.example.php
 * 
 * Example configuration file for SVP Web Application.
 * Copy this file to config.php and update credentials accordingly.
 */

// ------------------ MySQL ------------------
define("DB_HOST", "localhost");
define("DB_NAME", "svp");
define("DB_USER", "your_mysql_user");
define("DB_PASS", "your_mysql_password");

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("MySQL connection failed: " . htmlspecialchars($e->getMessage()));
}

// ------------------ MSSQL ------------------
// For Windows/XAMPP: use pdo_sqlsrv
// For Ubuntu/Linux: use pdo_odbc (DSN defined in /etc/odbc.ini)
$server   = "127.0.0.1,1433";  // or use DSN: 'odbc:MSSQL_SVP'
$dbname   = "svp";
$username = "your_mssql_user";
$password = "your_mssql_password";

if (extension_loaded("pdo_sqlsrv")) {
    $dsn = "sqlsrv:Server=$server;Database=$dbname";
} elseif (extension_loaded("pdo_odbc")) {
    $dsn = "odbc:MSSQL_SVP";
} else {
    die("No suitable MSSQL driver found (pdo_sqlsrv or odbc required).");
}

try {
    $sqlsrv_pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("MSSQL connection failed: " . $e->getMessage());
}

// ------------------ SMTP / Email ------------------
define('SMTP_HOST', 'smtp.yourserver.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_email@example.com');
define('SMTP_PASS', 'your_email_password');
define('SMTP_FROM_EMAIL', 'your_email@example.com');
define('SMTP_FROM_NAME', 'Your App Name');
define('SMTP_BCC', 'optional_bcc@example.com');
```

### 2. Customize Branding

Edit header and footer files:
- `header.php` - Logo, company name, navigation
- `footer.php` - Copyright, contact information
- Report headers in individual PHP files

### 3. Email Configuration ⚠️ Unnecesary if config.php configured 

Edit `send_daily.php` for automated reports:
```php
$to = 'manager@charity.org';
$from = 'reports@charity.org';
$smtp_host = 'smtp.gmail.com';
$smtp_port = 587;
```

### 4. Test Installation

Four test scripts are provided:
```bash
# Test logo and branding
https://your-domain.com/svp/test_logo.php

# Test dashboard connectivity
https://your-domain.com/svp/test_dashboard.php

# Test inventory scanner
https://your-domain.com/svp/test_inventory.php

# Test phpMailer
https://your-domain.com/svp/test_phpmailer.php
```

---

## 🔒 Security Notes

### Production Deployment

⚠️ **This repository contains a sterilized version** - sensitive configuration has been removed.

**Before deploying:**

1. ✅ Set strong database passwords
2. ✅ Enable HTTPS (required for PWA)
3. ✅ Configure firewall rules
4. ✅ Set restrictive file permissions
5. ✅ Review and customize `config.php`
6. ✅ Change default admin credentials
7. ✅ Enable error logging (disable display_errors)

### HTTPS Setup
```bash
# Install Certbot for Let's Encrypt
sudo apt install certbot python3-certbot-apache

# Get SSL certificate
sudo certbot --apache -d your-domain.com

# Auto-renewal
sudo certbot renew --dry-run
```

### File Permissions
```bash
sudo chown -R www-data:www-data /var/www/html/svp
sudo chmod 644 /var/www/html/svp/*.php
sudo chmod 755 /var/www/html/svp
sudo chmod 600 /var/www/html/svp/config.php
```

---

## 📊 Inventory Database Schema

### InventoryScans Table
```sql
CREATE TABLE InventoryScans (
    ScanID INT AUTO_INCREMENT PRIMARY KEY,
    Barcode VARCHAR(50) NOT NULL,
    ProductID VARCHAR(50),
    ProductName VARCHAR(255),
    Quantity INT DEFAULT 1,
    Status VARCHAR(20) DEFAULT 'OnFloor',
    ScanDateTime DATETIME DEFAULT CURRENT_TIMESTAMP,
    ScannedBy VARCHAR(100),
    DeviceID VARCHAR(255),
    Location VARCHAR(100),
    Notes TEXT,
    INDEX idx_barcode (Barcode),
    INDEX idx_status (Status),
    INDEX idx_datetime (ScanDateTime)
);
```

For complete schema, see `schema/database.sql`, `schema/create_inventory_table.php`

#### Setup automated inventory updates:
# Windows Scheduler:
1. Windows Task Scheduler (Primary automated sync)
Create the batch file if you haven't already:
C:\xampp\htdocs\svp\run_sync.bat1. Windows Task Scheduler (Primary automated sync)

```@echo off
SET PHP_PATH=C:\xampp\php\php.exe
SET SCRIPT_PATH=C:\xampp\htdocs\svp\sync_pos_sales.php

"%PHP_PATH%" "%SCRIPT_PATH%"
```
2. Task Scheduler Setup:

Open Task Scheduler (Win + R → taskschd.msc)
Create Task:

Name: SVP POS Sync
Trigger: Daily at 10:00 AM

Repeat every: 15 minutes
For a duration of: 8 hours


Action: Start program → C:\xampp\htdocs\svp\run_sync.bat
Conditions:
✅ Run only if computer is on AC power (unchecked)
✅ Wake computer to run (optional)

# Ubuntu crontab:

1. Add to crontab (Mon-Sat, 10:00-17:00, every 15 minutes):

```bash
crontab -e

# Add this line:
*/15 10-17 * * 1-6 /usr/bin/php /var/www/finance/svp/sync_pos_sales.php >> /var/log/pos_sync.log 2>&1
```

**Cron explanation:**
- `*/15` = Every 15 minutes
- `10-17` = Between 10:00 and 17:00
- `* *` = Every day of month, every month
- `1-6` = Monday (1) through Saturday (6)

---

## 🤝 Contributing

This is a production application in active use. Contributions welcome:

1. Fork the repository
2. Create a feature branch
3. Test thoroughly
4. Submit a pull request

**Please note**: Maintain compatibility with existing production deployment.

---

## 📝 License

This project is provided as-is for use by nonprofit organizations and charity shops.

**Attribution**: Developed for and with St. Vincent de Paul Society charity shop operations.

---

## 📧 Contact

**Developer**: Simon Landau  
**Email**: simon@landau.ws  
**Repository**: [github.com/simondlandau/pos-webapp-public](https://github.com/simondlandau/pos-webapp-public)

---

## 🙏 Acknowledgments

- Built for charity shop operations management
- QuaggaJS library for barcode scanning
- Eruda console for mobile debugging
- Open source community

---

**⭐ If this project helps your organization, please consider starring the repository!**
