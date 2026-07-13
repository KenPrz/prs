<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccessLogConfigController extends Controller
{
    /**
     * Display a filterable listing of access-log entries.
     */
    public function __invoke(Request $request): Response
    {
        $query = AccessLog::query()
            ->with('user:id,name,email')
            ->latest('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // 'route' means route hits (no auth event); otherwise match the event.
        if ($request->filled('event')) {
            $request->input('event') === 'route'
                ? $query->whereNull('event')
                : $query->where('event', $request->input('event'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        return Inertia::render('admin/config/access-logs/index', [
            'logs' => $query->paginate($request->input('per_page', 20))->withQueryString(),
            'filters' => $request->only(['user_id', 'event', 'from', 'to']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
