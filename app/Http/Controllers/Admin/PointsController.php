<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PointsEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PointsController extends Controller
{
    public function index(Request $request): View
    {
        $events = PointsEvent::query()
            ->with(['user:id,username', 'creator:id,username'])
            ->when($request->filled('user'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('username', $request->string('user'))))
            ->when($request->filled('type'), fn ($q) => $q->where('event_type', $request->string('type')))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.points', [
            'events' => $events,
            'leaders' => User::query()->orderByDesc('points')->limit(10)->get(['id', 'username', 'points', 'is_banned']),
        ]);
    }

    public function toggleBan(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot ban yourself.');
        $user->forceFill(['is_banned' => ! $user->is_banned])->save();

        return back()->with('status', $user->is_banned ? "{$user->username} banned." : "{$user->username} unbanned.");
    }
}
