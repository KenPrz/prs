import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

export default function ConfigPageHeader({
    title,
    description,
    createHref,
    createLabel = 'Add new',
    action,
}: {
    title: string;
    description?: string;
    createHref?: string;
    createLabel?: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    {title}
                </h1>
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>

            {action
                ? action
                : createHref && (
                      <Button asChild>
                          <Link href={createHref}>
                              <Plus className="mr-1.5 h-4 w-4" />
                              {createLabel}
                          </Link>
                      </Button>
                  )}
        </div>
    );
}
