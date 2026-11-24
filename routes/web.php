<?php

use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Admin\AcademySettingsController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AiToolsController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\CertificateController as AdminCertificateController;
use App\Http\Controllers\Admin\ConsultationController as AdminConsultationController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\CoursePackageController;
use App\Http\Controllers\Admin\CourseSubmissionReviewController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EnrollmentController;
use App\Http\Controllers\Admin\FinalProjectController;
use App\Http\Controllers\Admin\InstructorProfileController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\MarketingController;
use App\Http\Controllers\Admin\PaymentRequestController;
use App\Http\Controllers\Admin\PolicySettingsController;
use App\Http\Controllers\Admin\PromoCodeController;
use App\Http\Controllers\Admin\QuizController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SupportStudentController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\ConsultationBookingController;
use App\Http\Controllers\CourseCatalogController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PackageCatalogController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\Student\AssignmentSubmissionController;
use App\Http\Controllers\Student\CertificateController as StudentCertificateController;
use App\Http\Controllers\Student\CourseAiTutorController;
use App\Http\Controllers\Student\CourseBrowseController;
use App\Http\Controllers\Student\CoursePlayerController;
use App\Http\Controllers\Student\CourseQaController;
use App\Http\Controllers\Student\CourseReviewController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\FinalProjectSubmissionController;
use App\Http\Controllers\Student\MyCoursesController;
use App\Http\Controllers\Student\PackageSubscriptionController;
use App\Http\Controllers\Student\QuizAttemptController;
use App\Http\Controllers\Student\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User Preferences (Bilingual EN/AR & Light/Dark Mode)
|--------------------------------------------------------------------------
*/
Route::post('/preferences/locale', [PreferenceController::class, 'setLocale'])->name('preferences.locale');
Route::post('/preferences/theme', [PreferenceController::class, 'setTheme'])->name('preferences.theme');

/*
|--------------------------------------------------------------------------
| Public Routes (Phases 1–5)
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/courses', [CourseCatalogController::class, 'index'])->name('courses.index');
Route::get('/courses/{course:slug}', [CourseCatalogController::class, 'show'])->name('courses.show');
Route::get('/packages/{package:slug}', [PackageCatalogController::class, 'show'])->name('packages.show');
Route::get('/policy', [PolicyController::class, 'show'])->name('policy.show');
Route::get('/certificates/verify', [CertificateVerificationController::class, 'index'])
    ->middleware('throttle:30,1')
    ->name('certificates.verify');
Route::get('/verify-certificate', [CertificateVerificationController::class, 'index'])
    ->middleware('throttle:30,1')
    ->name('verify-certificate');

// 1-on-1 Consultations ("استشارة") — Respects enable_consultations toggle
Route::get('/consultations', [ConsultationBookingController::class, 'index'])->name('consultations.index');

/*
|--------------------------------------------------------------------------
| Guest Authentication Routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Shared Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active'])->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/account/password', [PasswordController::class, 'edit'])->name('account.password.edit');
    Route::put('/account/password', [PasswordController::class, 'update'])->name('account.password.update');

    // In-App Notifications
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // 1-on-1 Consultation Booking submission (Students + Super Admins)
    Route::post('/consultations', [ConsultationBookingController::class, 'store'])->name('consultations.store');
});

/*
|--------------------------------------------------------------------------
| Student Portal Routes (Students + Unrestricted Super Admin 1 & 2 Access)
|--------------------------------------------------------------------------
*/
Route::prefix('student')
    ->name('student.')
    ->middleware(['auth', 'active', 'role:student,super_admin_1,super_admin_2'])
    ->group(function (): void {
        Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
        Route::get('/my-courses', [MyCoursesController::class, 'index'])->name('my-courses.index');
        Route::get('/courses', [CourseBrowseController::class, 'index'])->name('courses.index');

        // Phase 5: My Certificates (Strictly locked until Final Project submitted)
        Route::get('/certificates', [StudentCertificateController::class, 'index'])->name('certificates.index');
        Route::get('/certificates/{course:slug}', [StudentCertificateController::class, 'show'])->name('certificates.show');
        Route::get('/certificates/{course:slug}/download', [StudentCertificateController::class, 'download'])->name('certificates.download');

        // Phase 3: InstaPay Subscription (Single Courses & Course Packages), Promo Code Validation & My Payments
        Route::get('/courses/{course:slug}/subscribe', [SubscriptionController::class, 'create'])->name('courses.subscribe');
        Route::post('/courses/{course:slug}/promo-check', [SubscriptionController::class, 'checkPromo'])
            ->middleware('throttle:20,1')
            ->name('courses.promo-check');
        Route::post('/courses/{course:slug}/subscribe', [SubscriptionController::class, 'store'])->name('courses.subscribe.store');

        Route::get('/packages/{package:slug}/subscribe', [PackageSubscriptionController::class, 'create'])->name('packages.subscribe');
        Route::post('/packages/{package:slug}/promo-check', [PackageSubscriptionController::class, 'checkPromo'])
            ->middleware('throttle:20,1')
            ->name('packages.promo-check');
        Route::post('/packages/{package:slug}/subscribe', [PackageSubscriptionController::class, 'store'])->name('packages.subscribe.store');

        Route::get('/payments', [SubscriptionController::class, 'index'])->name('payments.index');
        Route::get('/payments/{paymentRequest}/screenshot', [SubscriptionController::class, 'screenshot'])->name('payments.screenshot');

        // Phase 3 & Phase 4: Central CourseAccess Middleware-Protected Learning Workspace
        Route::middleware('course.access')->group(function (): void {
            Route::get('/courses/{course:slug}/access', [SubscriptionController::class, 'access'])->name('courses.access');

            // Phase 4: Course Player, Drip-Locked Private Video Stream & 100% Progress API
            Route::get('/courses/{course:slug}/learn/{lesson?}', [CoursePlayerController::class, 'show'])->name('courses.learn');
            Route::get('/courses/{course:slug}/lessons/{lesson}/stream', [CoursePlayerController::class, 'stream'])->name('courses.lessons.stream');
            Route::post('/courses/{course:slug}/lessons/{lesson}/progress', [CoursePlayerController::class, 'recordProgress'])->name('courses.lessons.progress');

            // Phase 4: Quiz Taking & Auto-Grading
            Route::get('/courses/{course:slug}/quizzes/{quiz}', [QuizAttemptController::class, 'show'])->name('courses.quizzes.show');
            Route::post('/courses/{course:slug}/quizzes/{quiz}/submit', [QuizAttemptController::class, 'submit'])->name('courses.quizzes.submit');

            // Phase 4: Flexible Assignment Submission (File / MCQ / Both)
            Route::get('/courses/{course:slug}/assignments/{assignment}', [AssignmentSubmissionController::class, 'show'])->name('courses.assignments.show');
            Route::post('/courses/{course:slug}/assignments/{assignment}/submit', [AssignmentSubmissionController::class, 'submit'])->name('courses.assignments.submit');
            Route::get('/courses/{course:slug}/assignments/{assignment}/file', [AssignmentSubmissionController::class, 'downloadOwnFile'])->name('courses.assignments.file');

            // Course Q&A & Star Reviews (Respects enable_course_qa & enable_course_reviews)
            Route::post('/courses/{course:slug}/questions', [CourseQaController::class, 'storeQuestion'])->name('courses.questions.store');
            Route::post('/courses/{course:slug}/questions/{question}/reply', [CourseQaController::class, 'storeReply'])->name('courses.questions.reply');
            Route::post('/courses/{course:slug}/reviews', [CourseReviewController::class, 'store'])->name('courses.reviews.store');

            // Student AI Course Tutor (Strictly gated by enable_ai_student_tutor — OFF by default)
            Route::post('/courses/{course:slug}/ai-tutor', [CourseAiTutorController::class, 'ask'])
                ->middleware('throttle:20,1')
                ->name('courses.ai-tutor.ask');

            // Phase 4: Final Project (Strictly Locked until >= 95% Course Completion)
            Route::get('/courses/{course:slug}/final-project', [FinalProjectSubmissionController::class, 'show'])->name('courses.final-project.show');
            Route::post('/courses/{course:slug}/final-project/submit', [FinalProjectSubmissionController::class, 'submit'])->name('courses.final-project.submit');
            Route::get('/courses/{course:slug}/final-project/brief', [FinalProjectSubmissionController::class, 'downloadBrief'])->name('courses.final-project.brief');
            Route::get('/courses/{course:slug}/final-project/submission-file', [FinalProjectSubmissionController::class, 'downloadOwnSubmission'])->name('courses.final-project.submission-file');
        });
    });

/*
|--------------------------------------------------------------------------
| Admin Portal Routes (4 Seeded Staff Roles)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'active', 'role:super_admin_1,super_admin_2,teacher,support'])
    ->group(function (): void {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Dashboard Users Management (All 4 staff roles: Add Student + Block/Unblock Policy Enforcement)
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::post('/users/{user}/block', [UserManagementController::class, 'block'])->name('users.block');
        Route::post('/users/{user}/unblock', [UserManagementController::class, 'unblock'])->name('users.unblock');

        // Marketing & Social Media Analytics (View for all 4 staff roles; write endpoints restricted to Super Admins below)
        Route::get('/marketing', [MarketingController::class, 'index'])->name('marketing.index');

        // Phase 5: Analytics, Student Directory & Certificate Verification (Super Admins + Read-Only Support; Teacher blocked)
        Route::middleware('role:super_admin_1,super_admin_2,support')->group(function (): void {
            Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
            Route::get('/analytics/export', [AnalyticsController::class, 'export'])->name('analytics.export');

            Route::get('/students', [SupportStudentController::class, 'index'])->name('students.index');
            Route::get('/students/{student}', [SupportStudentController::class, 'show'])->name('students.show');

            Route::get('/certificates', [AdminCertificateController::class, 'index'])->name('certificates.index');
        });

        // Course & Content Management + Instructor Profile & Personal QR (Super Admins 1 & 2 + Teacher; Support blocked)
        Route::middleware('role:super_admin_1,super_admin_2,teacher')->group(function (): void {
            Route::get('/instructor-profile', [InstructorProfileController::class, 'edit'])->name('instructor-profile.edit');
            Route::put('/instructor-profile', [InstructorProfileController::class, 'update'])->name('instructor-profile.update');
            Route::delete('/instructor-profile/avatar', [InstructorProfileController::class, 'destroyAvatar'])->name('instructor-profile.avatar.destroy');
            Route::delete('/instructor-profile/instapay-qr', [InstructorProfileController::class, 'destroyInstaPayQr'])->name('instructor-profile.instapay-qr.destroy');

            Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
            Route::get('/courses/create', [CourseController::class, 'create'])->name('courses.create');
            Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
            Route::get('/courses/{course}/edit', [CourseController::class, 'edit'])->name('courses.edit');
            Route::put('/courses/{course}', [CourseController::class, 'update'])->name('courses.update');
            Route::delete('/courses/{course}/instapay-qr', [CourseController::class, 'destroyInstaPayQr'])->name('courses.instapay-qr.destroy');
            Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');

            // Lessons & Private Video Streaming Preview
            Route::get('/courses/{course}/lessons', [LessonController::class, 'index'])->name('courses.lessons.index');
            Route::post('/courses/{course}/lessons', [LessonController::class, 'store'])->name('courses.lessons.store');
            Route::put('/courses/{course}/lessons/{lesson}', [LessonController::class, 'update'])->name('courses.lessons.update');
            Route::post('/courses/{course}/lessons/{lesson}/move', [LessonController::class, 'move'])->name('courses.lessons.move');
            Route::delete('/courses/{course}/lessons/{lesson}', [LessonController::class, 'destroy'])->name('courses.lessons.destroy');
            Route::get('/courses/{course}/lessons/{lesson}/stream', [LessonController::class, 'stream'])->name('courses.lessons.stream');

            // Standalone Quiz Builder & AI Question Generator
            Route::get('/courses/{course}/quizzes', [QuizController::class, 'index'])->name('courses.quizzes.index');
            Route::post('/courses/{course}/quizzes', [QuizController::class, 'store'])->name('courses.quizzes.store');
            Route::put('/courses/{course}/quizzes/{quiz}', [QuizController::class, 'update'])->name('courses.quizzes.update');
            Route::delete('/courses/{course}/quizzes/{quiz}', [QuizController::class, 'destroy'])->name('courses.quizzes.destroy');
            Route::post('/courses/{course}/quizzes/{quiz}/questions', [QuizController::class, 'storeQuestion'])->name('courses.quizzes.questions.store');
            Route::delete('/courses/{course}/quizzes/{quiz}/questions/{question}', [QuizController::class, 'destroyQuestion'])->name('courses.quizzes.questions.destroy');
            Route::post('/courses/{course}/quizzes/{quiz}/ai-generate', [AiToolsController::class, 'generateQuizQuestions'])->name('courses.quizzes.ai-generate');
            Route::post('/courses/{course}/quizzes/{quiz}/ai-save', [AiToolsController::class, 'saveGeneratedQuizQuestions'])->name('courses.quizzes.ai-save');

            // Dashboard AI Tool: Bilingual Course Description Generator
            Route::post('/ai/course-description', [AiToolsController::class, 'generateCourseDescription'])->name('ai.course-description');

            // Flexible Assignment Builder (File Upload / MCQ / Both)
            Route::get('/courses/{course}/assignments', [AssignmentController::class, 'index'])->name('courses.assignments.index');
            Route::post('/courses/{course}/assignments', [AssignmentController::class, 'store'])->name('courses.assignments.store');
            Route::put('/courses/{course}/assignments/{assignment}', [AssignmentController::class, 'update'])->name('courses.assignments.update');
            Route::delete('/courses/{course}/assignments/{assignment}', [AssignmentController::class, 'destroy'])->name('courses.assignments.destroy');
            Route::post('/courses/{course}/assignments/{assignment}/questions', [AssignmentController::class, 'storeQuestion'])->name('courses.assignments.questions.store');
            Route::delete('/courses/{course}/assignments/{assignment}/questions/{question}', [AssignmentController::class, 'destroyQuestion'])->name('courses.assignments.questions.destroy');

            // Final Project Configuration (95% Completion Gate)
            Route::get('/courses/{course}/final-project', [FinalProjectController::class, 'edit'])->name('courses.final-project.edit');
            Route::put('/courses/{course}/final-project', [FinalProjectController::class, 'upsert'])->name('courses.final-project.upsert');
            Route::delete('/courses/{course}/final-project', [FinalProjectController::class, 'destroy'])->name('courses.final-project.destroy');
            Route::get('/courses/{course}/final-project/attachment', [FinalProjectController::class, 'downloadAttachment'])->name('courses.final-project.attachment');

            // Admin Instructor Q&A Reply (Teachers for own courses + Super Admins)
            Route::post('/courses/{course}/questions/{question}/reply', [CourseQaController::class, 'storeReply'])->name('courses.questions.reply');

            // 1-on-1 Consultations Management (Super Admins + Instructors)
            Route::get('/consultations', [AdminConsultationController::class, 'index'])->name('consultations.index');
            Route::put('/consultations/{consultation}/status', [AdminConsultationController::class, 'updateStatus'])->name('consultations.status');

            // Phase 4: Teacher/Admin Student Submissions & Quiz Attempts Review
            Route::get('/courses/{course}/submissions', [CourseSubmissionReviewController::class, 'index'])->name('courses.submissions.index');
            Route::get('/courses/{course}/assignment-submissions/{submission}/download', [CourseSubmissionReviewController::class, 'downloadAssignmentFile'])->name('courses.assignment-submissions.download');
            Route::put('/courses/{course}/assignment-submissions/{submission}/review', [CourseSubmissionReviewController::class, 'reviewAssignment'])->name('courses.assignment-submissions.review');
            Route::get('/courses/{course}/final-project-submissions/{submission}/download', [CourseSubmissionReviewController::class, 'downloadFinalProjectFile'])->name('courses.final-project-submissions.download');
            Route::put('/courses/{course}/final-project-submissions/{submission}/review', [CourseSubmissionReviewController::class, 'reviewFinalProject'])->name('courses.final-project-submissions.review');
        });

        // Super Admin 1 & Super Admin 2 Only (Write Controls: Packages, Marketing, Payments, Promo Codes, Enrollments, Cert Revocation, Settings, Staff, Logs)
        Route::middleware('role:super_admin_1,super_admin_2')->group(function (): void {
            // Consultation Settings (Super Admins 1 & 2 Only)
            Route::put('/consultations/settings', [AdminConsultationController::class, 'updateSettings'])->name('consultations.settings.update');
            Route::delete('/consultations/instapay-qr', [AdminConsultationController::class, 'destroyInstaPayQr'])->name('consultations.instapay-qr.destroy');

            // Course Packages / Bundles Management
            Route::get('/packages', [CoursePackageController::class, 'index'])->name('packages.index');
            Route::get('/packages/create', [CoursePackageController::class, 'create'])->name('packages.create');
            Route::post('/packages', [CoursePackageController::class, 'store'])->name('packages.store');
            Route::get('/packages/{package}/edit', [CoursePackageController::class, 'edit'])->name('packages.edit');
            Route::put('/packages/{package}', [CoursePackageController::class, 'update'])->name('packages.update');
            Route::patch('/packages/{package}/toggle-publish', [CoursePackageController::class, 'togglePublish'])->name('packages.toggle-publish');
            Route::delete('/packages/{package}/instapay-qr', [CoursePackageController::class, 'destroyInstaPayQr'])->name('packages.instapay-qr.destroy');
            Route::delete('/packages/{package}', [CoursePackageController::class, 'destroy'])->name('packages.destroy');

            // Marketing & Social Media Analytics Write & Sync Controls
            Route::post('/marketing', [MarketingController::class, 'store'])->name('marketing.store');
            Route::put('/marketing/settings', [MarketingController::class, 'saveSettings'])->name('marketing.settings.update');
            Route::put('/marketing/{marketingAccount}/metrics', [MarketingController::class, 'updateMetrics'])->name('marketing.metrics.update');
            Route::post('/marketing/{marketingAccount}/sync', [MarketingController::class, 'sync'])->name('marketing.sync');
            Route::delete('/marketing/{marketingAccount}', [MarketingController::class, 'destroy'])->name('marketing.destroy');

            // Phase 5: Certificate Revoke / Reinstate
            Route::post('/certificates/{certificate}/revoke', [AdminCertificateController::class, 'revoke'])->name('certificates.revoke');
            Route::post('/certificates/{certificate}/reinstate', [AdminCertificateController::class, 'reinstate'])->name('certificates.reinstate');

            // Phase 3: Payments Review Inbox
            Route::get('/payments', [PaymentRequestController::class, 'index'])->name('payments.index');
            Route::get('/payments/{paymentRequest}/screenshot', [PaymentRequestController::class, 'screenshot'])->name('payments.screenshot');
            Route::post('/payments/{paymentRequest}/approve', [PaymentRequestController::class, 'approve'])->name('payments.approve');
            Route::post('/payments/{paymentRequest}/reject', [PaymentRequestController::class, 'reject'])->name('payments.reject');

            // Phase 3: Promo Code Manager
            Route::get('/promo-codes', [PromoCodeController::class, 'index'])->name('promo-codes.index');
            Route::post('/promo-codes', [PromoCodeController::class, 'store'])->name('promo-codes.store');
            Route::put('/promo-codes/{promoCode}', [PromoCodeController::class, 'update'])->name('promo-codes.update');
            Route::patch('/promo-codes/{promoCode}/toggle', [PromoCodeController::class, 'toggle'])->name('promo-codes.toggle');
            Route::delete('/promo-codes/{promoCode}', [PromoCodeController::class, 'destroy'])->name('promo-codes.destroy');

            // Phase 3: Time-Limited Enrollment Manager
            Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
            Route::post('/enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
            Route::post('/enrollments/{enrollment}/extend', [EnrollmentController::class, 'extend'])->name('enrollments.extend');
            Route::post('/enrollments/{enrollment}/revoke', [EnrollmentController::class, 'revoke'])->name('enrollments.revoke');

            // Settings (Academy Info, Logo, InstaPay QR, AI Integration, Policy)
            Route::get('/settings', [AcademySettingsController::class, 'edit'])->name('settings.edit');
            Route::put('/settings', [AcademySettingsController::class, 'update'])->name('settings.update');
            Route::delete('/settings/logo', [AcademySettingsController::class, 'destroyLogo'])->name('settings.logo.destroy');
            Route::put('/settings/instapay-qr', [AcademySettingsController::class, 'updateInstaPayQr'])->name('settings.instapay-qr.update');
            Route::delete('/settings/instapay-qr', [AcademySettingsController::class, 'destroyInstaPayQr'])->name('settings.instapay-qr.destroy');
            Route::put('/settings/ai', [AcademySettingsController::class, 'updateAi'])->name('settings.ai.update');
            Route::delete('/settings/ai-key', [AcademySettingsController::class, 'destroyAiKey'])->name('settings.ai-key.destroy');

            // Super Admin AI Sandbox / Preview Tester
            Route::get('/ai-assistant', [AiToolsController::class, 'sandboxIndex'])->name('ai-assistant.index');
            Route::post('/ai-assistant/ask', [AiToolsController::class, 'sandboxAsk'])->name('ai-assistant.ask');

            Route::get('/settings/policy', [PolicySettingsController::class, 'edit'])->name('settings.policy.edit');
            Route::post('/settings/policy', [PolicySettingsController::class, 'store'])->name('settings.policy.store');

            Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
            Route::put('/staff/{staff}/reset-password', [StaffController::class, 'resetPassword'])->name('staff.reset-password');
            Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');

            Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        });
    });
