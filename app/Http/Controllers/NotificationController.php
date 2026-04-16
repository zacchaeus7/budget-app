<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()->notifications()->latest()->get()
        );
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

        return response()->json($notification, 201);
    }

    public function show(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        return response()->json($notification);
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

        return response()->json($notification->fresh());
    }

    public function destroy(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        $notification->delete();

        return response()->json(['message' => 'Notification supprimee avec succes.']);
    }
}
