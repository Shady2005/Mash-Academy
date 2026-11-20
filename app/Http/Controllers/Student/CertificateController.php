<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\CertificateService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function __construct(
        private readonly CertificateService $certificateService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            $courses = Course::query()->published()->with('finalProject')->latest()->get();
            $earnedItems = $courses->map(function (Course $course) use ($user): array {
                $existing = Certificate::query()
                    ->where('user_id', $user->id)
                    ->where('course_id', $course->id)
                    ->first();

                $previewCert = $existing ?: new Certificate([
                    'certificate_code' => 'MA-PREVIEW-'.str_pad((string) $course->id, 4, '0', STR_PAD_LEFT),
                    'issued_at' => now(),
                ]);

                return [
                    'course' => $course,
                    'eligible' => true,
                    'reason' => null,
                    'completion_percentage' => 100.0,
                    'required_percentage' => $course->finalProject?->required_completion_percentage ?? 95,
                    'certificate' => $previewCert,
                    'is_preview' => ! $existing,
                ];
            });

            return view('student.certificates.index', [
                'earnedItems' => $earnedItems,
                'lockedItems' => collect(),
            ]);
        }

        $enrolledCourses = Enrollment::query()
            ->with('course.finalProject')
            ->where('user_id', $user->id)
            ->orderByDesc('expires_at')
            ->get()
            ->pluck('course')
            ->filter()
            ->unique('id')
            ->values();

        $certificateItems = $enrolledCourses->map(function (Course $course) use ($user): array {
            $eval = $this->certificateService->eligibilityFor($user, $course);
            $certificate = $eval['certificate'];

            if ($eval['eligible'] && ! $certificate) {
                $certificate = $this->certificateService->issueIfEligible($user, $course);
            }

            return [
                'course' => $course,
                'eligible' => $eval['eligible'],
                'reason' => $eval['reason'],
                'completion_percentage' => $eval['completion_percentage'],
                'required_percentage' => $eval['required_percentage'],
                'certificate' => $certificate,
                'is_preview' => false,
            ];
        });

        return view('student.certificates.index', [
            'earnedItems' => $certificateItems->filter(fn (array $i): bool => $i['eligible'] && $i['certificate']?->isValid())->values(),
            'lockedItems' => $certificateItems->filter(fn (array $i): bool => ! $i['eligible'] || ! $i['certificate']?->isValid())->values(),
        ]);
    }

    public function show(Request $request, Course $course): View
    {
        $user = $request->user();
        $certificate = $this->resolveCertificateForViewer($request, $course);

        return view('student.certificates.show', [
            'certificate' => $certificate,
            'student' => $user,
            'course' => $course,
        ]);
    }

    public function download(Request $request, Course $course): Response
    {
        $user = $request->user();
        $certificate = $this->resolveCertificateForViewer($request, $course);

        $html = view('student.certificates.printable', [
            'certificate' => $certificate,
            'student' => $user,
            'course' => $course,
        ])->render();

        $filename = "Mash-Academy-Certificate-{$certificate->certificate_code}.html";

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function resolveCertificateForViewer(Request $request, Course $course): Certificate
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            $existing = Certificate::query()
                ->where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->whereNull('revoked_at')
                ->first();

            return $existing ?? new Certificate([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'certificate_code' => 'MA-PREVIEW-'.str_pad((string) $course->id, 4, '0', STR_PAD_LEFT),
                'issued_at' => now(),
            ]);
        }

        $eval = $this->certificateService->eligibilityFor($user, $course);

        if (! $eval['eligible']) {
            abort(403, $eval['reason'] ?? 'Your certificate is locked until you complete the course and submit the Final Project.');
        }

        $certificate = $this->certificateService->issueIfEligible($user, $course, $request->ip());
        abort_unless($certificate && $certificate->isValid(), 403, 'Certificate is unavailable or revoked.');

        return $certificate;
    }
}
