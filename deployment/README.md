# Deployment Guide

This directory contains all the necessary files and documentation to deploy this web application to a Windows XAMPP environment.

## 📋 Quick Start

1. Read [INSTALLATION_GUIDE.md](./INSTALLATION_GUIDE.md) for complete step-by-step instructions
2. Ensure your target system meets the [prerequisites](./docs/PREREQUISITES.md)
3. Run the installation scripts as Administrator
4. Configure your `config.php` based on `config.template.php`

## 📁 Directory Structure

```
deployment/
├── README.md                    # This file
├── INSTALLATION_GUIDE.md        # Complete installation instructions
├── scripts/
│   ├── install.bat              # Main installation script
│   ├── setup_scheduled_task.bat # Configure daily tasks
│   └── test_connection.php      # Test database connections
├── config/
│   ├── config.template.php      # Configuration template
│   ├── httpd-vhosts.conf       # Apache virtual host config
│   └── php.ini.example         # Recommended PHP settings
└── docs/
    ├── PREREQUISITES.md         # System requirements
    └── TROUBLESHOOTING.md      # Common issues and solutions
```

## 🔧 Prerequisites

### Target System Requirements

- **Operating System**: Windows 10/11 or Windows Server 2016+
- **XAMPP**: Version 8.x (PHP 8.x, Apache 2.4, MySQL 8.x)
- **MSSQL Server**: With replicated POS database
- **Network**: Static IP capability (192.168.1.10)
- **Access**: Administrator privileges

### Required Software

1. **XAMPP for Windows**
   - Download: https://www.apachefriends.org
   - Version: 8.2.x recommended

2. **Microsoft SQL Server PHP Drivers**
   - Download: https://docs.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server
   - Version: SQLSRV 5.8+ for PHP 8.x

3. **OpenSSH Server** (for remote access)
   - Included in Windows 10/11
   - Enable via Windows Features

## 🚀 Installation Steps

### Quick Installation

```batch
# 1. Extract/clone this repository to target system
# 2. Navigate to deployment/scripts/
cd deployment/scripts/

# 3. Run installation (as Administrator)
install.bat

# 4. Configure application
# Edit: C:\xampp\htdocs\webapp\config.php

# 5. Setup scheduled tasks (as Administrator)
setup_scheduled_task.bat

# 6. Test installation
php C:\xampp\htdocs\webapp\test_connection.php
```

For detailed instructions, see [INSTALLATION_GUIDE.md](./INSTALLATION_GUIDE.md)

## ⚙️ Configuration

### 1. Database Configuration

Copy `config/config.template.php` to your application root as `config.php` and update:

```php
// MySQL (Local)
define('MYSQL_HOST', 'localhost');
define('MYSQL_DATABASE', 'your_database');
define('MYSQL_USERNAME', 'root');
define('MYSQL_PASSWORD', 'your_password');

// MSSQL (POS System)
define('MSSQL_SERVER', '192.168.1.1');
define('MSSQL_DATABASE', 'pos_database');
define('MSSQL_USERNAME', 'pos_user');
define('MSSQL_PASSWORD', 'pos_password');
```

### 2. Email Configuration

Configure SMTP settings in `config.php`:

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'your_email@gmail.com');
define('SMTP_PASSWORD', 'your_app_password');
define('DAILY_REPORT_RECIPIENTS', 'recipient@example.com');
```

### 3. Network Configuration

The installation configures:
- **Server IP**: 192.168.1.10
- **Apache Port**: 9090
- **POS System**: 192.168.1.1

Adjust in `config/httpd-vhosts.conf` if needed.

## 🧪 Testing

After installation, run these tests:

```batch
# Test database connections
php C:\xampp\htdocs\webapp\test_connection.php

# Test web access
# Open browser: http://192.168.1.10:9090/webapp

# Test phpMyAdmin
# Open browser: http://192.168.1.10:9090/phpmyadmin

# Test SSH (from remote machine)
ssh username@192.168.1.10

# Test scheduled task
schtasks /Run /TN "WebApp_DailyEmail"
```

## 🔒 Security Considerations

### Important Security Steps

1. **Change Default Passwords**
   - MySQL root password
   - Application admin accounts

2. **Restrict phpMyAdmin Access**
   - Edit `httpd-vhosts.conf`
   - Limit to specific IP ranges

3. **Enable HTTPS**
   - Generate SSL certificate
   - Configure SSL virtual host

4. **Firewall Rules**
   - Only open required ports (22, 9090, 3306)
   - Restrict access by IP where possible

5. **Regular Updates**
   - Keep XAMPP updated
   - Update PHP extensions
   - Apply security patches

## 📝 Environment-Specific Notes

### Development Environment
- Enable PHP error display
- Verbose logging
- Disable caching

### Production Environment
- Disable PHP error display
- Set appropriate log levels
- Enable caching
- Configure backups

## 🆘 Troubleshooting

### Common Issues

**Apache won't start**
```batch
# Check if port is in use
netstat -ano | findstr :9090

# Check Apache error log
type C:\xampp\apache\logs\error.log
```

**Cannot connect to MSSQL**
```batch
# Test connectivity
telnet 192.168.1.1 1433

# Verify TCP/IP enabled in SQL Server Configuration Manager
# Check SQL Server allows remote connections
```

**Scheduled task not running**
```batch
# Check task status
schtasks /Query /TN "WebApp_DailyEmail" /V /FO LIST

# View task history in Task Scheduler
```

For more troubleshooting, see [TROUBLESHOOTING.md](./docs/TROUBLESHOOTING.md)

## 📚 Additional Documentation

- [Complete Installation Guide](./INSTALLATION_GUIDE.md) - Step-by-step instructions
- [Prerequisites](./docs/PREREQUISITES.md) - Detailed requirements
- [Troubleshooting](./docs/TROUBLESHOOTING.md) - Common issues and solutions

## 🔄 Updating the Application

To update an existing installation:

```batch
# 1. Backup current installation
xcopy C:\xampp\htdocs\webapp C:\backup\webapp_%date% /E /I

# 2. Stop services
net stop Apache2.4

# 3. Update files (preserve config.php)
# Copy new files to C:\xampp\htdocs\webapp\

# 4. Run database migrations if any
# Check schema/ directory for updates

# 5. Restart services
net start Apache2.4

# 6. Test
php C:\xampp\htdocs\webapp\test_connection.php
```

## 📞 Support

- **Issues**: Create an issue on GitHub
- **Documentation**: See docs/ directory
- **Logs**: Check `C:\xampp\htdocs\webapp\logs/`

## 📄 License

[Your License Here]

## ✅ Deployment Checklist

Before going live:

- [ ] XAMPP installed and configured
- [ ] SQL Server drivers installed
- [ ] Application files deployed
- [ ] config.php configured with correct credentials
- [ ] Database schema imported
- [ ] Apache serving on correct IP:Port
- [ ] MySQL root password changed
- [ ] MSSQL connection tested
- [ ] SSH access configured
- [ ] Firewall rules applied
- [ ] phpMyAdmin access restricted
- [ ] Scheduled tasks configured and tested
- [ ] Backups configured
- [ ] All tests passing
- [ ] SSL certificate installed (production)
- [ ] Monitoring configured

---

**Note**: Never commit `config.php` or any files containing real credentials to version control.
