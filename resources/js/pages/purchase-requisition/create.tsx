import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import PurchaseRequisitionController from '@/actions/App/Http/Controllers/PurchaseRequisitionController';
import { DocumentAttachmentsField } from '@/components/document-attachments-field';
import { AdditionalNotesCard } from '@/components/procurement/additional-notes-card';
import type { NoteData } from '@/components/procurement/additional-notes-card';
import { DocumentPageLayout } from '@/components/procurement/document-page-layout';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { priceBreakdown } from '@/lib/price-type';
import * as purchaseRequisitions from '@/routes/purchase-requisitions';
import * as workflows from '@/routes/workflows';
import type { BreadcrumbItem } from '@/types';
import { LineItemsCard } from './partials/line-items-card';
import { RequestDetailsCard } from './partials/request-details-card';
import { SummaryCard } from './partials/summary-card';

interface Department {
    id: number;
    name: string;
    code: string;
}

interface ItemUnit {
    id: number;
    name: string;
    code: string;
}

interface LabeledOption {
    value: string;
    label: string;
}

interface UserOption {
    id: number;
    name: string;
    email?: string;
}

export interface LineItemData {
    id?: number;
    name: string;
    quantity: number | '';
    unit_id: number | '';
    price: number | '';
}

export type { NoteData };

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Purchase requisitions',
        href: purchaseRequisitions.index.url(),
    },
    {
        title: 'Create',
        href: purchaseRequisitions.create.url(),
    },
];

export default function PurchaseRequisitionCreate({
    departments = [],
    itemUnits = [],
    priceTypes = [],
    purposeTypes = [],
    users = [],
}: {
    departments: Department[];
    itemUnits: ItemUnit[];
    priceTypes: string[];
    purposeTypes: LabeledOption[];
    users: UserOption[];
}) {
    const form = useForm({
        title: '',
        description: '',
        delivery_date: '' as string,
        department_ids: [] as number[],
        price_type: priceTypes[0] || 'VAT_INCLUSIVE',
        purpose_type: '',
        expected_useful_life: '',
        to_be_ordered_by_id: null as number | null,
        line_items: [
            {
                name: '',
                quantity: 1,
                unit_id: '' as number | '',
                price: '' as number | '',
            },
        ] as LineItemData[],
        notes: [{ content: '' }] as NoteData[],
        status: 'DRAFT',
        attachments: [] as File[],
    });

    const [submittingWorkflow, setSubmittingWorkflow] = useState(false);

    const handleAction = (status: 'DRAFT' | 'REVIEWING') => {
        const submitOpts = {
            preserveScroll: true,
            forceFormData: form.data.attachments.length > 0,
        } as const;

        if (status === 'DRAFT') {
            form.transform((data) => ({
                ...data,
                status: 'DRAFT',
            }));
            form.post(PurchaseRequisitionController.store.url(), submitOpts);

            return;
        }

        setSubmittingWorkflow(true);
        form.transform((data) => ({
            ...data,
            status: 'DRAFT',
        }));
        form.post(PurchaseRequisitionController.store.url(), {
            ...submitOpts,
            onSuccess: (page) => {
                const pr = page.props.purchaseRequisition as
                    | { id: number }
                    | undefined;

                if (!pr?.id) {
                    setSubmittingWorkflow(false);

                    return;
                }

                router.post(
                    workflows.submit.url({ type: 'pr', id: pr.id }),
                    {},
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            setSubmittingWorkflow(false);
                        },
                        onError: () => {
                            setSubmittingWorkflow(false);
                        },
                        onFinish: () => {
                            setSubmittingWorkflow(false);
                        },
                    },
                );
            },
            onError: () => {
                setSubmittingWorkflow(false);
            },
        });
    };

    const rawTotal = form.data.line_items.reduce((sum, item) => {
        const qty = Number(item.quantity) || 0;
        const price = Number(item.price) || 0;

        return sum + qty * price;
    }, 0);

    const { netTotal, grossTotal, vatTotal } = priceBreakdown(
        rawTotal,
        form.data.price_type,
    );

    const selectedDepartmentNames = form.data.department_ids
        .map((id) => departments.find((d) => d.id === id)?.name)
        .filter(Boolean)
        .join(', ');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Purchase Requisition" />

            <form onSubmit={(e) => e.preventDefault()} className="w-full">
                <DocumentPageLayout
                    className="md:p-8"
                    header={
                        <div className="mb-2 flex items-start gap-4">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="mt-1 h-8 w-8 text-muted-foreground"
                                asChild
                            >
                                <Link href={purchaseRequisitions.index.url()}>
                                    <ArrowLeft className="h-4 w-4" />
                                    <span className="sr-only">Back</span>
                                </Link>
                            </Button>
                            <div className="flex flex-col gap-1">
                                <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                    Create Purchase Requisition
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    Fill in the details for your new requisition
                                    request
                                </p>
                            </div>
                        </div>
                    }
                    main={
                        <>
                            <RequestDetailsCard
                                departments={departments}
                                departmentIds={form.data.department_ids}
                                onDepartmentIdsChange={(ids) =>
                                    form.setData('department_ids', ids)
                                }
                                deliveryDate={form.data.delivery_date}
                                onDeliveryDateChange={(date) =>
                                    form.setData('delivery_date', date)
                                }
                                title={form.data.title}
                                onTitleChange={(val) =>
                                    form.setData('title', val)
                                }
                                description={form.data.description}
                                onDescriptionChange={(val) =>
                                    form.setData('description', val)
                                }
                                priceType={form.data.price_type}
                                onPriceTypeChange={(val) =>
                                    form.setData('price_type', val)
                                }
                                priceTypes={priceTypes}
                                purposeType={form.data.purpose_type}
                                onPurposeTypeChange={(val) =>
                                    form.setData('purpose_type', val)
                                }
                                purposeTypes={purposeTypes}
                                expectedUsefulLife={
                                    form.data.expected_useful_life
                                }
                                onExpectedUsefulLifeChange={(val) =>
                                    form.setData('expected_useful_life', val)
                                }
                                toBeOrderedById={form.data.to_be_ordered_by_id}
                                onToBeOrderedByIdChange={(val) =>
                                    form.setData('to_be_ordered_by_id', val)
                                }
                                users={users}
                                errors={form.errors}
                            />

                            <LineItemsCard
                                lineItems={form.data.line_items}
                                onLineItemsChange={(items) =>
                                    form.setData('line_items', items)
                                }
                                itemUnits={itemUnits}
                                errors={form.errors}
                            />
                            <AdditionalNotesCard
                                notes={form.data.notes}
                                onNotesChange={(notes) =>
                                    form.setData('notes', notes)
                                }
                                errors={form.errors}
                            />
                        </>
                    }
                    sidebar={
                        <>
                            <SummaryCard
                                processing={
                                    form.processing || submittingWorkflow
                                }
                                itemCount={form.data.line_items.length}
                                departmentNames={selectedDepartmentNames || '—'}
                                deliveryDate={form.data.delivery_date || '—'}
                                totalAmount={grossTotal}
                                netAmount={netTotal}
                                vatAmount={vatTotal}
                                onAction={handleAction}
                            />

                            {/* Attachments: desktop-only sidebar (unchanged from prior behavior) */}
                            <div className="hidden lg:block">
                                <DocumentAttachmentsField
                                    attachments={form.data.attachments}
                                    onAttachmentsChange={(files) =>
                                        form.setData('attachments', files)
                                    }
                                    removedAttachmentIds={[]}
                                    onRemovedAttachmentIdsChange={() => {}}
                                    errors={form.errors}
                                    disabled={
                                        form.processing || submittingWorkflow
                                    }
                                />
                            </div>
                        </>
                    }
                />
            </form>
        </AppLayout>
    );
}
