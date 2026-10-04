<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class CustomerNotificationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('bookings.view'), 403);

        return Inertia::render('notifications/Index', [
            'notifications' => $request->user()->notifications()
                ->orderByDesc('created_at')->orderByDesc('id')->paginate(50)
                ->through(fn (DatabaseNotification $notification): array => $this->summary($notification)),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        abort_unless($request->user()?->can('bookings.view'), 403);
        $owned = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $request->user()->notifications()->whereKey($owned->id)->whereNull('read_at')
            ->update(['read_at' => now()]);

        return redirect()->route('notifications.index');
    }

    /** @return array<string, string|null> */
    private function summary(DatabaseNotification $notification): array
    {
        $data = $notification->data;
        $actionUrl = $data['action_url'] ?? null;

        return [
            'id' => $notification->id,
            'type' => is_string($data['type'] ?? null) ? $data['type'] : 'notification',
            'title' => is_string($data['title'] ?? null) ? $data['title'] : 'Notification',
            'body' => is_string($data['body'] ?? null) ? $data['body'] : '',
            'action_label' => is_string($data['action_label'] ?? null) ? $data['action_label'] : null,
            'action_url' => is_string($actionUrl) && preg_match('#^/(?:bookings|invoices)(?:/|\\?|$)#', $actionUrl) === 1 ? $actionUrl : null,
            'occurred_at' => is_string($data['occurred_at'] ?? null) ? $data['occurred_at'] : $notification->created_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
            'read_at' => $notification->read_at?->toISOString(),
        ];
    }
}
