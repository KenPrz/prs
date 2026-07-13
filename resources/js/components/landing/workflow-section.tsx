import { SectionContainer } from './section-container';
import { SectionHeader } from './section-header';

const steps = [
    {
        number: '1',
        title: 'Submit a Request',
        description:
            'A requestor creates a Purchase Requisition, specifying items, quantities, and supporting notes. The document enters the approval queue immediately.',
    },
    {
        number: '2',
        title: 'Approval Routing',
        description:
            'The workflow engine routes the PR to the configured approvers in sequence. Each approver can approve, reject, or escalate with a comment.',
    },
    {
        number: '3',
        title: 'Purchase Order',
        description:
            'Once approved, procurement converts the requisition into a Purchase Order. The PO is sent to the supplier and moves through its own approval chain.',
    },
    {
        number: '4',
        title: 'Receiving Verification',
        description:
            'When goods arrive, the warehouse creates a Receiving Report — recording quantities received and rejected against each line item on the PO.',
    },
];

export function WorkflowSection() {
    return (
        <section id="how-it-works" className="bg-muted/40 py-20 md:py-24">
            <SectionContainer>
                <SectionHeader
                    title="How it works"
                    subtitle="Four structured steps from initial request to delivery confirmation — every stage controlled and recorded."
                />

                {/* Main flow: PR → PO → RR */}
                <div className="mt-14 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    {steps.map((step) => (
                        <div key={step.number} className="space-y-3">
                            <span className="text-5xl font-bold text-border">
                                {step.number}
                            </span>
                            <h3 className="text-base font-semibold">
                                {step.title}
                            </h3>
                            <p className="text-sm leading-relaxed text-muted-foreground">
                                {step.description}
                            </p>
                        </div>
                    ))}
                </div>

                {/* PRF callout */}
                <div className="mt-10 rounded-lg border border-border bg-card p-6 shadow-sm">
                    <div className="flex flex-col gap-1 sm:flex-row sm:items-start sm:gap-6">
                        <span className="shrink-0 rounded bg-muted px-1.5 py-0.5 font-mono text-xs font-medium">
                            PRF
                        </span>
                        <div>
                            <h3 className="text-base font-semibold">
                                Payment Request Form — independent workflow
                            </h3>
                            <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                                For recurring billing and standing payment
                                obligations, OpenPRS provides a separate
                                Payment Request Form workflow. It has its own
                                configurable approval chain and audit trail —
                                independent of the PR → PO → RR purchasing
                                process.
                            </p>
                        </div>
                    </div>
                </div>
            </SectionContainer>
        </section>
    );
}
