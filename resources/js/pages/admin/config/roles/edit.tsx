import { Head, Link, useForm } from '@inertiajs/react';
import ConfigFormSection from '@/components/admin-config/config-form-section';
import { PermissionSelector } from '@/components/admin-config/permission-selector';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminConfigLayout from '@/layouts/admin-config/layout';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, PermissionConfig } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'System Configuration', href: '/admin/config/workflows' },
    { title: 'Roles', href: '/admin/config/roles' },
    { title: 'Edit', href: '#' },
];

type RoleEditProps = {
    role: { id: number; name: string; permissions: string[] };
    availablePermissions: PermissionConfig[];
};

export default function RoleEdit({
    role,
    availablePermissions,
}: RoleEditProps) {
    const { data, setData, put, processing, errors } = useForm({
        name: role.name,
        permissions: role.permissions as string[],
    });

    function togglePermission(name: string) {
        const current = [...data.permissions];
        const idx = current.indexOf(name);

        if (idx > -1) {
            current.splice(idx, 1);
        } else {
            current.push(name);
        }

        setData('permissions', current);
    }

    function setGroup(names: string[], checked: boolean) {
        const set = new Set(data.permissions);
        names.forEach((name) => (checked ? set.add(name) : set.delete(name)));
        setData('permissions', [...set]);
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/config/roles/${role.id}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${role.name} — Configuration`} />
            <AdminConfigLayout>
                <form onSubmit={handleSubmit} className="space-y-6">
                    <ConfigFormSection
                        title="Edit Role"
                        description={`Update role "${role.name}".`}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="name">Role Name</Label>
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

                    <ConfigFormSection
                        title="Permissions"
                        description="Select the permissions granted to this role, grouped by domain."
                    >
                        <PermissionSelector
                            availablePermissions={availablePermissions}
                            selected={data.permissions}
                            onToggle={togglePermission}
                            onSetGroup={setGroup}
                        />
                        <InputError message={errors.permissions} />
                    </ConfigFormSection>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving…' : 'Save Changes'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/admin/config/roles">Cancel</Link>
                        </Button>
                    </div>
                </form>
            </AdminConfigLayout>
        </AppLayout>
    );
}
