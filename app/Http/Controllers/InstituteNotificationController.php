<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Support\NotificationCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstituteNotificationController extends Controller
{
    public function index(): View
    {
        $notifications = NotificationCenter::visibleQuery()
            ->with('institute')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return view('notifications.index', [
            'notifications' => $notifications,
            'readIds' => NotificationCenter::readIds(),
            'unreadCount' => NotificationCenter::unreadCount(),
        ]);
    }

    public function markRead(Request $request, Notification $notification): RedirectResponse
    {
        NotificationCenter::markAsRead($notification);

        return redirect()->back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        NotificationCenter::markAllRead();

        return redirect()->back()->with('status', mawa_lang('notifications.marked_all_read'));
    }
}
