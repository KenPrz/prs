import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { useState } from 'react';
import ReceivingReportController from '@/actions/App/Http/Controllers/ReceivingReportController';
import { DocumentAttachmentsField } from '@/components/document-attachments-field';
import type { DocumentAttachmentRecord } from '@/components/document-attachments-field';
import { AdditionalNotesCard } from '@/components/procurement/additional-notes-card';
import type { NoteData } from '@/components/procurement/additional-notes-card';
import { DocumentPageLayout } from '@/components/procurement/document-page-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import * as receivingReports from '@/routes/receiving-reports';
import type {
    BreadcrumbItem,
    PurchaseOrderModel,
    ReceivingReportModel,
} from '@/types';

type ReceivingItem = {
    id?: number;
    purchase_order_item_id: number;
    quantity_received: number | '';
    quantity_rejected: number | '';
    remarks: string;
    selected: boolean;
};

export default function ReceivingReportEdit({
    receivingReport,
    purchaseOrder,
    attachments = [],
}: {
    receivingReport: ReceivingReportModel & {
        notes?: { id: number; content: string }[];
    };
    purchaseOrder: PurchaseOrderModel;
    attachments?: DocumentAttachmentRecord[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Receiving reports',
            href: receivingReports.index.url(),
        },
        {
            title: receivingReport.rr_number,
            href: receivingReports.show.url(receivingReport.id),
        },
        {
            title: 'Edit',
            href: receivingReports.edit.url(receivingReport.id),
        },
    ];

    const poItems = purchaseOrder.items ?? [];
    const existingByPoItem = new Map(
        (receivingReport.items ?? []).map((item) => [
            item.purchase_order_item_id,
            item,
        ]),
    );

    const [receivingItems, setReceivingItems] = useState<ReceivingItem[]>(
        poItems.map((item) => {
            const existing = existingByPoItem.get(item.id);

            return {
                id: existing?.id,
                purchase_order_item_id: item.id,
                quantity_received: existing?.quantity_received ?? '',
                quantity_rejected: existing?.quantity_rejected ?? '',
                remarks: existing?.remarks ?? '',
                selected: existing !== undefined,
            };
        }),
    );

    const form = useForm({
        received_date: receivingReport.received_date?.split('T')[0] ?? '',
        remarks: receivingReport.remarks ?? '',
        items: [] as {
            id?: number;
            purchase_order_item_id: number;
            quantity_received: number;
            quantity_rejected: number;
            remarks: string;
        }[],
        notes:
            (receivingReport.notes?.length ?? 0) > 0
                ? (receivingReport.notes!.map((note) => ({
                      id: note.id,
                      content: note.content,
                  })) as NoteData[])
                : ([{ content: '' }] as NoteData[]),
        attachments: [] as File[],
        removed_attachment_ids: [] as number[],
    });

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
                ...(r.id ? { id: r.id } : {}),
                purchase_order_item_id: r.purchase_order_item_id,
                quantity_received: Number(r.quantity_received) || 0,
                quantity_rejected: Number(r.quantity_rejected) || 0,
                remarks: r.remarks,
            }));

        form.transform((data) => ({
            ...data,
            items,
        }));
        form.put(ReceivingReportController.update.url(receivingReport.id), {
            forceFormData: form.data.attachments.length > 0,
        });
    };

    // Omitted items count as deselected everywhere, whatever the row state says.
    const selectedItems = receivingItems.filter((r) => {
        const poItem = poItems.find((p) => p.id === r.purchase_order_item_id);

        return r.selected && !poItem?.is_omitted;
    });
    const totalReceived = selectedItems.reduce(
        (sum, r) => sum + (Number(r.quantity_received) || 0),
        0,
    );
    const totalRejected = selectedItems.reduce(
        (sum, r) => sum + (Number(r.quantity_rejected) || 0),
        0,
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
            <Head title={`Edit ${receivingReport.rr_number}`} />

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
                                    href={receivingReports.show.url(
                                        receivingReport.id,
                                    )}
                                >
                                    <ArrowLeft className="h-4 w-4" />
                                    <span className="sr-only">Back</span>
                                </Link>
                            </Button>
                            <div className="flex flex-col gap-1">
                                <h1 className="text-2xl font-semibold tracking-tight">
                                    Edit Receiving Report{' '}
                                    {receivingReport.rr_number}
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
                                                    <th className="w-[80px] pb-3 text-center font-medium">
                                                        Available
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

                                                        // This report's own draft rows already count against
                                                        // quantity_remaining, so add them back as available.
                                                        const ownQty =
                                                            existingByPoItem.get(
                                                                poItem.id,
                                                            )
                                                                ?.quantity_received ??
                                                            0;
                                                        const available =
                                                            (poItem?.quantity_remaining ??
                                                                0) + ownQty;

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
                                                                            available ===
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
                                                                <td className="py-4 text-center font-medium">
                                                                    {available}
                                                                </td>
                                                                <td className="py-4 pr-2">
                                                                    <Input
                                                                        type="number"
                                                                        min={0}
                                                                        max={
                                                                            available
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
                                                                </td>
                                                            </tr>
                                                        );
                                                    },
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
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
                                            {selectedItems.length}
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
                                        Save Changes
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        asChild
                                        className="w-full"
                                    >
                                        <Link
                                            href={receivingReports.show.url(
                                                receivingReport.id,
                                            )}
                                        >
                                            Cancel
                                        </Link>
                                    </Button>
                                </div>

                                <p className="px-2 text-center text-xs leading-relaxed text-muted-foreground/80">
                                    Only drafts can be edited. Deselected items
                                    return to the pool when you save.
                                </p>
                            </CardContent>
                        </Card>
                    }
                />
            </form>
        </AppLayout>
    );
}
