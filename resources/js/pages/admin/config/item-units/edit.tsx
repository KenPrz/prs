import { Head, Link, useForm } from '@inertiajs/react';
import ConfigFormSection from '@/components/admin-config/config-form-section';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminConfigLayout from '@/layouts/admin-config/layout';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, ItemUnitConfig } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'System Configuration', href: '/admin/config/workflows' },
    { title: 'Item Units', href: '/admin/config/item-units' },
    { title: 'Edit', href: '#' },
];

export default function ItemUnitEdit({
    itemUnit,
}: {
    itemUnit: ItemUnitConfig;
}) {
    const { data, setData, put, processing, errors } = useForm({
        code: itemUnit.code,
        name: itemUnit.name,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/config/item-units/${itemUnit.id}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${itemUnit.code} — Configuration`} />
            <AdminConfigLayout>
                <form onSubmit={handleSubmit} className="space-y-6">
                    <ConfigFormSection
                        title="Edit Item Unit"
                        description={`Update item unit "${itemUnit.code}".`}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="code">Code</Label>
                            <Input
                                id="code"
                                value={data.code}
                                onChange={(e) =>
                                    setData('code', e.target.value)
                                }
                                required
                            />
                            <InputError message={errors.code} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                required
                            />
                            <InputError message={errors.name} />
                        </div>
                    </ConfigFormSection>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving…' : 'Save Changes'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/admin/config/item-units">Cancel</Link>
                        </Button>
                    </div>
                </form>
            </AdminConfigLayout>
        </AppLayout>
    );
}
