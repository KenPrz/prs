import type { LucideIcon } from 'lucide-react';

type Props = {
    icon: LucideIcon;
    title: string;
    description: string;
};

export function FeatureCard({ icon: Icon, title, description }: Props) {
    return (
        <div className="rounded-lg border border-border bg-card p-6">
            <Icon className="mb-4 h-5 w-5 text-primary" />
            <h3 className="mb-2 text-base font-semibold">{title}</h3>
            <p className="text-sm leading-relaxed text-muted-foreground">
                {description}
            </p>
        </div>
    );
}
