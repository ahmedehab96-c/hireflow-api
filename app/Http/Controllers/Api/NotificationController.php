<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/** The current user's in-app notifications. Always scoped through $user->notifications(). */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unread' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $request->user();
        $page = ($validated['unread'] ?? false ? $user->unreadNotifications() : $user->notifications())
            ->paginate($validated['per_page'] ?? 15);

        return ApiResponse::success([
            'items' => collect($page->items())->map(fn (DatabaseNotification $n) => $this->present($n)),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        // Looked up through the user's own notifications, so another user's id is a 404.
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return ApiResponse::success($this->present($item), 'Notification marked as read.');
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return ApiResponse::success(message: 'All notifications marked as read.');
    }

    /** @return array<string, mixed> */
    private function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'kind' => $notification->data['kind'] ?? null,
            'title' => $notification->data['title'] ?? '',
            'message' => $notification->data['message'] ?? '',
            'subject' => $notification->data['subject'] ?? null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
