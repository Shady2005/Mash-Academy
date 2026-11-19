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
| Frontend | Blade + Bootstrap 5 + vanilla JS | RTL/Arabic-ready layout recommended (Egypt market, InstaPay) |
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
| Edit academy info / logo / policy / QR | ✅ | ✅ | ❌ | ❌ | ❌ |
| Create courses & upload videos | ✅ | ✅ | ✅ | ❌ | ❌ |
| Edit own courses / quizzes / assignments | ✅ | ✅ | ✅ (own only) | ❌ | ❌ |
| Delete a course | ✅ any | ✅ any | ✅ **own only** (never Super Admin courses) | ❌ | ❌ |
| Review payments & manage enrollments | ✅ | ✅ | ❌ | ❌ | ❌ |
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
| 2 | `academy_settings` | id, key, value (or single-row: academy_name, tagline, about, vision, logo_path, instapay_qr_path, contact_email, contact_phone, social links) | singleton/key-value |
| 3 | `policies` | id, title, body (long text), version, is_active, updated_by (FK users), timestamps | has many policy_acceptances |
| 4 | `policy_acceptances` | id, user_id (FK), policy_id (FK), policy_version, accepted_at, ip_address | belongs to user & policy (audit proof of consent) |
| 5 | `courses` | id, title, slug, short_description, description, price, currency, thumbnail_path, `created_by` (FK users), `is_featured`, `featured_order`, is_published, timestamps, soft deletes | has many lessons; created by user |
| 6 | `lessons` | id, course_id (FK), title, description, `position` (order), video_path, video_mime, duration_seconds, is_published, timestamps | belongs to course; has many progress rows, quizzes, assignments |
| 7 | `lesson_progress` | id, user_id, lesson_id, watched_seconds, last_position_seconds, `completed_at`, timestamps; unique(user_id, lesson_id) | drives drip-lock and analytics |
| 8 | `quizzes` | id, course_id, lesson_id (nullable), title, pass_mark, `is_required_for_next` (bool), created_by | has many questions, attempts |
| 9 | `quiz_questions` | id, quiz_id, question_text, type, position, points | has many options |
| 10 | `quiz_options` | id, question_id, option_text, is_correct | belongs to question |
| 11 | `quiz_attempts` | id, quiz_id, user_id, score, max_score, percentage, passed, started_at, submitted_at | belongs to quiz & user |
| 12 | `quiz_attempt_answers` | id, attempt_id, question_id, option_id, is_correct | belongs to attempt |
| 13 | `assignments` | id, course_id, lesson_id (nullable), title, instructions, due_at (nullable), created_by | has many submissions |
| 14 | `assignment_submissions` | id, assignment_id, user_id, file_path, notes, status (`pending`/`submitted`/`reviewed`), grade (nullable), feedback (nullable), submitted_at | belongs to assignment & user |
| 15 | `payment_requests` | id, user_id, course_id, name, email, phone, screenshot_path, status (`pending`/`approved`/`rejected`), admin_note, reviewed_by (FK users), reviewed_at, timestamps | belongs to user & course |
| 16 | `enrollments` | id, user_id, course_id, payment_request_id (nullable), `starts_at`, `expires_at`, duration_value, duration_unit (`days`/`months`), status (`active`/`expired`/`revoked`), created_by (FK users), timestamps; index(user_id, course_id, status, expires_at) | belongs to user & course |
| 17 | `activity_logs` | id, actor_id, action, subject_type, subject_id, meta (JSON), ip, created_at | audit trail (enrollments, deletions, policy/QR changes) |
| 18 | Laravel defaults | `sessions`, `password_reset_tokens`, `jobs`, `failed_jobs`, `cache`, `migrations` | framework |

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

**C. Completion definition**
A video counts as "completed" when the student has watched **≥ 90 %** of its duration (threshold configurable). The JS player reports progress periodically; the server validates plausibility (e.g. watched time cannot exceed elapsed real time by a large margin) before marking complete. The 90 % rule and anti-skip validation are confirmed with the owner in Phase 4.

**D. Auto-expiration**
`php artisan enrollments:expire` runs via the Laravel Scheduler (every minute/hour) and sets `status = expired` where `expires_at < now()`. Re-subscribing creates a new enrollment (or extends the existing one) — the behavior is confirmed with the owner in Phase 3. Existing progress is **kept** after expiry, so renewing students resume where they left off.

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

### PHASE 2 — Course Management, Content Upload & Public Home Page

**Goals**
- Let staff create and manage courses, lessons (videos), quizzes, and assignments.
- Build the public landing page featuring the 3 main courses.
- Enforce the Teacher/Super Admin deletion rules.

**Database Tables / Migrations**
`courses`, `lessons`, `quizzes`, `quiz_questions`, `quiz_options`, `assignments`. (`lesson_progress`, `quiz_attempts`, and `assignment_submissions` are **not** created yet, so Phase 2 stays focused on content authoring.)
Seeders: demo courses (optional, clearly marked as demo data) for testing.

**Backend Deliverables**
- **Course CRUD**: title, description, price, thumbnail, published flag, **featured flag + order** (max 3 featured on the home page).
- **Lesson/Video management**: upload videos directly to the server (chunk/size handling, MIME validation: mp4/webm, size limit configured in `php.ini` — `upload_max_filesize`, `post_max_size`, `max_execution_time`), set lesson order (drag/drop or up/down), edit, delete, store duration.
- **Quiz builder**: create quizzes linked to a lesson or a course, add multiple-choice questions/options, mark correct answers, set pass mark.
- **Assignment builder**: title, instructions, optional due date, linked to a lesson or a course.
- **Ownership rules via Policies:**
  - Teacher: create courses; edit/delete **only own** courses and their content; **cannot delete courses created by Super Admin 1/2**; no access to payments/enrollments.
  - Super Admins: manage all courses.
  - Support: no content management.
- Private storage for videos; secure admin-only preview streaming route.
- Activity log entries for create/delete actions.

**Frontend Deliverables**
- Admin pages: Course list (filtered by role), Course create/edit form, Lesson manager with video upload progress bar, Quiz builder UI, Assignment form, delete confirmation modals.
- **Public Home Page:** hero section, Academy vision/about (dynamic), **3 featured courses** as cards (title, description, price, "View / Subscribe" button), footer with contact info.
- Public Course details page (curriculum outline visible, videos shown as locked/preview-only).
- Student "Browse Courses" page (all courses shown as **locked** by default).

**Phase 2 Exit & Testing Checklist**

*Developer / automated (Gate A)*
- [ ] Migrations run cleanly on top of Phase 1.
- [ ] Tests: Teacher can edit/delete own course; **cannot** delete a course created by SA1/SA2 (403).
- [ ] Tests: Super Admins can delete any course; Support/Student cannot create/delete.
- [ ] Tests: upload validation rejects non-video files and oversized files with clear errors.
- [ ] Tests: only published courses appear publicly; max 3 featured enforced.
- [ ] Deleting a course cleans up its lessons, quizzes, assignments, and stored files (or soft-deletes, as agreed).

*Manual UAT on XAMPP (Gate B)*
- [ ] As Teacher: create a course, upload 2–3 videos, reorder them, add a quiz and an assignment.
- [ ] As Teacher: try to delete a Super Admin's course → blocked.
- [ ] As Super Admin: delete the Teacher's course → allowed.
- [ ] Mark 3 courses as featured → they show on the Home page with the correct title, description, and price.
- [ ] Log in as a new Student → every course appears locked.
- [ ] Upload a video close to the size limit and confirm the behavior/error message.
- [ ] Owner confirms the Home page design and content.

🛑 **STOP — deliver the Phase 2 report and testing steps. Wait for explicit approval of Phase 2.**

---

### PHASE 3 — Manual InstaPay Payments & Time-Limited Enrollment Engine

**Goals**
- Implement the manual payment flow (InstaPay QR + proof upload).
- Let Super Admins review payments and create/manage time-limited enrollments.
- Implement automatic expiry and re-locking.

**Database Tables / Migrations**
`payment_requests`, `enrollments`. Update `academy_settings` usage for the **InstaPay QR** image. Add indexes for access checks.

**Backend Deliverables**
- **QR management (Super Admins):** upload/replace the InstaPay QR image from Settings.
- **Student subscription flow:**
  - "Subscribe to Course" → page showing the **QR code**, the course name and price, and a **payment confirmation form**.
  - Form auto-fills **Name** and **Account/Email** (read-only) from the logged-in student, requires **Phone Number** and a **Transfer Screenshot** (image validation: jpg/png/webp, size limit).
  - Creates a `payment_requests` row with status `pending`; blocks duplicate pending requests for the same course; the student sees their request status (Pending / Approved / Rejected + note).
- **Admin Payments inbox (Super Admins):** list with filters, view the screenshot, approve/reject with a note.
- **Enrollment section (Super Admins):**
  - Choose **Student**, choose **Course**, set **Access Duration** (preset 1/3/6 months or custom days/months).
  - Approving a payment can pre-fill/launch the enrollment form; manual creation without a payment request is also supported.
  - Calculates `starts_at` and `expires_at`; supports **extend**, **revoke**, and **view history**.
- **Central access service/middleware** (`CourseAccess`): returns allowed only for an active, unexpired enrollment; used on all course routes from here on.
- **Auto-expiry:** Artisan command `enrollments:expire` + scheduler registration; Windows Task Scheduler setup instructions for XAMPP.
- Activity logs for approvals, enrollments, extensions, and revocations.
- Teacher and Support have **no access** to payments/enrollments (Support can see enrollment *status* read-only, wired in Phase 5).

**Frontend Deliverables**
- Student: Subscribe page (QR + form), "My Payments" status page, course cards updating from **Locked → Unlocked** after enrollment, "Expires on …" badge, remaining-days indicator.
- Admin: QR upload UI, Payments inbox with the screenshot viewer modal, Enrollment create/extend/revoke UI with a duration picker, Student ↔ Course enrollment list with status badges (Active / Expired / Revoked).

**Phase 3 Exit & Testing Checklist**

*Developer / automated (Gate A)*
- [ ] Tests: payment form requires phone + valid screenshot; the name/email cannot be tampered with.
- [ ] Tests: only Super Admins can approve/reject payments and create enrollments (403 for Teacher/Support/Student).
- [ ] Tests: enrollment date math is correct for days and months (including month-end edge cases).
- [ ] Tests: `CourseAccess` allows an active enrollment, denies expired/revoked/missing ones.
- [ ] Tests: `enrollments:expire` flips only the expired rows; time-travel tests (Carbon `travelTo`) confirm a 3-month enrollment expires at 3 months.
- [ ] Tests: access is denied **immediately** after `expires_at`, even before the scheduler runs.

*Manual UAT on XAMPP (Gate B)*
- [ ] As Super Admin: upload the InstaPay QR → it appears on the student's Subscribe page.
- [ ] As Student: subscribe → the form is pre-filled with name/email → submit phone + screenshot → see "Pending".
- [ ] As Super Admin: open the payment, view the screenshot, approve, enroll the student for 3 months.
- [ ] As Student: the course is now unlocked, showing the expiry date.
- [ ] Set a short duration (e.g. 1 day, or a manually adjusted `expires_at` in the DB for testing) → after expiry the course re-locks.
- [ ] Confirm the Windows Task Scheduler/`schedule:run` instructions work on the owner's machine.
- [ ] Reject a payment → the student sees "Rejected" with the note.

🛑 **STOP — deliver the Phase 3 report and testing steps. Wait for explicit approval of Phase 3.**

---

### PHASE 4 — Student Learning Experience: Drip-Lock, Video Player, Quizzes & Assignments

**Goals**
- Deliver the full learning experience for enrolled students.
- Implement strict sequential video locking, progress tracking, quizzes, and assignment submission.

**Database Tables / Migrations**
`lesson_progress`, `quiz_attempts`, `quiz_attempt_answers`, `assignment_submissions`.

**Backend Deliverables**
- **Drip-lock engine** (service class + route middleware): Lesson 1 open; Lesson *N* unlocks only when Lesson *N‑1* is completed (and its required quiz is passed, if configured). Applies to the lesson page and the **video stream endpoint**.
- **Secure video streaming** controller with HTTP Range support, enrollment + lock verification, and non-guessable URLs.
- **Progress API** (AJAX): saves `last_position_seconds`, `watched_seconds`; marks `completed_at` when the threshold (default 90 %) is reached, with server-side plausibility checks; resume-from-last-position support.
- **Quiz engine:** attempt, auto-grade, store score/percentage/pass-fail; retake rules (configurable, confirmed with the owner).
- **Assignment submission:** file upload (pdf/doc/zip/images, size limit), resubmission rule, status `pending → submitted → (reviewed)`; staff can download submissions (Teacher: own courses; Super Admins: all).
- Handling of expiry mid-session: a stream request after expiry returns 403 and the UI redirects to the "Subscribe/Renew" page.

**Frontend Deliverables**
- Student: "My Courses" page, course player page with a **lesson sidebar** (✔ completed / ▶ current / 🔒 locked), HTML5 video player with JS progress reporting and resume, locked-lesson modal explaining "Complete Lesson X first", quiz-taking UI with a results screen, assignment upload form with submission status, and a progress bar per course.
- Teacher/Admin: submissions list per assignment (view/download/grade-optional), quiz attempt overview.

**Phase 4 Exit & Testing Checklist**

*Developer / automated (Gate A)*
- [ ] Tests: Lesson 2 request → 403 until Lesson 1 is completed; Lesson 3 locked until Lesson 2, and so on for a 5-video course.
- [ ] Tests: direct video-URL access without an enrollment, with an expired enrollment, or to a locked lesson → denied.
- [ ] Tests: the progress endpoint cannot be abused to mark completion instantly (validation rules).
- [ ] Tests: quiz grading is correct; the pass/fail threshold gates the next lesson when configured.
- [ ] Tests: assignment upload validation (type/size), ownership (a student can't view another student's submission).
- [ ] Tests: progress is preserved after expiry and resumes after renewal.

*Manual UAT on XAMPP (Gate B)*
- [ ] Enroll a test student in a 5-video course → only Video 1 is playable; 2–5 show 🔒.
- [ ] Watch Video 1 to completion → Video 2 unlocks; repeat through Video 5.
- [ ] Try pasting the URL of a locked video → blocked.
- [ ] Close the browser mid-video → reopening resumes near the last position.
- [ ] Take a quiz (pass and fail cases) and see the correct score.
- [ ] Submit an assignment → status shows "Submitted"; the Teacher/Super Admin can download it.
- [ ] Expire the enrollment during a session → the next action re-locks the course.
- [ ] Owner confirms the player experience on desktop and mobile widths.

🛑 **STOP — deliver the Phase 4 report and testing steps. Wait for explicit approval of Phase 4.**

---

### PHASE 5 — Analytics Dashboard, Customer Support Views, Hardening & Final QA

**Goals**
- Deliver the Student Progress & Analytics dashboard.
- Complete the read-only Customer Support experience.
- Harden security/performance and run full-system QA.
- Prepare handover documentation.

**Database Tables / Migrations**
No major new tables. Add **indexes**, optional DB views/aggregate queries, and a possible `report_exports` helper (only if CSV export is approved). Final seeders for production-like data (admin credentials rotation).

**Backend Deliverables**
- **Analytics service** computing per student/course: total videos watched, **completion %**, quiz scores (latest/best/average), assignment status (**Submitted / Pending**), enrollment status and expiry date, last activity.
- **Access:** Super Admins 1 & 2 (full), Customer Support (**read-only**). Teacher is excluded unless the owner approves a per-own-course view.
- Customer Support module: searchable student list; student detail (account info, contact details, enrollments, progress) — **no edit/delete/create actions anywhere** (enforced by Policies and tested).
- Filters (course, date range, status), pagination, and optional CSV export.
- **Security hardening:** re-audit of all Policies/routes against the permission matrix, rate limiting, upload hardening, XSS/CSRF/SQL-injection review, secure file permissions, `APP_DEBUG=false` guidance, error logging, and password change/rotation for the seeded staff accounts.
- **Performance:** eager loading, indexes, caching of heavy aggregates where useful.
- **Documentation:** README (XAMPP install, `.env`, migrate/seed, scheduler setup, `php.ini` upload limits, backup/restore), role/permission guide, admin user guide.

**Frontend Deliverables**
- Analytics dashboard with summary cards (active students, active enrollments, expiring soon, pending payments), a per-student table with progress bars, a per-course report, and charts (e.g. Chart.js).
- Customer Support dashboard (read-only UI, with no action buttons).
- Final UI polish: responsive QA, accessibility basics, consistent empty/error states, loading states.

**Phase 5 Exit & Testing Checklist**

*Developer / automated (Gate A)*
- [ ] Full test suite is green (unit + feature) across all 5 phases.
- [ ] Tests: Support can view analytics/student data but every write route returns 403.
- [ ] Tests: analytics numbers match known fixtures (completion %, quiz averages, submitted/pending).
- [ ] Security checklist completed (authorization matrix re-verified, file access, throttling).
- [ ] No N+1 query hot spots on the analytics pages.

*Manual UAT on XAMPP (Gate B)*
- [ ] **End-to-end scenario:** Register (policy checkbox) → browse (all locked) → subscribe with QR + screenshot → Super Admin approves + enrolls for 3 months → Student learns in sequence → quiz + assignment → analytics reflect the activity → enrollment expires → course re-locks.
- [ ] As Support: view student info/progress; confirm there are no edit/delete/create controls.
- [ ] As Super Admin 1/2: full dashboard access; confirm the mutual-deletion protection.
- [ ] As Teacher: confirm no access to payments, enrollments, or others' course deletion.
- [ ] Cross-browser check (Chrome/Edge/Firefox) and mobile-width check.
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
- Platform name: Mash Academy, with a dynamic logo and Academy info (editable from the dashboard).
- Home page: a clean landing page showcasing the Academy and its vision, and prominently featuring 3 main courses (title, description, price).
- Student Registration and Login pages. During registration the student must view the Academy Policy (managed dynamically from the Admin Dashboard) and tick a mandatory "I agree to the Policy" checkbox before the account can be created (enforced server-side, and the acceptance is recorded).

ROLES (exactly 4 staff accounts + Student)
1. Super Admin 1 (Owner): full access to everything. Can delete Teacher and Customer Support accounts. CANNOT delete Super Admin 2.
2. Super Admin 2 (Co-Owner): full access to everything. Can delete Teacher and Customer Support accounts. CANNOT delete Super Admin 1.
3. Teacher Admin: can create/add courses, upload videos, and manage their OWN courses/quizzes/assignments. CANNOT delete courses created by Super Admin 1 or 2. No access to financial/enrollment controls.
4. Customer Support Admin: READ-ONLY access to student accounts, contact details, enrollment status, and student attendance/progress analytics. Cannot add/delete courses or modify settings.
5. Student: browses courses, submits payment requests, and accesses unlocked courses during an active enrollment period.

COURSE STRUCTURE & DRIP-LOCK
- All courses/lessons are LOCKED by default for newly registered students.
- Each course has recorded videos (uploaded directly to the server via the dashboard), quizzes linked to lessons/courses, and assignments (students upload their solutions).
- Strict sequential locking: inside an unlocked course, Video 1 is open; Video 2 stays locked until Video 1 is completed; Video 3 until Video 2 is completed; and so on. Enforce on the server (including the video stream endpoint), not only in the UI.

MANUAL PAYMENT (InstaPay QR) & TIME-BASED ENROLLMENT
- Super Admins can upload/update the InstaPay QR image and edit the Registration Policy text from the dashboard.
- Student flow: "Subscribe to Course" shows the QR + a payment confirmation form. The form auto-fills the student's Account/Email and Name, asks for Phone Number, and requires a Transfer Screenshot. Submitted proofs appear in the Admin Dashboard for review.
- Enrollment section (Super Admins): select a Student, select a Course, set an Access Duration (e.g. 3 months, or custom days/months). On activation the course unlocks immediately. When the duration ends the system AUTOMATICALLY revokes access and re-locks the course (enforce at request time AND with a scheduled command).

ANALYTICS
- For Super Admins (1 & 2) and Customer Support Admin (read-only). Per student: total videos watched, completion percentage, quiz scores, assignment status (Submitted / Pending).

=====================================================
DATABASE (planned tables)
=====================================================
users (role: super_admin_1 | super_admin_2 | teacher | support | student), academy_settings, policies, policy_acceptances, courses, lessons, lesson_progress, quizzes, quiz_questions, quiz_options, quiz_attempts, quiz_attempt_answers, assignments, assignment_submissions, payment_requests, enrollments, activity_logs, plus Laravel defaults. Use foreign keys, indexes, and soft deletes where sensible.

=====================================================
THE 5 PHASES
=====================================================
PHASE 1 — Foundation, Branding, Auth, Policy Gate & Roles
  Laravel setup on XAMPP/MySQL; layouts + Bootstrap theme; dynamic logo/academy info; register/login/logout/password reset; mandatory Policy checkbox + policy_acceptances; seed the 4 staff accounts; role middleware + Policies (including the Super Admin mutual-deletion protection and the "only 4 staff accounts" guard); admin shell + Settings (academy info, logo, Policy editor); staff management; activity logs.
  Tables: users, academy_settings, policies, policy_acceptances, activity_logs, Laravel defaults.

PHASE 2 — Course Management, Content Upload & Public Home Page
  Course CRUD (price, thumbnail, featured max 3); lesson/video upload with ordering (php.ini limits explained); quiz builder; assignment builder; Teacher ownership rules (cannot delete Super Admin courses); public Home page with 3 featured courses; public course details; Student browse page with all courses locked.
  Tables: courses, lessons, quizzes, quiz_questions, quiz_options, assignments.

PHASE 3 — InstaPay Payments & Time-Limited Enrollments
  QR upload; Subscribe page with QR + prefilled form + screenshot; payment_requests review (approve/reject); Enrollment manager (student + course + duration; extend/revoke); CourseAccess service/middleware; `enrollments:expire` command + scheduler (+ XAMPP Task Scheduler instructions); unlocked/locked UI with expiry badges.
  Tables: payment_requests, enrollments.

PHASE 4 — Student Learning Experience
  Drip-lock engine; secure Range-enabled video streaming; progress tracking (completion threshold 90%, resume position, anti-abuse validation); quiz taking/grading; assignment submission; lesson sidebar with ✔ / ▶ / 🔒 states; expiry handling mid-session.
  Tables: lesson_progress, quiz_attempts, quiz_attempt_answers, assignment_submissions.

PHASE 5 — Analytics, Support Views, Hardening & Final QA
  Analytics dashboard (videos watched, completion %, quiz scores, assignment Submitted/Pending); read-only Customer Support module; filters/export (if approved); security + performance hardening; full end-to-end QA; README, role guide, and handover documentation.

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
| 4 | Video completion threshold | 90 % watched |
| 5 | After an enrollment expires, is previous progress kept? | Yes, kept and resumed on renewal |
| 6 | Quiz retake rule (unlimited / limited)? | Unlimited retakes, best score kept |
| 7 | Does a required quiz block the next video? | Optional per quiz (`is_required_for_next`) |
| 8 | May Teachers see analytics for their own courses? | No (per the original requirements) |
| 9 | Max video size / hosting limits on the final server | Set via `php.ini` locally; revisit before production |
| 10 | Email verification on sign-up needed? | Not required |

---

*End of plan. Next step: paste the Master Prompt (Section 3) into Andy Craft, then approve each phase explicitly as it is delivered.*
