<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Support\Presenters\NotificationPresenter;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user's own notifications only.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->limit(20)->get();

        return ApiResponse::success(NotificationPresenter::list($user, $notifications), [
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->first();
        abort_if($item === null, 404);

        $item->markAsRead();

        return ApiResponse::success(['id' => $item->id, 'read' => true], [
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }
}
