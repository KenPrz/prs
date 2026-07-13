import { CheckCircle2 } from 'lucide-react';
import { SectionContainer } from './section-container';
import { SectionHeader } from './section-header';

const personas = [
    {
        role: 'Finance Teams',
        subtitle: 'Control spend before it happens',
        benefits: [
            'Approve or reject requests with full document context',
            'Enforce budget controls at the requisition stage',
            'Track payment requests against verified deliveries',
            'Maintain complete financial audit records',
            'No more chasing paper approvals or email chains',
        ],
    },
    {
        role: 'Procurement Officers',
        subtitle: 'Less coordination, more execution',
        benefits: [
            'Clear visibility into every active requisition',
            'Convert approved PRs to purchase orders in one step',
            'Manage supplier relationships from a central registry',
            'Automatic approval routing — no manual forwarding',
            'Know the status of every order at a glance',
        ],
    },
    {
        role: 'Management & Directors',
        subtitle: 'Accountability without micromanagement',
        benefits: [
            'Override approvals when needed, with a recorded reason',
            'Full audit trail of every procurement decision',
            'Configurable approval chains per document type',
            'Organization-wide procurement transparency',
            'Consistent, repeatable procurement governance',
        ],
    },
];

export function BenefitsSection() {
    return (
        <section className="py-20 md:py-24">
            <SectionContainer>
                <SectionHeader
                    title="Built for every stakeholder in the process"
                    subtitle="OpenPRS brings clarity and accountability to procurement — for everyone involved."
                />
                <div className="mt-12 grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {personas.map((persona) => (
                        <div
                            key={persona.role}
                            className="rounded-lg border border-border bg-card p-6"
                        >
                            <h3 className="text-base font-semibold">
                                {persona.role}
                            </h3>
                            <p className="mt-1 mb-5 text-sm text-muted-foreground">
                                {persona.subtitle}
                            </p>
                            <ul className="space-y-3">
                                {persona.benefits.map((benefit) => (
                                    <li
                                        key={benefit}
                                        className="flex items-start gap-2.5"
                                    >
                                        <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-success" />
                                        <span className="text-sm text-muted-foreground">
                                            {benefit}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
            </SectionContainer>
        </section>
    );
}
