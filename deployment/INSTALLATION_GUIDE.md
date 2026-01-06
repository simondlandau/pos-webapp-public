# Web Application - Complete Installation Guide

## Prerequisites

Before starting the installation, ensure the target system has:
- Windows 10/11 or Windows Server 2016+
- MSSQL Server with replicated database installed
- Internet connection for downloading required software
- Administrator access

## Part 1: Prepare Installation Package

### 1.1 Create Installation Package on Source System

On your development/source system, create the following structure:

```
installation_package/
├── app/                    # Copy all your application files here
├── config/
│   ├── config.template.php
│   ├── httpd-vhosts.conf
├── scripts/
│   ├── install.bat
│   ├── setup_scheduled_task.bat
│   ├── setup_ssh.bat
│   └── test_connection.php
├── docs/
│   └── INSTALLATION_GUIDE.md
└── README.txt
```

### 1.2 Copy Application Files

Copy all files from your working tree to `installation_package/app/`:
- All PHP files
- PHPMailer directory
- Schema directory
- Images and assets
- Create empty `logs/` directory

**Important**: Do NOT copy `config.php` (if it exists) - we'll create this during installation.

### 1.3 Create Portable Package

1. Create a ZIP file of the entire `installation_package/` folder
2. Name it: `webapp_installation_v1.0.zip`
3. Transfer to target system via USB drive, network share, or secure file transfer

---

## Part 2: Target System Installation

### 2.1 Install XAMPP

1. **Download XAMPP**
   - Visit: https://www.apachefriends.org
   - Download XAMPP for Windows (PHP 8.x recommended)
   - File size: ~150MB

2. **Install XAMPP**
   - Run the installer as Administrator
   - Install to: `C:\xampp` (default location)
   - Components to install:
     - ✅ Apache
     - ✅ MySQL
     - ✅ PHP
     - ✅ phpMyAdmin
     - ❌ Perl (optional)
     - ❌ Tomcat (not needed)

3. **Complete XAMPP Installation**
   - Allow Windows Firewall exceptions when prompted
   - Do NOT start services yet

### 2.2 Install Microsoft SQL Server PHP Drivers

1. **Download PHP SQL Server Drivers**
   - Visit: https://docs.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server
   - Download: SQLSRV drivers for your PHP version
   - Example: `SQLSRV58.EXE` for PHP 8.x

2. **Install Drivers**
   - Run the downloaded file
   - Extract to: `C:\xampp\php\ext\`
   - Files to extract:
     - `php_sqlsrv_XX_ts.dll`
     - `php_pdo_sqlsrv_XX_ts.dll`

3. **Enable Extensions**
   - Open: `C:\xampp\php\php.ini`
   - Find the `;extension=` section
   - Add these lines:
     ```ini
     extension=php_sqlsrv_82_ts.dll
     extension=php_pdo_sqlsrv_82_ts.dll
     extension=mysqli
     extension=pdo_mysql
     ```
   - Adjust version number (82) to match your PHP version

### 2.3 Run Installation Script

1. **Extract Installation Package**
   - Extract `webapp_installation_v1.0.zip` to a temporary location
   - Example: `C:\temp\installation_package\`

2. **Run Installation Script**
   - Navigate to: `installation_package\scripts\`
   - Right-click `install.bat`
   - Select **"Run as administrator"**
   - Follow prompts

3. **Installation Process**
   The script will:
   - Verify XAMPP installation
   - Copy application files to `C:\xampp\htdocs\webapp\`
   - Configure Apache to listen on `192.168.1.10:9090`
   - Enable required PHP extensions
   - Create configuration template
   - Set up Windows Firewall rules
   - Set directory permissions
   - Start Apache and MySQL services

### 2.4 Configure Application

1. **Edit Configuration File**
   - Open: `C:\xampp\htdocs\webapp\config.php`
   - Update database settings:
     ```php
     // MySQL Settings
     define('MYSQL_DATABASE', 'your_database_name');
     define('MYSQL_USERNAME', 'root');
     define('MYSQL_PASSWORD', '');
     
     // MSSQL Settings
     define('MSSQL_SERVER', '192.168.1.1');
     define('MSSQL_DATABASE', 'your_mssql_db');
     define('MSSQL_USERNAME', 'your_username');
     define('MSSQL_PASSWORD', 'your_password');
     
     // Email Settings
     define('SMTP_USERNAME', 'your_email@gmail.com');
     define('SMTP_PASSWORD', 'your_app_password');
     define('DAILY_REPORT_RECIPIENTS', 'recipient@example.com');
     ```

2. **Import Database Schema**
   - Open browser: `http://192.168.1.10:9090/phpmyadmin`
   - Create new database (name must match config.php)
   - Import SQL files from `schema/` directory:
     - `database.sql`
     - `inventory_scanner.sql`
     - Other schema files as needed

3. **Test Connections**
   - Navigate to installation package scripts folder
   - Run: `php test_connection.php`
   - Verify both MySQL and MSSQL connections succeed

### 2.5 Configure Network Settings

1. **Set Static IP Address**
   - Open: Control Panel → Network and Sharing Center
   - Change adapter settings
   - Right-click network adapter → Properties
   - Select IPv4 → Properties
   - Configure:
     - IP Address: `192.168.1.10`
     - Subnet Mask: `255.255.255.0`
     - Gateway: `192.168.1.1` (your POS system)
     - DNS: Your network DNS servers

2. **Verify Apache Binding**
   - Open: `C:\xampp\apache\conf\httpd.conf`
   - Verify line exists: `Listen 192.168.1.10:9090`
   - Restart Apache from XAMPP Control Panel

3. **Test Web Access**
   - From target machine: `http://192.168.1.10:9090/webapp`
   - From another machine on network: `http://192.168.1.10:9090/webapp`
   - From another machine: `http://192.168.1.10:9090/phpmyadmin`

---

## Part 3: Setup SSH Access

### 3.1 Install OpenSSH Server

1. **Enable OpenSSH via Windows Features**
   - Open: Settings → Apps → Optional Features
   - Click "Add a feature"
   - Find and install: **OpenSSH Server**
   
   OR via PowerShell (as Administrator):
   ```powershell
   Add-WindowsCapability -Online -Name OpenSSH.Server~~~~0.0.1.0
   ```

2. **Start SSH Service**
   ```powershell
   Start-Service sshd
   Set-Service -Name sshd -StartupType 'Automatic'
   ```

3. **Configure Firewall**
   ```powershell
   New-NetFirewallRule -Name sshd -DisplayName 'OpenSSH Server (sshd)' -Enabled True -Direction Inbound -Protocol TCP -Action Allow -LocalPort 22
   ```

### 3.2 Configure SSH

1. **Edit SSH Config** (Optional)
   - File: `C:\ProgramData\ssh\sshd_config`
   - Important settings:
     ```
     Port 22
     PermitRootLogin no
     PasswordAuthentication yes
     PubkeyAuthentication yes
     ```

2. **Restart SSH Service**
   ```powershell
   Restart-Service sshd
   ```

3. **Test SSH Connection**
   From remote machine:
   ```bash
   ssh username@192.168.1.10
   ```

### 3.3 Setup SSH Keys (Recommended)

1. **Generate SSH Key** (on client machine)
   ```bash
   ssh-keygen -t rsa -b 4096
   ```

2. **Copy Public Key to Server**
   ```bash
   ssh-copy-id username@192.168.1.10
   ```
   
   OR manually copy to: `C:\Users\username\.ssh\authorized_keys`

---

## Part 4: Setup Scheduled Task

### 4.1 Configure Daily Email Task

1. **Run Setup Script**
   - Navigate to: `installation_package\scripts\`
   - Right-click `setup_scheduled_task.bat`
   - Select **"Run as administrator"**

2. **Verify Task Creation**
   - Open: Task Scheduler
   - Navigate to: Task Scheduler Library
   - Find: `WebApp_DailyEmail`
   - Verify: Runs daily at 6:00 AM

3. **Test Manual Execution**
   ```cmd
   schtasks /Run /TN "WebApp_DailyEmail"
   ```

4. **Check Log File**
   - Location: `C:\xampp\htdocs\webapp\logs\scheduled_task.log`
   - Verify email was sent successfully

### 4.2 Adjust Schedule (Optional)

To change the schedule:
```cmd
schtasks /Change /TN "WebApp_DailyEmail" /ST 07:00
```

---

## Part 5: Security Hardening

### 5.1 Secure phpMyAdmin

1. **Edit phpMyAdmin Config**
   - File: `C:\xampp\phpMyAdmin\config.inc.php`
   - Add authentication:
     ```php
     $cfg['Servers'][$i]['auth_type'] = 'cookie';
     $cfg['Servers'][$i]['AllowNoPassword'] = false;
     ```

2. **Set MySQL Root Password**
   - Open: `http://192.168.1.10:9090/phpmyadmin`
   - User Accounts → root → Change password
   - Update password in config files

3. **Restrict Access by IP** (Recommended)
   - Edit: `C:\xampp\apache\conf\extra\httpd-vhosts.conf`
   - Find phpMyAdmin section
   - Change:
     ```apache
     Require all granted
     ```
   - To:
     ```apache
     Require ip 192.168.1.0/24
     ```

### 5.2 Application Security

1. **Disable PHP Errors in Production**
   - Edit: `C:\xampp\htdocs\webapp\config.php`
   - Change:
     ```php
     error_reporting(0);
     ini_set('display_errors', 0);
     ```

2. **Secure Logs Directory**
   - Logs directory already denied in virtual host config
   - Verify no web access: `http://192.168.1.10:9090/webapp/logs/`

3. **Enable HTTPS** (Highly Recommended)
   - Generate SSL certificate
   - Configure Apache SSL virtual host
   - Force HTTPS redirect

---

## Part 6: Testing & Verification

### 6.1 Test Checklist

- [ ] Apache serves on `http://192.168.1.10:9090`
- [ ] Application accessible: `http://192.168.1.10:9090/webapp`
- [ ] phpMyAdmin accessible: `http://192.168.1.10:9090/phpmyadmin`
- [ ] MySQL connection working
- [ ] MSSQL connection to POS system working
- [ ] SSH access from external machine working
- [ ] Daily email task scheduled and working
- [ ] All application features functional
- [ ] Logs being written correctly

### 6.2 Test Database Connections

Create and run this test script: `C:\xampp\htdocs\webapp\test_install.php`

```php
<?php
require_once 'config.php';

echo "<h2>Installation Test</h2>";

// Test MySQL
try {
    $mysql = getMySQLConnection();
    echo "✓ MySQL Connection: <strong>SUCCESS</strong><br>";
} catch (Exception $e) {
    echo "✗ MySQL Connection: <strong>FAILED</strong> - " . $e->getMessage() . "<br>";
}

// Test MSSQL
try {
    $mssql = getMSSQLConnection();
    echo "✓ MSSQL Connection: <strong>SUCCESS</strong><br>";
} catch (Exception $e) {
    echo "✗ MSSQL Connection: <strong>FAILED</strong> - " . $e->getMessage() . "<br>";
}

// Test Email Configuration
echo "<br><h3>Email Configuration:</h3>";
echo "SMTP Host: " . SMTP_HOST . "<br>";
echo "SMTP Port: " . SMTP_PORT . "<br>";
echo "From Email: " . SMTP_FROM_EMAIL . "<br>";

// Test File Permissions
echo "<br><h3>File Permissions:</h3>";
$testDirs = ['logs', 'uploads'];
foreach ($testDirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_writable($path)) {
        echo "✓ $dir: <strong>WRITABLE</strong><br>";
    } else {
        echo "✗ $dir: <strong>NOT WRITABLE</strong><br>";
    }
}

echo "<br><h3>PHP Configuration:</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Max Upload Size: " . ini_get('upload_max_filesize') . "<br>";
echo "Post Max Size: " . ini_get('post_max_size') . "<br>";

// Test loaded extensions
echo "<br><h3>Required Extensions:</h3>";
$required = ['mysqli', 'pdo_mysql', 'sqlsrv', 'pdo_sqlsrv'];
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ $ext: <strong>LOADED</strong><br>";
    } else {
        echo "✗ $ext: <strong>NOT LOADED</strong><br>";
    }
}
?>
```

Access: `http://192.168.1.10:9090/webapp/test_install.php`

---

## Part 7: Troubleshooting

### Common Issues

**Issue: Apache won't start**
- Check if IIS is running (disable it)
- Check if port 9090 is in use: `netstat -ano | findstr :9090`
- Review error log: `C:\xampp\apache\logs\error.log`

**Issue: Cannot connect to MSSQL**
- Verify MSSQL Server allows remote connections
- Check SQL Server Configuration Manager → SQL Server Network Configuration → TCP/IP is enabled
- Verify firewall allows port 1433
- Test with: `telnet 192.168.1.1 1433`

**Issue: phpMyAdmin access denied**
- Verify MySQL service is running
- Check MySQL root password
- Review Apache error log

**Issue: Scheduled task not running**
- Open Task Scheduler → View task history
- Check task runs as SYSTEM account
- Verify PHP path is correct
- Check log file for errors

**Issue: Cannot access from external network**
- Verify router port forwarding if accessing from internet
- Check Windows Firewall rules
- Verify static IP configuration

### Log Files

- Apache Error: `C:\xampp\apache\logs\error.log`
- Apache Access: `C:\xampp\apache\logs\access.log`
- MySQL Error: `C:\xampp\mysql\data\mysql_error.log`
- Application: `C:\xampp\htdocs\webapp\logs\`
- Scheduled Task: `C:\xampp\htdocs\webapp\logs\scheduled_task.log`

---

## Part 8: Maintenance

### Regular Tasks

1. **Weekly**
   - Check log files for errors
   - Verify scheduled task execution
   - Review application performance

2. **Monthly**
   - Update XAMPP if security patches available
   - Backup MySQL database
   - Review and archive old logs

3. **Backup Strategy**
   - Database: Export from phpMyAdmin
   - Application files: `C:\xampp\htdocs\webapp\`
   - Configuration: `config.php`

### Updating Application

To update the application:
1. Backup current installation
2. Stop Apache service
3. Copy new files to `C:\xampp\htdocs\webapp\`
4. Preserve `config.php`
5. Run any database migrations in `schema/`
6. Start Apache service
7. Test functionality

---

## Support Information

**Application Location**: `C:\xampp\htdocs\webapp`  
**Configuration File**: `C:\xampp\htdocs\webapp/config.php`  
**Log Directory**: `C:\xampp\htdocs\webapp/logs/`  
**XAMPP Control Panel**: `C:\xampp\xampp-control.exe`

For issues, check logs and review this guide's troubleshooting section.
