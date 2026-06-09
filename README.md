# UTMSPACE Claim System

## Project Overview

The **UTMSPACE Claim System** is a web-based claim management system developed for the DSPD2794 final-year project. The system can be tested locally with XAMPP and has also been deployed online using InfinityFree hosting. It supports the full reimbursement workflow for three user roles:

- **Staff** submit claims, upload receipts, edit eligible claims, cancel pending claims, and track claim status.
- **Finance** review claims, approve or reject claims, process payments, and export claim reports.
- **Admin** manage user accounts, monitor claim activity, force-cancel invalid claims, and generate user reports.

The system is designed using **Native PHP 8**, **MySQL/MariaDB**, **MySQLi prepared statements**, and **Bootstrap 5**. XAMPP is used for local development and testing, while InfinityFree is used for online hosting and demonstration.

## Technology Stack

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS3, Bootstrap 5, JavaScript |
| Backend | Native PHP 8 |
| Database | MySQL / MariaDB |
| Database API | MySQLi prepared statements |
| Email Service | PHPMailer |
| UI Notifications | SweetAlert2 |
| Local Server | XAMPP Apache and MySQL |
| Online Hosting | InfinityFree |

## Main Features

### Staff Portal

- Staff registration with email verification.
- Secure login and logout.
- Real-time claim amount validation.
- Maximum claim policy of **RM 200 per transaction**.
- New claim submission with receipt upload.
- Hardened receipt validation for PDF, JPG, and PNG files.
- Claim history with status filtering.
- Edit rejected or pending claims where allowed.
- Cancel pending claims.
- Profile update with department dropdown.

### Finance Portal

- Finance dashboard with claim statistics.
- View all submitted claims.
- Review claim details and receipt evidence.
- Approve claims with optional remarks.
- Reject claims with mandatory remarks.
- Process payment for approved claims.
- Automated email notification after claim decision and payment.
- Export claim reports to CSV or printable PDF view.

### Admin Portal

- Admin dashboard with user and claim summaries.
- Manage user accounts.
- Add Staff, Finance, and Admin accounts.
- Activate or deactivate user accounts.
- View user details.
- Manage all claims.
- Force-cancel claims when necessary.
- Generate user reports with role, department, and date filters.

## Security Features

- Passwords are stored using `password_hash()`.
- Login uses `password_verify()`.
- SQL queries use prepared statements with `bind_param()`.
- User output is escaped using `htmlspecialchars()`.
- CSRF tokens protect important forms and state-changing actions.
- Role-based access control prevents Staff, Finance, and Admin pages from being accessed by the wrong role.
- Receipt uploads are validated by server-side MIME detection.
- Receipt filenames are randomly generated.
- Upload folder includes Apache hardening through `.htaccess`.
- Public registration only creates Staff accounts.
- Admin and Finance accounts must be created by Admin.
- Email verification blocks unverified self-registered accounts from logging in.

## Minimum System Requirements

### For Online Access

- Modern web browser such as Chrome, Edge, or Firefox.
- Internet connection.
- Live hosted website URL.

### For Local Development

- Windows with XAMPP installed.
- Apache enabled in XAMPP.
- MySQL enabled in XAMPP.
- PHP 8 or above.
- Internet connection for CDN assets and email delivery.
- Gmail App Password or SMTP credentials if email features are tested.

## Online Hosting Deployment

The system has been deployed online using **InfinityFree** for web hosting and hosted MySQL database access.

| Item | Description |
|---|---|
| Hosting Provider | InfinityFree |
| Deployment Type | Online PHP and MySQL hosting |
| Live Demo URL | Replace this text with your InfinityFree website URL |
| Online Database | InfinityFree MySQL database created from the hosting control panel |

### InfinityFree Deployment Notes

1. Upload all project files to the InfinityFree `htdocs` directory using File Manager or FTP.
2. Create a MySQL database from the InfinityFree control panel.
3. Import the database SQL using the InfinityFree phpMyAdmin tool.
4. Update `db.php` with the online database host, database name, username, and password provided by InfinityFree.
5. Confirm that the `uploads/receipts` directory exists after upload.
6. Test login, registration, claim submission, Finance approval, payment processing, and Admin reports from the live URL.

Example InfinityFree database configuration format:

```php
$host     = "sqlXXX.infinityfree.com";
$user     = "if0_XXXXXXXX";
$password = "your_online_database_password";
$database = "if0_XXXXXXXX_utmspace_claim";
```

The exact values must be copied from the InfinityFree control panel because each hosting account receives different database credentials.

## Local Installation and Run Steps

### 1. Copy Project Folder

Place the project folder inside the XAMPP `htdocs` directory:

```text
C:\xampp\htdocs\finalproject\PROJECT_SEC43_GROUP16
```

### 2. Start XAMPP Services

Open XAMPP Control Panel and start:

- Apache
- MySQL

### 3. Create the Database

Open phpMyAdmin:

```text
http://localhost/phpmyadmin
```

Create and run the database setup SQL shown in the **Database Setup SQL** section below.

### 4. Confirm Database Connection

The database configuration is located in:

```text
db.php
```

Default local configuration:

```php
$host     = "localhost";
$user     = "root";
$password = "";
$database = "utmspace_claim";
```

### 5. Configure Email Sending

Open:

```text
mailer_helper.php
```

Update the SMTP placeholders with a real Gmail address and Gmail App Password:

```php
$mail->Username = 'YOUR_EMAIL@gmail.com';
$mail->Password = 'YOUR_16_CHAR_PASSWORD';
```

If SMTP is not configured, registration verification and email notifications may fail.

### 6. Open the System

Use this local URL:

```text
http://localhost/finalproject/PROJECT_SEC43_GROUP16/
```

## Default Login Credentials

Use these demo accounts after running the SQL setup script.

| Role | Email | Password |
|---|---|---|
| Admin | admin@utm.my | Password@123 |
| Finance | finance@utm.my | Password@123 |
| Staff | staff01@utm.my | Password@123 |

## Database Setup SQL

Run the following SQL in phpMyAdmin. If you already have important data in the same database, export a backup first because this script recreates the database.

```sql
DROP DATABASE IF EXISTS utmspace_claim;
CREATE DATABASE utmspace_claim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE utmspace_claim;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('staff', 'finance', 'admin') NOT NULL DEFAULT 'staff',
    department VARCHAR(100) NULL,
    phone VARCHAR(30) NULL,
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    email_verified TINYINT(1) NOT NULL DEFAULT 1,
    email_verification_token VARCHAR(128) NULL,
    email_verification_expires_at DATETIME NULL,
    reset_token VARCHAR(128) NULL,
    reset_token_expire DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    claim_type VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE NULL,
    description TEXT NOT NULL,
    receipt VARCHAR(255) NULL,
    status ENUM('Pending', 'Approved', 'Rejected', 'Paid', 'Cancelled') NOT NULL DEFAULT 'Pending',
    finance_comment TEXT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_claims_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users
(staff_id, name, email, password, role, department, phone, status, email_verified)
VALUES
('A001', 'Admin Boss', 'admin@utm.my', '$2y$10$P.MGXwItRXycZlJnUDb3ZetduabTNEStrMWneqQe0Jj3t8itZBZiu', 'admin', NULL, '0123456789', 'Active', 1),
('F001', 'Finance Officer', 'finance@utm.my', '$2y$10$P.MGXwItRXycZlJnUDb3ZetduabTNEStrMWneqQe0Jj3t8itZBZiu', 'finance', 'Finance', '0123456788', 'Active', 1),
('S001', 'Staff 01', 'staff01@utm.my', '$2y$10$P.MGXwItRXycZlJnUDb3ZetduabTNEStrMWneqQe0Jj3t8itZBZiu', 'staff', 'Human Resources', '0123456787', 'Active', 1);

INSERT INTO claims
(user_id, claim_type, amount, expense_date, description, receipt, status, finance_comment)
VALUES
(3, 'Travel', 120.00, CURDATE(), 'Demo travel claim for system testing.', NULL, 'Pending', NULL),
(3, 'Meal', 50.00, CURDATE(), 'Demo meal claim approved by Finance.', NULL, 'Approved', 'Approved for payment processing.'),
(3, 'Medical', 80.00, CURDATE(), 'Demo medical claim paid by Finance.', NULL, 'Paid', 'Payment processed successfully.');
```

## Folder Structure

```text
PROJECT_SEC43_GROUP16/
├── admin/                  Admin dashboard, account management, claim management, reports
├── finance/                Finance dashboard, claim approval, payment, export report
├── staff/                  Staff dashboard, new claim, claim history, edit profile
├── css/                    Stylesheets and images
├── uploads/receipts/       Uploaded receipt evidence files
├── vendor/                 PHPMailer source files
├── csrf_helper.php         Reusable CSRF protection helper
├── receipt_upload_helper.php Hardened receipt upload helper
├── mailer_helper.php       Central PHPMailer email helper
├── db.php                  Database connection and activity logging
├── login.php               Authentication page
├── register.php            Staff registration page
├── verify_email.php        Email verification page
├── forgot_password.php     Password reset request page
├── reset_password.php      Password reset page
└── index.php               Public landing page
```

## Basic Test Flow

### Staff Test

1. Login as Staff.
2. Submit a claim below RM 200.
3. Upload a valid PDF, JPG, or PNG receipt.
4. Try submitting more than RM 200 and confirm the system blocks it.
5. Open Claim History and confirm the new claim appears as Pending.

### Finance Test

1. Login as Finance.
2. Open the submitted claim.
3. Approve or reject the claim.
4. If approved, proceed to payment.
5. Confirm the claim status changes to Paid.

### Admin Test

1. Login as Admin.
2. Add a new user.
3. View user account details.
4. Deactivate and reactivate a user.
5. Open Manage Claims and view claim details.
6. Generate user reports using filters.

## Claim Status Meaning

| Status | Meaning |
|---|---|
| Pending | Staff has submitted the claim and Finance has not reviewed it yet. |
| Approved | Finance has approved the claim for payment. |
| Rejected | Finance has rejected the claim and provided a reason. |
| Paid | Finance has completed the payment process. |
| Cancelled | Staff or Admin has cancelled the claim. |

## Troubleshooting

### Database Connection Failed

Check that:

- MySQL is running in XAMPP.
- Database name is `utmspace_claim`.
- `db.php` uses the correct username and password.

### Email Verification Not Received

Check that:

- SMTP credentials in `mailer_helper.php` are correct.
- Gmail App Password is used instead of a normal Gmail password.
- Internet connection is available.
- The email is not in Spam or Junk.

### Login Blocked After Registration

Self-registered Staff accounts must verify email before login. Open the verification link sent to the registered email address.

### Receipt Upload Failed

Only these file types are accepted:

- PDF
- JPG / JPEG
- PNG

Maximum file size:

```text
5MB
```

### Styling or Icons Not Loading

The system uses CDN links for Bootstrap, Font Awesome, SweetAlert2, and other frontend assets. Make sure the computer has internet access during demo.

## Notes for Evaluators

This project can be demonstrated online through InfinityFree hosting and can also be installed locally using XAMPP for development or backup evaluation. The system includes security and validation controls commonly expected in a claim workflow, including prepared statements, password hashing, role access control, CSRF protection, file upload hardening, and email verification.

## Project Information

| Item | Description |
|---|---|
| Project Name | UTMSPACE Claim System |
| Course | DSPD2794 Project |
| Project Type | Web Application |
| Architecture | Native PHP 8, MySQLi, MySQL/MariaDB, Bootstrap 5 |
| Deployment Target | InfinityFree Online Hosting and Local XAMPP Environment |
