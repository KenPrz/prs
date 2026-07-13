import { Head, Link, useForm } from '@inertiajs/react';
import ConfigFormSection from '@/components/admin-config/config-form-section';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AdminConfigLayout from '@/layouts/admin-config/layout';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'System Configuration', href: '/admin/config/workflows' },
    { title: 'Suppliers', href: '/admin/config/suppliers' },
    { title: 'Create', href: '/admin/config/suppliers/create' },
];

export default function SupplierCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        address: '',
        contact_person_1: '',
        contact_person_2: '',
        contact_person_3: '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/admin/config/suppliers');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Supplier — Configuration" />
            <AdminConfigLayout>
                <form onSubmit={handleSubmit} className="space-y-6">
                    <ConfigFormSection
                        title="New Supplier"
                        description="Add a new vendor or supplier."
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="name">Company Name</Label>
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

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                />
                                <InputError message={errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="phone">Phone</Label>
                                <Input
                                    id="phone"
                                    value={data.phone}
                                    onChange={(e) =>
                                        setData('phone', e.target.value)
                                    }
                                />
                                <InputError message={errors.phone} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="address">Address</Label>
                            <Textarea
                                id="address"
                                value={data.address}
                                onChange={(e) =>
                                    setData('address', e.target.value)
                                }
                                rows={2}
                            />
                            <InputError message={errors.address} />
                        </div>
                    </ConfigFormSection>

                    <ConfigFormSection
                        title="Contact Persons"
                        description="Up to 3 contact persons for this supplier."
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="contact_person_1">
                                Contact Person 1
                            </Label>
                            <Input
                                id="contact_person_1"
                                value={data.contact_person_1}
                                onChange={(e) =>
                                    setData('contact_person_1', e.target.value)
                                }
                            />
                            <InputError message={errors.contact_person_1} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="contact_person_2">
                                Contact Person 2
                            </Label>
                            <Input
                                id="contact_person_2"
                                value={data.contact_person_2}
                                onChange={(e) =>
                                    setData('contact_person_2', e.target.value)
                                }
                            />
                            <InputError message={errors.contact_person_2} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="contact_person_3">
                                Contact Person 3
                            </Label>
                            <Input
                                id="contact_person_3"
                                value={data.contact_person_3}
                                onChange={(e) =>
                                    setData('contact_person_3', e.target.value)
                                }
                            />
                            <InputError message={errors.contact_person_3} />
                        </div>
                    </ConfigFormSection>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Creating…' : 'Create Supplier'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/admin/config/suppliers">Cancel</Link>
                        </Button>
                    </div>
                </form>
            </AdminConfigLayout>
        </AppLayout>
    );
}
