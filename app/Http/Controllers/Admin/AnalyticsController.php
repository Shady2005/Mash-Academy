<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'course_id' => $request->query('course_id'),
            'status' => trim((string) $request->query('status', '')),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
            'q' => trim((string) $request->query('q', '')),
        ];

        $summary = $this->analytics->summaryMetrics();
        $courseReport = $this->analytics->courseReport();
        $chartData = $this->analytics->chartData($courseReport);
        $studentRows = $this->analytics->studentProgressTable($filters, 15, expireFirst: false);
        $courses = $courseReport->pluck('course');

        return view('admin.analytics.index', [
            'summary' => $summary,
            'courseReport' => $courseReport,
            'chartData' => $chartData,
            'studentRows' => $studentRows,
            'courses' => $courses,
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = [
            'course_id' => $request->query('course_id'),
            'status' => trim((string) $request->query('status', '')),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
            'q' => trim((string) $request->query('q', '')),
        ];

        $rows = $this->analytics->studentProgressTable($filters, 1000)->getCollection();
        $filename = 'mash-academy-analytics-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'Student Name',
                'Email',
                'Phone',
                'Course',
                'Enrollment Status',
                'Expires At',
                'Videos Watched',
                'Total Videos',
                'Completion %',
                'Quiz Avg %',
                'Quiz Best %',
                'Assignment Status',
                'Final Project Status',
                'Certificate Code',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['student']?->name ?? 'Deleted',
                    $row['student']?->email ?? '',
                    $row['student']?->phone ?? '',
                    $row['course']?->title ?? 'Deleted',
                    ucfirst($row['effective_status']),
                    $row['enrollment']->expires_at->format('Y-m-d H:i'),
                    $row['videos_watched'],
                    $row['total_videos'],
                    $row['completion_percentage'],
                    $row['quiz_avg'] ?? 'N/A',
                    $row['quiz_best'] ?? 'N/A',
                    $row['assignment_status'],
                    $row['final_project_status'],
                    $row['certificate']?->isValid() ? $row['certificate']->certificate_code : 'Locked',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
