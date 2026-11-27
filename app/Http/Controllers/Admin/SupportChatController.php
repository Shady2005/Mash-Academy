<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportChatController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = trim((string) $request->query('status', 'open'));
        if (! in_array($statusFilter, [SupportConversation::STATUS_OPEN, SupportConversation::STATUS_RESOLVED, 'all'], true)) {
            $statusFilter = SupportConversation::STATUS_OPEN;
        }

        $conversations = SupportConversation::query()
            ->with(['user', 'latestMessage'])
            ->withCount([
                'messages as unread_count' => fn ($q) => $q
                    ->whereIn('sender_type', [SupportMessage::SENDER_USER, SupportMessage::SENDER_GUEST])
                    ->where('is_read', false),
            ])
            ->when(
                $statusFilter !== 'all',
                fn ($q) => $q->where('status', $statusFilter)
            )
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        $activeId = (int) $request->query('conversation', $conversations->first()?->id ?? 0);
        $activeConversation = $activeId > 0
            ? SupportConversation::query()
                ->with([
                    'user.enrollments' => fn ($q) => $q->with('course')->latest(),
                    'messages.sender',
                ])
                ->find($activeId)
            : null;

        if ($activeConversation) {
            // Mark incoming customer messages as read when staff opens this conversation
            $activeConversation->messages()
                ->whereIn('sender_type', [SupportMessage::SENDER_USER, SupportMessage::SENDER_GUEST])
                ->where('is_read', false)
                ->update(['is_read' => true]);

            // Zero out unread_count in sidebar collection for the opened conversation
            $conversations->each(function (SupportConversation $conv) use ($activeConversation): void {
                if ($conv->id === $activeConversation->id) {
                    $conv->unread_count = 0;
                }
            });
        }

        $counts = [
            'open' => SupportConversation::query()->where('status', SupportConversation::STATUS_OPEN)->count(),
            'resolved' => SupportConversation::query()->where('status', SupportConversation::STATUS_RESOLVED)->count(),
        ];

        return view('admin.support-chat.index', [
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'statusFilter' => $statusFilter,
            'counts' => $counts,
        ]);
    }

    public function messages(SupportConversation $conversation): JsonResponse
    {
        $conversation->messages()
            ->whereIn('sender_type', [SupportMessage::SENDER_USER, SupportMessage::SENDER_GUEST])
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = $conversation->messages()
            ->with('sender', 'conversation')
            ->get();

        return response()->json([
            'ok' => true,
            'status' => $conversation->status,
            'messages' => $messages->map(fn (SupportMessage $m): array => [
                'id' => $m->id,
                'sender_type' => $m->sender_type,
                'sender_name' => $m->senderDisplayName(),
                'is_staff' => $m->isFromStaff(),
                'message' => $m->message,
                'created_at' => $m->created_at?->format('M d, H:i'),
                'time_ago' => $m->created_at?->diffForHumans(),
            ])->values(),
        ]);
    }

    public function reply(Request $request, SupportConversation $conversation): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $staff = $request->user();

        $msg = DB::transaction(function () use ($conversation, $staff, $validated): SupportMessage {
            $conversation->messages()
                ->whereIn('sender_type', [SupportMessage::SENDER_USER, SupportMessage::SENDER_GUEST])
                ->where('is_read', false)
                ->update(['is_read' => true]);

            $conversation->last_message_at = now();
            if ($conversation->isResolved()) {
                $conversation->status = SupportConversation::STATUS_OPEN;
            }
            $conversation->save();

            return SupportMessage::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $staff->id,
                'sender_type' => SupportMessage::SENDER_STAFF,
                'message' => trim((string) $validated['message']),
                'is_read' => false,
            ]);
        });

        $msg->load('sender', 'conversation');

        ActivityLogger::log(
            action: 'support_chat.replied',
            subject: $conversation,
            meta: ['message_id' => $msg->id],
            actor: $staff,
            ip: $request->ip()
        );

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'status' => $conversation->status,
                'message' => [
                    'id' => $msg->id,
                    'sender_type' => $msg->sender_type,
                    'sender_name' => $msg->senderDisplayName(),
                    'is_staff' => true,
                    'message' => $msg->message,
                    'created_at' => $msg->created_at?->format('M d, H:i'),
                    'time_ago' => $msg->created_at?->diffForHumans(),
                ],
            ], 201);
        }

        return redirect()
            ->route('admin.support-chat.index', ['conversation' => $conversation->id, 'status' => $conversation->status])
            ->with('status', 'Reply sent.');
    }

    public function updateStatus(Request $request, SupportConversation $conversation): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([SupportConversation::STATUS_OPEN, SupportConversation::STATUS_RESOLVED])],
        ]);

        $conversation->status = $validated['status'];
        $conversation->save();

        ActivityLogger::log(
            action: 'support_chat.status_updated',
            subject: $conversation,
            meta: ['status' => $conversation->status],
            actor: $request->user(),
            ip: $request->ip()
        );

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'status' => $conversation->status,
            ]);
        }

        return redirect()
            ->route('admin.support-chat.index', ['conversation' => $conversation->id, 'status' => $conversation->status])
            ->with('status', $conversation->isResolved() ? 'Conversation marked as Resolved.' : 'Conversation reopened.');
    }
}
