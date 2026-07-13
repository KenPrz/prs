import { Head, usePage } from '@inertiajs/react';
import { BenefitsSection } from '@/components/landing/benefits-section';
import { CtaSection } from '@/components/landing/cta-section';
import { FeaturesSection } from '@/components/landing/features-section';
import { HeroSection } from '@/components/landing/hero-section';
import { LandingFooter } from '@/components/landing/landing-footer';
import { LandingNav } from '@/components/landing/landing-nav';
import { ProblemSection } from '@/components/landing/problem-section';
import { SolutionSection } from '@/components/landing/solution-section';
import { WorkflowSection } from '@/components/landing/workflow-section';
import type { User } from '@/types';

export default function Welcome() {
    const { auth } = usePage().props;
    const user = (auth as { user: User | null }).user;

    return (
        <>
            <Head title="Welcome — OpenPRS" />
            <div className="min-h-screen bg-background">
                <LandingNav user={user} />
                <main>
                    <HeroSection user={user} />
                    <ProblemSection />
                    <SolutionSection />
                    <FeaturesSection />
                    <BenefitsSection />
                    <WorkflowSection />
                    <CtaSection />
                </main>
                <LandingFooter />
            </div>
        </>
    );
}
