import { Link } from '@inertiajs/react';
import { AlertTriangle, ChevronRight, ClipboardCheck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export interface QueueItem {
    id: number;
    doc_type: string;
    href: string;
    number: string;
    title: string | null;
    step_name: string;
    waiting_since: string;
    due_at: string | null;
    is_overdue: boolean;
}

const DOC_TYPE_STYLES: Record<string, string> = {
    PR: 'bg-blue-50 text-blue-700 border-blue-200/60 dark:bg-blue-950/30 dark:text-blue-400 dark:border-blue-800/50',
    PO: 'bg-violet-50 text-violet-700 border-violet-200/60 dark:bg-violet-950/30 dark:text-violet-400 dark:border-violet-800/50',
    RR: 'bg-amber-50 text-amber-700 border-amber-200/60 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-800/50',
    PRF: 'bg-teal-50 text-teal-700 border-teal-200/60 dark:bg-teal-950/30 dark:text-teal-400 dark:border-teal-800/50',
};

export function ActiveQueue({ items }: { items: QueueItem[] }) {
    return (
        <Card className="flex h-full flex-col">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 border-b border-border/40 pb-3">
                <CardTitle className="text-base font-semibold">
                    Action Queue
                </CardTitle>
                <div className="flex h-6 min-w-6 items-center justify-center rounded-full bg-primary/10 px-1.5 text-[11px] font-bold text-primary">
                    {items.length}
                </div>
            </CardHeader>
            <CardContent className="flex-1 overflow-auto px-0">
                {items.length === 0 ? (
                    <div className="flex h-40 flex-col items-center justify-center gap-3 p-6 text-center">
                        <div className="rounded-full bg-muted/50 p-4">
                            <ClipboardCheck className="h-6 w-6 text-muted-foreground/40" />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            No documents are awaiting your action.
                        </p>
                    </div>
                ) : (
                    <div className="divide-y divide-border/40">
                        {items.map((item) => (
                            <Link
                                key={item.id}
                                href={item.href}
                                className="group flex items-center gap-4 px-6 py-3.5 transition-colors hover:bg-accent/50"
                            >
                                <Badge
                                    variant="outline"
                                    className={`h-5 w-11 shrink-0 justify-center px-1 text-[10px] font-bold ${DOC_TYPE_STYLES[item.doc_type] ?? ''}`}
                                >
                                    {item.doc_type}
                                </Badge>
                                <div className="min-w-0 flex-1 space-y-1">
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-semibold">
                                            {item.number}
                                        </span>
                                        {item.title && (
                                            <span className="truncate text-sm text-muted-foreground">
                                                {item.title}
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-muted-foreground/70">
                                        <span className="font-medium text-primary">
                                            {item.step_name}
                                        </span>
                                        <span className="h-1 w-1 rounded-full bg-muted-foreground/30" />
                                        <span>
                                            Assigned {item.waiting_since}
                                        </span>
                                        {item.is_overdue ? (
                                            <span className="flex items-center gap-1 font-semibold text-destructive">
                                                <AlertTriangle className="h-3 w-3" />
                                                Overdue
                                            </span>
                                        ) : (
                                            item.due_at && (
                                                <span>Due {item.due_at}</span>
                                            )
                                        )}
                                    </div>
                                </div>
                                <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground/40 transition-transform group-hover:translate-x-0.5 group-hover:text-foreground" />
                            </Link>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
