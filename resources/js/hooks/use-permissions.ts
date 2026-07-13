import { usePage } from '@inertiajs/react';
import type { Auth } from '@/types';

/**
 * Permission checks for the current user. Super Admins pass every check
 * (mirroring the backend Gate::before), otherwise the user must hold the
 * permission explicitly (shared as `auth.permissions`).
 */
export function usePermissions() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const permissions = auth?.permissions ?? [];
    const isSuperAdmin = auth?.is_super_admin ?? false;

    const can = (permission: string): boolean =>
        isSuperAdmin || permissions.includes(permission);

    const canAny = (...perms: string[]): boolean =>
        isSuperAdmin || perms.some((p) => permissions.includes(p));

    return { can, canAny, isSuperAdmin };
}
