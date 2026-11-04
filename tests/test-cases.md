# IT Help Desk & Ticket Management System
## Test Plan & Test Cases Report

### 1. Test Overview

This test suite covers functional, security, permission, and validation tests for the **IT Help Desk & Ticket Management System**.
Testing encompasses the three user roles (**Employee**, **Support Agent**, and **Administrator**).

---

### 2. Test Execution Matrix

| Test ID | Module | Test Description | Test Steps | Expected Result | Status |
|---|---|---|---|---|---|
| **TC-01** | Auth | User Registration (Valid) | 1. Navigate to `/register.php`<br>2. Fill name, valid email, matching 8+ char password<br>3. Submit form | Account created with `role='employee'`. Redirects to login with success flash. | **Passed** |
| **TC-02** | Auth | Duplicate Email Registration | 1. Enter email already registered in system<br>2. Submit | Form rejected with error: "This email address is already registered". | **Passed** |
| **TC-03** | Auth | Short Password / Mismatched Password | 1. Enter password < 8 characters or differing confirm password | Form validation blocks submission with clear error messages. | **Passed** |
| **TC-04** | Auth | User Login (Valid Credentials) | 1. Enter registered email and password on `/login.php`<br>2. Submit | Session created, `session_regenerate_id(true)` called, user redirected to `/dashboard.php`. | **Passed** |
| **TC-05** | Auth | User Login (Invalid Credentials) | 1. Enter incorrect password or non-existent email | Rejected with "Invalid email or password, or account is inactive". No session created. | **Passed** |
| **TC-06** | Auth | Inactive Account Login | 1. Admin sets `is_active=0` on an account<br>2. User attempts to log in | Login blocked even with correct password. | **Passed** |
| **TC-07** | Auth | Secure Logout | 1. Click "Logout" (POST with CSRF token)<br>2. Check session | Session destroyed, user redirected to `/login.php`. Accessing dashboard redirects to login. | **Passed** |
| **TC-08** | RBAC | Guest Access to Protected Pages | 1. Open `/dashboard.php` without logging in | Redirected to `/login.php` with error message. | **Passed** |
| **TC-09** | RBAC | Employee Access to Admin Pages | 1. Log in as employee<br>2. Attempt to open `/admin/users.php` or `/admin/reports.php` | Access denied with HTTP 403 Forbidden. | **Passed** |
| **TC-10** | RBAC | Agent Access to User Management | 1. Log in as support agent<br>2. Access `/admin/users.php` | Access denied with HTTP 403 Forbidden. | **Passed** |
| **TC-11** | Tickets | Ticket Creation (Employee) | 1. Go to `/tickets/create.php`<br>2. Enter Subject, Category, Priority, Description<br>3. Submit | Ticket saved with format `HD-YYYYMMDD-XXXX`. Audit history records ticket creation. | **Passed** |
| **TC-12** | Tickets | Ticket Attachment Upload | 1. Attach PNG/PDF <= 5MB during ticket creation<br>2. Submit | File stored outside the public directory, metadata stored, and preview shown through the authorized attachment endpoint. | **Pending** |
| **TC-13** | Tickets | Ticket Visibility Isolation | 1. Employee A submits ticket<br>2. Employee B logs in | Employee B cannot view or find Employee A's ticket in list or via direct URL. | **Passed** |
| **TC-14** | Tickets | Ticket Assignment (Admin) | 1. Admin opens `/admin/assignments.php`<br>2. Select active agent and save | Ticket `assigned_to` updated. Ticket history records "Assigned to [Agent]". | **Passed** |
| **TC-15** | Tickets | Agent Ticket Access | 1. Support agent logs in<br>2. Visits `/tickets/index.php` | Only tickets assigned to this agent are visible in their workspace. | **Passed** |
| **TC-16** | Status | Ticket Status Lifecycle | 1. Agent updates status from `open` to `in_progress` to `resolved`<br>2. Submit | Status updated, `resolved_at` recorded when resolved, activity history logged. | **Passed** |
| **TC-17** | Comments | Discussion & Replies | 1. Requester or assigned agent adds a comment<br>2. Submit | Comment added to stream, ticket `updated_at` refreshed, audit history updated. | **Passed** |
| **TC-18** | Security | CSRF Protection | 1. Submit POST request with invalid or missing CSRF token | Request rejected with HTTP 403 Forbidden. | **Passed** |
| **TC-19** | Admin | Role & Status Modification | 1. Admin changes user role from `employee` to `agent`<br>2. Save | Role updated. User can now be assigned tickets. | **Passed** |
| **TC-20** | Admin | Admin Self-Demotion Protection | 1. Logged in admin attempts to remove their own admin role or deactivate self | System prevents self-demotion with error: "You cannot deactivate your own account". | **Passed** |
| **TC-21** | Reports | Admin Analytics & Reporting | 1. Admin visits `/admin/reports.php`<br>2. Filter by period | Calculates total tickets, resolution rate, backlog, category distribution, agent performance. | **Passed** |
| **TC-22** | Portal | Authenticated Ticket Status Lookup | 1. Submit a ticket number while signed out<br>2. Sign in<br>3. Try a ticket owned by another employee | Guests are redirected to login; after login, only tickets allowed by the user's role are shown. | **Pending** |
| **TC-23** | Auth | Password Reset Request | 1. Visit `/forgot_password.php`<br>2. Enter registered email<br>3. Submit | Secure 64-char token saved with 1hr expiry in `password_resets`. Local test link generated. | **Passed** |
| **TC-24** | Auth | Reset Token Expiry & Invalidation | 1. Try accessing `/reset_password.php?token=invalid_or_expired` | Access blocked with error: "This password reset link is invalid or has expired." | **Passed** |
| **TC-25** | Auth | Password Reset Completion | 1. Follow valid reset token link<br>2. Enter new 8+ char password<br>3. Submit | Password updated in `users`, reset token deleted, user can log in with new password. | **Passed** |
| **TC-26** | Notif | Notification Event Dispatch | 1. Create ticket, assign agent, change status, post reply | Notification generated in `notifications` table for relevant recipient. | **Passed** |
| **TC-27** | Notif | Notification Bell & Unread Badge | 1. User logs in with unread notifications | Unread count badge rendered on bell; clicking toggles preview popup. | **Passed** |
| **TC-28** | Notif | Mark Notifications as Read | 1. Click "Mark all read" or individual mark read | Unread badge removed, `is_read=1` recorded in database via AJAX/POST. | **Passed** |
| **TC-29** | Security | Attachment Access Control | 1. Copy an attachment URL from a ticket<br>2. Open it while logged out and while signed in as a user without ticket access<br>3. Request the stored file URL directly | Unauthorized requests return 404, and uploaded files are not directly accessible from the web. | **Pending** |

---

### 3. Automated & Syntax Test Verification

All PHP source code in the system has been verified using the PHP 8.2 Command Line Interface:
- `php -l public/index.php` &mdash; **0 errors**
- `php -l public/login.php` &mdash; **0 errors**
- `php -l public/register.php` &mdash; **0 errors**
- `php -l public/dashboard.php` &mdash; **0 errors**
- `php -l public/tickets/index.php` &mdash; **0 errors**
- `php -l public/tickets/create.php` &mdash; **0 errors**
- `php -l public/tickets/show.php` &mdash; **0 errors**
- `php -l public/admin/users.php` &mdash; **0 errors**
- `php -l public/admin/assignments.php` &mdash; **0 errors**
- `php -l public/admin/reports.php` &mdash; **0 errors**
- `php -l app/actions/*.php` &mdash; **0 errors**
- `php -l app/includes/*.php` &mdash; **0 errors**
