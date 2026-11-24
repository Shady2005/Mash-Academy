# Mash Academy — Development Execution Plan

**Stack:** PHP / Laravel · MySQL (XAMPP, local) · HTML5, CSS3, Bootstrap 5, JavaScript
**Developer / Executor:** Andy Craft
**Client / Approver:** Project Owner

> **No application code is written in this document.** This is the plan only.

---

## 0. Governing Workflow Rules

1. **Phase-Gated Execution.** The project is split into **5 sequential phases**. Every phase ends with a mandatory **Testing & Verification Gate**:
   - **Gate A – Developer/automated tests** (PHPUnit feature/unit tests, migrations run clean, seeders work).
   - **Gate B – Manual User Acceptance Testing (UAT)** by the owner on the local XAMPP server (`http://localhost/...` or `php artisan serve`).
2. **Approval Lock.** At the end of each phase, Andy Craft **MUST STOP**, then:
   - Deliver a phase report (what was built, files/migrations added, how to run it).
   - Provide step-by-step **manual testing instructions** and test credentials.
   - **Wait for the owner's explicit written approval** (e.g. "Phase 2 approved") before starting the next phase.
   - Never start, scaffold, or "prepare" any part of a later phase early.
3. **Failed gate = fix, not skip.** If any checklist item fails, Andy Craft fixes it, re-runs the gate, and asks for approval again.
4. **Definition of Done for each phase:** all checklist items ticked, tests green, owner approval received.

---

## 1. System Architecture & Database Schema Overview

### 1.1 Architecture

| Layer | Choice | Notes |
|---|---|---|
| Framework | Laravel (current LTS-supported release compatible with the installed XAMPP PHP version) | MVC, Eloquent ORM, Blade views |
| Database | MySQL / MariaDB via XAMPP | `utf8mb4`, InnoDB, foreign keys enforced |
| Frontend | Blade + Bootstrap 5 + vanilla JS | Strict **White (`#FFFFFF`) / Crisp Black (`#111111`) / Navy Blue (`#0A192F` / `#1E3A8A`)** palette, **Light/Dark Mode toggle** (persisted per session/cookie), and **Bilingual Arabic (RTL) & English (LTR)** switcher |
| Auth | Laravel session auth (Breeze-style or custom) | Roles stored on `users.role` |
| Authorization | Gates & Policies + `role` middleware | Hierarchy rules enforced server-side, never just by hiding buttons |
| File storage | `storage/app/private` (videos, assignment files, payment proofs) and `storage/app/public` (logo, QR, thumbnails) | `php artisan storage:link` |
| Video delivery | Controller-streamed private files with HTTP Range support | Prevents direct URL sharing; checks enrollment + lock status on every request |
| Scheduling | Laravel Scheduler (`php artisan schedule:run` every minute via Windows Task Scheduler on XAMPP) | Auto-expiry of enrollments |
| Testing | PHPUnit / Pest (feature + unit), manual UAT checklists | SQLite in-memory or a dedicated `mash_academy_test` MySQL DB |

### 1.2 Roles & Permission Matrix

Roles (`users.role` enum): `super_admin_1`, `super_admin_2`, `teacher`, `support`, `student`.

| Capability | Super Admin 1 | Super Admin 2 | Teacher | Support | Student |
|---|:---:|:---:|:---:|:---:|:---:|
| Edit academy info / bilingual vision / logo / policy / QR | ✅ | ✅ | ❌ | ❌ | ❌ |
| Create courses, upload videos, quizzes, flexible assignments, final projects | ✅ | ✅ | ✅ | ❌ | ❌ |
| Edit own courses / quizzes / assignments / final projects | ✅ | ✅ | ✅ (own only) | ❌ | ❌ |
| Delete a course | ✅ any | ✅ any | ✅ **own only** (never Super Admin courses) | ❌ | ❌ |
| Manage Promo Codes (create/edit/toggle/delete) | ✅ | ✅ | ❌ | ❌ | ❌ |
| Review payments & manage enrollments | ✅ | ✅ | ❌ | ❌ | ❌ |
| Manage / verify student certificates | ✅ | ✅ | ❌ | ✅ read-only | own only |
| View student accounts / contact / enrollment status | ✅ | ✅ | ❌ | ✅ read-only | ❌ |
| View progress analytics | ✅ | ✅ | ❌ (own-course view optional, only if approved) | ✅ read-only | own only |
| Delete Teacher / Support accounts | ✅ | ✅ | ❌ | ❌ | ❌ |
| Delete Super Admin 1 | ❌ | ❌ | ❌ | ❌ | ❌ |
| Delete Super Admin 2 | ❌ | ❌ | ❌ | ❌ | ❌ |
| Browse courses, subscribe, learn | – | – | – | – | ✅ |

> **Hierarchy rule (enforced in a Policy + tested):** Neither Super Admin can delete the other, nor themselves. Exactly **4 staff accounts** exist (seeded); the UI offers no way to create extra staff roles, and a DB-level/application guard prevents a second account holding any staff role.

### 1.3 MySQL Tables & Relationships

| # | Table | Key columns | Relationships |
|---|---|---|---|
| 1 | `users` | id, name, email (unique), phone, password, `role`, is_active, email_verified_at, last_login_at, timestamps, soft deletes | has many enrollments, payment_requests, progress, attempts, submissions |
| 2 | `academy_settings` | id, key, value (academy_name, academy_name_ar, tagline, tagline_ar, about, about_ar, vision, vision_ar, logo_path, instapay_qr_path, contact_email, contact_phone, social links) | key-value (bilingual EN/AR) |
| 3 | `policies` | id, title, body (long text), version, is_active, updated_by (FK users), timestamps | has many policy_acceptances |
| 4 | `policy_acceptances` | id, user_id (FK), policy_id (FK), policy_version, accepted_at, ip_address | belongs to user & policy (audit proof of consent) |
| 5 | `courses` | id, title, title_ar, slug, short_description, short_description_ar, description, description_ar, price, currency, thumbnail_path, `created_by` (FK users), `is_featured`, `featured_order`, is_published, timestamps, soft deletes | has many lessons, quizzes, assignments; has one final_project; created by user |
| 6 | `lessons` | id, course_id (FK), title, description, `position` (order), video_path, video_mime, duration_seconds, is_published, timestamps | belongs to course; has many progress rows, quizzes, assignments |
| 7 | `lesson_progress` | id, user_id, lesson_id, watched_seconds, last_position_seconds, `completed_at`, timestamps; unique(user_id, lesson_id) | drives drip-lock, 95% gate, and analytics |
| 8 | `quizzes` | id, course_id, lesson_id (nullable), title, pass_mark, `is_required_for_next` (bool), created_by | has many questions, attempts |
| 9 | `quiz_questions` | id, quiz_id, question_text, type, position, points | has many options |
| 10 | `quiz_options` | id, question_id, option_text, is_correct | belongs to question |
| 11 | `quiz_attempts` | id, quiz_id, user_id, score, max_score, percentage, passed, started_at, submitted_at | belongs to quiz & user |
| 12 | `quiz_attempt_answers` | id, attempt_id, question_id, option_id, is_correct | belongs to attempt |
| 13 | `assignments` | id, course_id, lesson_id (nullable), title, `submission_type` (`file`/`mcq`/`both`), instructions (nullable), pass_mark (nullable), due_at (nullable), created_by | has many assignment_questions & submissions |
| 14 | `assignment_questions` | id, assignment_id (FK), question_text, position, points | has many assignment_options (used for `mcq` / `both`) |
| 15 | `assignment_options` | id, question_id (FK), option_text, is_correct | belongs to assignment_question |
| 16 | `assignment_submissions` | id, assignment_id, user_id, file_path (nullable), notes (nullable), mcq_answers (JSON nullable), mcq_score (nullable), mcq_max_score (nullable), mcq_percentage (nullable), status (`pending`/`submitted`/`reviewed`), grade (nullable), feedback (nullable), submitted_at | belongs to assignment & user |
| 17 | `final_projects` | id, course_id (FK unique), title, instructions, attachment_path (nullable), `required_completion_percentage` (default 95), is_published, created_by, timestamps | belongs to course; has many final_project_submissions |
| 18 | `final_project_submissions` | id, final_project_id (FK), user_id (FK), file_path, notes, status (`submitted`/`reviewed`), grade (nullable), feedback (nullable), submitted_at, reviewed_at | unlocks student certificate |
| 19 | `certificates` | id, user_id (FK), course_id (FK), final_project_submission_id (FK), `certificate_code` (unique), issued_at, revoked_at (nullable), timestamps; unique(user_id, course_id) | issued ONLY after Final Project submission/completion |
| 20 | `promo_codes` | id, code (unique), discount_type (`percentage`/`fixed`), discount_value, max_uses (nullable), used_count (default 0), expires_at (nullable), is_active, created_by (FK users), timestamps | applied during student subscription checkout |
| 21 | `payment_requests` | id, user_id, course_id, promo_code_id (nullable FK), promo_code_snapshot (nullable), original_price, discount_amount, final_price, name, email, phone, screenshot_path, status (`pending`/`approved`/`rejected`), admin_note, reviewed_by (FK users), reviewed_at, timestamps | belongs to user, course & optional promo_code |
| 22 | `enrollments` | id, user_id, course_id, payment_request_id (nullable), `starts_at`, `expires_at`, duration_value, duration_unit (`days`/`months`), status (`active`/`expired`/`revoked`), created_by (FK users), timestamps; index(user_id, course_id, status, expires_at) | belongs to user & course |
| 23 | `activity_logs` | id, actor_id, action, subject_type, subject_id, meta (JSON), ip, created_at | audit trail (enrollments, deletions, policy/QR/promo/certificate changes) |
| 24 | Laravel defaults | `sessions`, `password_reset_tokens`, `jobs`, `failed_jobs`, `cache`, `migrations` | framework |

**Key relationship summary**

- `users 1—N enrollments N—1 courses`
- `courses 1—N lessons 1—N lesson_progress N—1 users`
- `courses/lessons 1—N quizzes 1—N quiz_questions 1—N quiz_options`; `quizzes 1—N quiz_attempts N—1 users`
- `courses/lessons 1—N assignments 1—N assignment_submissions N—1 users`
- `users 1—N payment_requests N—1 courses`; `payment_requests 1—0..1 enrollments`
- `users 1—N policy_acceptances N—1 policies`

### 1.4 Core Business Logic (specified once, applied in phases)

**A. Course access rule**
A student may access a course **only if** an enrollment exists with `status = active` **and** `starts_at <= now() <= expires_at`. This is checked **at request time** by a central service/middleware (so access is revoked the instant it expires, even if the scheduler hasn't run), and the scheduler additionally flips `status` to `expired` for cleanliness and analytics.

**B. Sequential drip-lock rule**
For an accessible course, lessons are ordered by `position`.
- Lesson 1 is unlocked.
- Lesson *N* is unlocked **only if** Lesson *N‑1* has `completed_at` set for that student (and, if `is_required_for_next` is set on its quiz, the quiz is passed).
- Enforced **server-side** on every video-stream and lesson-page request (direct URL guessing returns 403).

**C. Completion definition (100% Full Watch)**
A video counts as "completed" **only** when the student has watched **100% of the entire video from start to finish** — triggered only upon reaching the actual end of the video (`ended` event + full duration reached) with strict server-side duration and anti-skip plausibility validation (watched time cannot exceed elapsed real time, and seeking past unwatched segments is blocked) before unlocking the next video (even when multiple videos exist per lesson/module).

**D. Auto-expiration**
`php artisan enrollments:expire` runs via the Laravel Scheduler (every minute/hour) and sets `status = expired` where `expires_at < now()`. Re-subscribing creates a new enrollment (or extends the existing one) — the behavior is confirmed with the owner in Phase 3. Existing progress is **kept** after expiry, so renewing students resume where they left off.

**E. Final Project (≥ 95% Gate) & Certificates**
Each course may configure a dedicated **Final Project**. On the student side (Phase 4), the Final Project remains **strictly locked server-side until the student completes ≥ 95%** of the course (videos + required quizzes/assignments). A student's **Completion Certificate** ("My Certificates") remains **100% locked** until the Final Project is submitted/completed, after which a uniquely coded certificate is generated and downloadable (and verifiable/manageable by Super Admins in Phase 4 & 5).

**F. Promo Codes & Discounted Checkout**
Super Admins (1 & 2) manage **Promo Codes** (code, percentage or fixed discount, expiry date, usage limit, active toggle). On the "Subscribe to Course" page (Phase 3), students can validate and apply a promo code to preview the discounted price before transferring via InstaPay; the applied promo code, original price, discount amount, and final payable amount are stored on `payment_requests`.

---

## 2. Five-Phase Execution Roadmap

> ⛔ **Reminder for every phase:** Andy Craft stops at the end of the phase, delivers testing instructions, and **waits for the owner's explicit approval**.

---

### PHASE 1 — Foundation, Branding, Authentication, Policy Gate & Role System

**Goals**
- Set up the Laravel project on XAMPP and connect it to MySQL.
- Establish the base layout, branding, and design system (Bootstrap).
- Build student registration/login with the mandatory Policy agreement.
- Build the 4-account staff system and the role/permission infrastructure.
- Build the admin dashboard shell and Settings (Academy info, logo, Policy text).

**Database Tables / Migrations**
`users` (with `role`, `phone`, `is_active`), `academy_settings`, `policies`, `policy_acceptances`, `activity_logs`, Laravel defaults (`sessions`, `password_reset_tokens`, `jobs`, `failed_jobs`, `cache`).
Seeders: **4 staff accounts** (Super Admin 1, Super Admin 2, Teacher, Customer Support) with temporary passwords to be changed on first login; default academy settings; default Policy v1.

**Backend Deliverables**
- Laravel installation, `.env` for XAMPP MySQL, database creation instructions, `storage:link`.
- Auth: register, login, logout, forgot/reset password, session handling, throttling on login.
- **Policy Gate:** registration form loads the active Policy; server-side validation rejects sign-up unless `agree_policy` is checked; a `policy_acceptances` record (user, policy version, timestamp, IP) is stored.
- `role` middleware + Gates/Policies encoding the permission matrix (including the "Super Admins can't delete each other / can delete Teacher & Support" rule and the "exactly 4 staff accounts" guard).
- Admin **Staff management** screen (Super Admins only): view the staff list, reset passwords, delete Teacher/Support per the hierarchy rules.
- Settings controllers: academy name, tagline, vision/about, contact info, **logo upload**, **Policy editor** (creates a new version, keeps history).
- Activity logging for sensitive actions.

**Frontend Deliverables**
- Master Blade layouts: public layout, student layout, admin layout (sidebar changes by role).
- Dynamic header showing the **Mash Academy logo + academy name** from settings.
- Pages: Login, Register (with Policy viewer + required checkbox, client-side + server-side validation), Forgot Password, minimal Student dashboard placeholder, Admin dashboard shell with role-aware menu, Settings pages.
- Responsive Bootstrap theme, form validation UX, flash messages, error pages (403/404/419/500).

**Phase 1 Exit & Testing Checklist**

*Developer / automated (Gate A)*
- [ ] `php artisan migrate:fresh --seed` runs with zero errors.
- [ ] Feature tests: registration fails without the Policy checkbox; succeeds with it and stores a `policy_acceptances` row.
- [ ] Feature tests: each role reaches only the routes allowed by the matrix (403 otherwise).
- [ ] Policy tests: SA1 cannot delete SA2 (and vice versa); SA1/SA2 can delete Teacher & Support; Teacher/Support/Student cannot delete anyone.
- [ ] Test: only 4 staff accounts can exist; a student can never be promoted via request tampering (mass-assignment protection on `role`).
- [ ] Password hashing, CSRF protection, and login throttling verified.

*Manual UAT on XAMPP (Gate B)*
- [ ] Start Apache + MySQL in XAMPP; the site loads locally.
- [ ] Log in as each of the 4 staff accounts; each sees the correct menu.
- [ ] Change the academy name/logo in Settings → the header updates site-wide.
- [ ] Edit the Policy text → the new text appears on the Register page.
- [ ] Try to register **without** ticking "I agree to the Policy" → blocked with a clear message.
- [ ] Register a student with the tick → account created, login works, logout works.
- [ ] Attempt forbidden URLs as Student/Support/Teacher → 403 page.
- [ ] Owner confirms layout, colors, and logo placement.

🛑 **STOP — deliver the Phase 1 report and testing steps. Wait for explicit approval of Phase 1.**

---

### PHASE 2 — Course Management, Content Upload, Flexible Assessments, Final Project & Bilingual/Dark-Mode Home Page

**Goals**
- Upgrade base layouts to the strict **White (`#FFFFFF`) / Crisp Black (`#111111`) / Navy Blue (`#0A192F` / `#1E3A8A`)** palette, **Light/Dark Mode toggle**, **Bilingual Arabic (RTL) & English (LTR) switcher**, and **Bilingual Vision (EN & AR)** editor in Admin Settings.
- Let staff create and manage courses, lessons (videos), standalone quizzes, **flexible assignments (File Upload, Interactive MCQ, or Both)**, and a dedicated **Final Project** per course.
- Build the bilingual/dark-mode-ready public landing page featuring the dynamic Academy Vision and the **3 featured courses**.
- Enforce the Teacher/Super Admin ownership & deletion rules.

**Database Tables / Migrations**
`courses`, `lessons`, `quizzes`, `quiz_questions`, `quiz_options`, `assignments` (with `submission_type`: `file`/`mcq`/`both`), `assignment_questions`, `assignment_options`, `final_projects`. (`lesson_progress`, `quiz_attempts`, `assignment_submissions`, `final_project_submissions`, `certificates`, `promo_codes`, `payment_requests`, and `enrollments` are **not** created yet, so Phase 2 stays focused on content authoring and branding upgrades.)
Seeders: demo courses (clearly marked as demo data) for testing.

**Backend Deliverables**
- **Theme, Bilingual AR/EN & Bilingual Vision:** `SetLocaleAndTheme` middleware + preference switcher endpoints (`/preferences/locale`, `/preferences/theme`), Laravel `lang/en` & `lang/ar` translation files, Bootstrap RTL/LTR dynamic switching, and EN/AR Vision & About fields in Academy Settings.
- **Course CRUD**: title (EN/AR), short description & description (EN/AR), price (EGP), thumbnail, published flag, **featured flag + order** (max 3 featured on the home page).
- **Lesson/Video management**: upload videos directly to private storage (MIME validation: mp4/webm, size limit configured in `php.ini` — `upload_max_filesize`, `post_max_size`, `max_execution_time`), set lesson order (up/down), edit, delete, store duration.
- **Quiz builder**: create standalone quizzes linked to a lesson or a course, add multiple-choice questions/options, mark correct answers, set pass mark and `is_required_for_next`.
- **Flexible Assignment builder**: title, `submission_type` (`file` | `mcq` | `both`), written instructions, optional due date, pass mark (for MCQ), and dynamic MCQ question/option builder when `mcq` or `both` is selected.
- **Final Project configuration**: dedicated per-course Final Project (`title`, `instructions`, optional private reference attachment, `required_completion_percentage` default 95%, published flag).
- **Ownership rules via Policies:**
  - Teacher: create courses; edit/delete **only own** courses and their content (lessons, quizzes, assignments, final project); **cannot delete courses created by Super Admin 1/2**; no access to settings/staff/payments/enrollments.
  - Super Admins: manage all courses and content.
  - Support: no content management (403 on all course management routes).
- Private storage for videos & project attachments; secure admin-only HTTP Range preview streaming route.
- Activity log entries for create/update/delete actions.

**Frontend Deliverables**
- Updated Light/Dark Mode toggle + AR/EN language switcher in Public, Student, and Admin layouts using the strict White / Black / Navy Blue palette.
- Admin pages: Course list (filtered by role), Course create/edit form, Lesson manager with video upload progress bar and preview player, Quiz builder UI, Flexible Assignment builder UI (File / MCQ / Both), Final Project configuration UI, delete confirmation modals.
- **Public Home Page:** hero section, dynamic **Academy About & Vision** (EN/AR), **3 featured courses** as cards (title, description, price, "View / Subscribe" button), footer with contact info.
- Public Course catalog & Course details page (curriculum outline visible, videos shown as locked, Final Project 95% unlock & Certificate eligibility shown).
- Student "Browse Courses" page (all published courses shown as **locked** by default).

**Phase 2 Exit & Testing Checklist**

*Developer / automated (Gate A)*
- [ ] Migrations run cleanly on top of Phase 1 (`migrate:fresh --seed` green).
- [ ] Tests: AR/EN locale switching (`dir="rtl"` / `dir="ltr"`) and Light/Dark theme persistence work; bilingual Vision updates reflect on Home Page.
- [ ] Tests: Teacher can edit/delete own course; **cannot** edit/delete a course created by SA1/SA2 (403).
- [ ] Tests: Super Admins can edit/delete any course; Support/Student cannot access course management (403).
- [ ] Tests: video upload validation rejects non-video files and oversized files with clear errors; admin preview stream serves private video with Range support and blocks unauthorized roles.
- [ ] Tests: flexible assignments validate and store `file`, `mcq`, and `both` types with MCQ questions/options; Final Project CRUD works per course with 95% default gate.
- [ ] Tests: only published courses appear publicly; max 3 featured enforced.
- [ ] Deleting a course cleans up its lessons, quizzes, assignments, final project, and stored files.

*Manual UAT on XAMPP (Gate B)*
- [ ] Verify Light/Dark Mode toggle and AR (RTL) / EN (LTR) language switcher across Public, Student, and Admin layouts; confirm White / Black / Navy Blue palette.
- [ ] As Super Admin: edit Academy Vision (EN & AR) in Settings → verify it updates on the Public Home Page in both languages.
- [ ] As Teacher: create a course, upload 2–3 videos, reorder them, add a quiz, add a flexible assignment (File / MCQ / Both), and configure the Final Project.
- [ ] As Teacher: try to edit/delete a Super Admin's course → blocked (403).
- [ ] As Super Admin: delete the Teacher's course → allowed.
- [ ] Mark 3 courses as featured → they show on the Home page with the correct title, description, and price.
- [ ] Log in as a new Student → every course appears locked on Browse Courses.
- [ ] Owner confirms the Home page design, colors, dark mode, RTL layout, and content.

🛑 **STOP — deliver the Phase 2 report and testing steps. Wait for explicit approval of Phase 2.**

---

### PHASE 3 — Manual InstaPay Payments, Promo Codes & Time-Limited Enrollment Engine

**Goals**
- Implement the **Admin Promo Code Manager** and student promo-code checkout calculation.
- Implement the manual payment flow (InstaPay QR + promo code discount preview + proof upload).
- Let Super Admins review payments and create/manage time-limited enrollments.
- Implement automatic expiry and re-locking.

**Database Tables / Migrations**
`promo_codes`, `payment_requests` (including `promo_code_id`, `promo_code_snapshot`, `original_price`, `discount_amount`, `final_price`), `enrollments`. Update `academy_settings` usage for the **InstaPay QR** image. Add indexes for access checks.

**Backend Deliverables**
- **Promo Code Manager (Super Admins 1 & 2):** create, edit, enable/disable, and delete promo codes (`code`, `discount_type`: `percentage` | `fixed`, `discount_value`, `expires_at`, `max_uses`, `used_count`, `is_active`). Server-side validation prevents expired, disabled, or maxed-out codes.
- **QR management (Super Admins):** upload/replace the InstaPay QR image from Settings.
- **Student subscription flow:**
  - "Subscribe to Course" → page showing the **QR code**, the course name and original price, an optional **Promo Code input** (AJAX/form validation displaying discount and final payable price before InstaPay transfer), and the **payment confirmation form**.
  - Form auto-fills **Name** and **Account/Email** (read-only) from the logged-in student, requires **Phone Number** and a **Transfer Screenshot** (image validation: jpg/png/webp, size limit).
  - Creates a `payment_requests` row with status `pending` (recording original price, applied promo code, discount amount, and final payable amount); blocks duplicate pending requests for the same course; the student sees their request status (Pending / Approved / Rejected + note).
- **Admin Payments inbox (Super Admins):** list with filters, view the screenshot + applied promo code / final price, approve/reject with a note.
- **Enrollment section (Super Admins):**
  - Choose **Student**, choose **Course**, set **Access Duration** (preset 1/3/6 months or custom days/months).
  - Approving a payment can pre-fill/launch the enrollment form; manual creation without a payment request is also supported.
  - Calculates `starts_at` and `expires_at`; supports **extend**, **revoke**, and **view history**.
- **Central access service/middleware** (`CourseAccess`): returns allowed only for an active, unexpired enrollment; used on all course routes from here on.
- **Auto-expiry:** Artisan command `enrollments:expire` + scheduler registration; Windows Task Scheduler setup instructions for XAMPP.
- Activity logs for promo codes, approvals, enrollments, extensions, and revocations.
- Teacher and Support have **no access** to promo codes/payments/enrollments (Support can see enrollment *status* read-only, wired in Phase 5).

**Frontend Deliverables**
- Student: Subscribe page (QR + Promo Code discount preview + form), "My Payments" status page, course cards updating from **Locked → Unlocked** after enrollment, "Expires on …" badge, remaining-days indicator.
- Admin: Promo Code Manager UI, QR upload UI, Payments inbox with the screenshot viewer modal, Enrollment create/extend/revoke UI with a duration picker, Student ↔ Course enrollment list with status badges (Active / Expired / Revoked).

**Phase 3 Exit & Testing Checklist**

*Developer / automated (Gate A)*
- [ ] Tests: Super Admins can CRUD & toggle Promo Codes; Teacher/Support/Student receive 403.
- [ ] Tests: Promo code validation calculates percentage & fixed discounts accurately and rejects expired, inactive, or maxed-out codes; `payment_requests` records promo snapshot & final price.
- [ ] Tests: payment form requires phone + valid screenshot; the name/email cannot be tampered with.
- [ ] Tests: only Super Admins can approve/reject payments and create enrollments (403 for Teacher/Support/Student).
- [ ] Tests: enrollment date math is correct for days and months (including month-end edge cases).
- [ ] Tests: `CourseAccess` allows an active enrollment, denies expired/revoked/missing ones.
- [ ] Tests: `enrollments:expire` flips only the expired rows; time-travel tests (Carbon `travelTo`) confirm a 3-month enrollment expires at 3 months.
- [ ] Tests: access is denied **immediately** after `expires_at`, even before the scheduler runs.

*Manual UAT on XAMPP (Gate B)*
- [ ] As Super Admin: create an active Promo Code (e.g., `MASH20` for 20% off) and upload the InstaPay QR.
- [ ] As Student: open Subscribe page → enter `MASH20` → confirm discounted price displays clearly → submit phone + screenshot → see "Pending" with applied discount.
- [ ] As Super Admin: open the payment, view screenshot & discounted amount, approve, enroll the student for 3 months.
- [ ] As Student: the course is now unlocked, showing the expiry date.
- [ ] Set a short duration (e.g. 1 day, or a manually adjusted `expires_at` in the DB for testing) → after expiry the course re-locks.
- [ ] Confirm the Windows Task Scheduler/`schedule:run` instructions work on the owner's machine.
- [ ] Reject a payment → the student sees "Rejected" with the note.

🛑 **STOP — deliver the Phase 3 report and testing steps. Wait for explicit approval of Phase 3.**

---

### PHASE 4 — Student Learning Experience: Drip-Lock, Video Player, Quizzes, Flexible Assignments, Final Project (95% Gate) & Certificates

**Goals**
- Deliver the full learning experience for enrolled students.
- Implement strict sequential video locking, progress tracking, standalone quizzes, and flexible File/MCQ/Both assignment submissions.
- Enforce the **≥ 95% Course Completion Gate** for the **Final Project**, and unlock **"My Certificates"** ONLY after Final Project completion/submission.

**Database Tables / Migrations**
`lesson_progress`, `quiz_attempts`, `quiz_attempt_answers`, `assignment_submissions`, `final_project_submissions`, `certificates`.

**Backend Deliverables**
- **Drip-lock engine** (service class + route middleware): Lesson 1 open; Lesson *N* unlocks only when Lesson *N‑1* is completed (and its required quiz is passed, if configured). Applies to the lesson page and the **video stream endpoint**.
- **Secure video streaming** controller with HTTP Range support, enrollment + lock verification, and non-guessable URLs.
- **Progress API** (AJAX): saves `last_position_seconds`, `watched_seconds`; marks `completed_at` **only upon 100% full watch** (reaching the actual end of the video with server-side full-duration & anti-skip plausibility checks) before unlocking the next video; resume-from-last-position support.
- **Quiz engine:** attempt, auto-grade, store score/percentage/pass-fail; retake rules (unlimited retakes, best score kept).
- **Flexible Assignment submission:** supports `file` upload (pdf/doc/zip/images), interactive `mcq` auto-grading, or `both` combined; status `pending → submitted → reviewed`; staff can view/download/grade submissions (Teacher: own courses; Super Admins: all).
- **Final Project & ≥ 95% Gate (server-side enforced):** computes course completion % (completed videos + required quizzes/assignments); returns 403 on Final Project view/submission until completion ≥ 95%; records `final_project_submissions` and allows Teacher/Super Admin review.
- **Certificates Engine:** generates a uniquely coded completion certificate (`certificates` table) **ONLY** after the student submits/completes the course's Final Project (100% locked server-side before that); provides **"My Certificates"** listing and secure PDF/printable download + public/admin verification lookup by `certificate_code`.
- Handling of expiry mid-session: a stream request after expiry returns 403 and the UI redirects to the "Subscribe/Renew" page.

**Frontend Deliverables**
- Student: "My Courses" page, course player page with a **lesson sidebar** (✔ completed / ▶ current / 🔒 locked), HTML5 video player with JS progress reporting and resume, locked-lesson modal explaining "Complete Lesson X first", quiz-taking UI with results screen, flexible assignment UI (file uploader and/or MCQ form), **Final Project panel** (locked with live % indicator below 95%, unlocked at ≥ 95%), **"My Certificates"** page (locked state before Final Project, downloadable branded certificate after), and progress bar per course.
- Teacher/Admin: assignment & final project submissions list (view/download/grade), quiz attempt overview.

**Phase 4 Exit & Testing Checklist**

*Developer / automated (Gate A)*
- [ ] Tests: Lesson 2 request → 403 until Lesson 1 is completed; Lesson 3 locked until Lesson 2, and so on for a 5-video course.
- [ ] Tests: direct video-URL access without an enrollment, with an expired enrollment, or to a locked lesson → denied.
- [ ] Tests: the progress endpoint cannot be abused to mark completion instantly (validation rules).
- [ ] Tests: quiz grading is correct; the pass/fail threshold gates the next lesson when configured.
- [ ] Tests: flexible assignment submissions work for `file`, `mcq`, and `both`, with upload validation & ownership checks.
- [ ] Tests: Final Project returns 403 when course completion < 95% and unlocks at ≥ 95%.
- [ ] Tests: Certificate download/issuance is strictly blocked (403) until Final Project is submitted/completed, and unlocks immediately afterward.
- [ ] Tests: progress is preserved after expiry and resumes after renewal.

*Manual UAT on XAMPP (Gate B)*
- [ ] Enroll a test student in a 5-video course → only Video 1 is playable; 2–5 show 🔒; Final Project & Certificate are locked.
- [ ] Watch Video 1 to completion → Video 2 unlocks; repeat through Video 5 (reaching ≥ 95%).
- [ ] Try pasting the URL of a locked video or locked Final Project before 95% → blocked.
- [ ] Close the browser mid-video → reopening resumes near the last position.
- [ ] Take a quiz and submit a flexible assignment (File and/or MCQ).
- [ ] At ≥ 95% completion, confirm Final Project unlocks → submit Final Project → visit **"My Certificates"** and download the earned certificate.
- [ ] Expire the enrollment during a session → the next action re-locks the course.
- [ ] Owner confirms the player experience on desktop and mobile widths (Light/Dark, EN/AR).

🛑 **STOP — deliver the Phase 4 report and testing steps. Wait for explicit approval of Phase 4.**

---

### PHASE 5 — Analytics Dashboard, Certificate Verification, Customer Support Views, Hardening & Final QA

**Goals**
- Deliver the Student Progress & Analytics dashboard.
- Deliver Admin **Certificate Verification & Management** alongside read-only Customer Support views.
- Harden security/performance and run full-system QA.
- Prepare handover documentation.

**Database Tables / Migrations**
No major new tables. Add **indexes**, optional DB views/aggregate queries, and a possible `report_exports` helper (only if CSV export is approved). Final seeders for production-like data (admin credentials rotation).

**Backend Deliverables**
- **Analytics service** computing per student/course: total videos watched, **completion %**, quiz scores (latest/best/average), assignment status (**Submitted / Pending** + MCQ score), Final Project status, Certificate status, enrollment status and expiry date, last activity.
- **Admin Certificate Management & Verification:** Super Admins can search/verify issued certificates by code or student, and revoke/reissue if required; Support has read-only verification view.
- **Access:** Super Admins 1 & 2 (full), Customer Support (**read-only**). Teacher is excluded unless the owner approves a per-own-course view.
- Customer Support module: searchable student list; student detail (account info, contact details, enrollments, progress, certificates) — **no edit/delete/create actions anywhere** (enforced by Policies and tested).
- Filters (course, date range, status), pagination, and optional CSV export.
- **Security hardening:** re-audit of all Policies/routes against the permission matrix, rate limiting, upload hardening, XSS/CSRF/SQL-injection review, secure file permissions, `APP_DEBUG=false` guidance, error logging, and password change/rotation for the seeded staff accounts.
- **Performance:** eager loading, indexes, caching of heavy aggregates where useful.
- **Documentation:** README (XAMPP install, `.env`, migrate/seed, scheduler setup, `php.ini` upload limits, backup/restore), role/permission guide, admin user guide.

**Frontend Deliverables**
- Analytics dashboard with summary cards (active students, active enrollments, expiring soon, pending payments, certificates issued), a per-student table with progress bars, a per-course report, and charts (e.g. Chart.js).
- Admin Certificate Verification & Management UI.
- Customer Support dashboard (read-only UI, with no action buttons).
- Final UI polish: responsive QA (Light/Dark + EN/AR), accessibility basics, consistent empty/error states, loading states.

**Phase 5 Exit & Testing Checklist**

*Developer / automated (Gate A)*
- [ ] Full test suite is green (unit + feature) across all 5 phases.
- [ ] Tests: Support can view analytics/student/certificate data but every write route returns 403.
- [ ] Tests: analytics numbers match known fixtures (completion %, quiz averages, submitted/pending, final project & certificate counts).
- [ ] Security checklist completed (authorization matrix re-verified, file access, throttling).
- [ ] No N+1 query hot spots on the analytics pages.

*Manual UAT on XAMPP (Gate B)*
- [ ] **End-to-end scenario:** Register (policy checkbox) → browse (all locked) → subscribe with Promo Code + QR + screenshot → Super Admin approves + enrolls for 3 months → Student learns in sequence → quiz + flexible assignment → reaches ≥ 95% → submits Final Project → downloads Certificate → analytics & certificate verification reflect the activity → enrollment expires → course re-locks.
- [ ] As Support: view student info/progress/certificates; confirm there are no edit/delete/create controls.
- [ ] As Super Admin 1/2: full dashboard access; confirm the mutual-deletion protection.
- [ ] As Teacher: confirm no access to payments, promo codes, enrollments, or others' course deletion.
- [ ] Cross-browser check (Chrome/Edge/Firefox), mobile-width check, Light/Dark & AR/EN check.
- [ ] Owner reviews the handover documentation and signs off.

🛑 **STOP — deliver the final report, the QA results, and the handover pack. Wait for the owner's final acceptance.**

---

## 3. Master Prompt for Andy Craft (Copy & Paste)

Copy everything inside the block below into Andy Craft.

````text
You are Andy Craft, a Senior Full-Stack Software Engineer. You will build "Mash Academy", an e-learning web application, using PHP/Laravel (backend), MySQL running locally on XAMPP, and HTML5/CSS3/Bootstrap 5/JavaScript (frontend). You will work PHASE BY PHASE according to the plan below.

=====================================================
ABSOLUTE WORKFLOW RULES (NEVER BREAK THESE)
=====================================================
1. The project has 5 sequential phases. Work ONLY on the phase I currently authorize. At the start I will say, for example, "Start Phase 1".
2. NEVER start, scaffold, or write code for a later phase, even partially, even if it seems efficient.
3. At the END of each phase you MUST:
   a) Run/describe the automated tests you wrote (PHPUnit/Pest) and report the results honestly.
   b) Give a "Phase Completion Report": what was built, the list of migrations/files/routes, test credentials, and the commands to run on XAMPP.
   c) Give clear, numbered MANUAL TESTING INSTRUCTIONS for me, based on that phase's checklist.
   d) Then STOP COMPLETELY and write: "PHASE X COMPLETE — Awaiting your explicit approval to begin Phase X+1."
4. Do NOT continue until I reply with explicit approval such as "Phase X approved. Start Phase X+1". If I report bugs, fix them, re-run the tests, re-report, and wait for approval again.
5. If any requirement is ambiguous, ask me BEFORE building that part; do not guess on business rules. If you must make an assumption, list it clearly in the report.
6. Never delete or rewrite earlier-phase work unless needed to fix a bug, and tell me exactly what changed.
7. Environment: Windows + XAMPP (Apache + MySQL), PHP version as installed in XAMPP, Composer. Give XAMPP-specific instructions (.env DB settings, php.ini upload limits, storage:link, Task Scheduler for `php artisan schedule:run`).
8. Security and quality are mandatory: server-side authorization (Policies/Gates/middleware) for every sensitive action, validation on every input, CSRF protection, hashed passwords, private storage for videos/proofs/submissions, and no secrets in code.

=====================================================
PROJECT REQUIREMENTS (SOURCE OF TRUTH)
=====================================================
BRANDING & PUBLIC PAGES
- Platform name: Mash Academy, with a dynamic logo, bilingual Academy info & Vision (English & Arabic, editable from the dashboard), strict **White (`#FFFFFF`) / Crisp Black (`#111111`) / Navy Blue (`#0A192F` / `#1E3A8A`)** palette, **Light/Dark Mode toggle**, and **Bilingual AR (RTL) / EN (LTR)** switcher across all layouts.
- Home page: a clean landing page showcasing the Academy and its bilingual vision, and prominently featuring 3 main courses (title, description, price).
- Student Registration and Login pages. During registration the student must view the Academy Policy (managed dynamically from the Admin Dashboard) and tick a mandatory "I agree to the Policy" checkbox before the account can be created (enforced server-side, and the acceptance is recorded).

ROLES (exactly 4 staff accounts + Student)
1. Super Admin 1 (Owner): full access to everything. Can delete Teacher and Customer Support accounts. CANNOT delete Super Admin 2.
2. Super Admin 2 (Co-Owner): full access to everything. Can delete Teacher and Customer Support accounts. CANNOT delete Super Admin 1.
3. Teacher Admin: can create/add courses, upload videos, and manage their OWN courses/quizzes/assignments. CANNOT delete courses created by Super Admin 1 or 2. No access to financial/enrollment controls.
4. Customer Support Admin: READ-ONLY access to student accounts, contact details, enrollment status, and student attendance/progress analytics. Cannot add/delete courses or modify settings.
5. Student: browses courses, submits payment requests, and accesses unlocked courses during an active enrollment period.

COURSE STRUCTURE, DRIP-LOCK, FINAL PROJECT & CERTIFICATES
- All courses/lessons are LOCKED by default for newly registered students.
- Each course has recorded videos (uploaded directly to the server via the dashboard), standalone quizzes linked to lessons/courses, flexible assignments (written file upload, interactive MCQ, or both combined), and a dedicated Final Project.
- Strict sequential locking: inside an unlocked course, Video 1 is open; Video 2 stays locked until Video 1 is completed; Video 3 until Video 2 is completed; and so on. Enforce on the server (including the video stream endpoint), not only in the UI.
- Final Project remains strictly locked until the student completes ≥ 95% of the course.
- Certificates ("My Certificates") remain 100% locked until the Final Project is submitted/completed.

MANUAL PAYMENT (InstaPay QR), PROMO CODES & TIME-BASED ENROLLMENT
- Super Admins can upload/update the InstaPay QR image, edit the Registration Policy text, and manage Promo Codes (percentage or fixed discount, expiry date, usage limit, active toggle) from the dashboard.
- Student flow: "Subscribe to Course" shows the QR, optional Promo Code input (showing the discounted price before transfer), and a payment confirmation form. The form auto-fills the student's Account/Email and Name, asks for Phone Number, and requires a Transfer Screenshot. Submitted proofs (with applied promo code and discounted price) appear in the Admin Dashboard for review.
- Enrollment section (Super Admins): select a Student, select a Course, set an Access Duration (e.g. 3 months, or custom days/months). On activation the course unlocks immediately. When the duration ends the system AUTOMATICALLY revokes access and re-locks the course (enforce at request time AND with a scheduled command).

ANALYTICS & CERTIFICATES
- For Super Admins (1 & 2) and Customer Support Admin (read-only). Per student: total videos watched, completion percentage, quiz scores, assignment status (Submitted / Pending), Final Project status, and Certificate verification.

=====================================================
DATABASE (planned tables)
=====================================================
users (role: super_admin_1 | super_admin_2 | teacher | support | student), academy_settings, policies, policy_acceptances, courses, lessons, lesson_progress, quizzes, quiz_questions, quiz_options, quiz_attempts, quiz_attempt_answers, assignments, assignment_questions, assignment_options, assignment_submissions, final_projects, final_project_submissions, certificates, promo_codes, payment_requests, enrollments, activity_logs, plus Laravel defaults. Use foreign keys, indexes, and soft deletes where sensible.

=====================================================
THE 5 PHASES
=====================================================
PHASE 1 — Foundation, Branding, Auth, Policy Gate & Roles
  Laravel setup on XAMPP/MySQL; layouts + Bootstrap theme; dynamic logo/academy info; register/login/logout/password reset; mandatory Policy checkbox + policy_acceptances; seed the 4 staff accounts; role middleware + Policies (including the Super Admin mutual-deletion protection and the "only 4 staff accounts" guard); admin shell + Settings (academy info, logo, Policy editor); staff management; activity logs.
  Tables: users, academy_settings, policies, policy_acceptances, activity_logs, Laravel defaults.

PHASE 2 — Course Management, Content Upload, Flexible Assessments, Final Project & Bilingual/Dark-Mode Home Page
  Strict White/Black/Navy palette + Light/Dark mode toggle + AR (RTL)/EN (LTR) switcher + Bilingual Vision editor; Course CRUD (price, thumbnail, featured max 3); lesson/video upload with ordering & private admin preview stream; standalone quiz builder; flexible assignment builder (File Upload, Interactive MCQ, or Both); Final Project setup per course (95% gate config); Teacher ownership rules (cannot edit/delete Super Admin courses); bilingual/dark-mode public Home page with dynamic Vision & 3 featured courses; public course details; Student browse page with all courses locked.
  Tables: courses, lessons, quizzes, quiz_questions, quiz_options, assignments, assignment_questions, assignment_options, final_projects.

PHASE 3 — InstaPay Payments, Promo Codes & Time-Limited Enrollments
  Promo Code manager (CRUD, percentage/fixed, expiry, usage limit, active toggle); QR upload; Subscribe page with QR + Promo Code discount preview + prefilled form + screenshot; payment_requests review (approve/reject + discount snapshot); Enrollment manager (student + course + duration; extend/revoke); CourseAccess service/middleware; `enrollments:expire` command + scheduler (+ XAMPP Task Scheduler instructions); unlocked/locked UI with expiry badges.
  Tables: promo_codes, payment_requests, enrollments.

PHASE 4 — Student Learning Experience, Final Project (95% Gate) & Certificates
  Drip-lock engine; secure Range-enabled video streaming; progress tracking (strict 100% full-watch completion triggered only at video end with server-side duration & anti-skip validation, resume position); quiz taking/grading; flexible assignment submission (File / MCQ / Both); Final Project strictly locked until ≥ 95% course completion; "My Certificates" locked until Final Project submission/completion + downloadable certificate; lesson sidebar with ✔ / ▶ / 🔒 states; expiry handling mid-session.
  Tables: lesson_progress, quiz_attempts, quiz_attempt_answers, assignment_submissions, final_project_submissions, certificates.

PHASE 5 — Analytics, Certificate Verification, Support Views, Hardening & Final QA
  Analytics dashboard (videos watched, completion %, quiz scores, assignment Submitted/Pending, Final Project & Certificate status); Admin certificate verification/management; read-only Customer Support module; filters/export (if approved); security + performance hardening; full end-to-end QA; README, role guide, and handover documentation.

=====================================================
EVERY PHASE ENDS WITH A TESTING & VERIFICATION GATE
=====================================================
Gate A (you): automated tests green + migrations clean (`migrate:fresh --seed`).
Gate B (me): manual UAT on my local XAMPP using your numbered instructions and the phase's exit checklist.
Only after BOTH gates pass and I explicitly approve may the next phase begin.

=====================================================
HOW TO BEGIN
=====================================================
Reply first with: (1) a brief confirmation that you understand the rules, (2) any questions/ambiguities about the requirements that you need me to settle BEFORE Phase 1 (for example: preferred Laravel/PHP versions in my XAMPP, UI language/RTL, currency, default completion threshold, retake rules for quizzes, whether Teachers may see analytics for their own courses), and (3) your Phase 1 checklist. Then WAIT for me to say "Start Phase 1". Do not write any code until I say so.
````

---

## 4. Open Questions to Settle Before Phase 1 (Recommended)

| # | Question | Default assumption if unanswered |
|---|---|---|
| 1 | PHP / Laravel version supported by your XAMPP install? | Latest Laravel version compatible with the installed PHP |
| 2 | Interface language: English, Arabic, or bilingual (RTL)? | English, with a layout ready for RTL |
| 3 | Currency for prices (EGP?) | EGP |
| 4 | Video completion threshold | **100% full watch** (start to finish, triggered only at video end with server-side duration & anti-skip validation) |
| 5 | After an enrollment expires, is previous progress kept? | Yes, kept and resumed on renewal |
| 6 | Quiz retake rule (unlimited / limited)? | Unlimited retakes, best score kept |
| 7 | Does a required quiz block the next video? | Optional per quiz (`is_required_for_next`) |
| 8 | May Teachers see analytics for their own courses? | No (per the original requirements) |
| 9 | Max video size / hosting limits on the final server | Set via `php.ini` locally; revisit before production |
| 10 | Email verification on sign-up needed? | Not required |

---

## 5. Pre-Sign-Off Enhancements & Fixes (Integrated Across Phases 1–5)

Before final production sign-off, four targeted enhancements were integrated cleanly via additive migrations (preserving all existing MySQL data):

1. **Unrestricted Student-Area Access for Super Admins (1 & 2) + Navbar Switcher:**
   - `super_admin_1` and `super_admin_2` have unrestricted access to all `/student/*` routes, including the Student Dashboard, My Courses, Browse Courses, Subscribe checkout preview, sequential Course Player (bypassing enrollment and drip-lock blocks), private video streaming, Quizzes, Assignments, Final Project (bypassing the 95% gate), and Certificate preview/download — never receiving a `403` error.
   - Dedicated **Student View** ↔ **Admin Panel** switcher buttons are placed in the Public, Admin, and Student navbars for Super Admins (1 & 2). Teacher and Customer Support remain blocked from student portal routes.

2. **Customer Support Student & Enrollment Visibility Fix (Strictly Read-Only):**
   - `/admin/students` and `/admin/students/{student}` run `EnrollmentService::expireOverdue()` on load, query all registered student accounts (`User::withTrashed()->where('role', 'student')`), eager-load enrollments and payment requests (including soft-deleted courses), and display accurate Active / Expired / Revoked / Pending Payment / Not Enrolled statuses and filters.
   - Customer Support remains 100% read-only with zero create/edit/approve/delete UI actions and server-side `403` enforcement on all write endpoints.

3. **Per-Course & Per-Instructor InstaPay QR Code System:**
   - Each instructor (`super_admin_1`, `super_admin_2`, `teacher`) can configure a personal default InstaPay QR & handle/note on `/admin/instructor-profile` (`users.instapay_qr_path`, `users.instapay_note`) AND optionally upload a dedicated per-course InstaPay QR & note (`courses.instapay_qr_path`, `courses.instapay_note`) on their own courses.
   - Teachers can only upload/update/remove QR codes on courses they own (`403` on other instructors' courses).
   - Student checkout (`/student/courses/{slug}/subscribe`) dynamically resolves the QR in strict priority order: **Course QR → Course Instructor Personal QR → Global Academy QR**, while all submitted proofs remain `pending` in `payment_requests` for Super Admin (1 & 2) review and approval.

4. **"Meet Our Instructors" Section on Home Page + Instructor Portfolio Profiles:**
   - All 3 instructors (`super_admin_1`, `super_admin_2`, `teacher`) can edit their public profile from the Admin Dashboard (`/admin/instructor-profile`): Display Name, bilingual Title/Role (EN/AR), Profile Photo/Avatar, bilingual Short Bio (EN/AR), Portfolio / Personal Website URL, plus optional LinkedIn & GitHub URLs.
   - The Public Home Page (`/`) renders a dedicated **"Meet Our Instructors"** (`#meet-our-instructors`) section adhering strictly to the White/Black/Navy Blue palette, Dark Mode, and EN LTR / AR RTL, featuring an external **View Portfolio / Website** button (`target="_blank" rel="noopener noreferrer"`).

---

## 6. Post-Launch Feature Expansion (Packages, Users & Blocking, Marketing Analytics)

Three additional production modules were integrated via additive migrations (`2026_11_24_000001`–`000003`) without modifying or resetting any existing data:

1. **Course Packages / Bundles System (الباكيدجات):**
   - Super Admins (`super_admin_1`, `super_admin_2`) manage bilingual Course Packages (`/admin/packages`) with bundle pricing, thumbnails, optional per-package InstaPay QR overrides, and a multi-select checklist of included courses.
   - Published Packages render on Public Home (`/`), Public Courses (`/courses`), Package Details (`/packages/{slug}`), and Student Browse Courses (`/student/courses`) with the identical InstaPay + Promo Code + Screenshot checkout flow (`/student/packages/{slug}/subscribe`).
   - Approving a package payment (or manually enrolling a student in a package) automatically creates or extends active individual `enrollments` for **every course inside the package** with that exact expiration date. No separate "My Packages" tab is added; unlocked courses appear directly in the student's regular **My Courses** view with full 100% video drip-lock, 95% Final Project gate, and auto-expiry enforcement.

2. **Dashboard Users Management (Create Student + Account Block/Ban System):**
   - Added `/admin/users` accessible to all 4 staff roles (`super_admin_1`, `super_admin_2`, `teacher`, `support`).
   - **Strict Role Creation Rules:** `teacher` and `support` are locked to creating `student` accounts only (both UI and server-side validation); only Super Admins may create available staff roles while preserving the 4-staff hierarchy constraint.
   - **Blocking / Banning:** Added `is_blocked`, `blocked_reason`, `blocked_at`, and `blocked_by` on `users`. All 4 staff roles can block/unblock student accounts; Super Admins can never be blocked, and Teacher/Support cannot block staff accounts. Global `EnsureUserIsActive` middleware and `LoginRequest` immediately terminate sessions and redirect blocked users to `/login` with: *"Your account has been blocked due to violating Mash Academy's Platform Policy."*

3. **Marketing & Social Media Analytics (`/admin/marketing`):**
   - Supports connecting multiple accounts across **TikTok, YouTube, Facebook, and Instagram** (`+ Add Account`), summary KPI cards, platform filter tabs, per-account analytics cards (Followers/Subscribers, Total Video Views, Total Likes & Engagement, Top Performing Video with direct link), live YouTube Data API v3 / public metadata sync (`MarketingMetricsService`), and a **Sync / Update Metrics** modal for all 4 platforms.

---

## 7. Student Engagement, Communication & Bunny.net Streaming Enhancements

Five additional features were integrated via additive migrations (`2026_11_24_100001`–`100003`) without modifying or resetting any existing data, with dedicated Super Admin feature toggles in **Academy & Branding Settings**:

1. **Optional Free Demo / Preview Videos (`enable_demo_videos`):**
   - Global toggle in Academy Settings (`enable_demo_videos`, default ON) plus per-course configuration (`has_demo_video`, `demo_video_type` [`upload` / `bunny` / `youtube`], `demo_video_value`) on Course Create/Edit.
   - Displays a **"Watch Free Demo / Preview"** modal on public Course Details (`/courses/{slug}`) and a **"Free Demo Videos"** showcase section on Home (`/`) when active; completely hides all demo UI when toggled OFF.

2. **In-App Notification Bell + 1-Click WhatsApp Quick-Message Buttons (`wa.me`):**
   - Database-backed `notifications` table + live Navbar Bell dropdown in Admin & Student layouts (unread badge, click-to-read + redirect, "Mark all as read").
   - Dispatches instant notifications on Payment Submitted → Super Admins, Payment Approved/Rejected → Student, Final Project Submitted → Super Admins & Teacher, Final Project Reviewed → Student, Q&A Instructor Reply → Student, Consultation Booked/Updated, PLUS a daily scheduled command `enrollments:notify-expiring` notifying students **3 days before enrollment expiry** (tracked via `enrollments.expiry_notified_at`).
   - Green 1-click **WhatsApp** (`https://wa.me/{phone}?text={message}`) buttons with bilingual EN/AR pre-filled templates across **Enrollments**, **Payments Inbox**, and **Customer Support Students Directory & Detail**.

3. **Public Certificate Verification Page (`enable_certificate_verification`):**
   - Global toggle in Academy Settings (default ON). Public `/verify-certificate` page (plus `/certificates/verify`) linked from navbar/footer and embedded with a scannable QR code + verification link on generated & printable certificates. Returns `404` and hides all links when disabled.

4. **Course Q&A, Student Star Reviews & 1-on-1 Consultation Booking ("استشارة"):**
   - **Course Q&A (`enable_course_qa`):** Enrolled students post questions inside unlocked courses; Teachers (own courses) and Super Admins reply from either the Course Player or Admin Submissions & Review, notifying the student.
   - **Student Star Reviews (`enable_course_reviews`):** Enrolled students with **≥ 50% completion** (or Super Admins) submit 1–5 star ratings and comments rendered on public Course Details.
   - **1-on-1 Consultations (`enable_consultations`):** Managed via `/admin/consultations` (enable/disable toggle, `30`/`45`/`60` min session duration, Free vs Paid pricing mode, EGP price, dedicated Consultation InstaPay QR, availability notes, and status updates). Public/Student `/consultations` page supports Free direct booking and Paid InstaPay QR + screenshot upload into `payment_requests` (auto-confirming the consultation upon Super Admin approval).

5. **Bunny.net Stream Video ID Support Alongside Direct Upload:**
   - Lesson form (`/admin/courses/{id}/lessons`) supports switching between **Direct Video Upload (MP4/WebM/MOV/M4V)** and **Bunny.net Stream (Library ID + Video ID / Embed URL)** with global default `bunny_library_id` in Academy Settings or per-lesson override.
   - Admin Preview and Student Course Player both render responsive `16:9` `.ma-video-shell` containers for direct `<video>` AND Bunny.net Stream `<iframe>` with Player.js `postMessage` progress tracking preserving sequential drip-lock and expiry enforcement.

---

## Future Phase (ON HOLD — DO NOT IMPLEMENT UNLESS EXPLICITLY INSTRUCTED): Student Wallet & Prepaid Top-Up Codes System

**`[STATUS: PLANNED / ON HOLD — DO NOT IMPLEMENT]`**

> **STRICT AGENT INSTRUCTION:** AI Agents must NOT implement, scaffold, or touch any files for this Student Wallet & Voucher Codes feature during subsequent bug fixes or feature updates until the user explicitly commands to start implementing this specific phase.

### Planned Scope for Future Implementation

1. **Student EGP Wallet (`wallet_balance`):**
   - Each student account has a wallet balance denominated in **EGP (جنيه مصري)** displayed in their student header/profile.
   - Ledger table (`wallet_transactions`) logging every top-up, voucher redemption, course/package purchase, and admin adjustment with timestamps and references.

2. **Two Ways to Top Up the Wallet:**
   - **Prepaid Voucher / Top-Up Codes (`wallet_vouchers`):** Super Admins (1 & 2) can generate single-use or batch alphanumeric top-up codes with a fixed EGP value (e.g., 60 EGP, 150 EGP, 300 EGP) and optional expiration date. Students enter the code in a **"Redeem Code"** box to instantly credit their EGP wallet balance.
   - **InstaPay Wallet Top-Up:** Students can submit a top-up request for a custom EGP amount via InstaPay QR + transfer screenshot; once approved by Super Admin in Payments Inbox, the EGP amount is credited directly to the student's wallet.

3. **Checkout Using Wallet Balance:**
   - On Course, Package, and Consultation checkout pages, add a **"Pay from Wallet Balance"** option alongside direct InstaPay payment.
   - If `wallet_balance >= price`, deducting from the wallet unlocks the course/package/consultation **instantly** without waiting for manual payment approval (respecting existing enrollment duration and drip-lock rules).

---

*End of plan. All 5 phases, Pre-Sign-Off Enhancements, Post-Launch Modules, and Engagement/Streaming Features are complete and verified. Future Phase (Student Wallet & Prepaid Top-Up Codes System) remains strictly ON HOLD until explicitly authorized.*
