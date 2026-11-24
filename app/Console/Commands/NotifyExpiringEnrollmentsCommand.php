<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;

class NotifyExpiringEnrollmentsCommand extends Command
{
    protected $signature = 'enrollments:notify-expiring';

    protected $description = 'Notify students in-app 3 days before their course enrollment expires';

    public function __construct(
        private readonly NotificationDispatcher $dispatcher
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = now();
        $cutoff = $now->copy()->addDays(3);

        $expiring = Enrollment::query()
            ->with(['user', 'course'])
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->whereNull('expiry_notified_at')
            ->where('expires_at', '>', $now)
            ->where('expires_at', '<=', $cutoff)
            ->get();

        $count = 0;
        foreach ($expiring as $enrollment) {
            $this->dispatcher->notifyExpiringEnrollment($enrollment);
            $enrollment->expiry_notified_at = $now;
            $enrollment->save();
            $count++;
        }

        $this->info("Sent {$count} expiring enrollment notification(s).");

        return self::SUCCESS;
    }
}
