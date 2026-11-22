# Smart-Enrol: School Admission and Enrollment Management System

Smart-Enrol is an enterprise web-based school admissions and enrollment management platform designed for secondary schools in Uganda and East Africa. It provides end-to-end management of student applications, intake verification, merit-based automated screening, provisional admission letter generation, multi-role staff access control, and historical cohort archiving.

The platform natively supports the Uganda National Examinations Board (UNEB) Primary Leaving Examination (PLE), the revised Lower Secondary Curriculum (NLSC) UCE 2024+ grading matrix (Grades A through E, competency scores 1-5, and Continuous Assessment Project Work), as well as Uganda Advanced Certificate of Education (UACE) A-Level subject combinations.

---

## Key Capabilities

- Public Admissions Portal: Mobile-responsive multi-step application wizard for Senior One (S1), Senior Five (S5), and Intermediate Transfer Classes (S2, S3, S4, S6).
- Dual Curriculum Architecture: Native support for UNEB PLE aggregate scoring and the 2024+ UCE NLSC competency-based letter grading standard.
- Learner Identification Number (LIN): Integrated optional LIN capture for learner tracking across national databases.
- Real-Time Public Tracking: Public status tracker with dynamic progress visualization by index number, parent phone, or parent email.
- Automated Admission Screening: Algorithmic checks against minimum aggregate and subject pass mark criteria with automatic combination validation.
- Provisional Admission Letters: Print-ready A4 PDF admission letters with school branding, QR verification codes, student numbers, fee schedules, and official Head Teacher electronic signatures.
- Cohort Archiving & Year Segmentation: Annual cohort segregation preserving historical data without purging or public exposure.
- Role-Based Access Control (RBAC): Strict CRUDAS matrix (Create, Read, Update, Delete, Admin actions, Sign/Export) with multi-role assignment per staff member.
- Multi-Factor Authentication (MFA): Built-in TOTP two-factor authentication compatible with Google Authenticator and Microsoft Authenticator.
- Cloudflare Turnstile Integration: Automated bot prevention on public submission endpoints.
- Interactive Analytics & Reports: Multi-year trend analytics, gender distribution graphs, enrolled vs. rejected statistics, and instant CSV/PDF reporting.

---

## System Requirements

Ensure your server environment satisfies the following baseline prerequisites:

- Operating System: Linux (Ubuntu 20.04/22.04 LTS, Debian 11/12, RHEL 8/9), macOS, or Windows (via XAMPP/MAMP/WampServer).
- Web Server: Apache 2.4+ (with `mod_rewrite` enabled) or Nginx 1.18+.
- PHP Version: PHP 8.0, 8.1, 8.2, or 8.3+.
- Database Engine: MySQL 5.7+ or MariaDB 10.3+.
- Required PHP Extensions:
  - `pdo_mysql` (Database communication)
  - `gd` (Passport photo resizing and white background processing)
  - `mbstring` (Multi-byte text handling)
  - `curl` (API verification and external asset resolution)
  - `openssl` (Secure cryptographic operations and MFA tokens)
  - `fileinfo` (MIME-type detection for document uploads)
  - `json` (Role arrays and configuration storage)
- Recommended PHP Settings:
  - `memory_limit = 256M`
  - `upload_max_filesize = 16M`
  - `post_max_size = 20M`
  - `max_execution_time = 120`

---

## Step-by-Step Installation Guide

### Step 1: Clone or Extract the Project

Clone the repository to your web server document root:

```bash
git clone https://github.com/linknixon/smart-enrol.git /var/www/html/smart-enrol
cd /var/www/html/smart-enrol
```

If using MAMP on macOS:
```bash
git clone https://github.com/linknixon/smart-enrol.git /Applications/MAMP/htdocs/admission-system-main
cd /Applications/MAMP/htdocs/admission-system-main
```

If using XAMPP on Windows:
```bash
git clone https://github.com/linknixon/smart-enrol.git C:/xampp/htdocs/smart-enrol
cd C:/xampp/htdocs/smart-enrol
```

### Step 2: Configure Directory Permissions

The application requires write access to the `uploads/` directory for storing student passport photographs, academic result slips, identity documents, and exported assets.

On Linux / macOS:
```bash
chmod -R 775 uploads
chmod -R 775 config
# If running Apache under www-data:
sudo chown -R www-data:www-data uploads
```

Ensure the following subdirectories exist inside `uploads/`:
```bash
mkdir -p uploads/photos
mkdir -p uploads/documents
mkdir -p uploads/signatures
chmod -R 775 uploads/photos uploads/documents uploads/signatures
```

### Step 3: Database Setup

1. Open your MySQL client or phpMyAdmin.
2. Create a new database with UTF-8 Unicode character set:

```sql
CREATE DATABASE `mutolere_admissions` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

3. Import the database schema from `mutolere_admissions.sql`:

```bash
mysql -u root -p mutolere_admissions < mutolere_admissions.sql
```

Note: The system contains an auto-migration engine in `config/db.php`. If you prefer automated schema installation, simply establishing the database connection will auto-create all missing tables, alter columns, and insert initial configuration values.

### Step 4: Configure Database Connection

Open `config/db.php` in a text editor:

```bash
nano config/db.php
```

Update the database connection credentials to match your local or production environment:

```php
$host    = "localhost";
$db      = "mutolere_admissions";
$user    = "your_db_username";
$pass    = "your_db_password";
$charset = "utf8mb4";
```

### Step 5: Web Server Configuration

#### Apache
Ensure `mod_rewrite` is enabled on Apache:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

Ensure your VirtualHost configuration enables `.htaccess` overrides:

```apache
<Directory /var/www/html/smart-enrol>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

#### Nginx
If running on Nginx, configure your server block to direct PHP requests through `php-fpm`:

```nginx
server {
    listen 80;
    server_name admissions.yourschool.sc.ug;
    root /var/www/html/smart-enrol;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }

    location /uploads/ {
        # Secure uploaded files from direct PHP execution
        location ~ \.php$ {
            deny all;
        }
    }
}
```

---

## Default Administrative Credentials

Upon completing the database import or initial startup, the default Super Administrator account is seeded with the following credentials:

- Login URL: `http://your-server-domain/admin/login.php`
- Default Username: `admin`
- Default Password: `User@2026@` (or `admin` if imported via legacy dump)

Security Notice: Immediately upon first login, navigate to Profile Settings (`admin/profile.php`), update your password, and activate Multi-Factor Authentication (MFA).

---

## Role-Based Access Control (CRUDAS Matrix)

The application enforces a granular security matrix across system roles:

| Role Key | Role Name | Manage Settings | Admissions Policies | Admit / Reject | View Reports | Export Data | Manage Users | Manage Roles | Take Application Actions | View Audit Logs |
|---|---|---|---|---|---|---|---|---|---|---|
| `super_admin` | Super Administrator | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| `admin` | System Administrator | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| `admissions_registry` | Admissions Officer | No | Yes | Yes | Yes | Yes | No | No | Yes | No |
| `bursar` | Bursar & Finance | No | No | No | Yes | Yes | No | No | No | No |
| `viewer` | Read-Only Observer | No | No | No | Yes | No | No | No | No | No |
| `ict_admin` | ICT Officer | Yes | No | No | Yes | Yes | Yes | No | No | Yes |

Multi-Role Assignment: Individual users can be granted secondary roles via their profile settings to expand permissions dynamically without elevating them to Super Administrator.

---

## School Configuration and Customization

Access the Administrative Settings page at `admin/settings.php` to customize:

1. School Identity:
   - Full School Name, Short Code, and Official School Motto.
   - School Logo and Favicon.
   - Gender Admission Policy: Mixed, Boys Only, or Girls Only.
2. Academic Sessions:
   - Active Academic Year (e.g., 2026).
   - Application Open and Close Dates per intake category (S1, S5, Transfers).
   - Official Reporting Dates for admitted candidates.
3. Admissions Thresholds:
   - PLE Cut-off Aggregates for Senior One.
   - UCE Cut-off Aggregates and Division thresholds for Senior Five.
   - A-Level Subject Combinations catalog management.
4. Tuition and Fees Matrix:
   - Class-by-class fee schedule breakdown (Tuition, Admission fee, Functional fee).
   - Fee structures are automatically embedded into generated provisional admission letters.
5. Notification Gateway:
   - SMTP host, port, authentication, encryption, and sender address.
   - Alert recipient email lists for instantaneous new application notifications.
6. Head Teacher Electronic Signature:
   - Official e-signature upload for automatic placement on admission letters and circulars.

---

## Project Structure

```
├── .gitignore                      # Git exclusion rules
├── .htaccess                       # Apache URL routing and security directives
├── README.md                       # Installation and operational documentation
├── apply.php                       # Public admissions application wizard
├── index.php                       # Public admissions landing portal
├── submit.php                      # Application ingestion and validation processor
├── success.php                     # Application receipt and submission confirmation
├── track.php                       # Public application status tracker
├── mutolere_admissions.sql         # Base database schema and seed data
├── config/
│   └── db.php                      # PDO connection handler, auto-migrations, and helper functions
├── admin/
│   ├── admission_letter.php        # Print-ready A4 admission letter generator
│   ├── admission_list.php          # Admitted candidates list and batch operations
│   ├── dashboard.php               # Administrative analytics and KPI dashboard
│   ├── details.php                 # Comprehensive candidate dossier inspector
│   ├── login.php                   # Administrative authentication and MFA verification
│   ├── logout.php                  # Session termination
│   ├── mfa_setup.php               # TOTP Multi-Factor Authentication enrollment
│   ├── profile.php                 # User profile management and password reset
│   ├── reports.php                 # Advanced custom reporting and data filtering
│   ├── reset_password.php          # Emergency credential recovery workflow
│   ├── roles.php                   # Staff Roles & Access Level Manager
│   ├── settings.php                # System configuration and fee structure portal
│   └── users.php                   # Administrative user account management
├── css/
│   └── (custom styling assets)
├── images/
│   └── (branding, badge icons, and placeholder assets)
└── uploads/
    ├── photos/                     # Student passport photographs
    ├── documents/                  # Result slips and identification files
    └── signatures/                 # Administrative electronic signatures
```

---

## Maintenance and Archiving

- Archiving Previous Years: When transitioning to a new academic year, update the Academic Year in `admin/settings.php`. Prior cohorts remain safely indexed in the database and accessible via the Admin Dashboard and Custom Reports with historical year filters, while being automatically shielded from the public portal.
- Data Backups: Regular MySQL dumps of the `mutolere_admissions` database combined with backups of the `uploads/` folder provide complete disaster recovery protection.

---

## Technical Support and Inquiries

For technical assistance, system integrations, or feature enhancements, contact your designated system administrator or school IT registry.
