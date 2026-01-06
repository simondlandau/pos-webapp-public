# Web Application - POS Integration System

A comprehensive web-based application for integrating with Point of Sale (POS) systems, providing real-time inventory management, sales reporting, and automated daily reporting via email.

## 🌟 Features

- **Real-time POS Integration** - Sync data from MSSQL POS system to local MySQL database
- **Inventory Management** - Track inventory levels with barcode scanner support
- **Sales Reporting** - Generate detailed sales reports from multiple data sources
- **Cash Count Management** - Track and manage daily cash counts
- **Dashboard Analytics** - Visual dashboards for business insights
- **Automated Email Reports** - Daily scheduled reports sent automatically
- **Multi-database Support** - Works with both MySQL and MSSQL databases
- **Barcode Scanner Integration** - HTML5-based inventory scanning interface

## 📋 Table of Contents

- [Features](#-features)
- [System Requirements](#-system-requirements)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Usage](#-usage)
- [Project Structure](#-project-structure)
- [Database Schema](#-database-schema)
- [API Documentation](#-api-documentation)
- [Contributing](#-contributing)
- [Troubleshooting](#-troubleshooting)
- [License](#-license)

## 💻 System Requirements

### Production Environment

- **Operating System**: Windows 10/11 or Windows Server 2016+
- **Web Server**: Apache 2.4+ (via XAMPP)
- **PHP**: 8.0 or higher
- **Databases**:
  - MySQL 8.0+ (local storage)
  - Microsoft SQL Server (POS integration)
- **Memory**: 4GB RAM minimum, 8GB recommended
- **Storage**: 20GB minimum free space
- **Network**: Static IP capability

### Development Environment

- Any modern operating system (Windows/Linux/macOS)
- PHP 8.0+
- MySQL 8.0+
- Access to MSSQL test database (optional)
- Git for version control

For detailed requirements, see [deployment/docs/PREREQUISITES.md](deployment/docs/PREREQUISITES.md)

## 🚀 Installation

### Quick Start (Production)

For production deployment on Windows with XAMPP:

```bash
# 1. Clone the repository
git clone https://github.com/yourusername/your-repo.git
cd your-repo

# 2. Follow the complete installation guide
# See: deployment/INSTALLATION_GUIDE.md

# 3. Run the automated installer (Windows, as Administrator)
cd deployment/scripts
install.bat
```

### Development Setup

```bash
# 1. Clone the repository
git clone https://github.com/yourusername/your-repo.git
cd your-repo

# 2. Install dependencies
composer install

# 3. Copy configuration template
cp deployment/config/config.template.php config.php

# 4. Edit config.php with your local settings

# 5. Import database schema
mysql -u root -p your_database < schema/database.sql
mysql -u root -p your_database < schema/inventory_scanner.sql

# 6. Start your local server
php -S localhost:8000
```

### Docker Setup (Optional)

```bash
# Coming soon - Docker support planned for future release
```

For complete installation instructions, see [deployment/INSTALLATION_GUIDE.md](deployment/INSTALLATION_GUIDE.md)

## ⚙️ Configuration

### Basic Configuration

1. Copy the configuration template:
   ```bash
   cp deployment/config/config.template.php config.php
   ```

2. Edit `config.php` with your settings:
   ```php
   // MySQL Configuration
   define('MYSQL_HOST', 'localhost');
   define('MYSQL_DATABASE', 'your_database');
   define('MYSQL_USERNAME', 'root');
   define('MYSQL_PASSWORD', 'your_password');
   
   // MSSQL Configuration (POS System)
   define('MSSQL_SERVER', '192.168.1.1');
   define('MSSQL_DATABASE', 'pos_database');
   define('MSSQL_USERNAME', 'pos_user');
   define('MSSQL_PASSWORD', 'pos_password');
   
   // Email Configuration
   define('SMTP_HOST', 'smtp.gmail.com');
   define('SMTP_USERNAME', 'your_email@gmail.com');
   define('SMTP_PASSWORD', 'your_app_password');
   ```

3. Set appropriate file permissions:
   ```bash
   # Linux/Mac
   chmod 755 logs/
   chmod 755 uploads/
   
   # Windows (via PowerShell as Administrator)
   icacls logs /grant Users:(OI)(CI)F /T
   icacls uploads /grant Users:(OI)(CI)F /T
   ```

### Environment Variables

Alternatively, you can use environment variables (recommended for sensitive data):

```bash
# .env file (create this, already in .gitignore)
MYSQL_HOST=localhost
MYSQL_DATABASE=webapp_db
MYSQL_USERNAME=root
MYSQL_PASSWORD=secret

MSSQL_SERVER=192.168.1.1
MSSQL_DATABASE=pos_db
MSSQL_USERNAME=pos_user
MSSQL_PASSWORD=secret

SMTP_USERNAME=your_email@gmail.com
SMTP_PASSWORD=your_app_password
```

## 📖 Usage

### Web Interface

After installation, access the application:

```
Production: http://192.168.1.10:9090/webapp
Development: http://localhost:8000
```

### Main Features

**Dashboard**
- Navigate to `/dashboard.php` for overview analytics
- View sales summaries, inventory levels, and key metrics

**Inventory Management**
- Access inventory dashboard: `/inventory_dashboard.php`
- Use barcode scanner: `/inventory_scanner.html`
- Export inventory data: `/inventory_export.php`

**Reports**
- MySQL Reports: `/mysql_reports.php`
- MSSQL Reports: `/mssql_reports.php`
- Unified Reports: `/reports.php`

**Cash Management**
- Daily cash count: `/cash_count_v3.php`
- Start of day procedures: `/start_of_day.php`

### API Endpoints

The application provides REST API endpoints:

```bash
# Inventory API
GET  /inventory_api.php?action=list
POST /inventory_api.php?action=update

# Dashboard Data
GET  /get_dashboard_data.php

# POS Sync
POST /sync_pos_sales_ajax.php
```

For complete API documentation, see [API Documentation](#-api-documentation) section below.

### Command Line Tools

```bash
# Test database connections
php deployment/scripts/test_connection.php

# Manual POS sync
php sync_pos_sales.php

# Send daily report
php send_daily.php

# Refresh MSSQL data
php refresh_mssql_data.php
```

## 📁 Project Structure

```
webapp/
├── deployment/              # Deployment scripts and documentation
│   ├── INSTALLATION_GUIDE.md
│   ├── README.md
│   ├── scripts/
│   └── config/
├── schema/                  # Database schemas
│   ├── database.sql
│   ├── inventory_scanner.sql
│   └── *.sql
├── PHPMailer/              # Email library
│   └── src/
├── logs/                   # Application logs (not in repo)
│   ├── debug_mssql.log
│   ├── debug_save.log
│   └── pos_sync.log
├── docs/                   # Additional documentation
│   └── Scanner_README.md
├── config.php              # Configuration (not in repo)
├── header.php              # Common header
├── footer.php              # Common footer
├── dashboard.php           # Main dashboard
├── inventory_*.php         # Inventory management
├── reports.php             # Reporting interface
├── sync_pos_sales.php      # POS synchronization
├── send_daily.php          # Daily email reports
└── *.php                   # Various modules
```

### Key Files

| File | Purpose |
|------|---------|
| `config.php` | Main configuration (created from template) |
| `dashboard.php` | Main application dashboard |
| `inventory_dashboard.php` | Inventory management interface |
| `inventory_scanner.html` | Barcode scanner interface |
| `sync_pos_sales.php` | POS data synchronization |
| `send_daily.php` | Automated daily reports |
| `reports.php` | Report generation |
| `cash_count_v3.php` | Cash counting interface |

## 🗄️ Database Schema

### MySQL Tables

The application uses the following main tables:

- `pos_sales` - Synchronized sales data from POS
- `inventory` - Inventory items and stock levels
- `cash_counts` - Daily cash count records
- `users` - User accounts and permissions
- `sync_log` - Synchronization audit trail

### MSSQL Integration

Connects to POS system tables:
- `Sales` - Point of sale transactions
- `Inventory` - POS inventory data
- `Products` - Product catalog

For complete schema documentation, see files in `schema/` directory.

### Database Migrations

```bash
# Initial setup
mysql -u root -p your_database < schema/database.sql

# Inventory tables
mysql -u root -p your_database < schema/inventory_scanner.sql

# Additional schemas
mysql -u root -p your_database < schema/target.sql
```

## 🔌 API Documentation

### Inventory API

**List Inventory Items**
```http
GET /inventory_api.php?action=list&category=all
```

Response:
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "barcode": "123456789",
      "name": "Product Name",
      "quantity": 100,
      "price": 19.99
    }
  ]
}
```

**Update Inventory**
```http
POST /inventory_api.php
Content-Type: application/json

{
  "action": "update",
  "barcode": "123456789",
  "quantity": 95
}
```

### Dashboard API

**Get Dashboard Data**
```http
GET /get_dashboard_data.php?range=today
```

Response:
```json
{
  "sales": {
    "total": 1250.00,
    "count": 45,
    "average": 27.78
  },
  "inventory": {
    "low_stock": 12,
    "out_of_stock": 3
  }
}
```

### Sync API

**Trigger POS Sync**
```http
POST /sync_pos_sales_ajax.php
Content-Type: application/json

{
  "sync_type": "incremental",
  "date_from": "2025-01-01"
}
```

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

### Development Workflow

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Make your changes
4. Write/update tests if applicable
5. Commit with clear messages: `git commit -m "Add feature: description"`
6. Push to your fork: `git push origin feature/your-feature`
7. Create a Pull Request

### Code Standards

- Follow PSR-12 coding standards for PHP
- Use meaningful variable and function names
- Comment complex logic
- Keep functions focused and small
- Sanitize all user inputs
- Use prepared statements for database queries

### Testing

Before submitting a PR:

```bash
# Test database connections
php deployment/scripts/test_connection.php

# Check for PHP syntax errors
find . -name "*.php" -exec php -l {} \;

# Test critical functionality
# (Add your test procedures here)
```

## 🐛 Troubleshooting

### Common Issues

**Cannot connect to MSSQL**
```bash
# Verify SQL Server allows remote connections
# Check TCP/IP is enabled in SQL Server Configuration Manager
# Test connectivity: telnet 192.168.1.1 1433
```

**Apache won't start**
```bash
# Check if port is in use
netstat -ano | findstr :9090

# Review error log
tail -f logs/error.log  # Linux/Mac
type C:\xampp\apache\logs\error.log  # Windows
```

**Scheduled tasks not running**
```bash
# Windows: Check Task Scheduler
schtasks /Query /TN "WebApp_DailyEmail" /V

# Verify PHP path in scheduled task
# Check logs/scheduled_task.log for errors
```

For more troubleshooting information, see [deployment/docs/TROUBLESHOOTING.md](deployment/docs/TROUBLESHOOTING.md)

## 📊 Monitoring & Logging

### Log Files

Application logs are stored in `logs/` directory:

- `debug_mssql.log` - MSSQL connection and query logs
- `debug_save.log` - Data save operations
- `pos_sync.log` - POS synchronization logs
- `scheduled_task.log` - Scheduled task execution logs

### Monitoring

```bash
# Watch logs in real-time (Linux/Mac)
tail -f logs/pos_sync.log

# Windows
Get-Content logs\pos_sync.log -Wait -Tail 50
```

## 🔒 Security

### Important Security Notes

- Never commit `config.php` or files with credentials
- Change default MySQL root password
- Restrict phpMyAdmin access by IP
- Use strong passwords for all accounts
- Enable HTTPS in production
- Keep software updated (XAMPP, PHP, dependencies)
- Regular security audits recommended

### Reporting Security Issues

Please report security vulnerabilities privately:
- Email: papasuite@gmail.com
- Do not create public GitHub issues for security problems

## 📄 License

MIT License

Copyright (c) 2025 Simon D Landau

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

## 👥 Authors & Acknowledgments

- **Your Name** - S. Landau
- **Contributors** - See GitHub contributors list

## 📞 Support

- **Documentation**: See `deployment/` directory
- **Issues**: [GitHub Issues](https://github.com/simondlandau/pos-webapp-public/issues)
- **Email**: papasuite@gmail.com

## 🗺️ Roadmap

- [ ] Docker containerization
- [ ] RESTful API expansion
- [ ] Mobile app integration
- [ ] Multi-location support
- [ ] Advanced analytics dashboard
- [ ] Automated backup system
- [ ] Real-time notifications
- [ ] Multi-language support

## 📝 Changelog

### Version 1.0.0 (Current)
- Initial release
- POS integration
- Inventory management
- Sales reporting
- Automated daily emails
- Barcode scanner support

---

**Last Updated**: January 2026  
**Version**: 1.0.0  
**Maintainer**: [Simon/PAPA]
