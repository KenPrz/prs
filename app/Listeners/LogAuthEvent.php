<?php

namespace App\Listeners;

use App\Models\AccessLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;

class LogAuthEvent
{
    public function handleLogin(Login $event): void
    {
        $this->record('login', $event->user->getAuthIdentifier());
    }

    public function handleLogout(Logout $event): void
    {
        $this->record('logout', $event->user?->getAuthIdentifier());
    }

    public function handleFailed(Failed $event): void
    {
        $this->record('failed_login', null, $event->credentials['email'] ?? null);
    }

    private function record(string $event, ?int $userId, ?string $path = null): void
    {
        $request = request();

        AccessLog::create([
            'user_id' => $userId,
            'event' => $event,
            'path' => $path ?? $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
        ];
    }
}
