import { Link } from '@inertiajs/react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-muted/50 p-6 md:p-10 dark:bg-muted/10">
            <div className="w-full max-w-md">
                <div className="mb-8 flex flex-col items-center justify-center">
                    <Link
                        href={home()}
                        className="flex items-center font-medium"
                    >
                        <span className="text-2xl font-bold tracking-tight">
                            OpenPRS
                        </span>
                    </Link>
                </div>

                <Card className="w-full border-border/40 shadow-lg">
                    <CardHeader className="pb-4 text-center">
                        <CardTitle className="text-2xl font-bold tracking-tight">
                            {title}
                        </CardTitle>
                        <CardDescription className="text-[13px]">
                            {description}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>{children}</CardContent>
                </Card>

                <p className="mt-6 text-center text-[11px] font-medium tracking-widest text-muted-foreground/50 uppercase">
                    Procurement Request System
                </p>
            </div>
        </div>
    );
}
