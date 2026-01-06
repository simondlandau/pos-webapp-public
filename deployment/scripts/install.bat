@echo off
REM Installation Script for Web Application
REM Run as Administrator

echo ============================================
echo Web Application Installation Script
echo ============================================
echo.

REM Check if running as administrator
net session >nul 2>&1
if %errorLevel% == 0 (
    echo Running with administrator privileges...
    echo.
) else (
    echo ERROR: This script must be run as Administrator
    echo Right-click and select "Run as administrator"
    pause
    exit /b 1
)

REM Set installation variables
set XAMPP_PATH=C:\xampp
set APP_NAME=webapp
set APP_PATH=%XAMPP_PATH%\htdocs\%APP_NAME%
set PHP_PATH=%XAMPP_PATH%\php\php.exe
set APACHE_PORT=9090
set APACHE_IP=192.168.1.10

echo Step 1: Checking XAMPP installation...
if not exist "%XAMPP_PATH%" (
    echo ERROR: XAMPP not found at %XAMPP_PATH%
    echo Please install XAMPP first from https://www.apachefriends.org
    pause
    exit /b 1
)
echo XAMPP found at %XAMPP_PATH%
echo.

echo Step 2: Stopping Apache and MySQL services...
net stop Apache2.4 2>nul
net stop mysql 2>nul
echo Services stopped.
echo.

echo Step 3: Copying application files...
if not exist "%APP_PATH%" mkdir "%APP_PATH%"
xcopy /E /I /Y "app\*" "%APP_PATH%\"
echo Application files copied.
echo.

echo Step 4: Setting up Apache configuration...
REM Backup original httpd.conf
copy "%XAMPP_PATH%\apache\conf\httpd.conf" "%XAMPP_PATH%\apache\conf\httpd.conf.backup"

REM Update Listen port
powershell -Command "(Get-Content '%XAMPP_PATH%\apache\conf\httpd.conf') -replace 'Listen 80', 'Listen %APACHE_IP%:%APACHE_PORT%' | Set-Content '%XAMPP_PATH%\apache\conf\httpd.conf'"

REM Enable virtual hosts
powershell -Command "(Get-Content '%XAMPP_PATH%\apache\conf\httpd.conf') -replace '#Include conf/extra/httpd-vhosts.conf', 'Include conf/extra/httpd-vhosts.conf' | Set-Content '%XAMPP_PATH%\apache\conf\httpd.conf'"

REM Copy virtual host configuration
copy /Y "config\httpd-vhosts.conf" "%XAMPP_PATH%\apache\conf\extra\httpd-vhosts.conf"

echo Apache configuration updated.
echo.

echo Step 5: Configuring PHP...
REM Enable required PHP extensions
powershell -Command "(Get-Content '%XAMPP_PATH%\php\php.ini') -replace ';extension=sqlsrv', 'extension=sqlsrv' | Set-Content '%XAMPP_PATH%\php\php.ini'"
powershell -Command "(Get-Content '%XAMPP_PATH%\php\php.ini') -replace ';extension=pdo_sqlsrv', 'extension=pdo_sqlsrv' | Set-Content '%XAMPP_PATH%\php\php.ini'"
powershell -Command "(Get-Content '%XAMPP_PATH%\php\php.ini') -replace ';extension=mysqli', 'extension=mysqli' | Set-Content '%XAMPP_PATH%\php\php.ini'"

echo PHP configuration updated.
echo.

echo Step 6: Setting up configuration file...
if not exist "%APP_PATH%\config.php" (
    copy "config\config.template.php" "%APP_PATH%\config.php"
    echo Configuration template copied. Please edit %APP_PATH%\config.php with your settings.
) else (
    echo config.php already exists. Skipping...
)
echo.

echo Step 7: Configuring Windows Firewall...
netsh advfirewall firewall add rule name="Apache Web Server" dir=in action=allow protocol=TCP localport=%APACHE_PORT%
netsh advfirewall firewall add rule name="SSH Server" dir=in action=allow protocol=TCP localport=22
netsh advfirewall firewall add rule name="MySQL Remote" dir=in action=allow protocol=TCP localport=3306
echo Firewall rules added.
echo.

echo Step 8: Setting up directory permissions...
icacls "%APP_PATH%" /grant "Users:(OI)(CI)F" /T
icacls "%APP_PATH%\logs" /grant "Users:(OI)(CI)F" /T
echo Permissions set.
echo.

echo Step 9: Starting services...
net start Apache2.4
net start mysql
echo Services started.
echo.

echo ============================================
echo Installation Complete!
echo ============================================
echo.
echo Application URL: http://%APACHE_IP%:%APACHE_PORT%/%APP_NAME%
echo phpMyAdmin URL: http://%APACHE_IP%:%APACHE_PORT%/phpmyadmin
echo.
echo IMPORTANT NEXT STEPS:
echo 1. Edit %APP_PATH%\config.php with your database credentials
echo 2. Run setup_scheduled_task.bat to configure daily email task
echo 3. Configure SSH server (see documentation)
echo 4. Test the application connection
echo.
pause
