# Mash Academy — E-Learning Platform (Handover & Operations Guide)

**Stack:** PHP 8.2+ / Laravel 12 · MySQL / MariaDB (XAMPP) · Bootstrap 5 (RTL/LTR) · Chart.js · Vanilla JS  
**Design System:** Pure White (`#FFFFFF`) · Crisp Black (`#111111`) · Navy Blue (`#0A192F` / `#1E3A8A`) · Light/Dark Mode · Bilingual English (LTR) & Arabic (RTL)

---

## 1. Local XAMPP Setup & Installation

### Prerequisites
- **XAMPP** with Apache, MySQL/MariaDB, and PHP `>= 8.2` (`pdo_mysql`, `fileinfo`, `mbstring`, `openssl`, `zip`).
- **Composer** 2.x.

### Step-by-Step Setup
1. **Start MySQL** (and Apache if serving via virtual host) in **XAMPP Control Panel**.
2. **Create the MySQL database** (`mash_academy`) in phpMyAdmin or CLI:
   ```sql
   CREATE DATABASE IF NOT EXISTS mash_academy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. **Configure `.env`** (copy from `.env.example` if needed):
   ```dotenv
   APP_NAME="Mash Academy"
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://127.0.0.1:8000

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=mash_academy
   DB_USERNAME=root
   DB_PASSWORD=

   MAIL_MAILER=log
   MASH_MAX_VIDEO_UPLOAD_KB=512000
   ```
4. **Install dependencies, generate app key, link public storage, migrate & seed:**
   ```powershell
   composer install
   php artisan key:generate
   php artisan storage:link
   php artisan migrate:fresh --seed
   ```
5. **Run the local development server:**
   ```powershell
   php artisan serve
   ```
   Open **http://127.0.0.1:8000**.

---

## 2. Default Seeded Staff Accounts & Password Rotation

Exactly **4 staff accounts** exist in Mash Academy (enforced by both application logic and a unique `staff_role_key` database constraint).

| Role (`users.role`) | Email | Password |
|---|---|---|
| **Super Admin 1 (Owner)** | `shady.admin@mashacademy.com` | `Shady.admin@123` |
| **Super Admin 2 (Co-Owner)** | `abdullah.admin@mashacademy.com` | `Abdalluh.admin@123` |
| **Teacher Admin** | `mariam.teacher@mashacademy.com` | `Mariam.Teacher@123` |
| **Customer Support Admin** | `mohammed.support@mashacademy.com` | `Mohammed.Support@123` |

> **Mandatory Before Production:** Sign in to each of the 4 staff accounts and immediately rotate the temporary password via **Change Password** (`/account/password`), or reset Teacher/Support passwords from **Admin → Staff Management** (`/admin/staff`).

---

## 3. Role & Permission Matrix

| Capability | Super Admin 1 | Super Admin 2 | Teacher | Customer Support | Student |
|---|:---:|:---:|:---:|:---:|:---:|
| Academy info, bilingual Vision (EN/AR), Logo, Policy, InstaPay QR | Full | Full | Denied (403) | Denied (403) | Denied (403) |
| Courses, Videos, Quizzes, Flexible Assignments, Final Projects | All Courses | All Courses | **Own Only** | Denied (403) | Denied (403) |
| Delete Courses | Any | Any | **Own Only** (never Super Admin courses) | Denied (403) | Denied (403) |
| Promo Codes, Payments Review & Time-Limited Enrollments | Full | Full | Denied (403) | Denied (403) | Own Checkout/Payments |
| Progress Analytics & Student Directory | Full | Full | Denied (403) | **Read-Only** | Own Only |
| Certificates Registry | Full (Verify/Revoke/Reinstate) | Full (Verify/Revoke/Reinstate) | Denied (403) | **Read-Only** | Own Earned Certificates |
| Delete Teacher / Support Accounts | Allowed | Allowed | Denied (403) | Denied (403) | Denied (403) |
| Delete Super Admin 1 or Super Admin 2 | **Blocked (403)** | **Blocked (403)** | Denied (403) | Denied (403) | Denied (403) |

---

## 4. Video Upload Limits (`php.ini` on XAMPP)

Course videos are stored privately in `storage/app/private` and streamed only via HTTP Range-enabled authenticated controllers. To support large MP4/WebM uploads locally, edit `C:\xampp\php\php.ini`:

```ini
upload_max_filesize = 512M
post_max_size = 520M
max_execution_time = 300
memory_limit = 512M
```
Restart Apache / `php artisan serve` after saving `php.ini`.

---

## 5. Automatic Enrollment Expiration (Windows Task Scheduler)

Access is revoked **immediately at request time** via `CourseAccess` middleware (`starts_at <= now() <= expires_at`), and `php artisan enrollments:expire` updates overdue rows to `expired` every minute via Laravel Scheduler.

### Windows Task Scheduler Setup (XAMPP)
1. Open **Task Scheduler** (`taskschd.msc`) → **Create Basic Task** → Name: `Mash Academy Scheduler`.
2. Trigger: **Daily** → Edit trigger → check **Repeat task every: `1 minute`** for duration **Indefinitely**.
3. Action: **Start a program**:
   - **Program/script:** `C:\xampp\php\php.exe`
   - **Add arguments:** `artisan schedule:run`
   - **Start in:** `C:\Users\S3ODI STORE\OneDrive\Desktop\Mash-Academy-Website\Mash-Academy`

Manual CLI execution:
```powershell
php artisan enrollments:expire
php artisan schedule:list
```

---

## 6. Core Learning & Business Rules
- **Policy Gate:** Students cannot register without ticking the mandatory agreement to the active versioned Platform Policy (`policy_acceptances` audit trail).
- **100% Sequential Drip-Lock:** Video 1 is open; Video *N* remains strictly locked server-side until Video *N-1* is watched **100% from start to finish** (triggered only on video end with server-side duration & anti-skip validation) and any required quiz is passed.
- **Flexible Assignments:** Support Written/File Upload, Interactive MCQ, or Both Combined.
- **95% Final Project Gate:** The per-course Final Project stays strictly locked server-side until the student reaches **≥ 95% overall course completion**.
- **Certificates:** Remain **100% locked** until the student satisfies course requirements AND submits their Final Project, then unlock a printable/downloadable certificate with a unique verification code (`/certificates/verify`).

---

## 7. Production Hardening & Backup Checklist
- Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`.
- Enforce HTTPS (`APP_URL=https://...`, `SESSION_SECURE_COOKIE=true`).
- Rotate all 4 seeded staff passwords immediately.
- Configure real SMTP credentials (`MAIL_MAILER=smtp`) for password resets.
- Ensure `storage/app/private` is never web-accessible (only `public/` is the web document root).
- Schedule daily MySQL backups (`mysqldump -u root mash_academy > backup.sql`) and backups of `storage/app/private` & `storage/app/public`.

---

## 8. Running the Automated QA Test Suite
```powershell
php artisan test
```
Runs all Unit, Feature, Authorization, Payment/Expiry, 100% Drip-Lock, 95% Final Project Gate, Certificate, Analytics/N+1, and full End-to-End tests.
