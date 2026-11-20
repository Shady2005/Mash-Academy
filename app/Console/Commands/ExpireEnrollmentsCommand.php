<?php

namespace App\Console\Commands;

use App\Services\EnrollmentService;
use Illuminate\Console\Command;

class ExpireEnrollmentsCommand extends Command
{
    protected $signature = 'enrollments:expire';

    protected $description = 'Mark active course enrollments whose expires_at timestamp has passed as expired.';

    public function handle(EnrollmentService $enrollmentService): int
    {
        $expiredCount = $enrollmentService->expireOverdue();

        $this->info("Expired {$expiredCount} overdue enrollment(s).");

        return self::SUCCESS;
    }
}
