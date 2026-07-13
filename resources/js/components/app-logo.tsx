import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <span className="text-xl font-bold tracking-tight group-data-[collapsible=icon]:hidden">
                OpenPRS
            </span>
            <AppLogoIcon className="hidden size-5 fill-current text-sidebar-foreground group-data-[collapsible=icon]:block" />
        </>
    );
}
