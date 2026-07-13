import { Link } from '@inertiajs/react';
import {
    Building2,
    FileText,
    GitBranch,
    Package,
    Ruler,
    ScrollText,
    Shield,
    Truck,
    Users,
} from 'lucide-react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { usePermissions } from '@/hooks/use-permissions';
import { cn, toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

type ConfigNavItem = NavItem & { permission: string };

type NavGroup = {
    label: string;
    items: ConfigNavItem[];
};

const navGroups: NavGroup[] = [
    {
        label: 'Workflow Configuration',
        items: [
            {
                title: 'Workflows',
                href: '/admin/config/workflows',
                icon: GitBranch,
                permission: 'config.workflows.manage',
            },
        ],
    },
    {
        label: 'System Settings',
        items: [
            {
                title: 'Company Profile',
                href: '/admin/config/company-profile',
                icon: Building2,
                permission: 'config.company_profile.manage',
            },
            {
                title: 'Documents',
                href: '/admin/config/documents',
                icon: FileText,
                permission: 'config.documents.manage',
            },
        ],
    },
    {
        label: 'Organizational',
        items: [
            {
                title: 'Departments',
                href: '/admin/config/departments',
                icon: Package,
                permission: 'config.departments.manage',
            },
            {
                title: 'Users',
                href: '/admin/config/users',
                icon: Users,
                permission: 'access.users.manage',
            },
            {
                title: 'Roles',
                href: '/admin/config/roles',
                icon: Shield,
                permission: 'access.roles.manage',
            },
        ],
    },
    {
        label: 'Master Data',
        items: [
            {
                title: 'Suppliers',
                href: '/admin/config/suppliers',
                icon: Truck,
                permission: 'config.suppliers.manage',
            },
            {
                title: 'Item Units',
                href: '/admin/config/item-units',
                icon: Ruler,
                permission: 'config.item_units.manage',
            },
        ],
    },
    {
        label: 'Monitoring',
        items: [
            {
                title: 'Access Logs',
                href: '/admin/config/access-logs',
                icon: ScrollText,
                permission: 'access.logs.view',
            },
        ],
    },
];

export default function AdminConfigLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { can } = usePermissions();

    if (typeof window === 'undefined') {
        return null;
    }

    const visibleGroups = navGroups
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => can(item.permission)),
        }))
        .filter((group) => group.items.length > 0);

    return (
        <div className="px-4 py-8 md:px-6">
            <Heading
                title="System Configuration"
                description="Manage workflows, company settings, and master data"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl shrink-0 lg:w-56">
                    <nav
                        className="flex flex-col space-y-4"
                        aria-label="Admin Configuration"
                    >
                        {visibleGroups.map((group) => (
                            <div key={group.label}>
                                <p className="mb-1.5 px-3 text-[10px] font-semibold tracking-widest text-muted-foreground/60 uppercase">
                                    {group.label}
                                </p>
                                <div className="flex flex-col space-y-0.5">
                                    {group.items.map((item, index) => (
                                        <Button
                                            key={`${toUrl(item.href)}-${index}`}
                                            size="sm"
                                            variant="ghost"
                                            asChild
                                            className={cn(
                                                'w-full justify-start gap-2',
                                                {
                                                    'bg-muted font-medium':
                                                        isCurrentOrParentUrl(
                                                            item.href,
                                                        ),
                                                },
                                            )}
                                        >
                                            <Link href={item.href}>
                                                {item.icon && (
                                                    <item.icon className="h-4 w-4 text-muted-foreground" />
                                                )}
                                                {item.title}
                                            </Link>
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="min-w-0 flex-1">
                    <section className="space-y-6">{children}</section>
                </div>
            </div>
        </div>
    );
}
