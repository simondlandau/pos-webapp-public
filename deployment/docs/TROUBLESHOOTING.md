# Troubleshooting Guide

This guide covers common issues you may encounter during installation, configuration, and operation of the web application.

## 📋 Table of Contents

- [Installation Issues](#installation-issues)
- [Apache/Web Server Issues](#apacheweb-server-issues)
- [Database Connection Issues](#database-connection-issues)
- [PHP Issues](#php-issues)
- [Email/SMTP Issues](#emailsmtp-issues)
- [Network and Firewall Issues](#network-and-firewall-issues)
- [Scheduled Tasks Issues](#scheduled-tasks-issues)
- [Performance Issues](#performance-issues)
- [Security Issues](#security-issues)
- [Application-Specific Issues](#application-specific-issues)
- [Log Files and Diagnostics](#log-files-and-diagnostics)

---

## 🔧 Installation Issues

### Issue: XAMPP Installation Fails

**Symptoms**:
- Installer crashes or hangs
- Error: "Cannot write to directory"
- Installation incomplete

**Solutions**:

1. **Run as Administrator**
   ```cmd
   Right-click XAMPP installer → Run as administrator
   ```

2. **Disable Antivirus Temporarily**
   - Many antivirus programs block XAMPP installation
   - Disable during installation, re-enable after

3. **Check User Account Control (UAC)**
   ```cmd
   Control Panel → User Accounts → Change User Account Control settings
   Set to lowest level temporarily
   ```

4. **Clear Temp Directory**
   ```cmd
   del /q %TEMP%\*
   ```

5. **Install to Different Location**
   - Try `D:\xampp` instead of `C:\xampp`
   - Avoid paths with spaces or special characters

### Issue: SQL Server Drivers Not Loading

**Symptoms**:
- PHP warning: "Unable to load dynamic library 'php_sqlsrv'"
- MSSQL connections fail

**Solutions**:

1. **Verify Driver Files Exist**
   ```cmd
   dir C:\xampp\php\ext\php_sqlsrv*.dll
   dir C:\xampp\php\ext\php_pdo_sqlsrv*.dll
   ```

2. **Check php.ini Configuration**
   ```cmd
   notepad C:\xampp\php\php.ini
   ```
   
   Ensure these lines exist (not commented with `;`):
   ```ini
   extension=php_sqlsrv_82_ts.dll
   extension=php_pdo_sqlsrv_82_ts.dll
   ```

3. **Verify PHP Version Match**
   ```cmd
   php -v
   # Must match driver version (e.g., PHP 8.2 needs _82_ drivers)
   ```

4. **Install Visual C++ Redistributables**
   - Download from: https://aka.ms/vs/17/release/vc_redist.x64.exe
   - Install both x64 and x86 versions
   - Restart system

5. **Check Driver Architecture**
   - Use Thread Safe (TS) version for Apache
   - Use Non-Thread Safe (NTS) for CLI only

### Issue: Installation Script Fails

**Symptoms**:
- `install.bat` exits with errors
- "Access denied" errors
- Files not copied

**Solutions**:

1. **Run as Administrator**
   ```cmd
   Right-click install.bat → Run as administrator
   ```

2. **Check Paths**
   ```cmd
   # Verify XAMPP is in default location
   dir C:\xampp
   
   # Or edit install.bat to match your installation
   set XAMPP_PATH=D:\xampp
   ```

3. **Verify Source Files**
   ```cmd
   # Ensure app/ directory exists with files
   dir installation_package\app
   ```

4. **Check Disk Space**
   ```cmd
   wmic logicaldisk get caption,freespace
   # Need at least 5 GB free
   ```

---

## 🌐 Apache/Web Server Issues

### Issue: Apache Won't Start

**Symptoms**:
- XAMPP Control Panel shows Apache as stopped
- Error: "Port 80/443 in use"
- Apache service fails to start

**Solutions**:

1. **Check Port Conflicts**
   ```cmd
   netstat -ano | findstr :80
   netstat -ano | findstr :443
   netstat -ano | findstr :9090
   ```

2. **Stop Conflicting Services**
   
   **IIS (Internet Information Services)**:
   ```cmd
   iisreset /stop
   # Or disable permanently:
   sc config W3SVC start=disabled
   ```
   
   **Skype**:
   - Skype → Settings → Advanced → Connection
   - Uncheck "Use port 80 and 443 for incoming connections"
   
   **VMware/VirtualBox**:
   - May use port 443
   - Stop virtual machines or change their port settings

3. **Check Apache Error Log**
   ```cmd
   type C:\xampp\apache\logs\error.log
   # Look for specific error messages
   ```

4. **Test Apache Configuration**
   ```cmd
   C:\xampp\apache\bin\httpd.exe -t
   # Should show "Syntax OK"
   ```

5. **Verify Listen Directive**
   ```cmd
   notepad C:\xampp\apache\conf\httpd.conf
   ```
   
   Check:
   ```apache
   Listen 192.168.1.10:9090
   # Not: Listen 80
   ```

6. **Reset Apache Configuration**
   ```cmd
   # Backup current config
   copy C:\xampp\apache\conf\httpd.conf C:\xampp\apache\conf\httpd.conf.backup
   
   # Restore original
   copy C:\xampp\apache\conf\original\httpd.conf C:\xampp\apache\conf\httpd.conf
   ```

### Issue: Cannot Access Website

**Symptoms**:
- Browser shows "Can't reach this page"
- "Connection refused" error
- Blank page

**Solutions**:

1. **Verify Apache is Running**
   ```cmd
   netstat -ano | findstr :9090
   # Should show LISTENING
   ```

2. **Check Firewall**
   ```cmd
   # Test from local machine first
   http://localhost:9090/webapp
   http://127.0.0.1:9090/webapp
   
   # Then test with IP
   http://192.168.1.10:9090/webapp
   ```

3. **Verify Virtual Host Configuration**
   ```cmd
   notepad C:\xampp\apache\conf\extra\httpd-vhosts.conf
   ```
   
   Ensure:
   ```apache
   <VirtualHost 192.168.1.10:9090>
       DocumentRoot "C:/xampp/htdocs/webapp"
       ServerName 192.168.1.10
   </VirtualHost>
   ```

4. **Check Directory Permissions**
   ```cmd
   icacls C:\xampp\htdocs\webapp
   # Should show Users with appropriate permissions
   ```

5. **Test with Simple HTML**
   ```cmd
   echo ^<html^>^<body^>Test^</body^>^</html^> > C:\xampp\htdocs\webapp\test.html
   # Access: http://192.168.1.10:9090/webapp/test.html
   ```

### Issue: 403 Forbidden Error

**Symptoms**:
- "Forbidden - You don't have permission to access this resource"

**Solutions**:

1. **Check Directory Configuration**
   ```apache
   <Directory "C:/xampp/htdocs/webapp">
       Options Indexes FollowSymLinks
       AllowOverride All
       Require all granted  # Not "Require all denied"
   </Directory>
   ```

2. **Verify Index File Exists**
   ```cmd
   dir C:\xampp\htdocs\webapp\index.php
   # Or check DirectoryIndex in httpd.conf
   ```

3. **Check File Permissions**
   ```cmd
   icacls C:\xampp\htdocs\webapp\index.php
   # Should be readable by Users
   ```

---

## 🗄️ Database Connection Issues

### Issue: Cannot Connect to MySQL

**Symptoms**:
- "Access denied for user 'root'@'localhost'"
- "Can't connect to MySQL server"
- Application shows database errors

**Solutions**:

1. **Verify MySQL is Running**
   ```cmd
   netstat -ano | findstr :3306
   # Or in XAMPP Control Panel
   ```

2. **Test Connection**
   ```cmd
   C:\xampp\mysql\bin\mysql.exe -u root -p
   # Enter password when prompted
   ```

3. **Reset MySQL Root Password**
   ```cmd
   # Stop MySQL
   net stop mysql
   
   # Start in safe mode
   C:\xampp\mysql\bin\mysqld.exe --skip-grant-tables
   
   # In new terminal
   C:\xampp\mysql\bin\mysql.exe -u root
   
   # Run these SQL commands:
   FLUSH PRIVILEGES;
   ALTER USER 'root'@'localhost' IDENTIFIED BY 'new_password';
   FLUSH PRIVILEGES;
   EXIT;
   
   # Stop mysqld and restart normally
   net start mysql
   ```

4. **Check config.php Settings**
   ```php
   define('MYSQL_HOST', 'localhost');      // Or '127.0.0.1'
   define('MYSQL_USERNAME', 'root');
   define('MYSQL_PASSWORD', 'your_password');  // Must match MySQL password
   define('MYSQL_DATABASE', 'existing_database');  // Must exist
   ```

5. **Create Database if Missing**
   ```sql
   CREATE DATABASE IF NOT EXISTS your_database_name
   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

### Issue: Cannot Connect to MSSQL

**Symptoms**:
- "SQLSTATE[08001] SQL Server does not exist"
- "Connection timeout"
- "Login failed for user"

**Solutions**:

1. **Test Network Connectivity**
   ```cmd
   ping 192.168.1.1
   telnet 192.168.1.1 1433
   # If telnet fails, MSSQL is not reachable
   ```

2. **Verify SQL Server Configuration**
   
   On SQL Server machine:
   - Open SQL Server Configuration Manager
   - SQL Server Network Configuration → Protocols
   - Ensure TCP/IP is **Enabled**
   - Right-click TCP/IP → Properties → IP Addresses
   - Set TCP Port to 1433
   - Restart SQL Server service

3. **Check SQL Server Authentication**
   
   ```sql
   -- On SQL Server, verify authentication mode
   -- SQL Server Management Studio
   -- Server Properties → Security
   -- Must be "SQL Server and Windows Authentication mode"
   ```

4. **Test SQL Server Login**
   ```cmd
   # From target machine, use SQL Server Management Studio or sqlcmd
   sqlcmd -S 192.168.1.1 -U your_username -P your_password -d your_database
   # Should connect without errors
   ```

5. **Check Firewall on SQL Server**
   ```cmd
   # On SQL Server machine
   netsh advfirewall firewall add rule ^
       name="SQL Server" dir=in action=allow ^
       protocol=TCP localport=1433
   ```

6. **Verify config.php Settings**
   ```php
   define('MSSQL_SERVER', '192.168.1.1');  // IP, not hostname
   define('MSSQL_PORT', '1433');
   define('MSSQL_DATABASE', 'actual_db_name');  // Exact case
   define('MSSQL_USERNAME', 'sql_user');
   define('MSSQL_PASSWORD', 'sql_password');
   ```

7. **Check PHP SQL Server Extensions**
   ```cmd
   php -m | findstr sqlsrv
   # Should show: sqlsrv, pdo_sqlsrv
   ```

### Issue: "No database selected" Error

**Solutions**:

1. **Verify Database Exists**
   ```sql
   SHOW DATABASES;  -- MySQL
   SELECT name FROM sys.databases;  -- MSSQL
   ```

2. **Check USE Statement**
   ```php
   // In PHP code
   $pdo->exec("USE your_database_name");
   ```

3. **Verify Config**
   ```php
   // Ensure database is specified in connection
   define('MYSQL_DATABASE', 'actual_database_name');
   ```

---

## 🐘 PHP Issues

### Issue: PHP Errors Not Displaying

**Symptoms**:
- Blank white page
- No error messages shown

**Solutions**:

1. **Enable Error Display (Development)**
   ```ini
   ; In php.ini
   display_errors = On
   error_reporting = E_ALL
   ```

2. **Check Error Log**
   ```cmd
   type C:\xampp\php\logs\php_error.log
   # Or check Apache error log
   type C:\xampp\apache\logs\error.log
   ```

3. **In Script (Temporary)**
   ```php
   <?php
   ini_set('display_errors', 1);
   ini_set('display_startup_errors', 1);
   error_reporting(E_ALL);
   ```

### Issue: PHP Extension Not Loaded

**Symptoms**:
- "Call to undefined function mysqli_connect()"
- "Class 'PDO' not found"

**Solutions**:

1. **Check Extension in php.ini**
   ```cmd
   notepad C:\xampp\php\php.ini
   ```
   
   Ensure not commented:
   ```ini
   extension=mysqli
   extension=pdo_mysql
   extension=openssl
   extension=mbstring
   ```

2. **Verify Extension File Exists**
   ```cmd
   dir C:\xampp\php\ext\php_mysqli.dll
   ```

3. **Check Extension Directory**
   ```ini
   ; In php.ini
   extension_dir = "C:\xampp\php\ext"
   ```

4. **Restart Apache**
   ```cmd
   net stop Apache2.4
   net start Apache2.4
   ```

### Issue: Memory Limit Errors

**Symptoms**:
- "Fatal error: Allowed memory size exhausted"

**Solutions**:

1. **Increase Memory Limit**
   ```ini
   ; In php.ini
   memory_limit = 256M  ; Or higher
   ```

2. **Per-Script Override**
   ```php
   ini_set('memory_limit', '512M');
   ```

3. **Check Memory Usage**
   ```php
   echo memory_get_usage(true) / 1024 / 1024 . ' MB';
   ```

---

## 📧 Email/SMTP Issues

### Issue: Emails Not Sending

**Symptoms**:
- No errors but emails don't arrive
- SMTP connection timeout
- Authentication failure

**Solutions**:

1. **Verify SMTP Settings**
   ```php
   define('SMTP_HOST', 'smtp.gmail.com');
   define('SMTP_PORT', 587);  // Or 465 for SSL
   define('SMTP_ENCRYPTION', 'tls');  // Or 'ssl'
   define('SMTP_USERNAME', 'your_email@gmail.com');
   define('SMTP_PASSWORD', 'your_app_password');  // Not regular password
   ```

2. **For Gmail: Use App Password**
   - Go to: https://myaccount.google.com/apppasswords
   - Generate app-specific password
   - Use this instead of regular password

3. **Test SMTP Connection**
   ```cmd
   telnet smtp.gmail.com 587
   # Should connect
   ```

4. **Check PHP OpenSSL Extension**
   ```cmd
   php -m | findstr openssl
   # Must be loaded for TLS/SSL
   ```

5. **Enable Less Secure Apps (Gmail Legacy)**
   - Not recommended, use App Passwords instead
   - https://myaccount.google.com/lesssecureapps

6. **Check Firewall/Antivirus**
   - May block SMTP ports 587/465
   - Temporarily disable to test

7. **Enable PHPMailer Debug**
   ```php
   $mail->SMTPDebug = 2;  // Verbose debug output
   $mail->Debugoutput = 'html';
   ```

8. **Check Spam Folder**
   - Emails may be filtered as spam
   - Check recipient's spam/junk folder

### Issue: "SMTP Error: Could not authenticate"

**Solutions**:

1. **Verify Credentials**
   - Double-check username (full email)
   - Use app password, not account password
   - Check for typos in config.php

2. **Check Account Status**
   - Ensure email account is active
   - Check for account lockouts
   - Verify account allows SMTP access

---

## 🔥 Network and Firewall Issues

### Issue: Cannot Access from Other Computers

**Symptoms**:
- Works on server but not from other machines
- "Connection refused" from network

**Solutions**:

1. **Verify Server IP**
   ```cmd
   ipconfig
   # Should show 192.168.1.10
   ```

2. **Test from Server First**
   ```cmd
   # On server machine
   curl http://192.168.1.10:9090/webapp
   # Should work
   ```

3. **Check Windows Firewall**
   ```cmd
   # View rules
   netsh advfirewall firewall show rule name=all | findstr 9090
   
   # Add rule if missing
   netsh advfirewall firewall add rule ^
       name="Apache-9090" dir=in action=allow ^
       protocol=TCP localport=9090
   ```

4. **Disable Firewall Temporarily (Testing Only)**
   ```cmd
   netsh advfirewall set allprofiles state off
   # Test access, then re-enable:
   netsh advfirewall set allprofiles state on
   ```

5. **Check Router/Switch**
   - Ensure no VLAN isolation
   - Check router firewall rules
   - Verify network connectivity

6. **Test with Telnet**
   ```cmd
   # From client machine
   telnet 192.168.1.10 9090
   # Should connect
   ```

### Issue: Static IP Not Working

**Symptoms**:
- IP address changes
- Network drops intermittently

**Solutions**:

1. **Set Static IP Properly**
   ```cmd
   # Open Network Connections
   ncpa.cpl
   
   # Right-click adapter → Properties → IPv4
   # Set manually:
   IP: 192.168.1.10
   Subnet: 255.255.255.0
   Gateway: 192.168.1.1
   DNS: 8.8.8.8, 8.8.4.4
   ```

2. **Check for DHCP Conflicts**
   - Reserve IP in router DHCP settings
   - Ensure no other device uses 192.168.1.10

3. **Flush DNS and Renew**
   ```cmd
   ipconfig /flushdns
   ipconfig /release
   ipconfig /renew
   ```

---

## ⏰ Scheduled Tasks Issues

### Issue: Scheduled Task Not Running

**Symptoms**:
- Daily emails not received
- Task appears in Task Scheduler but doesn't execute
- Task history shows failures

**Solutions**:

1. **Verify Task Exists**
   ```cmd
   schtasks /Query /TN "WebApp_DailyEmail"
   ```

2. **Check Task Configuration**
   ```cmd
   schtasks /Query /TN "WebApp_DailyEmail" /V /FO LIST
   ```
   
   Verify:
   - Status: Ready
   - Run As User: SYSTEM
   - Trigger: Daily at correct time

3. **Test Manual Execution**
   ```cmd
   schtasks /Run /TN "WebApp_DailyEmail"
   # Check if runs manually
   ```

4. **Check Task History**
   - Open Task Scheduler
   - Find task → History tab
   - Look for error codes

5. **Verify PHP Path**
   ```cmd
   # Task should execute:
   "C:\xampp\php\php.exe" "C:\xampp\htdocs\webapp\send_daily.php"
   
   # Test command directly:
   C:\xampp\php\php.exe C:\xampp\htdocs\webapp\send_daily.php
   ```

6. **Check Log File**
   ```cmd
   type C:\xampp\htdocs\webapp\logs\scheduled_task.log
   ```

7. **Verify Script Permissions**
   ```cmd
   icacls C:\xampp\htdocs\webapp\send_daily.php
   ```

8. **Run Task as Different User**
   ```cmd
   # If SYSTEM doesn't work, try local admin
   schtasks /Change /TN "WebApp_DailyEmail" /RU "Administrator" /RP
   ```

### Issue: Task Runs but No Email Sent

**Solutions**:

1. **Check Script Output**
   ```cmd
   # Run script manually and check output
   php C:\xampp\htdocs\webapp\send_daily.php
   ```

2. **Check Email Configuration**
   - Verify SMTP settings in config.php
   - Test email sending separately

3. **Check Application Log**
   ```cmd
   type C:\xampp\htdocs\webapp\logs\app.log
   ```

---

## ⚡ Performance Issues

### Issue: Slow Page Load Times

**Symptoms**:
- Pages take 5+ seconds to load
- Database queries slow
- High CPU usage

**Solutions**:

1. **Enable Query Caching**
   ```ini
   ; In my.ini (MySQL)
   query_cache_type = 1
   query_cache_size = 32M
   ```

2. **Optimize PHP Settings**
   ```ini
   ; In php.ini
   opcache.enable = 1
   opcache.memory_consumption = 128
   realpath_cache_size = 4096K
   ```

3. **Check Slow Queries**
   ```sql
   -- Enable slow query log
   SET GLOBAL slow_query_log = 'ON';
   SET GLOBAL long_query_time = 2;
   
   -- Check log
   -- Location: C:\xampp\mysql\data\[hostname]-slow.log
   ```

4. **Add Database Indexes**
   ```sql
   -- Find queries without indexes
   SHOW INDEX FROM your_table;
   
   -- Add indexes on frequently queried columns
   CREATE INDEX idx_column_name ON table_name(column_name);
   ```

5. **Optimize Apache**
   ```apache
   # In httpd.conf
   KeepAlive On
   MaxKeepAliveRequests 100
   KeepAliveTimeout 5
   ```

---

## 🔒 Security Issues

### Issue: Unauthorized Access to phpMyAdmin

**Solutions**:

1. **Restrict by IP**
   ```apache
   # In httpd-vhosts.conf
   <Directory "C:/xampp/phpMyAdmin">
       Require ip 192.168.1.0/24
       Require ip 127.0.0.1
   </Directory>
   ```

2. **Change phpMyAdmin URL**
   ```apache
   Alias /secretadmin "C:/xampp/phpMyAdmin"
   ```

3. **Enable HTTP Authentication**
   ```apache
   <Directory "C:/xampp/phpMyAdmin">
       AuthType Basic
       AuthName "Restricted Area"
       AuthUserFile C:/xampp/security/passwords
       Require valid-user
   </Directory>
   ```

   Create password file:
   ```cmd
   C:\xampp\apache\bin\htpasswd.exe -c C:\xampp\security\passwords admin
   ```

---

## 📊 Application-Specific Issues

### Issue: Inventory Scanner Not Working

**Solutions**:

1. **Check Browser Permissions**
   - Allow camera access in browser
   - HTTPS required for camera API (or localhost)

2. **Test Camera**
   ```javascript
   // In browser console
   navigator.mediaDevices.getUserMedia({video: true})
       .then(stream => console.log('Camera works!'))
       .catch(err => console.error('Camera error:', err));
   ```

3. **Check Scanner Library**
   - Verify HTML5-QRCode library loaded
   - Check browser console for JavaScript errors

### Issue: POS Sync Failing

**Solutions**:

1. **Check Sync Log**
   ```cmd
   type C:\xampp\htdocs\webapp\logs\pos_sync.log
   ```

2. **Test MSSQL Connection**
   ```cmd
   php deployment/scripts/test_connection.php
   ```

3. **Run Manual Sync**
   ```cmd
   php C:\xampp\htdocs\webapp\sync_pos_sales.php
   ```

4. **Check Sync Interval**
   ```php
   // In config.php
   define('SYNC_ENABLED', true);
   define('SYNC_INTERVAL', 300);  // Seconds
   ```

---

## 📝 Log Files and Diagnostics

### Important Log Locations

```cmd
# Apache Logs
C:\xampp\apache\logs\error.log
C:\xampp\apache\logs\access.log

# PHP Logs
C:\xampp\php\logs\php_error.log

# MySQL Logs
C:\xampp\mysql\data\[hostname].err
C:\xampp\mysql\data\[hostname]-slow.log

# Application Logs
C:\xampp\htdocs\webapp\logs\app.log
C:\xampp\htdocs\webapp\logs\debug_mssql.log
C:\xampp\htdocs\webapp\logs\pos_sync.log
C:\xampp\htdocs\webapp\logs\scheduled_task.log

# Windows Event Logs
eventvwr.msc → Windows Logs → Application
```

### Diagnostic Commands

```cmd
# System Information
systeminfo

# Network Configuration
ipconfig /all
route print
netstat -ano

# Service Status
net start
sc query Apache2.4
sc query mysql

# PHP Information
php -v
php -m
php -i

# Test Database
php deployment/scripts/test_connection.php

# Check Ports
netstat -ano | findstr LISTENING
```

---

## 🆘 Getting Additional Help

If none of these solutions work:

1. **Collect Diagnostic Information**
   ```cmd
   # Run these and save output
   php -i > phpinfo.txt
   netstat -ano > netstat.txt
   type C:\xampp\apache\logs\error.log > apache_errors.txt
   ```

2. **Check Application Logs**
   - Review all log files in `logs/` directory
   - Note exact error messages and timestamps

3. **Create GitHub Issue**
   - Include error messages
   - Include relevant log excerpts
   - Describe steps to reproduce
   - Include system information

4. **Contact Support**
   - Email: support@yourcompany.com
   - Include diagnostic information collected above

---

**Last Updated**: January 2025  
**Document Version**: 1.0.0
