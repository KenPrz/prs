import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';
import type { User } from '@/types';
import { SectionContainer } from './section-container';

type Props = {
    user: User | null;
};

type FlowStatus = 'approved' | 'pending' | 'draft';

function StatusPill({ status }: { status: FlowStatus }) {
    if (status === 'approved') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full border border-success/20 bg-success/10 px-2 py-0.5 text-xs font-medium text-success">
                <span className="h-1.5 w-1.5 rounded-full bg-success" />
                Approved
            </span>
        );
    }

    if (status === 'pending') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full border border-warning/20 bg-warning/10 px-2 py-0.5 text-xs font-medium text-warning">
                <span className="h-1.5 w-1.5 rounded-full bg-warning" />
                Pending Approval
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1 rounded-full border border-border bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
            <span className="h-1.5 w-1.5 rounded-full bg-muted-foreground/40" />
            Draft
        </span>
    );
}

const procurementChain = [
    {
        type: 'Purchase Requisition',
        ref: 'PR-2024-001',
        description: 'Office furniture & equipment',
        status: 'approved' as FlowStatus,
    },
    {
        type: 'Purchase Order',
        ref: 'PO-2024-001',
        description: 'HP Supplies Inc. — ₱48,500.00',
        status: 'pending' as FlowStatus,
    },
    {
        type: 'Receiving Report',
        ref: 'RR-2024-001',
        description: 'Delivery verification pending',
        status: 'draft' as FlowStatus,
    },
];

function ProcurementFlowPreview() {
    return (
        <div className="w-full max-w-sm space-y-1.5">
            {/* Main procurement chain: PR → PO → RR */}
            {procurementChain.map((step, i) => (
                <div key={step.ref}>
                    <div className="rounded-lg border border-border bg-card p-4">
                        <div className="mb-2 flex items-start justify-between gap-2">
                            <span className="text-xs text-muted-foreground">
                                {step.type}
                            </span>
                            <StatusPill status={step.status} />
                        </div>
                        <p className="text-sm font-semibold">{step.ref}</p>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {step.description}
                        </p>
                    </div>
                    {i < procurementChain.length - 1 && (
                        <div className="flex justify-center py-1">
                            <div className="h-4 w-px border-l border-dashed border-border" />
                        </div>
                    )}
                </div>
            ))}

            {/* PRF — independent workflow */}
            <div className="mt-4 border-t border-border pt-4">
                <p className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                    Also available
                </p>
                <div className="rounded-lg border border-border bg-card p-4 opacity-75">
                    <div className="mb-2 flex items-start justify-between gap-2">
                        <span className="text-xs text-muted-foreground">
                            Payment Request Form
                        </span>
                        <StatusPill status="draft" />
                    </div>
                    <p className="text-sm font-semibold">PRF-2024-001</p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        Recurring billing — independent workflow
                    </p>
                </div>
            </div>
        </div>
    );
}

export function HeroSection({ user }: Props) {
    return (
        <section className="py-20 md:py-28">
            <SectionContainer>
                <div className="grid grid-cols-1 items-center gap-12 lg:grid-cols-2">
                    <div className="space-y-6">
                        <p className="text-xs font-medium tracking-widest text-primary uppercase">
                            Procurement Workflow Platform
                        </p>
                        <h1 className="text-4xl leading-tight font-light tracking-tight md:text-6xl">
                            Procurement Without
                            <br />
                            the Paper Trail
                        </h1>
                        <p className="max-w-md text-base leading-relaxed text-muted-foreground">
                            OpenPRS digitizes your entire purchasing process —
                            from requisition to payment — with structured
                            approval workflows and a complete audit trail at
                            every step.
                        </p>
                        <div className="flex flex-wrap gap-3">
                            {user ? (
                                <Button asChild size="lg">
                                    <Link href={dashboard()}>
                                        Go to Dashboard
                                        <ArrowRight className="ml-2 h-4 w-4" />
                                    </Link>
                                </Button>
                            ) : (
                                <>
                                    <Button asChild size="lg">
                                        <Link href={login()}>
                                            Get Started
                                            <ArrowRight className="ml-2 h-4 w-4" />
                                        </Link>
                                    </Button>
                                    <Button asChild size="lg" variant="outline">
                                        <a href="#how-it-works">
                                            See how it works
                                        </a>
                                    </Button>
                                </>
                            )}
                        </div>
                    </div>

                    <div className="flex justify-center lg:justify-end">
                        <ProcurementFlowPreview />
                    </div>
                </div>
            </SectionContainer>
        </section>
    );
}
