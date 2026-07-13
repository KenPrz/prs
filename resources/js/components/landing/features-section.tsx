import {
    Activity,
    Building2,
    GitBranch,
    History,
    Paperclip,
    Shield,
} from 'lucide-react';
import { FeatureCard } from './feature-card';
import { SectionContainer } from './section-container';
import { SectionHeader } from './section-header';

const features = [
    {
        icon: GitBranch,
        title: 'Configurable Approval Chains',
        description:
            'Define multi-step approval workflows per document type. Assign any combination of approvers and set per-step completion strategies.',
    },
    {
        icon: Activity,
        title: 'Real-Time Status Tracking',
        description:
            'Every document has a clear, live status. Requestors and approvers always know exactly where things stand in the process.',
    },
    {
        icon: History,
        title: 'Complete Audit History',
        description:
            'Every approval, rejection, and override is timestamped and attributed. Full accountability, permanently recorded.',
    },
    {
        icon: Shield,
        title: 'Role-Based Authorization',
        description:
            'Granular permissions for requestors, reviewers, approvers, and administrators. Control who can see and act on each document type.',
    },
    {
        icon: Building2,
        title: 'Supplier & Department Management',
        description:
            'Centralized configuration for vendors, departments, and item units. Clean master data referenced across all documents.',
    },
    {
        icon: Paperclip,
        title: 'Document Attachments',
        description:
            'Attach quotes, delivery receipts, and supporting documents at any stage. Everything stays with the record.',
    },
];

export function FeaturesSection() {
    return (
        <section className="bg-muted/40 py-20 md:py-24">
            <SectionContainer>
                <SectionHeader
                    title="Everything procurement requires"
                    subtitle="Built for the full procurement lifecycle — not just request management."
                />
                <div className="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {features.map((feature) => (
                        <FeatureCard
                            key={feature.title}
                            icon={feature.icon}
                            title={feature.title}
                            description={feature.description}
                        />
                    ))}
                </div>
            </SectionContainer>
        </section>
    );
}
