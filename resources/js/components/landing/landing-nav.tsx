import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';
import type { User } from '@/types';

type Props = {
    user: User | null;
};

export function LandingNav({ user }: Props) {
    return (
        <header className="sticky top-0 z-50 border-b border-border bg-background/95 backdrop-blur-sm">
            <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
                <Link href="/" className="flex items-center">
                    <span className="text-xl font-bold tracking-tight">
                        OpenPRS
                    </span>
                </Link>

                {user ? (
                    <Button asChild size="sm" variant="outline">
                        <Link href={dashboard()}>Go to Dashboard</Link>
                    </Button>
                ) : (
                    <Button asChild size="sm" variant="outline">
                        <Link href={login()}>Log in</Link>
                    </Button>
                )}
            </div>
        </header>
    );
}
