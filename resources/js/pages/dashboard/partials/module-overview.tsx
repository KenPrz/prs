import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';

export interface ModuleOverview {
    key: string;
    label: string;
    href: string;
    in_review: number;
}

export function ModuleOverviewStrip({
    modules,
}: {
    modules: ModuleOverview[];
}) {
    if (modules.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {modules.map((module) => (
                <Card
                    key={module.key}
                    className="border-border/60 transition-shadow hover:shadow-md"
                >
                    <CardContent className="pt-6">
                        <Link
                            href={module.href}
                            className="group flex items-start justify-between"
                        >
                            <div className="space-y-1">
                                <p className="text-xs font-medium tracking-wide text-muted-foreground">
                                    {module.label}
                                </p>
                                <p className="text-2xl font-bold tracking-tight text-foreground">
                                    {module.in_review}
                                </p>
                                <p className="text-xs text-muted-foreground/70">
                                    In review
                                </p>
                            </div>
                            <ArrowUpRight className="h-4 w-4 text-muted-foreground/40 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-foreground" />
                        </Link>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
