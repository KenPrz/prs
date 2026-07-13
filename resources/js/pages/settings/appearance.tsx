import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit as editAppearance } from '@/routes/appearance';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: editAppearance(),
    },
];

export default function Appearance() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Appearance settings" />

            <h1 className="sr-only">Appearance settings</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Appearance settings</CardTitle>
                            <CardDescription>
                                Update your account's appearance settings
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <AppearanceTabs />
                        </CardContent>
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
