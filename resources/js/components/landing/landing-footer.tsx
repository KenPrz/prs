import { Link } from '@inertiajs/react';
import { login } from '@/routes';

export function LandingFooter() {
    return (
        // Carbon inverse footer — the one sanctioned dark surface
        <footer className="bg-sidebar text-sidebar-foreground">
            <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 py-12 sm:flex-row sm:px-6">
                <Link href="/" className="flex items-center">
                    <span className="text-base font-semibold tracking-tight">
                        OpenPRS
                    </span>
                </Link>
                <p className="text-xs text-sidebar-foreground/60">
                    © {new Date().getFullYear()} OpenPRS. All rights reserved.
                </p>
                <Link
                    href={login()}
                    className="text-xs text-sidebar-foreground/60 transition-colors hover:text-sidebar-foreground"
                >
                    Log in
                </Link>
            </div>
        </footer>
    );
}
