import { Head, Link, useForm, usePoll } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { useState } from 'react';
import PurchaseOrderController from '@/actions/App/Http/Controllers/PurchaseOrderController';
import { DocumentAttachmentsField } from '@/components/document-attachments-field';
import type { DocumentAttachmentRecord } from '@/components/document-attachments-field';
import { AdditionalNotesCard } from '@/components/procurement/additional-notes-card';
import type { NoteData } from '@/components/procurement/additional-notes-card';
import { DocumentPageLayout } from '@/components/procurement/document-page-layout';
import { OmitItemButton } from '@/components/procurement/omit-item-button';
import { OmittedItemsTable } from '@/components/procurement/omitted-items-table';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import type { ComboboxOption } from '@/components/ui/combobox';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCurrency } from '@/hooks/use-currency';
import AppLayout from '@/layouts/app-layout';
import { priceBreakdown } from '@/lib/price-type';
import * as purchaseOrders from '@/routes/purchase-orders';
import type {
    BreadcrumbItem,
    ItemUnit,
    LineItemModel,
    PurchaseOrderModel,
    Supplier,
} from '@/types';

type Address = {
    id: number;
    recipient_name: string;
    street: string;
    city: string;
};

type AllocationItem = {
    line_item_id: number;
    quantity: number | '';
    unit_id: number | '';
    price: number | '';
    selected: boolean;
};

type PurchaseRequisitionWithItems = {
    id: number;
    pr_number: string;
    title: string;
    price_type?: string;
    line_items?: LineItemModel[];
};

export default function PurchaseOrderEdit({
    purchaseOrder,
    purchaseRequisition,
    suppliers = [],
    itemUnits = [],
    priceTypes = [],
    addresses = [],
    attachments = [],
}: {
    purchaseOrder: PurchaseOrderModel & {
        notes?: { id: number; content: string }[];
    };
    purchaseRequisition: PurchaseRequisitionWithItems;
    suppliers: Supplier[];
    itemUnits: ItemUnit[];
    priceTypes: string[];
    addresses: Address[];
    attachments?: DocumentAttachmentRecord[];
}) {
    const { symbol } = useCurrency();
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Purchase orders',
            href: purchaseOrders.index.url(),
        },
        {
            title: purchaseOrder.po_number,
            href: purchaseOrders.show.url(purchaseOrder.id),
        },
        {
            title: 'Edit',
            href: purchaseOrders.edit.url(purchaseOrder.id),
        },
    ];

    const prItems = purchaseRequisition?.line_items ?? [];
    const existingItems = purchaseOrder.items ?? [];

    const [allocations, setAllocations] = useState<AllocationItem[]>(
        prItems.map((prItem) => {
            const existing = existingItems.find(
                (i) => i.line_item_id === prItem.id,
            );

            return {
                line_item_id: prItem.id,
                quantity: existing ? existing.quantity : '',
                unit_id: existing ? existing.unit_id : prItem.unit_id,
                price: existing
                    ? Number(existing.price)
                    : prItem.price
                      ? Number(prItem.price)
                      : '',
                selected: !!existing && !prItem.is_omitted,
            };
        }),
    );

    const form = useForm({
        purchase_requisition_id: purchaseRequisition.id,
        supplier_id: purchaseOrder.supplier?.id ?? ('' as number | ''),
        expected_delivery_date: purchaseOrder.expected_delivery_date ?? '',
        terms_and_conditions: purchaseOrder.terms_and_conditions ?? '',
        remarks: purchaseOrder.remarks ?? '',
        bill_to_id: purchaseOrder.bill_to_id ?? ('' as number | ''),
        ship_to_id: purchaseOrder.ship_to_id ?? ('' as number | ''),
        payment_terms: purchaseOrder.payment_terms ?? '',
        currency: purchaseOrder.currency ?? 'PHP',
        price_type:
            purchaseOrder.price_type || priceTypes[0] || 'VAT_INCLUSIVE',
        items: [] as {
            line_item_id: number;
            quantity: number;
            unit_id: number;
            price: number;
        }[],
        notes:
            (purchaseOrder.notes?.length ?? 0) > 0
                ? (purchaseOrder.notes!.map((note) => ({
                      id: note.id,
                      content: note.content,
                  })) as NoteData[])
                : ([{ content: '' }] as NoteData[]),
        attachments: [] as File[],
        removed_attachment_ids: [] as number[],
    });

    usePoll(5000, { only: ['purchaseRequisition', 'purchaseOrder'] });

    const updateAllocation = (
        index: number,
        field: keyof AllocationItem,
        value: string | number | boolean,
    ) => {
        const updated = [...allocations];
        updated[index] = { ...updated[index], [field]: value };
        setAllocations(updated);
    };

    const handleSubmit = () => {
        const selectedItems = allocations
            .filter((a) => {
                const prItem = prItems.find((p) => p.id === a.line_item_id);

                return (
                    a.selected && !prItem?.is_omitted && Number(a.quantity) > 0
                );
            })
            .map((a) => ({
                line_item_id: a.line_item_id,
                quantity: Number(a.quantity),
                unit_id: Number(a.unit_id),
                price: Number(a.price) || 0,
            }));

        form.transform((data) => ({
            ...data,
            items: selectedItems,
        }));
        form.put(PurchaseOrderController.update.url(purchaseOrder.id), {
            forceFormData: form.data.attachments.length > 0,
        });
    };

    const supplierOptions: ComboboxOption[] = suppliers.map((s) => ({
        label: s.name,
        value: String(s.id),
    }));

    const priceTypeOptions: ComboboxOption[] = priceTypes.map((pt) => ({
        label: pt,
        value: pt,
    }));

    const addressOptions: ComboboxOption[] = addresses.map((a) => ({
        label: `${a.recipient_name} - ${a.street}, ${a.city}`,
        value: String(a.id),
    }));

    const currencyOptions: ComboboxOption[] = [
        { label: 'PHP - Philippine Peso', value: 'PHP' },
        { label: 'USD - US Dollar', value: 'USD' },
        { label: 'EUR - Euro', value: 'EUR' },
        { label: 'JPY - Japanese Yen', value: 'JPY' },
        { label: 'GBP - British Pound', value: 'GBP' },
    ];

    const rawTotal = allocations
        .filter((a) => a.selected)
        .reduce((sum, a) => {
            return sum + (Number(a.quantity) || 0) * (Number(a.price) || 0);
        }, 0);

    const { netTotal, grossTotal, vatTotal } = priceBreakdown(
        rawTotal,
        form.data.price_type,
    );

    // Server-side item errors can belong to rows that are no longer
    // rendered (e.g. omitted mid-edit) — surface them all here.
    const itemErrors = [
        ...new Set(
            Object.entries(form.errors)
                .filter(([key]) => key.startsWith('items'))
                .map(([, message]) => String(message)),
        ),
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${purchaseOrder.po_number}`} />

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    handleSubmit();
                }}
                className="w-full"
            >
                {itemErrors.length > 0 && (
                    <div className="mb-4 rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                        {itemErrors.map((message) => (
                            <p key={message}>{message}</p>
                        ))}
                    </div>
                )}
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
                                <Link
                                    href={purchaseOrders.show.url(
                                        purchaseOrder.id,
                                    )}
                                >
                                    <ArrowLeft className="h-4 w-4" />
                                    <span className="sr-only">Back</span>
                                </Link>
                            </Button>
                            <div className="flex flex-col gap-1">
                                <h1 className="text-2xl font-semibold tracking-tight">
                                    Edit {purchaseOrder.po_number}
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    From PR {purchaseRequisition.pr_number} —{' '}
                                    {purchaseRequisition.title}
                                </p>
                            </div>
                        </div>
                    }
                    main={
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg">
                                        Supplier & Details
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="supplier">
                                            Supplier
                                        </Label>
                                        <Combobox
                                            options={supplierOptions}
                                            value={String(
                                                form.data.supplier_id,
                                            )}
                                            onChange={(val) =>
                                                form.setData(
                                                    'supplier_id',
                                                    Number(val),
                                                )
                                            }
                                            placeholder="Select a supplier"
                                        />
                                        {form.errors.supplier_id && (
                                            <p className="text-xs text-destructive">
                                                {form.errors.supplier_id}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="expected_delivery_date">
                                            Expected Delivery Date
                                        </Label>
                                        <DatePicker
                                            date={
                                                form.data.expected_delivery_date
                                            }
                                            onChange={(val) =>
                                                form.setData(
                                                    'expected_delivery_date',
                                                    val,
                                                )
                                            }
                                        />
                                        {form.errors.expected_delivery_date && (
                                            <p className="text-xs text-destructive">
                                                {
                                                    form.errors
                                                        .expected_delivery_date
                                                }
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="bill_to_id">
                                            Bill To
                                        </Label>
                                        <Combobox
                                            options={addressOptions}
                                            value={String(form.data.bill_to_id)}
                                            onChange={(val) =>
                                                form.setData(
                                                    'bill_to_id',
                                                    Number(val),
                                                )
                                            }
                                            placeholder="Select billing address"
                                        />
                                        {form.errors.bill_to_id && (
                                            <p className="text-xs text-destructive">
                                                {form.errors.bill_to_id}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="ship_to_id">
                                            Ship To
                                        </Label>
                                        <Combobox
                                            options={addressOptions}
                                            value={String(form.data.ship_to_id)}
                                            onChange={(val) =>
                                                form.setData(
                                                    'ship_to_id',
                                                    Number(val),
                                                )
                                            }
                                            placeholder="Select shipping address"
                                        />
                                        {form.errors.ship_to_id && (
                                            <p className="text-xs text-destructive">
                                                {form.errors.ship_to_id}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="payment_terms">
                                            Payment Terms
                                        </Label>
                                        <Input
                                            id="payment_terms"
                                            value={form.data.payment_terms}
                                            onChange={(e) =>
                                                form.setData(
                                                    'payment_terms',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="e.g. Net 30, Cash on Delivery"
                                        />
                                        {form.errors.payment_terms && (
                                            <p className="text-xs text-destructive">
                                                {form.errors.payment_terms}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="currency">
                                            Currency
                                        </Label>
                                        <Combobox
                                            options={currencyOptions}
                                            value={form.data.currency}
                                            onChange={(val) =>
                                                form.setData('currency', val)
                                            }
                                            placeholder="Select currency"
                                        />
                                        {form.errors.currency && (
                                            <p className="text-xs text-destructive">
                                                {form.errors.currency}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="price_type">
                                            Price Type
                                        </Label>
                                        <Combobox
                                            options={priceTypeOptions}
                                            value={form.data.price_type}
                                            onChange={(val) =>
                                                form.setData('price_type', val)
                                            }
                                            placeholder="Select price type"
                                        />
                                        {form.errors.price_type && (
                                            <p className="text-xs text-destructive">
                                                {form.errors.price_type}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="terms_and_conditions">
                                            Terms & Conditions
                                        </Label>
                                        <textarea
                                            id="terms_and_conditions"
                                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            rows={3}
                                            value={
                                                form.data.terms_and_conditions
                                            }
                                            onChange={(e) =>
                                                form.setData(
                                                    'terms_and_conditions',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {form.errors.terms_and_conditions && (
                                            <p className="text-xs text-destructive">
                                                {
                                                    form.errors
                                                        .terms_and_conditions
                                                }
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="remarks">Remarks</Label>
                                        <textarea
                                            id="remarks"
                                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            rows={2}
                                            value={form.data.remarks}
                                            onChange={(e) =>
                                                form.setData(
                                                    'remarks',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {form.errors.remarks && (
                                            <p className="text-xs text-destructive">
                                                {form.errors.remarks}
                                            </p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg">
                                        Item Allocation
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {form.errors.items && (
                                        <p className="mb-4 text-sm text-destructive">
                                            {form.errors.items}
                                        </p>
                                    )}
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b text-muted-foreground">
                                                    <th className="w-[40px] pb-3" />
                                                    <th className="pb-3 text-left font-medium">
                                                        Description
                                                    </th>
                                                    <th className="w-[70px] pb-3 text-center font-medium">
                                                        PR Qty
                                                    </th>
                                                    <th className="w-[70px] pb-3 text-center font-medium">
                                                        Available
                                                    </th>
                                                    <th className="w-[80px] pb-3 text-left font-medium">
                                                        Allocate
                                                    </th>
                                                    <th className="w-[100px] pb-3 text-left font-medium">
                                                        Unit
                                                    </th>
                                                    <th className="w-[100px] pb-3 text-left font-medium">
                                                        Price
                                                    </th>
                                                    <th className="w-[90px] pb-3 text-right font-medium">
                                                        Total
                                                    </th>
                                                    <th className="w-[110px] pb-3" />
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {allocations.map(
                                                    (alloc, index) => {
                                                        // Match by id — never by index: the prop array's
                                                        // order can change under updates.
                                                        const prItem =
                                                            prItems.find(
                                                                (p) =>
                                                                    p.id ===
                                                                    alloc.line_item_id,
                                                            );

                                                        if (
                                                            !prItem ||
                                                            prItem.is_omitted
                                                        ) {
                                                            return null;
                                                        }

                                                        const existingItem =
                                                            existingItems.find(
                                                                (i) =>
                                                                    i.line_item_id ===
                                                                    prItem?.id,
                                                            );
                                                        // Available = unallocated + what this PO currently holds
                                                        const baseAvailable =
                                                            prItem?.quantity_unallocated ??
                                                            prItem?.quantity ??
                                                            0;
                                                        const currentAlloc =
                                                            existingItem
                                                                ? existingItem.quantity
                                                                : 0;
                                                        const available =
                                                            baseAvailable +
                                                            currentAlloc;
                                                        const rowTotal =
                                                            alloc.selected
                                                                ? (Number(
                                                                      alloc.quantity,
                                                                  ) || 0) *
                                                                  (Number(
                                                                      alloc.price,
                                                                  ) || 0)
                                                                : 0;

                                                        return (
                                                            <tr
                                                                key={
                                                                    alloc.line_item_id
                                                                }
                                                                className={`border-b align-top ${!alloc.selected ? 'opacity-50' : ''}`}
                                                            >
                                                                <td className="py-4 text-center">
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={
                                                                            alloc.selected
                                                                        }
                                                                        disabled={
                                                                            available ===
                                                                            0
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updateAllocation(
                                                                                index,
                                                                                'selected',
                                                                                e
                                                                                    .target
                                                                                    .checked,
                                                                            )
                                                                        }
                                                                        className="rounded border-input"
                                                                    />
                                                                </td>
                                                                <td className="py-4 pr-2">
                                                                    <span>
                                                                        {prItem?.name ??
                                                                            '—'}
                                                                    </span>
                                                                </td>
                                                                <td className="py-4 text-center">
                                                                    {prItem?.quantity ??
                                                                        0}
                                                                </td>
                                                                <td className="py-4 text-center font-medium">
                                                                    {available}
                                                                </td>
                                                                <td className="py-4 pr-2">
                                                                    <Input
                                                                        type="number"
                                                                        min={1}
                                                                        max={
                                                                            available
                                                                        }
                                                                        className="h-9 w-20 px-2"
                                                                        value={
                                                                            alloc.quantity
                                                                        }
                                                                        disabled={
                                                                            !alloc.selected
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updateAllocation(
                                                                                index,
                                                                                'quantity',
                                                                                e
                                                                                    .target
                                                                                    .value ===
                                                                                    ''
                                                                                    ? ''
                                                                                    : Number(
                                                                                          e
                                                                                              .target
                                                                                              .value,
                                                                                      ),
                                                                            )
                                                                        }
                                                                    />
                                                                    {form
                                                                        .errors[
                                                                        `items.${index}.quantity` as keyof typeof form.errors
                                                                    ] && (
                                                                        <p className="mt-1 text-xs text-destructive">
                                                                            {
                                                                                form
                                                                                    .errors[
                                                                                    `items.${index}.quantity` as keyof typeof form.errors
                                                                                ]
                                                                            }
                                                                        </p>
                                                                    )}
                                                                </td>
                                                                <td className="py-4 pr-2">
                                                                    <span className="text-sm text-muted-foreground">
                                                                        {itemUnits.find(
                                                                            (
                                                                                u,
                                                                            ) =>
                                                                                u.id ===
                                                                                alloc.unit_id,
                                                                        )
                                                                            ?.code ??
                                                                            '—'}
                                                                    </span>
                                                                    {form
                                                                        .errors[
                                                                        `items.${index}.unit_id` as keyof typeof form.errors
                                                                    ] && (
                                                                        <p className="mt-1 text-xs text-destructive">
                                                                            {
                                                                                form
                                                                                    .errors[
                                                                                    `items.${index}.unit_id` as keyof typeof form.errors
                                                                                ]
                                                                            }
                                                                        </p>
                                                                    )}
                                                                </td>
                                                                <td className="py-4 pr-2">
                                                                    <Input
                                                                        type="number"
                                                                        min={0}
                                                                        step="0.01"
                                                                        required
                                                                        className="h-9 w-24 px-2"
                                                                        value={
                                                                            alloc.price
                                                                        }
                                                                        disabled={
                                                                            !alloc.selected
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updateAllocation(
                                                                                index,
                                                                                'price',
                                                                                e
                                                                                    .target
                                                                                    .value ===
                                                                                    ''
                                                                                    ? ''
                                                                                    : Number(
                                                                                          e
                                                                                              .target
                                                                                              .value,
                                                                                      ),
                                                                            )
                                                                        }
                                                                    />
                                                                    {form
                                                                        .errors[
                                                                        `items.${index}.price` as keyof typeof form.errors
                                                                    ] && (
                                                                        <p className="mt-1 text-xs text-destructive">
                                                                            {
                                                                                form
                                                                                    .errors[
                                                                                    `items.${index}.price` as keyof typeof form.errors
                                                                                ]
                                                                            }
                                                                        </p>
                                                                    )}
                                                                </td>
                                                                <td className="py-4 text-right font-medium">
                                                                    {symbol}
                                                                    {rowTotal.toLocaleString(
                                                                        'en-PH',
                                                                        {
                                                                            minimumFractionDigits: 2,
                                                                        },
                                                                    )}
                                                                </td>
                                                                <td className="py-4 text-right">
                                                                    <OmitItemButton
                                                                        endpoint={`/line-items/${alloc.line_item_id}/omission`}
                                                                        permission="pr.omit"
                                                                        isOmitted={
                                                                            !!prItem?.is_omitted
                                                                        }
                                                                        disabled={
                                                                            available ===
                                                                            0
                                                                        }
                                                                    />
                                                                </td>
                                                            </tr>
                                                        );
                                                    },
                                                )}
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td
                                                        colSpan={7}
                                                        className="py-5 pr-4 text-right font-medium"
                                                    >
                                                        Grand Total:
                                                    </td>
                                                    <td className="py-5 text-right text-base font-bold">
                                                        {symbol}
                                                        {grossTotal.toLocaleString(
                                                            'en-PH',
                                                            {
                                                                minimumFractionDigits: 2,
                                                            },
                                                        )}
                                                    </td>
                                                    <td />
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    <OmittedItemsTable
                                        items={prItems
                                            .filter((p) => p?.is_omitted)
                                            .map((p) => ({
                                                name: p.name,
                                                quantity: p.quantity,
                                                reason: p.omit_reason,
                                                endpoint: `/line-items/${p.id}/omission`,
                                            }))}
                                    />
                                </CardContent>
                            </Card>

                            <AdditionalNotesCard
                                notes={form.data.notes}
                                onNotesChange={(notes) =>
                                    form.setData('notes', notes)
                                }
                                errors={form.errors}
                            />

                            <DocumentAttachmentsField
                                existingAttachments={attachments}
                                attachments={form.data.attachments}
                                onAttachmentsChange={(files) =>
                                    form.setData('attachments', files)
                                }
                                removedAttachmentIds={
                                    form.data.removed_attachment_ids
                                }
                                onRemovedAttachmentIdsChange={(ids) =>
                                    form.setData('removed_attachment_ids', ids)
                                }
                                errors={form.errors}
                                disabled={form.processing}
                            />
                        </>
                    }
                    sidebar={
                        <Card className="bg-card/50">
                            <CardHeader>
                                <CardTitle className="text-lg">
                                    Summary
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-6">
                                <div className="grid gap-3">
                                    <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                        <span className="text-muted-foreground">
                                            Source PR
                                        </span>
                                        <span className="font-medium">
                                            {purchaseRequisition.pr_number}
                                        </span>
                                    </div>
                                    <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                        <span className="text-muted-foreground">
                                            Items Selected
                                        </span>
                                        <span className="font-medium">
                                            {
                                                allocations.filter(
                                                    (a) => a.selected,
                                                ).length
                                            }
                                        </span>
                                    </div>
                                    <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                        <span className="text-muted-foreground">
                                            Net Amount
                                        </span>
                                        <span className="font-medium">
                                            {symbol}
                                            {netTotal.toLocaleString('en-PH', {
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2,
                                            })}
                                        </span>
                                    </div>
                                    <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                        <span className="text-muted-foreground">
                                            VAT Amount
                                        </span>
                                        <span className="font-medium">
                                            {symbol}
                                            {vatTotal.toLocaleString('en-PH', {
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2,
                                            })}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between pt-2">
                                        <span className="font-semibold">
                                            Total Amount
                                        </span>
                                        <span className="text-lg font-bold">
                                            {symbol}
                                            {grossTotal.toLocaleString(
                                                'en-PH',
                                                {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2,
                                                },
                                            )}
                                        </span>
                                    </div>
                                </div>

                                <div className="flex flex-col gap-3">
                                    <Button
                                        type="submit"
                                        className="w-full gap-2"
                                        disabled={form.processing}
                                    >
                                        <Save className="h-4 w-4" />
                                        Update Purchase Order
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        asChild
                                        className="w-full"
                                    >
                                        <Link
                                            href={purchaseOrders.show.url(
                                                purchaseOrder.id,
                                            )}
                                        >
                                            Cancel
                                        </Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    }
                />
            </form>
        </AppLayout>
    );
}
