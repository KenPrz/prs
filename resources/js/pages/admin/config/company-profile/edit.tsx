import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import ConfigFormSection from '@/components/admin-config/config-form-section';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminConfigLayout from '@/layouts/admin-config/layout';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, CompanyProfileConfig, UserOption } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'System Configuration', href: '/admin/config/workflows' },
    { title: 'Company Profile', href: '/admin/config/company-profile' },
];

// Sentinel used by the Received-By select to represent "the requisition creator"
// (persisted as a null default_received_by_user_id).
const CREATOR_VALUE = 'CREATOR';

export default function CompanyProfileEdit({
    companyProfile,
    users = [],
}: {
    companyProfile: CompanyProfileConfig | null;
    users: UserOption[];
}) {
    const { data, setData, put, processing, errors, recentlySuccessful } =
        useForm({
            name: companyProfile?.name ?? '',
            email: companyProfile?.email ?? '',
            mobile_no: companyProfile?.mobile_no ?? '',
            tin: companyProfile?.tin ?? '',
            currency: companyProfile?.currency ?? 'PHP',
            default_received_by_user_id:
                companyProfile?.default_received_by_user_id ?? null,
            address: {
                recipient_name: companyProfile?.address?.recipient_name ?? '',
                street: companyProfile?.address?.street ?? '',
                barangay: companyProfile?.address?.barangay ?? '',
                city: companyProfile?.address?.city ?? '',
                province: companyProfile?.address?.province ?? '',
                zip_code: companyProfile?.address?.zip_code ?? '',
                mobile_no: companyProfile?.address?.mobile_no ?? '',
            },
        });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put('/admin/config/company-profile');
    }

    const receivedByOptions = [
        { label: 'Creator (requestor)', value: CREATOR_VALUE },
        ...users.map((user) => ({
            label: user.name,
            value: String(user.id),
        })),
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Company Profile — Configuration" />
            <AdminConfigLayout>
                <form onSubmit={handleSubmit} className="space-y-6">
                    <ConfigFormSection
                        title="Company Information"
                        description="Core company details used across documents and forms."
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
                                <Label htmlFor="mobile_no">Mobile No.</Label>
                                <Input
                                    id="mobile_no"
                                    value={data.mobile_no}
                                    onChange={(e) =>
                                        setData('mobile_no', e.target.value)
                                    }
                                />
                                <InputError message={errors.mobile_no} />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="tin">TIN</Label>
                                <Input
                                    id="tin"
                                    value={data.tin}
                                    onChange={(e) =>
                                        setData('tin', e.target.value)
                                    }
                                />
                                <InputError message={errors.tin} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="currency">Currency</Label>
                                <Input
                                    id="currency"
                                    value={data.currency}
                                    onChange={(e) =>
                                        setData('currency', e.target.value)
                                    }
                                    placeholder="PHP"
                                />
                                <InputError message={errors.currency} />
                            </div>
                        </div>
                    </ConfigFormSection>

                    <ConfigFormSection
                        title="Procurement Defaults"
                        description="Default signatories applied to new requisitions."
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="default_received_by_user_id">
                                Default Received By
                            </Label>
                            <Combobox
                                options={receivedByOptions}
                                value={
                                    data.default_received_by_user_id === null
                                        ? CREATOR_VALUE
                                        : String(
                                              data.default_received_by_user_id,
                                          )
                                }
                                onChange={(value) =>
                                    setData(
                                        'default_received_by_user_id',
                                        value === CREATOR_VALUE || !value
                                            ? null
                                            : Number(value),
                                    )
                                }
                                placeholder="Select default received-by"
                            />
                            <InputError
                                message={errors.default_received_by_user_id}
                            />
                        </div>
                    </ConfigFormSection>

                    <ConfigFormSection
                        title="Company Address"
                        description="Primary business address."
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="addr_recipient">
                                Recipient Name
                            </Label>
                            <Input
                                id="addr_recipient"
                                value={data.address.recipient_name}
                                onChange={(e) =>
                                    setData('address', {
                                        ...data.address,
                                        recipient_name: e.target.value,
                                    })
                                }
                            />
                            <InputError
                                message={
                                    errors[
                                        'address.recipient_name' as keyof typeof errors
                                    ]
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="addr_street">Street</Label>
                            <Input
                                id="addr_street"
                                value={data.address.street}
                                onChange={(e) =>
                                    setData('address', {
                                        ...data.address,
                                        street: e.target.value,
                                    })
                                }
                            />
                            <InputError
                                message={
                                    errors[
                                        'address.street' as keyof typeof errors
                                    ]
                                }
                            />
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="addr_barangay">Barangay</Label>
                                <Input
                                    id="addr_barangay"
                                    value={data.address.barangay}
                                    onChange={(e) =>
                                        setData('address', {
                                            ...data.address,
                                            barangay: e.target.value,
                                        })
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="addr_city">City</Label>
                                <Input
                                    id="addr_city"
                                    value={data.address.city}
                                    onChange={(e) =>
                                        setData('address', {
                                            ...data.address,
                                            city: e.target.value,
                                        })
                                    }
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="addr_province">Province</Label>
                                <Input
                                    id="addr_province"
                                    value={data.address.province}
                                    onChange={(e) =>
                                        setData('address', {
                                            ...data.address,
                                            province: e.target.value,
                                        })
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="addr_zip">Zip Code</Label>
                                <Input
                                    id="addr_zip"
                                    value={data.address.zip_code}
                                    onChange={(e) =>
                                        setData('address', {
                                            ...data.address,
                                            zip_code: e.target.value,
                                        })
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="addr_mobile">Mobile No.</Label>
                                <Input
                                    id="addr_mobile"
                                    value={data.address.mobile_no}
                                    onChange={(e) =>
                                        setData('address', {
                                            ...data.address,
                                            mobile_no: e.target.value,
                                        })
                                    }
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
