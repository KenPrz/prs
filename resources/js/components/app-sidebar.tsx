import { Link } from '@inertiajs/react';
import {
    ClipboardList,
    FileCheck,
    LayoutDashboard,
    Package,
    PhilippinePeso,
    Settings2,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import paymentRequestForms from '@/routes/payment-request-forms';
import purchaseOrders from '@/routes/purchase-orders';
import purchaseRequisitions from '@/routes/purchase-requisitions';
import receivingReports from '@/routes/receiving-reports';
import type { NavItem } from '@/types';

/** Config/access permissions that grant entry to the Administration area. */
const CONFIG_ABILITIES = [
    'config.suppliers.manage',
    'config.departments.manage',
    'config.item_units.manage',
    'config.documents.manage',
    'config.company_profile.manage',
    'config.workflows.manage',
    'access.users.manage',
    'access.roles.manage',
];

export function AppSidebar() {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { can, canAny } = usePermissions();

    const mainNavItems: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutDashboard },
        can('pr.view') && {
            title: 'Purchase Requisitions',
            href: purchaseRequisitions.index.url(),
            icon: ClipboardList,
        },
        can('po.view') && {
            title: 'Purchase Orders',
            href: purchaseOrders.index.url(),
            icon: Package,
        },
        can('rr.view') && {
            title: 'Receiving Reports',
            href: receivingReports.index.url(),
            icon: FileCheck,
        },
        can('prf.view') && {
            title: 'Payment Requests',
            href: paymentRequestForms.index.url(),
            icon: PhilippinePeso,
        },
    ].filter(Boolean) as NavItem[];

    const showAdmin = canAny(...CONFIG_ABILITIES);
    const configActive = isCurrentOrParentUrl('/admin/config');

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />

                {showAdmin && (
                    <SidebarGroup className="mt-2 px-2 py-0">
                        <SidebarGroupLabel className="text-[10px] font-semibold tracking-widest text-sidebar-foreground/40 uppercase">
                            Administration
                        </SidebarGroupLabel>
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={configActive}
                                    tooltip={{
                                        children: 'System Configuration',
                                    }}
                                    className={
                                        configActive
                                            ? 'rounded-l-none border-l-2 border-sidebar-primary bg-sidebar-accent font-medium text-sidebar-accent-foreground'
                                            : ''
                                    }
                                >
                                    <Link href="/admin/config" prefetch>
                                        <Settings2 />
                                        <span>System Configuration</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
