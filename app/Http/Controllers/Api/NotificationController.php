<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $page = AdminNotification::latest()->paginate(30);

        return response()->json([
            'notifications' => collect($page->items())->map(fn ($n) => [
                'id'    => $n->id,
                'type'  => $n->type,
                'icon'  => $n->icon,
                'title' => $n->title,
                'body'  => $n->body,
                'read'  => $n->read_at !== null,
                'at'    => $n->created_at->toIso8601String(),
            ]),
            'unread'   => AdminNotification::unread()->count(),
            'has_more' => $page->hasMorePages(),
            'page'     => $page->currentPage(),
        ]);
    }

    public function markAllRead()
    {
        AdminNotification::unread()->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
