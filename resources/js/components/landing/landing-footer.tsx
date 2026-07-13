import { Link } from '@inertiajs/react';
import { login } from '@/routes';

export function LandingFooter() {
    return (
        <footer className="border-t border-border bg-background">
            <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 py-8 sm:flex-row sm:px-6">
                <Link href="/" className="flex items-center">
                    <span className="text-base font-bold tracking-tight">
                        OpenPRS
                    </span>
                </Link>
                <p className="text-xs text-muted-foreground">
                    © {new Date().getFullYear()} OpenPRS. All rights reserved.
                </p>
                <Link
                    href={login()}
                    className="text-xs text-muted-foreground transition-colors hover:text-foreground"
                >
                    Log in
                </Link>
            </div>
        </footer>
    );
}
