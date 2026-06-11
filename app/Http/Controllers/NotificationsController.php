<?php

namespace App\Http\Controllers;

use App\Models\ActivityNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationsController extends Controller
{
    public static function unreadCount(): int
    {
        if (!Auth::check()) {
            return 0;
        }

        return ActivityNotification::where('user_id', Auth::id())
            ->where('seen', false)
            ->count();
    }

    /**
     * Returns the 20 most recent notifications as JSON and marks them all as seen.
     */
    public function feed(Request $request)
    {
        $notifications = ActivityNotification::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->take(20)
            ->get();

        // Mark all as seen
        ActivityNotification::where('user_id', Auth::id())
            ->where('seen', false)
            ->update(['seen' => true]);

        $html = view('notifications.feed', compact('notifications'))->render();

        return response()->json(['html' => $html]);
    }
}
