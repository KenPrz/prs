import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            {/* Carbon inverse panel: flat charcoal, no gradients */}
            <div className="relative hidden h-full flex-col bg-sidebar p-10 text-sidebar-foreground lg:flex dark:border-r dark:border-sidebar-border">
                <Link
                    href={home()}
                    className="relative z-20 flex items-center text-lg font-normal"
                >
                    <AppLogoIcon className="mr-2 size-8 fill-current text-primary" />
                    {name}
                </Link>
                <div className="relative z-20 mt-auto">
                    <p className="text-sm font-normal text-sidebar-foreground/60">
                        Procurement Request System
                    </p>
                </div>
            </div>
            <div className="w-full bg-background lg:p-8">
                <div className="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <Link
                        href={home()}
                        className="relative z-20 flex items-center justify-center lg:hidden"
                    >
                        <span className="text-xl font-bold tracking-tight">
                            OpenPRS
                        </span>
                    </Link>
                    <div className="flex flex-col items-start gap-2 text-left sm:items-center sm:text-center">
                        <h1 className="text-xl font-bold tracking-tight">
                            {title}
                        </h1>
                        <p className="text-sm text-balance text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
