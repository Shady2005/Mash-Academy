<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $student = $request->user();
        $latestAcceptance = $student->policyAcceptances()
            ->with('policy')
            ->latest('accepted_at')
            ->first();

        return view('student.dashboard', [
            'student' => $student,
            'latestAcceptance' => $latestAcceptance,
        ]);
    }
}
