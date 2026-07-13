import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';

export function CtaSection() {
    return (
        <section className="bg-primary py-24 md:py-32">
            <div className="mx-auto max-w-6xl px-4 text-center sm:px-6">
                <h2 className="text-3xl font-semibold tracking-tight text-primary-foreground md:text-4xl">
                    Ready to modernize
                    <br />
                    your procurement process?
                </h2>
                <p className="mx-auto mt-4 max-w-md text-base text-primary-foreground/75">
                    Replace email chains and spreadsheets with a system built
                    for modern, accountable procurement operations.
                </p>
                <div className="mt-8">
                    <Button
                        asChild
                        size="lg"
                        className="border border-primary-foreground/30 bg-primary-foreground text-primary hover:bg-primary-foreground/90"
                    >
                        <Link href={login()}>
                            Get Started
                            <ArrowRight className="ml-2 h-4 w-4" />
                        </Link>
                    </Button>
                </div>
            </div>
        </section>
    );
}
