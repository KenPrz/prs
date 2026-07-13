import { Head, Link, useForm, usePoll } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { useState } from 'react';
import ReceivingReportController from '@/actions/App/Http/Controllers/ReceivingReportController';
import { DocumentAttachmentsField } from '@/components/document-attachments-field';
import { AdditionalNotesCard } from '@/components/procurement/additional-notes-card';
import type { NoteData } from '@/components/procurement/additional-notes-card';
import { DocumentPageLayout } from '@/components/procurement/document-page-layout';
import { OmitItemButton } from '@/components/procurement/omit-item-button';
import { OmittedItemsTable } from '@/components/procurement/omitted-items-table';
import { StatusBadge } from '@/components/procurement/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import * as purchaseOrders from '@/routes/purchase-orders';
import * as receivingReports from '@/routes/receiving-reports';
import type { BreadcrumbItem, PurchaseOrderModel } from '@/types';

type ReceivingItem = {
    purchase_order_item_id: number;
    quantity_received: number | '';
    quantity_rejected: number | '';
    remarks: string;
    selected: boolean;
};

type OrderedPurchaseOrder = {
    id: number;
    po_number: string;
    status: string;
    supplier: { id: number; name: string } | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Receiving reports',
        href: receivingReports.index.url(),
    },
    {
        title: 'Create',
        href: receivingReports.create.url(),
    },
];

export default function ReceivingReportCreate({
    purchaseOrder,
    orderedPurchaseOrders = [],
}: {
    purchaseOrder: PurchaseOrderModel | null;
    orderedPurchaseOrders?: OrderedPurchaseOrder[];
}) {
    const poItems = purchaseOrder?.items ?? [];

    const [receivingItems, setReceivingItems] = useState<ReceivingItem[]>(
        poItems.map((item) => ({
            purchase_order_item_id: item.id,
            quantity_received: item.is_omitted
                ? 0
                : (item.quantity_remaining ?? 0),
            quantity_rejected: '',
            remarks: '',
            selected: !item.is_omitted && (item.quantity_remaining ?? 0) > 0,
        })),
    );

    const form = useForm({
        purchase_order_id: purchaseOrder?.id ?? '',
        received_date: new Date().toISOString().split('T')[0],
        remarks: '',
        items: [] as {
            purchase_order_item_id: number;
            quantity_received: number;
            quantity_rejected: number;
            remarks: string;
        }[],
        notes: [{ content: '' }] as NoteData[],
        attachments: [] as File[],
    });

    usePoll(5000, { only: ['purchaseOrder'] });

    const updateReceivingItem = (
        index: number,
        field: keyof ReceivingItem,
        value: string | number | boolean,
    ) => {
        const updated = [...receivingItems];
        updated[index] = { ...updated[index], [field]: value };
        setReceivingItems(updated);
    };

    const handleSubmit = () => {
        const items = receivingItems
            .filter((r) => {
                const poItem = poItems.find(
                    (p) => p.id === r.purchase_order_item_id,
                );

                return (
                    r.selected &&
                    !poItem?.is_omitted &&
                    (Number(r.quantity_received) > 0 ||
                        Number(r.quantity_rejected) > 0)
                );
            })
            .map((r) => ({
                purchase_order_item_id: r.purchase_order_item_id,
                quantity_received: Number(r.quantity_received) || 0,
                quantity_rejected: Number(r.quantity_rejected) || 0,
                remarks: r.remarks,
            }));

        form.transform((data) => ({
            ...data,
            items,
        }));
        form.post(ReceivingReportController.store.url(), {
            forceFormData: form.data.attachments.length > 0,
        });
    };

    // Omitted items count as deselected everywhere, whatever the row state says.
    const selectedItems = receivingItems.filter((r) => {
        const poItem = poItems.find((p) => p.id === r.purchase_order_item_id);

        return r.selected && !poItem?.is_omitted;
    });
    const selectedCount = selectedItems.length;
    const totalReceived = selectedItems.reduce(
        (sum, r) => sum + (Number(r.quantity_received) || 0),
        0,
    );
    const totalRejected = selectedItems.reduce(
        (sum, r) => sum + (Number(r.quantity_rejected) || 0),
        0,
    );

    if (!purchaseOrder) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Create Receiving Report" />
                <div className="mx-auto w-full max-w-3xl p-6 md:p-8">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Create Receiving Report
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Select an ordered purchase order to receive against.
                    </p>

                    <Card className="mt-6">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Ordered Purchase Orders
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {orderedPurchaseOrders.length === 0 ? (
                                <p className="py-6 text-center text-sm text-muted-foreground">
                                    No purchase orders are ready for receiving.
                                    A purchase order must be marked as ordered
                                    first.
                                </p>
                            ) : (
                                <ul className="divide-y divide-border/60">
                                    {orderedPurchaseOrders.map((po) => (
                                        <li key={po.id}>
                                            <Link
                                                href={`${receivingReports.create.url()}?purchase_order_id=${po.id}`}
                                                className="flex items-center justify-between gap-4 py-3 transition-colors hover:bg-muted/40"
                                            >
                                                <span className="font-medium">
                                                    {po.po_number}
                                                    {po.supplier
                                                        ? ` — ${po.supplier.name}`
                                                        : ''}
                                                </span>
                                                <StatusBadge
                                                    status={po.status}
                                                />
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Button asChild variant="ghost" className="mt-4">
                        <Link href={receivingReports.index.url()}>
                            ← Back to receiving reports
                        </Link>
                    </Button>
                </div>
            </AppLayout>
        );
    }

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
            <Head title="Create Receiving Report" />

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
                                    Create Receiving Report
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    For PO {purchaseOrder.po_number}
                                    {purchaseOrder.supplier
                                        ? ` — ${purchaseOrder.supplier.name}`
                                        : ''}
                                </p>
                            </div>
                        </div>
                    }
                    main={
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg">
                                        Delivery Details
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="received_date">
                                            Received Date
                                        </Label>
                                        <DatePicker
                                            date={form.data.received_date}
                                            onChange={(val) =>
                                                form.setData(
                                                    'received_date',
                                                    val,
                                                )
                                            }
                                        />
                                        {form.errors.received_date && (
                                            <p className="text-xs text-destructive">
                                                {form.errors.received_date}
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
                                        Items to Receive
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
                                                        PO Qty
                                                    </th>
                                                    <th className="w-[70px] pb-3 text-center font-medium">
                                                        Already Rcvd
                                                    </th>
                                                    <th className="w-[70px] pb-3 text-center font-medium">
                                                        Remaining
                                                    </th>
                                                    <th className="w-[90px] pb-3 text-left font-medium">
                                                        Receive
                                                    </th>
                                                    <th className="w-[90px] pb-3 text-left font-medium">
                                                        Reject
                                                    </th>
                                                    <th className="pb-3 text-left font-medium">
                                                        Remarks
                                                    </th>
                                                    <th className="w-[110px] pb-3" />
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {receivingItems.map(
                                                    (receiving, index) => {
                                                        // Match by id — never by index: the prop array's
                                                        // order can change under polling/updates.
                                                        const poItem =
                                                            poItems.find(
                                                                (p) =>
                                                                    p.id ===
                                                                    receiving.purchase_order_item_id,
                                                            );

                                                        if (
                                                            !poItem ||
                                                            poItem.is_omitted
                                                        ) {
                                                            return null;
                                                        }

                                                        const alreadyReceived =
                                                            poItem?.quantity_received ??
                                                            0;
                                                        const remaining =
                                                            poItem?.quantity_remaining ??
                                                            0;

                                                        return (
                                                            <tr
                                                                key={
                                                                    receiving.purchase_order_item_id
                                                                }
                                                                className={`border-b align-top ${!receiving.selected ? 'opacity-50' : ''}`}
                                                            >
                                                                <td className="py-4 text-center">
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={
                                                                            receiving.selected
                                                                        }
                                                                        disabled={
                                                                            remaining ===
                                                                            0
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updateReceivingItem(
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
                                                                        {poItem
                                                                            ?.line_item
                                                                            ?.name ??
                                                                            '—'}
                                                                    </span>
                                                                </td>
                                                                <td className="py-4 text-center">
                                                                    {poItem?.quantity ??
                                                                        0}
                                                                </td>
                                                                <td className="py-4 text-center">
                                                                    {
                                                                        alreadyReceived
                                                                    }
                                                                </td>
                                                                <td className="py-4 text-center font-medium">
                                                                    {remaining}
                                                                </td>
                                                                <td className="py-4 pr-2">
                                                                    <Input
                                                                        type="number"
                                                                        min={0}
                                                                        max={
                                                                            remaining
                                                                        }
                                                                        disabled={
                                                                            !receiving.selected
                                                                        }
                                                                        className="h-9 w-20 px-2"
                                                                        value={
                                                                            receiving.quantity_received
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updateReceivingItem(
                                                                                index,
                                                                                'quantity_received',
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
                                                                        `items.${index}.quantity_received` as keyof typeof form.errors
                                                                    ] && (
                                                                        <p className="mt-1 text-xs text-destructive">
                                                                            {
                                                                                form
                                                                                    .errors[
                                                                                    `items.${index}.quantity_received` as keyof typeof form.errors
                                                                                ]
                                                                            }
                                                                        </p>
                                                                    )}
                                                                </td>
                                                                <td className="py-4 pr-2">
                                                                    <Input
                                                                        type="number"
                                                                        min={0}
                                                                        disabled={
                                                                            !receiving.selected
                                                                        }
                                                                        className="h-9 w-20 px-2"
                                                                        value={
                                                                            receiving.quantity_rejected
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updateReceivingItem(
                                                                                index,
                                                                                'quantity_rejected',
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
                                                                        `items.${index}.quantity_rejected` as keyof typeof form.errors
                                                                    ] && (
                                                                        <p className="mt-1 text-xs text-destructive">
                                                                            {
                                                                                form
                                                                                    .errors[
                                                                                    `items.${index}.quantity_rejected` as keyof typeof form.errors
                                                                                ]
                                                                            }
                                                                        </p>
                                                                    )}
                                                                </td>
                                                                <td className="py-4">
                                                                    <Input
                                                                        className="h-9"
                                                                        placeholder="Optional"
                                                                        disabled={
                                                                            !receiving.selected
                                                                        }
                                                                        value={
                                                                            receiving.remarks
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updateReceivingItem(
                                                                                index,
                                                                                'remarks',
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                    />
                                                                    {form
                                                                        .errors[
                                                                        `items.${index}.remarks` as keyof typeof form.errors
                                                                    ] && (
                                                                        <p className="mt-1 text-xs text-destructive">
                                                                            {
                                                                                form
                                                                                    .errors[
                                                                                    `items.${index}.remarks` as keyof typeof form.errors
                                                                                ]
                                                                            }
                                                                        </p>
                                                                    )}
                                                                </td>
                                                                <td className="py-4 text-right">
                                                                    <OmitItemButton
                                                                        endpoint={`/purchase-order-items/${receiving.purchase_order_item_id}/omission`}
                                                                        permission="po.omit"
                                                                        isOmitted={
                                                                            !!poItem?.is_omitted
                                                                        }
                                                                        disabled={
                                                                            remaining ===
                                                                            0
                                                                        }
                                                                    />
                                                                </td>
                                                            </tr>
                                                        );
                                                    },
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                    <OmittedItemsTable
                                        items={poItems
                                            .filter((p) => p?.is_omitted)
                                            .map((p) => ({
                                                name: p.line_item?.name ?? '—',
                                                quantity: p.quantity,
                                                reason: p.omit_reason,
                                                endpoint: `/purchase-order-items/${p.id}/omission`,
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
                                attachments={form.data.attachments}
                                onAttachmentsChange={(files) =>
                                    form.setData('attachments', files)
                                }
                                removedAttachmentIds={[]}
                                onRemovedAttachmentIdsChange={() => {}}
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
                                            Purchase Order
                                        </span>
                                        <span className="font-medium">
                                            {purchaseOrder.po_number}
                                        </span>
                                    </div>
                                    <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                        <span className="text-muted-foreground">
                                            Items Selected
                                        </span>
                                        <span className="font-medium">
                                            {selectedCount}
                                        </span>
                                    </div>
                                    <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                        <span className="text-muted-foreground">
                                            Total Receiving
                                        </span>
                                        <span className="font-medium text-emerald-600">
                                            {totalReceived}
                                        </span>
                                    </div>
                                    <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                                        <span className="text-muted-foreground">
                                            Total Rejected
                                        </span>
                                        <span className="font-medium text-destructive">
                                            {totalRejected}
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
                                        Save as Draft
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

                                <p className="px-2 text-center text-xs leading-relaxed text-muted-foreground/80">
                                    Selected quantities are reserved
                                    immediately, even as a draft. Submit the
                                    draft for verification from the report page.
                                </p>
                            </CardContent>
                        </Card>
                    }
                />
            </form>
        </AppLayout>
    );
}
