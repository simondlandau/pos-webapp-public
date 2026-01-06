# Prerequisites - Detailed System Requirements

This document outlines all system requirements, dependencies, and prerequisites needed before installing the web application.

## 📋 Table of Contents

- [Hardware Requirements](#hardware-requirements)
- [Operating System Requirements](#operating-system-requirements)
- [Software Requirements](#software-requirements)
- [Network Requirements](#network-requirements)
- [Database Requirements](#database-requirements)
- [PHP Requirements](#php-requirements)
- [Additional Dependencies](#additional-dependencies)
- [Pre-Installation Checklist](#pre-installation-checklist)

---

## 💾 Hardware Requirements

### Minimum Requirements

| Component | Minimum | Recommended | Notes |
|-----------|---------|-------------|-------|
| **CPU** | Dual-core 2.0 GHz | Quad-core 2.5 GHz+ | Intel/AMD x64 architecture |
| **RAM** | 4 GB | 8 GB or more | More RAM needed for concurrent users |
| **Storage** | 20 GB free | 50 GB+ free | SSD recommended for better performance |
| **Network** | 100 Mbps | 1 Gbps | Dedicated network interface preferred |

### Storage Breakdown

- **XAMPP Installation**: ~1-2 GB
- **Application Files**: ~100-200 MB
- **MySQL Database**: 1-5 GB (grows with data)
- **Log Files**: 500 MB - 2 GB
- **Temporary Files**: 1 GB
- **Backups**: 5-10 GB recommended

### Performance Considerations

- **Small Business** (< 50 transactions/day): Minimum specs sufficient
- **Medium Business** (50-200 transactions/day): Recommended specs
- **Large Business** (200+ transactions/day): Consider dedicated server hardware

---

## 🖥️ Operating System Requirements

### Supported Operating Systems

#### Production (Primary Support)
- ✅ **Windows 10** (Professional, Enterprise)
  - Version 1909 or later
  - 64-bit version required
- ✅ **Windows 11** (Professional, Enterprise)
  - All versions supported
  - 64-bit only
- ✅ **Windows Server**
  - Windows Server 2016 Standard/Datacenter
  - Windows Server 2019 Standard/Datacenter
  - Windows Server 2022 Standard/Datacenter

#### Development (Limited Support)
- ⚠️ **Windows 10/11 Home Edition** - Works but limited features
- ⚠️ **Linux** - Possible with modifications, not officially supported
- ⚠️ **macOS** - Development only, not production

### Windows Edition Features Required

| Feature | Home | Pro | Enterprise | Server |
|---------|------|-----|------------|--------|
| Static IP Configuration | ✅ | ✅ | ✅ | ✅ |
| Windows Firewall Advanced | ❌ | ✅ | ✅ | ✅ |
| Task Scheduler (Full) | ⚠️ | ✅ | ✅ | ✅ |
| Group Policy | ❌ | ✅ | ✅ | ✅ |
| Remote Desktop Server | ❌ | ⚠️ | ✅ | ✅ |

**Legend**: ✅ Fully Supported | ⚠️ Limited | ❌ Not Available

### Windows Updates

- Windows must be up-to-date with latest security patches
- Required: KB updates for network stack and security
- Recommended: Enable automatic updates for security patches

---

## 📦 Software Requirements

### Core Software Stack

#### 1. XAMPP for Windows

**Version**: 8.2.x (PHP 8.2) **Recommended** | 8.1.x Supported | 8.0.x Minimum

**Download**: https://www.apachefriends.org/download.html

**Required Components**:
- ✅ Apache 2.4.x
- ✅ MySQL 8.0.x (MariaDB also supported)
- ✅ PHP 8.0+ (8.2 recommended)
- ✅ phpMyAdmin
- ❌ Perl (not needed)
- ❌ Tomcat (not needed)
- ❌ Webalizer (optional)
- ❌ FileZilla FTP (optional)
- ❌ Mercury Mail (not needed)

**Installation Notes**:
- Install to default path: `C:\xampp`
- Run installer as Administrator
- Disable antivirus during installation (if issues occur)
- Allow firewall exceptions when prompted

#### 2. Microsoft SQL Server PHP Drivers

**Version**: SQLSRV 5.10+ for PHP 8.x

**Download**: https://docs.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server

**Required Files**:
- `php_sqlsrv_82_ts.dll` (Thread Safe)
- `php_pdo_sqlsrv_82_ts.dll` (Thread Safe)

**Version Compatibility**:

| PHP Version | SQLSRV Version | File Suffix |
|-------------|----------------|-------------|
| PHP 8.0 | SQLSRV 5.8+ | `_80_ts.dll` |
| PHP 8.1 | SQLSRV 5.9+ | `_81_ts.dll` |
| PHP 8.2 | SQLSRV 5.10+ | `_82_ts.dll` |

**Important**: Must match your PHP version exactly!

#### 3. Microsoft Visual C++ Redistributables

**Required for SQL Server Drivers**:
- Visual C++ Redistributable for Visual Studio 2015-2019 (x64)
- Visual C++ Redistributable for Visual Studio 2015-2019 (x86)

**Download**: https://support.microsoft.com/en-us/help/2977003/the-latest-supported-visual-c-downloads

**Check Installation**:
```cmd
wmic product where "name like 'Microsoft Visual C++%'" get name,version
```

#### 4. Composer (Optional but Recommended)

**Version**: 2.x latest

**Download**: https://getcomposer.org/download/

**Purpose**: Dependency management for PHPMailer and future packages

**Installation**:
```bash
# Download and run Composer-Setup.exe
# Or use command line:
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
```

---

## 🌐 Network Requirements

### Network Configuration

#### IP Address Configuration

**Target System Must Have**:
- Static IP: `192.168.1.10` (or configurable)
- Subnet Mask: `255.255.255.0`
- Gateway: `192.168.1.1` (POS system)
- DNS: Your network DNS servers

**Network Adapter Requirements**:
- Gigabit Ethernet adapter recommended
- 100 Mbps minimum
- Dedicated NIC preferred (not shared with other services)

#### Port Requirements

| Port | Protocol | Purpose | Required |
|------|----------|---------|----------|
| 9090 | TCP | Apache Web Server | ✅ Yes |
| 3306 | TCP | MySQL Database | ⚠️ External access only |
| 1433 | TCP | MSSQL Connection | ✅ Yes (outbound) |
| 22 | TCP | SSH Server | ⚠️ If remote access needed |
| 443 | TCP | HTTPS (if enabled) | ⚠️ Production recommended |
| 80 | TCP | HTTP redirect | ⚠️ Optional |

**Firewall Rules Needed**:
```powershell
# Inbound rules (will be created by installer)
New-NetFirewallRule -Name "Apache-9090" -Protocol TCP -LocalPort 9090 -Action Allow
New-NetFirewallRule -Name "SSH-22" -Protocol TCP -LocalPort 22 -Action Allow
New-NetFirewallRule -Name "MySQL-3306" -Protocol TCP -LocalPort 3306 -Action Allow

# Outbound rules
# Typically allowed by default, verify MSSQL connection (1433) is not blocked
```

#### Bandwidth Requirements

- **Minimum**: 10 Mbps for basic operation
- **Recommended**: 100 Mbps+ for smooth operation
- **Data Transfer**: ~50-200 MB/day (typical POS sync)

### Access Requirements

#### Internal Network Access
- Must reach POS system at `192.168.1.1:1433`
- Must be accessible from internal network for web interface
- phpMyAdmin accessible from administrator workstations

#### External Access (Optional)
- SSH access for remote administration
- Web access for remote reporting (requires additional security)
- VPN recommended over direct internet exposure

---

## 🗄️ Database Requirements

### Microsoft SQL Server (POS System)

**Version**: SQL Server 2012 or later

**Required Access**:
- TCP/IP protocol enabled
- SQL Server Browser service running (if named instances)
- Mixed authentication mode (SQL Server and Windows)
- Dedicated user account with read permissions

**SQL Server Configuration**:

1. **Enable TCP/IP**:
   - SQL Server Configuration Manager
   - SQL Server Network Configuration
   - Protocols for [Instance]
   - Enable TCP/IP
   - Restart SQL Server service

2. **Configure Firewall**:
   ```cmd
   netsh advfirewall firewall add rule ^
       name="SQL Server" dir=in action=allow ^
       protocol=TCP localport=1433
   ```

3. **Create Application User**:
   ```sql
   -- On POS SQL Server
   CREATE LOGIN webapp_user WITH PASSWORD = 'SecurePassword123!';
   USE [YourPOSDatabase];
   CREATE USER webapp_user FOR LOGIN webapp_user;
   GRANT SELECT ON SCHEMA::dbo TO webapp_user;
   ```

**Required Tables** (must exist in POS database):
- Sales transaction table
- Inventory/Products table
- Other tables as defined in your schema

**Network Test**:
```bash
# From target system, test MSSQL connectivity
telnet 192.168.1.1 1433

# Or use SQL Server Management Studio
# Connection string: 192.168.1.1,1433
```

### MySQL (Local Database)

**Version**: MySQL 8.0+ or MariaDB 10.5+

**Included with XAMPP**: Yes (no separate installation needed)

**Initial Configuration**:
- Default username: `root`
- Default password: (empty) - **MUST CHANGE**
- Default port: 3306
- Character set: UTF-8 (utf8mb4)

**Storage Requirements**:
- Initial: ~100 MB
- Growth: ~10-50 MB per month (varies by usage)
- Recommend: 5+ GB free space

**Performance Tuning** (edit `my.ini`):
```ini
[mysqld]
max_connections = 100
innodb_buffer_pool_size = 512M
query_cache_size = 32M
tmp_table_size = 64M
max_heap_table_size = 64M
```

---

## 🐘 PHP Requirements

### PHP Version

**Minimum**: PHP 8.0  
**Recommended**: PHP 8.2  
**Not Supported**: PHP 7.x (EOL)

### Required PHP Extensions

| Extension | Required | Purpose |
|-----------|----------|---------|
| `mysqli` | ✅ | MySQL database access |
| `pdo_mysql` | ✅ | MySQL PDO driver |
| `sqlsrv` | ✅ | SQL Server access |
| `pdo_sqlsrv` | ✅ | SQL Server PDO driver |
| `mbstring` | ✅ | Multi-byte string support |
| `openssl` | ✅ | SMTP/SSL connections |
| `curl` | ✅ | HTTP requests |
| `json` | ✅ | JSON parsing |
| `xml` | ✅ | XML processing |
| `session` | ✅ | Session management |
| `gd` or `imagick` | ⚠️ | Image processing (optional) |
| `zip` | ⚠️ | Archive handling (optional) |

### PHP Configuration (`php.ini`)

**Required Settings**:
```ini
; Memory and execution
memory_limit = 256M
max_execution_time = 300
max_input_time = 300

; File uploads
upload_max_filesize = 10M
post_max_size = 10M

; Error reporting (development)
display_errors = On
error_reporting = E_ALL

; Error reporting (production)
display_errors = Off
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
log_errors = On
error_log = C:\xampp\php\logs\php_error.log

; Extensions
extension=mysqli
extension=pdo_mysql
extension=sqlsrv
extension=pdo_sqlsrv
extension=mbstring
extension=openssl
extension=curl

; Timezone
date.timezone = America/New_York
```

**Verify PHP Configuration**:
```bash
php -v                    # Check PHP version
php -m                    # List loaded modules
php -i | findstr sqlsrv  # Check SQL Server drivers
php --ini                 # Show php.ini location
```

---

## 📚 Additional Dependencies

### PHPMailer

**Version**: 6.x (included in repository)

**Location**: `PHPMailer/src/`

**Purpose**: Send email reports

**Requirements**:
- PHP 8.0+
- OpenSSL extension for TLS/SSL

**No separate installation needed** - included in project

### Composer Packages (if using composer.json)

```json
{
    "require": {
        "phpmailer/phpmailer": "^6.8"
    }
}
```

Install via:
```bash
composer install
```

---

## ✅ Pre-Installation Checklist

Use this checklist to verify all prerequisites are met before installation:

### System Prerequisites

- [ ] Windows 10/11 Pro/Enterprise or Windows Server 2016+
- [ ] 64-bit operating system
- [ ] Administrator access available
- [ ] At least 4 GB RAM (8 GB recommended)
- [ ] At least 20 GB free disk space (50 GB recommended)
- [ ] System is up-to-date with Windows updates

### Network Prerequisites

- [ ] Network adapter configured with static IP (192.168.1.10)
- [ ] Can ping POS system (192.168.1.1)
- [ ] Ports 9090, 3306, 1433 are available
- [ ] Windows Firewall configured or ready to configure
- [ ] DNS servers configured correctly

### Software Prerequisites

- [ ] XAMPP 8.2.x downloaded
- [ ] Microsoft SQL Server PHP Drivers downloaded
- [ ] Visual C++ Redistributables installed
- [ ] Antivirus configured to allow XAMPP
- [ ] No conflicting web servers (IIS) running

### Database Prerequisites

- [ ] MSSQL Server accessible at 192.168.1.1:1433
- [ ] MSSQL database credentials available
- [ ] POS database name known
- [ ] Test connection to MSSQL successful
- [ ] Required tables exist in POS database
- [ ] Database user has appropriate read permissions

### Email Prerequisites

- [ ] SMTP server details available
- [ ] Email account credentials ready
- [ ] If using Gmail: App Password generated
- [ ] Test email account can send messages
- [ ] Recipient email addresses identified

### Access Prerequisites

- [ ] Local administrator account credentials
- [ ] Database passwords prepared (secure)
- [ ] SSH keys generated (if using SSH)
- [ ] VPN access configured (if needed for remote access)

### Security Prerequisites

- [ ] Understand security implications of external access
- [ ] Plan for regular backups
- [ ] SSL certificate available (for HTTPS - production)
- [ ] Security policy reviewed
- [ ] Incident response plan in place (for production)

---

## 🔍 Verification Steps

After checking prerequisites, verify your system:

### 1. Check Operating System
```cmd
systeminfo | findstr /B /C:"OS Name" /C:"OS Version"
```

### 2. Check Available Disk Space
```cmd
wmic logicaldisk get caption,freespace,size
```

### 3. Check Network Configuration
```cmd
ipconfig /all
ping 192.168.1.1
telnet 192.168.1.1 1433
```

### 4. Check Running Services
```cmd
netstat -ano | findstr :80
netstat -ano | findstr :9090
netstat -ano | findstr :3306
```

### 5. Check Visual C++ Redistributables
```cmd
wmic product where "name like 'Microsoft Visual C++%'" get name,version
```

---

## 🆘 If Prerequisites Are Not Met

### Missing Hardware Resources
- **Low RAM**: Close unnecessary applications, consider upgrade
- **Low Disk Space**: Clean temporary files, remove unused programs
- **Slow Network**: Check network cables, router configuration

### Wrong Windows Edition
- **Windows Home**: Consider upgrading to Pro for full feature support
- **32-bit Windows**: Must upgrade to 64-bit (clean install required)

### MSSQL Connection Issues
- Work with POS system administrator to:
  - Enable TCP/IP protocol
  - Configure firewall
  - Create application user account
  - Verify network connectivity

### Missing Software
- Download all required software before proceeding
- Verify checksums/signatures for security
- Use official download sources only

---

## 📞 Support Resources

If you encounter issues with prerequisites:

1. **Documentation**: Review installation guide
2. **XAMPP Forums**: https://community.apachefriends.org/
3. **Microsoft SQL Server**: https://docs.microsoft.com/en-us/sql/
4. **PHP Documentation**: https://www.php.net/docs.php

---

**Last Updated**: January 2025  
**Document Version**: 1.0.0
