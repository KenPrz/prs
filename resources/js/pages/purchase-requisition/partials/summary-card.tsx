import { Save, Send } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { useCurrency } from '@/hooks/use-currency';
import { formatPHP } from '@/lib/format-currency';

interface SummaryCardProps {
    processing: boolean;
    itemCount: number;
    departmentNames: string;
    deliveryDate: string;
    totalAmount: number;
    netAmount?: number;
    vatAmount?: number;
    onAction?: (status: 'DRAFT' | 'REVIEWING') => void;
    actions?: ReactNode;
}

export function SummaryCard({
    processing,
    itemCount,
    departmentNames,
    deliveryDate,
    totalAmount,
    netAmount,
    vatAmount,
    onAction,
    actions,
}: SummaryCardProps) {
    const { symbol } = useCurrency();

    return (
        <Card className="bg-card/50">
            <CardHeader>
                <CardTitle className="text-lg">Summary</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-6">
                <div className="grid gap-3">
                    <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                        <span className="text-muted-foreground">
                            Item Count
                        </span>
                        <span className="font-medium text-foreground">
                            {itemCount}
                        </span>
                    </div>
                    <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                        <span className="text-muted-foreground">
                            Requesting Department/s
                        </span>
                        <span className="max-w-[60%] truncate text-right font-medium text-foreground">
                            {departmentNames}
                        </span>
                    </div>
                    <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                        <span className="text-muted-foreground">
                            Expected Delivery Date
                        </span>
                        <span className="font-medium text-foreground">
                            {deliveryDate}
                        </span>
                    </div>
                    {netAmount !== undefined && (
                        <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                            <span className="text-muted-foreground">
                                Net Amount
                            </span>
                            <span className="font-medium text-foreground">
                                {formatPHP(netAmount, symbol)}
                            </span>
                        </div>
                    )}
                    {vatAmount !== undefined && (
                        <div className="flex justify-between border-b border-border/50 py-1 text-sm">
                            <span className="text-muted-foreground">
                                VAT Amount
                            </span>
                            <span className="font-medium text-foreground">
                                {formatPHP(vatAmount, symbol)}
                            </span>
                        </div>
                    )}
                    <div className="flex items-center justify-between pt-2">
                        <span className="font-semibold text-foreground">
                            Total Expected Amount
                        </span>
                        <span className="text-lg font-bold text-foreground">
                            {formatPHP(totalAmount, symbol)}
                        </span>
                    </div>
                </div>

                {actions ? (
                    <div className="flex flex-col gap-3">{actions}</div>
                ) : (
                    onAction && (
                        <div className="flex flex-col gap-3">
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button
                                        type="button"
                                        className="w-full gap-2"
                                        disabled={processing}
                                    >
                                        <Send className="h-4 w-4" />
                                        Submit for Approval
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>
                                            Submit for Approval?
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            Are you sure you want to officially
                                            submit this purchase requisition for
                                            approval? Once submitted, it will be
                                            routed to the approvers and can no
                                            longer be edited as a draft.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>
                                            Cancel
                                        </AlertDialogCancel>
                                        <AlertDialogAction
                                            onClick={() =>
                                                onAction('REVIEWING')
                                            }
                                        >
                                            Confirm Submission
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                            <Button
                                type="button"
                                variant="secondary"
                                className="w-full gap-2 bg-secondary text-foreground hover:bg-secondary/80"
                                disabled={processing}
                                onClick={() => onAction('DRAFT')}
                            >
                                <Save className="h-4 w-4" />
                                Save as Draft
                            </Button>
                        </div>
                    )
                )}

                {!actions && (
                    <p className="px-2 text-center text-xs leading-relaxed text-muted-foreground/80">
                        Submitting will route this requisition through the
                        approval workflow.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
