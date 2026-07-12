SwiftHaul Logistics

SwiftHaul Logistics is a PHP and MySQL-based logistics marketplace that connects customers with independent delivery riders. The platform supports shipment booking, online payments, live order tracking, rider management, automated payouts, customer support, reviews, and identity verification.

Features
Customer registration and authentication
Rider registration and verification
Shipment booking and tracking
Live GPS delivery tracking
Online payment with Paystack
Automated rider payouts
Customer reviews
Complaint and refund management
Admin dashboard
Secure KYC document management
Browser push notifications
Email notifications
Responsive interface
Technology Stack
PHP 8+
MySQL
JavaScript
HTML5 & CSS3
Composer
PHPMailer
Paystack API
Leaflet.js
OpenStreetMap
Requirements
PHP 8.0 or later
MySQL
Apache (XAMPP/LAMP/WAMP)
Composer
HTTPS (recommended for production)

Required PHP extensions:

PDO
GD
Fileinfo
OpenSSL
JSON
Installation
1. Clone the project
git clone https://github.com/yourusername/swifthaul.git
cd swifthaul

Or copy the project into your web server directory.

2. Install dependencies
composer install
3. Import the database

Import:

database/schema.sql

If upgrading from an earlier version, run the migration files in the order provided inside the database directory.

4. Configure the application

Update:

config/config.php

Configure:

Database credentials
Paystack keys
SMTP credentials
VAPID keys
Application URL
Platform commission
5. Configure web server

Ensure:

URL rewriting is enabled
HTTPS is configured
Uploads directory is not publicly accessible
.htaccess files are enabled
6. Start the application

Visit:

http://localhost/swifthaul

or your production domain.

Project Structure
swifthaul/
│
├── admin/
├── api/
├── assets/
├── config/
├── cron/
├── database/
├── includes/
├── rider/
├── uploads/
├── vendor/
└── index.php
Deployment Checklist
Install Composer dependencies
Import the database
Configure environment settings
Configure Paystack API keys
Configure SMTP
Generate VAPID keys
Enable HTTPS
Configure scheduled cron jobs
Verify file permissions
Test payment and notification workflows
Security

SwiftHaul includes:

Password hashing
Prepared SQL statements
CSRF protection
Input validation
Secure file uploads
KYC document protection
Authentication and authorization
Rate limiting
Integrations
Paystack
PHPMailer
Web Push
Leaflet.js
OpenStreetMap

## Author
**EL-NIMIM TAMUNOBERE**
GitHub: https://github.com/db-EL

Developer of SwiftHaul Logistics, a full-stack logistics marketplace built with PHP, MySQL, JavaScript, and Paystack integration.

License
This project is provided for educational and commercial use in accordance with its license.

Support
For issues or feature requests, create an issue in the project repository or contact the project maintainer.