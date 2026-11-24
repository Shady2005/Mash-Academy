<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function markRead(Request $request, string $notificationId): RedirectResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $notificationId)
            ->first();

        if ($notification) {
            if ($notification->read_at === null) {
                $notification->markAsRead();
            }

            $url = (string) ($notification->data['url'] ?? '');
            if ($url !== '' && (str_starts_with($url, '/') || str_starts_with($url, url('/')))) {
                return redirect()->to($url);
            }
        }

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }
}
