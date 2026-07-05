<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = AdminNotification::latest()->paginate(30);

        // Opening the list marks everything as read.
        AdminNotification::unread()->update(['read_at' => now()]);

        return view('admin.notifications.index', compact('notifications'));
    }

    public function markAllRead()
    {
        AdminNotification::unread()->update(['read_at' => now()]);

        return back()->with('success', 'Notifications marquées comme lues.');
    }
}
