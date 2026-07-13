import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted/50 p-6 md:p-10 dark:bg-muted/10">
            <div className="flex w-full max-w-md flex-col gap-6">
                <Link href={home()} className="flex items-center self-center">
                    <span className="text-xl font-bold tracking-tight">
                        OpenPRS
                    </span>
                </Link>

                <div className="flex flex-col gap-6">
                    <Card className="rounded-xl border-border/40 shadow-lg">
                        <CardHeader className="px-10 pt-8 pb-0 text-center">
                            <CardTitle className="text-xl font-bold tracking-tight">
                                {title}
                            </CardTitle>
                            <CardDescription>{description}</CardDescription>
                        </CardHeader>
                        <CardContent className="px-10 py-8">
                            {children}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
}
