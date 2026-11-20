<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use App\Services\CourseAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly CourseAccessService $courseAccess
    ) {}

    public function index(Request $request): View
    {
        $student = $request->user();
        $latestAcceptance = $student->policyAcceptances()
            ->with('policy')
            ->latest('accepted_at')
            ->first();

        $activeEnrollments = $this->courseAccess->activeEnrollmentsMapFor($student)->load('course');

        $pendingPaymentsCount = PaymentRequest::query()
            ->where('user_id', $student->id)
            ->where('status', PaymentRequest::STATUS_PENDING)
            ->count();

        return view('student.dashboard', [
            'student' => $student,
            'latestAcceptance' => $latestAcceptance,
            'activeEnrollments' => $activeEnrollments,
            'pendingPaymentsCount' => $pendingPaymentsCount,
        ]);
    }
}
