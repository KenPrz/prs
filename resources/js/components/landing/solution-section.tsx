import { SectionContainer } from './section-container';
import { SectionHeader } from './section-header';

const mainChain = [
    {
        number: '01',
        abbr: 'PR',
        name: 'Purchase Requisition',
        description:
            'Initiate and route purchase requests for approval before any spend is committed.',
    },
    {
        number: '02',
        abbr: 'PO',
        name: 'Purchase Order',
        description:
            'Convert approved requisitions into formal orders sent directly to suppliers.',
    },
    {
        number: '03',
        abbr: 'RR',
        name: 'Receiving Report',
        description:
            'Verify delivered goods against the purchase order, item by item.',
    },
];

export function SolutionSection() {
    return (
        <section className="py-20 md:py-24">
            <SectionContainer>
                <SectionHeader
                    title="One workflow for the entire procurement lifecycle"
                    subtitle="OpenPRS connects every stage of procurement into a single, controlled process — with approvals at every gate."
                />

                {/* Main procurement chain: PR → PO → RR */}
                <div className="mt-14">
                    <p className="mb-4 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        Main procurement flow
                    </p>
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        {mainChain.map((stage, i) => (
                            <div key={stage.number} className="relative">
                                <div className="rounded-lg border border-border bg-card p-6">
                                    <div className="mb-4 flex items-center gap-3">
                                        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                                            {stage.number}
                                        </span>
                                        <span className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs font-medium">
                                            {stage.abbr}
                                        </span>
                                    </div>
                                    <h3 className="mb-2 text-base font-semibold">
                                        {stage.name}
                                    </h3>
                                    <p className="text-sm leading-relaxed text-muted-foreground">
                                        {stage.description}
                                    </p>
                                </div>
                                {i < mainChain.length - 1 && (
                                    <div className="absolute top-8 -right-3 z-10 hidden items-center justify-center sm:flex">
                                        <span className="text-lg text-border">
                                            →
                                        </span>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>

                {/* PRF — independent workflow */}
                <div className="mt-10 border-t border-border pt-10">
                    <p className="mb-4 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                        Independent workflow
                    </p>
                    <div className="max-w-sm">
                        <div className="rounded-lg border border-border bg-card p-6">
                            <div className="mb-4 flex items-center gap-3">
                                <span className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs font-medium">
                                    PRF
                                </span>
                            </div>
                            <h3 className="mb-2 text-base font-semibold">
                                Payment Request Form
                            </h3>
                            <p className="text-sm leading-relaxed text-muted-foreground">
                                A separate approval workflow for recurring
                                billing and standing payment requests — not tied
                                to a specific purchase order.
                            </p>
                        </div>
                    </div>
                </div>
            </SectionContainer>
        </section>
    );
}
