<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::query()
            ->where('user_id', $request->user()->id)
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(fn (Notification $notification): array => [
                'id' => $notification->id,
                'type' => $notification->type?->value ?? (string) $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'data' => $notification->data,
                'is_read' => $notification->is_read,
                'created_at' => $notification->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => $notifications,
            'unread_count' => Notification::query()
                ->where('user_id', $request->user()->id)
                ->where('is_read', false)
                ->count(),
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $record = Notification::query()
            ->where('id', $notification)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $record->update(['is_read' => true]);

        return response()->json([
            'message' => 'Notification marquée comme lue.',
            'notification' => [
                'id' => $record->id,
                'is_read' => true,
            ],
        ]);
    }
}
