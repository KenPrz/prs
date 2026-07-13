<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureConfigAccess
{
    /**
     * Every admin/config capability. A user may enter the /admin/config area if
     * they hold at least one; each section then enforces its own `can:` gate.
     * Super Admins pass via Gate::before.
     *
     * @var list<string>
     */
    public const ABILITIES = [
        'config.suppliers.manage',
        'config.departments.manage',
        'config.item_units.manage',
        'config.documents.manage',
        'config.company_profile.manage',
        'config.workflows.manage',
        'access.users.manage',
        'access.roles.manage',
        'access.logs.view',
    ];

    /**
     * Abort with 403 unless the user holds at least one admin/config permission.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canAny(self::ABILITIES) ?? false, 403, 'You do not have permission to access system configuration.');

        return $next($request);
    }
}
