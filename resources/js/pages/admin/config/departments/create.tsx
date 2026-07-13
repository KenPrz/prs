import { Head, Link, useForm } from '@inertiajs/react';
import ConfigFormSection from '@/components/admin-config/config-form-section';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminConfigLayout from '@/layouts/admin-config/layout';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, UserOption } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'System Configuration', href: '/admin/config/workflows' },
    { title: 'Departments', href: '/admin/config/departments' },
    { title: 'Create', href: '/admin/config/departments/create' },
];

export default function DepartmentCreate({
    users = [],
}: {
    users: UserOption[];
}) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        code: '',
        department_head_id: null as number | null,
    });

    const userOptions = users.map((user) => ({
        label: user.name,
        value: String(user.id),
    }));

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/admin/config/departments');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Department — Configuration" />
            <AdminConfigLayout>
                <form onSubmit={handleSubmit} className="space-y-6">
                    <ConfigFormSection
                        title="New Department"
                        description="Add a new organizational department."
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="e.g. Finance"
                                required
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="code">Code</Label>
                            <Input
                                id="code"
                                value={data.code}
                                onChange={(e) =>
                                    setData('code', e.target.value)
                                }
                                placeholder="e.g. FIN"
                                required
                            />
                            <InputError message={errors.code} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="department_head_id">
                                Department Head
                            </Label>
                            <Combobox
                                options={userOptions}
                                value={
                                    data.department_head_id === null
                                        ? ''
                                        : String(data.department_head_id)
                                }
                                onChange={(value) =>
                                    setData(
                                        'department_head_id',
                                        value ? Number(value) : null,
                                    )
                                }
                                placeholder="Select department head"
                            />
                            <InputError message={errors.department_head_id} />
                        </div>
                    </ConfigFormSection>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Creating…' : 'Create Department'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/admin/config/departments">Cancel</Link>
                        </Button>
                    </div>
                </form>
            </AdminConfigLayout>
        </AppLayout>
    );
}
