# 🎧 HelpDesk Pro &mdash; IT Help Desk & Ticket Management System

> A modern, secure, and responsive web-based IT Service Management (ITSM) and Support Ticket system developed with PHP 8, MySQL, and modern Vanilla CSS.

---

## 📌 Project Overview

In organizational IT environments, employees frequently encounter technical problems involving computer hardware, software installations, network connectivity, and account privileges. Managing these issues through manual emails or spreadsheets leads to lost requests, delayed resolutions, and a lack of accountability.

**HelpDesk Pro** provides a centralized, role-based platform that allows:
1. **Employees** to submit support requests, track ticket progress, and communicate directly with IT staff.
2. **Support Agents** to manage assigned tickets, update resolution stages, and log troubleshooting steps.
3. **Administrators** to oversee the entire IT support operation, assign tickets, manage staff permissions, and review analytics reports.

---

## 🚀 Key Features

### 1. 🌐 Public Support Portal & Quick Status Tracker
- **No-login Ticket Tracker:** Employees and stakeholders can check the status, category, priority, and submission date of any ticket using just the reference number (e.g. `HD-20261102-AB12CD`).
- Quick links to staff login and employee account registration.

### 2. 🔐 Authentication & Role-Based Access Control (RBAC)
- Secure session-based authentication with `session_regenerate_id(true)`.
- Passwords hashed using industry-standard `password_hash()` (BCrypt/Argon2).
- **Self-Service Password Reset:** Secure token-based password reset workflow with 1-hour expiration and single-use invalidation. Includes a local XAMPP testing assistant so reset links can be tested without configuring external SMTP mail servers.
- CSRF token verification on all protected POST actions.
- Granular permissions enforced at the controller and view levels for:
  - **Employee:** Can only create and view their own tickets.
  - **Support Agent:** Can view and manage tickets assigned to them.
  - **Administrator:** Full system access (All tickets, assignments, users, and reports).

### 3. 🎫 End-to-End Ticket Lifecycle Management
- **Automatic Reference Generation:** Generates unique tracking numbers in `HD-YYYYMMDD-HEX` format.
- **Categorization:** Hardware, Software, Network, Account Access, and Other.
- **Priority Levels:** Critical (with pulsating visual alert), High, Medium, and Low.
- **Status Workflow:** `Open` &rarr; `In Progress` &rarr; `Pending` &rarr; `Resolved` &rarr; `Closed`.
- **Automatic Resolution Timestamps:** Automatically captures `resolved_at` upon resolution.

### 4. 📎 File & Screenshot Attachments
- Supports uploading screenshots and error logs (`.png`, `.jpg`, `.jpeg`, `.pdf`, `.txt`, `.docx`) up to 5MB during ticket creation and in comment replies.
- Secure upload handling with randomized filenames and directory traversal protection.

### 5. 💬 Discussion Stream & Activity Timeline
- **Chat-Style Discussion:** Displays replies in conversation bubbles distinguishing between requester and IT staff.
- **Audit Timeline:** Complete activity log tracking who made changes, old vs. new values, and timestamps.

### 6. 📊 Admin Reports & Analytics Suite
- **KPI Metrics:** Total ticket volume, overall resolution rate %, backlog count, and average resolution time in hours.
- **Visual Distribution Bars:** Breakdown by status, priority, and technical categories.
- **Agent Performance Leaderboard:** Tracks assigned vs. resolved tickets and resolution rates for each agent.
- **One-Click Export & Print:** Print-friendly view and CSV export for management audits.

---

## 🛠️ Technology Stack

| Component | Technology | Description |
|---|---|---|
| **Frontend** | HTML5, Vanilla CSS3, JavaScript (ES6) | Responsive layout, modern design tokens, interactive alerts, live filtering |
| **Backend** | PHP 8.2+ | Server-side logic, session management, RBAC, PDO prepared statements |
| **Database** | MySQL (InnoDB) | Relational database with foreign key constraints, UTF8mb4 encoding |
| **Environment**| XAMPP / Apache | Local web server and database hosting |

---

## 📁 Project Directory Structure

```text
helpdesk-system/
├── app/
│   ├── actions/
│   │   ├── comment_create.php         # Handles comment posting & attachments
│   │   ├── login_action.php           # User authentication & session init
│   │   ├── password_reset_request.php # Token generation & reset link dispatch
│   │   ├── password_reset_submit.php  # Token validation & password update
│   │   ├── ticket_create.php          # Ticket submission & file upload
│   │   └── ticket_update.php          # Status updating & audit history logging
│   ├── config/
│   │   └── database.php               # PDO database connection configuration
│   ├── includes/
│   │   ├── auth.php                   # requireLogin() and requireRole() guards
│   │   ├── footer.php                 # Global footer & scripts
│   │   ├── functions.php              # Badges, CSRF, flash notifications, avatars
│   │   └── header.php                 # Global navigation & user profile bar
│   └── .htaccess                      # Blocks direct HTTP access to internal PHP files
│
├── database/
│   └── schema.sql                     # Complete database schema and seed data
│
├── public/
│   ├── admin/
│   │   ├── assignments.php            # Ticket assignment to support agents
│   │   ├── reports.php                # Analytics, KPIs, and agent leaderboard
│   │   └── users.php                  # User role & account status management
│   ├── assets/
│   │   ├── css/
│   │   │   └── style.css              # Master modern SaaS design system
│   │   └── js/
│   │       └── app.js                 # Live search, copy button, auto-alerts
│   ├── actions/                       # Public entry points for protected app actions
│   ├── tickets/
│   │   ├── create.php                 # New ticket submission form
│   │   ├── index.php                  # Ticket list with filters & live search
│   │   └── show.php                   # Ticket details, discussion, and timeline
│   ├── attachment.php                # Authorized attachment delivery
│   ├── uploads/                       # Legacy upload area; direct access is blocked
│   ├── dashboard.php                  # Role-specific user dashboard
│   ├── forgot_password.php            # Request password reset page
│   ├── index.php                      # Public landing page & ticket lookup
│   ├── login.php                      # Authentication page (with forgot pw link)
│   ├── logout.php                     # Secure logout handler
│   ├── register.php                   # Employee registration page
│   └── reset_password.php             # New password submission page
│
├── storage/
│   └── ticket-uploads/                # Private ticket attachment storage
├── tests/
│   └── test-cases.md                  # Comprehensive test matrix and QA plan
├── .gitignore
└── README.md
```

---

## ⚙️ Installation & Setup on XAMPP

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) with PHP 8.1+ and MySQL.
- Web browser (Google Chrome, Mozilla Firefox, Microsoft Edge).

### Step 1: Clone or Copy Repository
Place the project folder into your XAMPP `htdocs` directory:
```text
C:\xampp\htdocs\helpdesk-system
```

### Step 2: Start Apache & MySQL
1. Open the **XAMPP Control Panel**.
2. Click **Start** for **Apache**.
3. Click **Start** for **MySQL**.

### Step 3: Import Database Schema
1. Open your browser and navigate to: `http://localhost/phpmyadmin/`
2. Create a new database named: `helpdesk_db` (Collation: `utf8mb4_unicode_ci`).
3. Click on the `helpdesk_db` database, go to the **Import** tab.
4. Select the file: `c:\xampp\htdocs\helpdesk-system\database\schema.sql` and click **Import**.

### Step 4: Configure Database Settings
If you have a customized MySQL password, update `app/config/database.php`:
```php
$host = '127.0.0.1';
$dbname = 'helpdesk_db';
$username = 'root';
$password = ''; // Your XAMPP MySQL password (default is empty)
```

### Step 5: Launch the Application
Open your browser and visit:
```text
http://localhost/helpdesk-system/public/
```

### Production Web Server and Uploads

- Configure the web server's document root to the `public/` directory, not the repository root. Preserve or configure the application's URL base (`/helpdesk-system/public`) to match the deployment.
- The public action entry points under `public/actions/` include their implementation from `app/`. Keep `app/`, `database/`, `tests/`, and `storage/` outside the document root where possible.
- Apache deployments should honor the included `.htaccess` access restrictions. Nginx and other servers must be configured with equivalent rules; `.htaccess` is Apache-specific.
- New attachments are stored in `storage/ticket-uploads/` and are delivered through an authenticated endpoint. Existing files in `public/uploads/tickets/` should remain blocked from direct web access; the application can still serve them to users authorized to view the ticket.
- Ensure the PHP/web-server account can write to `storage/ticket-uploads/`. Do not include runtime uploads in release packages or source control.

---

## 👥 Default User Roles & Setup

1. **Registering an Account:**
   - Navigate to `http://localhost/helpdesk-system/public/register.php` and create an account.
   - Newly registered users automatically receive the **Employee** role.

2. **Promoting to Administrator or Agent:**
   - In `phpMyAdmin` under the `users` table, set your user's `role` column to `'admin'` or `'agent'`.
   - Once logged in as an Administrator, you can promote any user directly via the **Manage Users** page (`/public/admin/users.php`).

---

## 🔒 Security Highlights

- **SQL Injection Prevention:** 100% prepared statements across all database queries.
- **Cross-Site Scripting (XSS):** All dynamic outputs sanitized via `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`.
- **Cross-Site Request Forgery (CSRF):** Cryptographically secure tokens validated on all mutating requests.
- **Session Security:** Session IDs regenerated on login (`session_regenerate_id(true)`) and strict role guards on all admin endpoints.
- **Admin Self-Demotion Lock:** The last active administrator cannot be deactivated or demoted to prevent system lockout.

---

## 📄 License
This project is open-source under the MIT License.
