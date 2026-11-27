<?php

namespace App\Http\Controllers;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportChatWidgetController extends Controller
{
    public const GUEST_TOKEN_SESSION_KEY = 'mash_support_chat_guest_token';

    public function state(Request $request): JsonResponse
    {
        $conversation = $this->resolveCurrentConversation($request);

        if (! $conversation) {
            return response()->json([
                'ok' => true,
                'has_conversation' => false,
                'conversation' => null,
                'messages' => [],
                'unread_count' => 0,
            ]);
        }

        if ($request->boolean('mark_read')) {
            $conversation->messages()
                ->where('sender_type', SupportMessage::SENDER_STAFF)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        $messages = $conversation->messages()
            ->with('sender')
            ->get();

        $unreadCount = $messages
            ->where('sender_type', SupportMessage::SENDER_STAFF)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'ok' => true,
            'has_conversation' => true,
            'conversation' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
                'subject' => $conversation->subject,
                'display_name' => $conversation->displayName(),
            ],
            'unread_count' => $unreadCount,
            'messages' => $messages->map(fn (SupportMessage $m): array => $this->formatMessage($m))->values(),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = [
            'message' => ['required', 'string', 'min:1', 'max:2000'],
            'subject' => ['nullable', 'string', 'max:255'],
        ];

        if (! $user) {
            $rules['guest_name'] = ['required', 'string', 'min:2', 'max:120'];
            $rules['guest_email'] = ['required', 'email', 'max:150'];
        }

        $validated = $request->validate($rules);

        $conversation = $this->resolveCurrentConversation($request);

        [$conversation, $msg] = DB::transaction(function () use ($request, $user, $validated, $conversation): array {
            if (! $conversation) {
                $token = null;
                if (! $user) {
                    $token = Str::random(48);
                    $request->session()->put(self::GUEST_TOKEN_SESSION_KEY, $token);
                }

                $conversation = SupportConversation::query()->create([
                    'user_id' => $user?->id,
                    'guest_name' => $user ? null : trim((string) $validated['guest_name']),
                    'guest_email' => $user ? null : trim((string) $validated['guest_email']),
                    'guest_token' => $token,
                    'subject' => isset($validated['subject']) && trim((string) $validated['subject']) !== ''
                        ? trim((string) $validated['subject'])
                        : Str::limit(trim((string) $validated['message']), 80),
                    'status' => SupportConversation::STATUS_OPEN,
                    'last_message_at' => now(),
                ]);
            } else {
                $conversation->status = SupportConversation::STATUS_OPEN;
                $conversation->last_message_at = now();
                $conversation->save();
            }

            $msg = SupportMessage::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user?->id,
                'sender_type' => $user ? SupportMessage::SENDER_USER : SupportMessage::SENDER_GUEST,
                'message' => trim((string) $validated['message']),
                'is_read' => false,
            ]);

            return [$conversation, $msg];
        });

        $msg->load('sender', 'conversation');

        return response()->json([
            'ok' => true,
            'conversation' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
                'subject' => $conversation->subject,
                'display_name' => $conversation->displayName(),
            ],
            'message' => $this->formatMessage($msg),
        ], 201);
    }

    public function storeMessage(Request $request): JsonResponse
    {
        $conversation = $this->resolveCurrentConversation($request);
        if (! $conversation) {
            return response()->json([
                'ok' => false,
                'message' => 'Please start a support conversation first.',
            ], 422);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $user = $request->user();

        $msg = DB::transaction(function () use ($conversation, $user, $validated): SupportMessage {
            $conversation->status = SupportConversation::STATUS_OPEN;
            $conversation->last_message_at = now();
            $conversation->save();

            return SupportMessage::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user?->id,
                'sender_type' => $user ? SupportMessage::SENDER_USER : SupportMessage::SENDER_GUEST,
                'message' => trim((string) $validated['message']),
                'is_read' => false,
            ]);
        });

        $msg->load('sender', 'conversation');

        return response()->json([
            'ok' => true,
            'conversation' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
            ],
            'message' => $this->formatMessage($msg),
        ], 201);
    }

    private function resolveCurrentConversation(Request $request): ?SupportConversation
    {
        $user = $request->user();
        if ($user) {
            return SupportConversation::query()
                ->where('user_id', $user->id)
                ->orderByRaw("CASE status WHEN 'open' THEN 0 ELSE 1 END ASC")
                ->latest('last_message_at')
                ->latest('id')
                ->first();
        }

        $token = (string) $request->session()->get(self::GUEST_TOKEN_SESSION_KEY, '');
        if ($token === '') {
            return null;
        }

        return SupportConversation::query()
            ->whereNull('user_id')
            ->where('guest_token', $token)
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMessage(SupportMessage $m): array
    {
        return [
            'id' => $m->id,
            'sender_type' => $m->sender_type,
            'sender_name' => $m->senderDisplayName(),
            'is_staff' => $m->isFromStaff(),
            'message' => $m->message,
            'created_at' => $m->created_at?->format('M d, H:i'),
            'time_ago' => $m->created_at?->diffForHumans(),
        ];
    }
}
