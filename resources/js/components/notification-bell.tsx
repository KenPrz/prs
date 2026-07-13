import { router, usePage, usePoll } from '@inertiajs/react';
import { Bell, CheckCheck, Inbox } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { read, readAll } from '@/routes/notifications';

export interface NotificationItem {
    id: string;
    title: string;
    message: string;
    action_url: string | null;
    read: boolean;
    created_at: string;
}

export interface NotificationFeed {
    unread_count: number;
    items: NotificationItem[];
}

export function NotificationBell() {
    const { notifications } = usePage<{
        notifications: NotificationFeed | null;
    }>().props;
    const [open, setOpen] = useState(false);

    // Refresh only this shared prop every 5s; no websockets needed for now.
    usePoll(5000, { only: ['notifications'] });

    if (!notifications) {
        return null;
    }

    const { unread_count: unreadCount, items } = notifications;

    const openItem = (item: NotificationItem) => {
        setOpen(false);

        if (!item.read) {
            router.post(
                read.url(item.id),
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        if (item.action_url) {
                            router.visit(item.action_url);
                        }
                    },
                },
            );
        } else if (item.action_url) {
            router.visit(item.action_url);
        }
    };

    const markAllRead = () => {
        router.post(readAll.url(), {}, { preserveScroll: true });
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={
                        unreadCount > 0
                            ? `Notifications (${unreadCount} unread)`
                            : 'Notifications'
                    }
                >
                    <Bell className="h-5 w-5" />
                    {unreadCount > 0 && (
                        <span className="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-bold text-white">
                            {unreadCount > 99 ? '99+' : unreadCount}
                        </span>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-96 p-0">
                <div className="flex items-center justify-between border-b border-border/60 px-4 py-3">
                    <p className="text-sm font-semibold">Notifications</p>
                    {unreadCount > 0 && (
                        <button
                            type="button"
                            onClick={markAllRead}
                            className="flex items-center gap-1 text-xs font-medium text-primary underline-offset-4 hover:underline"
                        >
                            <CheckCheck className="h-3.5 w-3.5" />
                            Mark all as read
                        </button>
                    )}
                </div>

                {items.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 px-4 py-10 text-center">
                        <Inbox className="h-6 w-6 text-muted-foreground/40" />
                        <p className="text-sm text-muted-foreground">
                            You're all caught up.
                        </p>
                    </div>
                ) : (
                    <div className="max-h-96 divide-y divide-border/40 overflow-y-auto">
                        {items.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => openItem(item)}
                                className={cn(
                                    'flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-accent/50',
                                    !item.read && 'bg-primary/5',
                                )}
                            >
                                <span
                                    className={cn(
                                        'mt-1.5 h-2 w-2 shrink-0 rounded-full',
                                        item.read
                                            ? 'bg-transparent'
                                            : 'bg-primary',
                                    )}
                                />
                                <span className="min-w-0 flex-1 space-y-0.5">
                                    <span className="block text-sm font-semibold">
                                        {item.title}
                                    </span>
                                    <span className="block text-xs leading-snug text-muted-foreground">
                                        {item.message}
                                    </span>
                                    <span className="block text-[11px] text-muted-foreground/60">
                                        {item.created_at}
                                    </span>
                                </span>
                            </button>
                        ))}
                    </div>
                )}
            </PopoverContent>
        </Popover>
    );
}
