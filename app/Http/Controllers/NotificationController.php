<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notificationsQuery = $this->notificationsQuery($request)->latest();

        if (! $request->boolean('include_read', false)) {
            $currentSlot = now()->format('Y-m');

            $notificationsQuery->where(function ($query) use ($currentSlot) {
                $query->where(function ($query) {
                    $query->whereNull('read_at')
                        ->where('type', '!=', 'budget_alert');
                })->orWhere(function ($query) use ($currentSlot) {
                    $query->where('type', 'budget_alert')
                        ->where('scheduled_slot', $currentSlot);
                });
            });
        }

        return response()->json([
            'notifications' => $notificationsQuery->get()->map(
                fn (Notification $notification) => $this->formatNotification($notification)
            ),
            'unread_count' => $this->unreadNotificationsCount($request),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:sms,email,whatsapp'],
            'message' => ['required', 'string'],
            'is_sent' => ['nullable', 'boolean'],
        ]);

        $notification = $request->user()->notifications()->create([
            ...$data,
            'is_sent' => $data['is_sent'] ?? false,
        ]);

        return response()->json([
            'message' => 'Notification creee avec succes.',
            'notification' => $this->formatNotification($notification),
            'unread_count' => $this->unreadNotificationsCount($request),
        ], 201);
    }

    public function show(Request $request, Notification $notification)
    {
        abort_unless($this->canAccessNotification($request, $notification), 404);

        return response()->json([
            'notification' => $this->formatNotification($notification),
            'unread_count' => $this->unreadNotificationsCount($request),
        ]);
    }

    public function update(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'type' => ['sometimes', 'required', 'in:sms,email,whatsapp'],
            'message' => ['sometimes', 'required', 'string'],
            'is_sent' => ['sometimes', 'required', 'boolean'],
        ]);

        $notification->update($data);

        return response()->json([
            'message' => 'Notification mise a jour avec succes.',
            'notification' => $this->formatNotification($notification->fresh()),
            'unread_count' => $this->unreadNotificationsCount($request),
        ]);
    }

    public function destroy(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        $notification->delete();

        return response()->json([
            'message' => 'Notification supprimee avec succes.',
            'unread_count' => $this->unreadNotificationsCount($request),
        ]);
    }

    public function markAsRead(Request $request, Notification $notification)
    {
        abort_unless($this->canAccessNotification($request, $notification), 404);

        if ($notification->read_at === null) {
            $notification->update([
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Notification marquee comme lue avec succes.',
            'notification' => $this->formatNotification($notification->fresh()),
            'unread_count' => $this->unreadNotificationsCount($request),
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $updatedCount = $this->notificationsQuery($request)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);

        return response()->json([
            'message' => 'Toutes les notifications ont ete marquees comme lues avec succes.',
            'updated_count' => $updatedCount,
            'unread_count' => $this->unreadNotificationsCount($request),
        ]);
    }

    private function notificationsQuery(Request $request)
    {
        if ($request->user()->role === 'admin') {
            return Notification::query();
        }

        return $request->user()->notifications();
    }

    private function unreadNotificationsCount(Request $request): int
    {
        return $this->notificationsQuery($request)
            ->whereNull('read_at')
            ->count();
    }

    private function canAccessNotification(Request $request, Notification $notification): bool
    {
        return $request->user()->role === 'admin'
            || $notification->user_id === $request->user()->id;
    }

    private function formatNotification(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'user_id' => $notification->user_id,
            'account_id' => $notification->account_id,
            'type' => $notification->type,
            'scheduled_slot' => $notification->scheduled_slot,
            'message' => $notification->message,
            'is_sent' => (bool) $notification->is_sent,
            'is_read' => $notification->read_at !== null,
            'read_at' => $notification->read_at?->toDateTimeString(),
            'sent_at' => $notification->sent_at?->toDateTimeString(),
            'created_at' => $notification->created_at?->toDateTimeString(),
            'updated_at' => $notification->updated_at?->toDateTimeString(),
        ];
    }
}
