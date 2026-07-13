import { Head, Link, useForm } from '@inertiajs/react';
import ConfigFormSection from '@/components/admin-config/config-form-section';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminConfigLayout from '@/layouts/admin-config/layout';
import AppLayout from '@/layouts/app-layout';
import type {
    BreadcrumbItem,
    DepartmentConfig,
    RoleConfig,
    UserConfig,
} from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'System Configuration', href: '/admin/config/workflows' },
    { title: 'Users', href: '/admin/config/users' },
    { title: 'Edit User', href: '#' },
];

export default function UserEdit({
    user,
    availableRoles,
    availableDepartments,
}: {
    user: UserConfig;
    availableRoles: RoleConfig[];
    availableDepartments: DepartmentConfig[];
}) {
    const { data, setData, put, processing, errors, reset } = useForm({
        name: user.name,
        email: user.email,
        password: '',
        password_confirmation: '',
        roles: user.roles?.map((r) => r.name) ?? [],
        departments: user.departments?.map((d) => d.id) ?? [],
    });

    const toggleRole = (roleName: string) => {
        const current = [...data.roles];
        const index = current.indexOf(roleName);

        if (index > -1) {
            current.splice(index, 1);
        } else {
            current.push(roleName);
        }

        setData('roles', current);
    };

    const toggleDepartment = (deptId: number) => {
        const current = [...data.departments];
        const index = current.indexOf(deptId);

        if (index > -1) {
            current.splice(index, 1);
        } else {
            current.push(deptId);
        }

        setData('departments', current);
    };

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/config/users/${user.id}`, {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit User ${user.name} — Configuration`} />
            <AdminConfigLayout>
                <form onSubmit={handleSubmit} className="space-y-6">
                    <ConfigFormSection
                        title="User Details"
                        description="Update the user's name, email, or reset their password."
                    >
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Full Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="John Doe"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email Address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    placeholder="john@example.com"
                                    required
                                />
                                <InputError message={errors.email} />
                            </div>
                        </div>

                        <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    New Password (leave blank to keep current)
                                </Label>
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) =>
                                        setData('password', e.target.value)
                                    }
                                />
                                <InputError message={errors.password} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm New Password
                                </Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    value={data.password_confirmation}
                                    onChange={(e) =>
                                        setData(
                                            'password_confirmation',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                        </div>
                    </ConfigFormSection>

                    <ConfigFormSection
                        title="Edit User Permissions"
                        description={`Manage roles and department assignments for ${user.name}.`}
                    >
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                            {/* Roles Section */}
                            <div className="space-y-4">
                                <Label className="text-base font-semibold">
                                    Roles
                                </Label>
                                <p className="text-sm text-muted-foreground">
                                    Assign security roles to this user to
                                    control access.
                                </p>
                                <div className="grid gap-3 pt-2">
                                    {availableRoles.map((role) => (
                                        <div
                                            key={role.id}
                                            className="flex items-center space-x-2"
                                        >
                                            <Checkbox
                                                id={`role-${role.id}`}
                                                checked={data.roles.includes(
                                                    role.name,
                                                )}
                                                onCheckedChange={() =>
                                                    toggleRole(role.name)
                                                }
                                            />
                                            <Label
                                                htmlFor={`role-${role.id}`}
                                                className="cursor-pointer text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                            >
                                                {role.name}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                                <InputError message={errors.roles} />
                            </div>

                            {/* Departments Section */}
                            <div className="space-y-4">
                                <Label className="text-base font-semibold">
                                    Departments
                                </Label>
                                <p className="text-sm text-muted-foreground">
                                    Assign the user to one or more departments
                                    for workflow routing.
                                </p>
                                <div className="grid gap-3 pt-2">
                                    {availableDepartments.map((dept) => (
                                        <div
                                            key={dept.id}
                                            className="flex items-center space-x-2"
                                        >
                                            <Checkbox
                                                id={`dept-${dept.id}`}
                                                checked={data.departments.includes(
                                                    dept.id,
                                                )}
                                                onCheckedChange={() =>
                                                    toggleDepartment(dept.id)
                                                }
                                            />
                                            <Label
                                                htmlFor={`dept-${dept.id}`}
                                                className="cursor-pointer text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                            >
                                                {dept.name} ({dept.code})
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                                <InputError message={errors.departments} />
                            </div>
                        </div>
                    </ConfigFormSection>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving…' : 'Save Changes'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/admin/config/users">Cancel</Link>
                        </Button>
                    </div>
                </form>
            </AdminConfigLayout>
        </AppLayout>
    );
}
