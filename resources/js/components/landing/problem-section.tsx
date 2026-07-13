import { Clock, EyeOff, Mail, ShieldAlert } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { SectionContainer } from './section-container';
import { SectionHeader } from './section-header';

type Problem = {
    icon: LucideIcon;
    title: string;
    description: string;
};

const problems: Problem[] = [
    {
        icon: Mail,
        title: 'Approvals buried in email',
        description:
            'Purchase requests get lost in crowded inboxes. Approvers miss emails, requests expire, and procurement stalls while the business waits.',
    },
    {
        icon: EyeOff,
        title: 'No visibility into status',
        description:
            'Requestors have no idea where their purchase stands in the process. Every update requires a manual follow-up call or email.',
    },
    {
        icon: Clock,
        title: 'Bottlenecks slow everything down',
        description:
            'Without structured routing, approvals pile up with key managers. One absent approver can halt an entire requisition for days.',
    },
    {
        icon: ShieldAlert,
        title: 'No audit trail when things go wrong',
        description:
            'When a dispute arises — over what was approved, by whom, or when — there is no authoritative record to reference.',
    },
];

export function ProblemSection() {
    return (
        <section className="bg-muted/40 py-20 md:py-24">
            <SectionContainer>
                <SectionHeader
                    title="Procurement runs on manual processes"
                    subtitle="Most organizations still manage purchasing through email threads, spreadsheets, and verbal approvals. The result is slow, opaque, and difficult to control."
                />
                <div className="mt-12 grid grid-cols-1 gap-8 sm:grid-cols-2">
                    {problems.map((problem) => (
                        <div key={problem.title} className="flex gap-4">
                            <div className="mt-0.5 shrink-0">
                                <problem.icon className="h-5 w-5 text-muted-foreground" />
                            </div>
                            <div>
                                <h3 className="mb-1 text-base font-semibold">
                                    {problem.title}
                                </h3>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    {problem.description}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            </SectionContainer>
        </section>
    );
}
