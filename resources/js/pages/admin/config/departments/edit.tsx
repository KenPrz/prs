import { Head, Link, useForm } from '@inertiajs/react';
import ConfigFormSection from '@/components/admin-config/config-form-section';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminConfigLayout from '@/layouts/admin-config/layout';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, DepartmentConfig, UserOption } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'System Configuration', href: '/admin/config/workflows' },
    { title: 'Departments', href: '/admin/config/departments' },
    { title: 'Edit', href: '#' },
];

export default function DepartmentEdit({
    department,
    users = [],
}: {
    department: DepartmentConfig;
    users: UserOption[];
}) {
    const { data, setData, put, processing, errors } = useForm({
        name: department.name,
        code: department.code,
        department_head_id: department.department_head_id ?? null,
    });

    const userOptions = users.map((user) => ({
        label: user.name,
        value: String(user.id),
    }));

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/config/departments/${department.id}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${department.name} — Configuration`} />
            <AdminConfigLayout>
                <form onSubmit={handleSubmit} className="space-y-6">
                    <ConfigFormSection
                        title="Edit Department"
                        description={`Update department "${department.name}".`}
                    >
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
                            {processing ? 'Saving…' : 'Save Changes'}
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
