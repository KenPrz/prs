import { Head, Link, useForm } from '@inertiajs/react';
import ConfigFormSection from '@/components/admin-config/config-form-section';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminConfigLayout from '@/layouts/admin-config/layout';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'System Configuration', href: '/admin/config/workflows' },
    { title: 'Item Units', href: '/admin/config/item-units' },
    { title: 'Create', href: '/admin/config/item-units/create' },
];

export default function ItemUnitCreate() {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        name: '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/admin/config/item-units');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Item Unit — Configuration" />
            <AdminConfigLayout>
                <form onSubmit={handleSubmit} className="space-y-6">
                    <ConfigFormSection
                        title="New Item Unit"
                        description="Add a new unit of measurement."
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="code">Code</Label>
                            <Input
                                id="code"
                                value={data.code}
                                onChange={(e) =>
                                    setData('code', e.target.value)
                                }
                                placeholder="e.g. pcs, kg, lot"
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
                                placeholder="e.g. Pieces, Kilograms, Lot"
                                required
                            />
                            <InputError message={errors.name} />
                        </div>
                    </ConfigFormSection>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Creating…' : 'Create Item Unit'}
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
