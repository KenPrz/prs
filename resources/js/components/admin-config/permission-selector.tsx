import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { describePermission } from '@/lib/permission-descriptions';
import type { PermissionConfig } from '@/types';

/**
 * Groups the flat permission list by domain (the segment before the first dot)
 * and renders a titled section of checkboxes per domain, with a per-group
 * select-all toggle. Permissions themselves are defined in code (RBAC seeder);
 * this only assigns existing ones to a role.
 */

const DOMAIN_LABELS: Record<string, string> = {
    pr: 'Purchase Requisition',
    po: 'Purchase Order',
    rr: 'Receiving Report',
    prf: 'Payment Request Form',
    workflow: 'Workflow',
    config: 'System Configuration',
    access: 'Access Control',
};

// Display order of domains.
const DOMAIN_ORDER = ['pr', 'po', 'rr', 'prf', 'workflow', 'config', 'access'];

type Group = { domain: string; label: string; permissions: PermissionConfig[] };

function groupByDomain(permissions: PermissionConfig[]): Group[] {
    const buckets = new Map<string, PermissionConfig[]>();

    for (const permission of permissions) {
        const domain = permission.name.split('.')[0];
        const list = buckets.get(domain) ?? [];
        list.push(permission);
        buckets.set(domain, list);
    }

    const order = (domain: string) => {
        const idx = DOMAIN_ORDER.indexOf(domain);

        return idx === -1 ? DOMAIN_ORDER.length : idx;
    };

    return [...buckets.entries()]
        .sort(([a], [b]) => order(a) - order(b) || a.localeCompare(b))
        .map(([domain, perms]) => ({
            domain,
            label: DOMAIN_LABELS[domain] ?? domain.toUpperCase(),
            permissions: [...perms].sort((a, b) =>
                a.name.localeCompare(b.name),
            ),
        }));
}

type Props = {
    availablePermissions: PermissionConfig[];
    selected: string[];
    onToggle: (name: string) => void;
    onSetGroup: (names: string[], checked: boolean) => void;
};

export function PermissionSelector({
    availablePermissions,
    selected,
    onToggle,
    onSetGroup,
}: Props) {
    if (availablePermissions.length === 0) {
        return (
            <p className="text-sm text-muted-foreground italic">
                No permissions are defined.
            </p>
        );
    }

    const groups = groupByDomain(availablePermissions);

    return (
        <div className="space-y-6">
            {groups.map((group) => {
                const names = group.permissions.map((p) => p.name);
                const allChecked = names.every((n) => selected.includes(n));

                return (
                    <div key={group.domain} className="space-y-3">
                        <div className="flex items-center justify-between border-b border-border/60 pb-1.5">
                            <h4 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                {group.label}
                            </h4>
                            <button
                                type="button"
                                className="text-xs font-medium text-primary underline-offset-4 hover:underline"
                                onClick={() => onSetGroup(names, !allChecked)}
                            >
                                {allChecked ? 'Clear all' : 'Select all'}
                            </button>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            {group.permissions.map((permission) => (
                                <div
                                    key={permission.id}
                                    className="flex items-center space-x-2"
                                >
                                    <Checkbox
                                        id={`perm-${permission.id}`}
                                        checked={selected.includes(
                                            permission.name,
                                        )}
                                        onCheckedChange={() =>
                                            onToggle(permission.name)
                                        }
                                    />
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Label
                                                htmlFor={`perm-${permission.id}`}
                                                className="cursor-pointer font-mono text-xs leading-none font-medium"
                                            >
                                                {permission.name}
                                            </Label>
                                        </TooltipTrigger>
                                        {describePermission(
                                            permission.name,
                                        ) && (
                                            <TooltipContent side="top">
                                                {describePermission(
                                                    permission.name,
                                                )}
                                            </TooltipContent>
                                        )}
                                    </Tooltip>
                                </div>
                            ))}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
