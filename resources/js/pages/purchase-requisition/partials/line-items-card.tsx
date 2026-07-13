import { ChevronLeft, ChevronRight, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardHeader,
    CardTitle,
    CardContent,
    CardFooter,
} from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import type { ComboboxOption } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { formatPHP } from '@/lib/format-currency';
import type { LineItemData } from '../create';

interface ItemUnit {
    id: number;
    name: string;
    code: string;
}

interface LineItemsCardProps {
    lineItems: LineItemData[];
    onLineItemsChange: (items: LineItemData[]) => void;
    itemUnits: ItemUnit[];
    errors: Record<string, string>;
    isReadOnly?: boolean;
    /** Optional trailing cell per row (e.g. an omit/restore control). */
    renderRowAction?: (index: number) => React.ReactNode;
    /** Visually mute a row (e.g. an omitted item). */
    rowMuted?: (index: number) => boolean;
}

export function LineItemsCard({
    lineItems,
    onLineItemsChange,
    itemUnits,
    errors,
    isReadOnly = false,
    renderRowAction,
    rowMuted,
}: LineItemsCardProps) {
    const [currentPage, setCurrentPage] = useState(1);
    const pageSize = 100;

    const totalPages = Math.ceil(lineItems.length / pageSize);
    const paginatedItems =
        lineItems.length > pageSize
            ? lineItems.slice(
                  (currentPage - 1) * pageSize,
                  currentPage * pageSize,
              )
            : lineItems;

    const addItem = () => {
        onLineItemsChange([
            ...lineItems,
            {
                name: '',
                quantity: 1,
                unit_id: '',
                price: '',
            },
        ]);
    };

    const removeItem = (index: number) => {
        if (lineItems.length === 1) {
            return;
        }

        const updated = [...lineItems];
        updated.splice(index, 1);
        onLineItemsChange(updated);
    };

    const updateItem = (
        index: number,
        field: keyof LineItemData,
        value: string | number,
    ) => {
        const updated = [...lineItems];
        updated[index] = { ...updated[index], [field]: value };
        onLineItemsChange(updated);
    };

    const unitOptions: ComboboxOption[] = itemUnits.map((unit) => ({
        label: unit.code,
        value: String(unit.id),
    }));

    const grandTotal = lineItems.reduce((sum, item, index) => {
        if (rowMuted?.(index)) {
            return sum;
        }

        const qty = Number(item.quantity) || 0;
        const price = Number(item.price) || 0;

        return sum + qty * price;
    }, 0);

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4">
                <CardTitle className="text-lg">Line Items</CardTitle>
                {!isReadOnly && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-8 gap-1.5 font-normal"
                        onClick={addItem}
                    >
                        <Plus className="h-3.5 w-3.5" />
                        Add Item
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                {errors.line_items && (
                    <p className="mb-4 text-sm text-destructive">
                        {errors.line_items}
                    </p>
                )}
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-muted-foreground">
                                <th className="pb-3 text-left font-medium">
                                    Description
                                </th>
                                <th className="w-[80px] pb-3 text-left font-medium">
                                    Qty
                                </th>
                                <th className="w-[120px] pb-3 text-left font-medium">
                                    Unit
                                </th>
                                <th className="w-[120px] pb-3 text-left font-medium">
                                    Unit Price
                                </th>
                                <th className="w-[100px] pb-3 text-right font-medium">
                                    Total
                                </th>
                                {!isReadOnly && (
                                    <th className="w-[40px] pb-3"></th>
                                )}
                                {renderRowAction && (
                                    <th className="w-[140px] pb-3"></th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {paginatedItems.map((item, pageIndex) => {
                                const index =
                                    (currentPage - 1) * pageSize + pageIndex;
                                const rowTotal =
                                    (Number(item.quantity) || 0) *
                                    (Number(item.price) || 0);

                                return (
                                    <tr
                                        key={index}
                                        className={
                                            rowMuted?.(index)
                                                ? 'border-b align-top opacity-60'
                                                : 'border-b align-top'
                                        }
                                    >
                                        <td className="py-4 pr-4">
                                            <Input
                                                placeholder="Item description"
                                                className="h-9 disabled:cursor-not-allowed"
                                                value={item.name}
                                                onChange={(e) =>
                                                    updateItem(
                                                        index,
                                                        'name',
                                                        e.target.value,
                                                    )
                                                }
                                                disabled={isReadOnly}
                                            />
                                            {errors[
                                                `line_items.${index}.name`
                                            ] && (
                                                <p className="mt-1 text-xs text-destructive">
                                                    {
                                                        errors[
                                                            `line_items.${index}.name`
                                                        ]
                                                    }
                                                </p>
                                            )}
                                        </td>
                                        <td className="py-4 pr-4">
                                            <Input
                                                type="number"
                                                min={1}
                                                className="h-9 px-2 disabled:cursor-not-allowed"
                                                value={item.quantity}
                                                onChange={(e) =>
                                                    updateItem(
                                                        index,
                                                        'quantity',
                                                        e.target.value === ''
                                                            ? ''
                                                            : Number(
                                                                  e.target
                                                                      .value,
                                                              ),
                                                    )
                                                }
                                                disabled={isReadOnly}
                                            />
                                            {errors[
                                                `line_items.${index}.quantity`
                                            ] && (
                                                <p className="mt-1 text-xs text-destructive">
                                                    {
                                                        errors[
                                                            `line_items.${index}.quantity`
                                                        ]
                                                    }
                                                </p>
                                            )}
                                        </td>
                                        <td className="py-4 pr-4">
                                            <Combobox
                                                options={unitOptions}
                                                value={String(item.unit_id)}
                                                onChange={(val) =>
                                                    updateItem(
                                                        index,
                                                        'unit_id',
                                                        Number(val),
                                                    )
                                                }
                                                placeholder="Unit"
                                                className="h-9 text-xs"
                                                disabled={isReadOnly}
                                            />
                                            {errors[
                                                `line_items.${index}.unit_id`
                                            ] && (
                                                <p className="mt-1 text-xs text-destructive">
                                                    {
                                                        errors[
                                                            `line_items.${index}.unit_id`
                                                        ]
                                                    }
                                                </p>
                                            )}
                                        </td>
                                        <td className="py-4 pr-4">
                                            <Input
                                                type="number"
                                                min={0}
                                                step="0.01"
                                                className="h-9 px-2 disabled:cursor-not-allowed"
                                                value={item.price}
                                                onChange={(e) =>
                                                    updateItem(
                                                        index,
                                                        'price',
                                                        e.target.value === ''
                                                            ? ''
                                                            : Number(
                                                                  e.target
                                                                      .value,
                                                              ),
                                                    )
                                                }
                                                disabled={isReadOnly}
                                            />
                                            {errors[
                                                `line_items.${index}.price`
                                            ] && (
                                                <p className="mt-1 text-xs text-destructive">
                                                    {
                                                        errors[
                                                            `line_items.${index}.price`
                                                        ]
                                                    }
                                                </p>
                                            )}
                                        </td>
                                        <td className="py-4 text-right font-medium">
                                            {formatPHP(rowTotal)}
                                        </td>
                                        {!isReadOnly && (
                                            <td className="py-4 text-right">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    disabled={
                                                        lineItems.length === 1
                                                    }
                                                    className="h-8 w-8 text-muted-foreground/60 hover:bg-destructive/10 hover:text-destructive"
                                                    onClick={() =>
                                                        removeItem(index)
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </td>
                                        )}
                                        {renderRowAction && (
                                            <td className="py-4 text-right align-middle">
                                                {renderRowAction(index)}
                                            </td>
                                        )}
                                    </tr>
                                );
                            })}
                        </tbody>
                        <tfoot>
                            <tr className="border-t border-border/60">
                                <td
                                    colSpan={4}
                                    className="py-4 pr-4 text-right font-semibold text-foreground"
                                >
                                    Grand Total:
                                </td>
                                <td className="py-4 text-right text-base font-bold text-foreground">
                                    {formatPHP(grandTotal)}
                                </td>
                                {!isReadOnly && <td></td>}
                                {renderRowAction && <td></td>}
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </CardContent>
            {totalPages > 1 && (
                <CardFooter className="flex items-center justify-between border-t border-border/50 px-6 py-4">
                    <span className="text-sm text-muted-foreground">
                        Showing {(currentPage - 1) * pageSize + 1} to{' '}
                        {Math.min(currentPage * pageSize, lineItems.length)} of{' '}
                        {lineItems.length} items
                    </span>
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={currentPage === 1}
                            onClick={() => setCurrentPage((p) => p - 1)}
                            className="h-8 gap-1"
                        >
                            <ChevronLeft className="h-4 w-4" />
                            Previous
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={currentPage === totalPages}
                            onClick={() => setCurrentPage((p) => p + 1)}
                            className="h-8 gap-1"
                        >
                            Next
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                </CardFooter>
            )}
        </Card>
    );
}
