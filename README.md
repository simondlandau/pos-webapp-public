# SVP Web Application

A PHP + MySQL web app for Point of Sale  operations.  
This repository contains the sterilised version of the finance and reconciliation dashboard used in production.
Also the Inventory Scanner PWA.

---

## 🚀 Features
- Drag-and-drop PHP interface for transaction management  
- MySQL backend for data storage and reporting  
- Secure HTTPS access via Apache or Dockerized deployment  
- Compatible with local LAMP servers and DigitalOcean Docker Droplets  

---

## 🧰 Requirements
- PHP 8.1+
- MySQL 8+
- Apache2 (with mod_php) or Docker
- Optional: Docker Compose for full container deployment

---

## ⚙️ Installation

### Option 1: Local LAMP Deployment
```bash
# Clone repository
git clone https://github.com/simondlandau/pos-webapp-public.git
cd svp

# Move to your Apache web root
sudo cp -r . /var/www/html/svp

Modify `header.php`, `footer.php` and report headers to include your company
   details.
Three test scripts are available 
    `test_logo.php`
    `test_dashboard.php`
    `test_inventory.php`.

##📱 For  Mobile Inventory Scanner (PWA) setup, 
    see [docs/Scanner_README.md](docs/Scanner_README.md)


