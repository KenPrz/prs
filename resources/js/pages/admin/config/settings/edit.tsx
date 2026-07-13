import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import ConfigFormSection from '@/components/admin-config/config-form-section';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminConfigLayout from '@/layouts/admin-config/layout';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, SystemSettingsConfig } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'System Configuration', href: '/admin/config/workflows' },
    { title: 'System Settings', href: '/admin/config/settings' },
];

export default function SystemSettingsEdit({
    settings,
}: {
    settings: SystemSettingsConfig;
}) {
    const { data, setData, put, processing, errors, recentlySuccessful } =
        useForm({
            vat_rate_percent: settings.vat_rate_percent,
            currency_symbol: settings.currency_symbol,
            records_per_page: settings.records_per_page,
            access_log_retention_days: settings.access_log_retention_days,
        });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put('/admin/config/settings');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="System Settings — Configuration" />
            <AdminConfigLayout>
                <form onSubmit={handleSubmit} className="space-y-6">
                    <ConfigFormSection
                        title="Finance"
                        description="Tax and currency defaults applied to purchase orders and money displays."
                    >
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="vat_rate_percent">
                                    VAT Rate (%)
                                </Label>
                                <Input
                                    id="vat_rate_percent"
                                    type="number"
                                    min={0}
                                    max={100}
                                    step="0.01"
                                    value={data.vat_rate_percent}
                                    onChange={(e) =>
                                        setData(
                                            'vat_rate_percent',
                                            e.target.value === ''
                                                ? ('' as unknown as number)
                                                : Number(e.target.value),
                                        )
                                    }
                                    required
                                />
                                <InputError message={errors.vat_rate_percent} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="currency_symbol">
                                    Currency Symbol
                                </Label>
                                <Input
                                    id="currency_symbol"
                                    value={data.currency_symbol}
                                    onChange={(e) =>
                                        setData(
                                            'currency_symbol',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="₱"
                                    required
                                />
                                <InputError message={errors.currency_symbol} />
                            </div>
                        </div>
                    </ConfigFormSection>

                    <ConfigFormSection
                        title="General"
                        description="Application-wide defaults."
                    >
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="records_per_page">
                                    Records Per Page
                                </Label>
                                <Input
                                    id="records_per_page"
                                    type="number"
                                    min={1}
                                    max={100}
                                    value={data.records_per_page}
                                    onChange={(e) =>
                                        setData(
                                            'records_per_page',
                                            e.target.value === ''
                                                ? ('' as unknown as number)
                                                : Number(e.target.value),
                                        )
                                    }
                                    required
                                />
                                <InputError message={errors.records_per_page} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="access_log_retention_days">
                                    Access Log Retention (days)
                                </Label>
                                <Input
                                    id="access_log_retention_days"
                                    type="number"
                                    min={1}
                                    max={3650}
                                    value={data.access_log_retention_days}
                                    onChange={(e) =>
                                        setData(
                                            'access_log_retention_days',
                                            e.target.value === ''
                                                ? ('' as unknown as number)
                                                : Number(e.target.value),
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.access_log_retention_days}
                                />
                            </div>
                        </div>
                    </ConfigFormSection>

                    <div className="flex items-center gap-4">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving…' : 'Save Changes'}
                        </Button>

                        <Transition
                            show={recentlySuccessful}
                            enter="transition ease-in-out"
                            enterFrom="opacity-0"
                            leave="transition ease-in-out"
                            leaveTo="opacity-0"
                        >
                            <p className="text-sm text-muted-foreground">
                                Saved
                            </p>
                        </Transition>
                    </div>
                </form>
            </AdminConfigLayout>
        </AppLayout>
    );
}
