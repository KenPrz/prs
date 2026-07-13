import { usePage } from '@inertiajs/react';
import { formatCurrency } from '@/lib/format-currency';

/**
 * The configured currency symbol (shared as `finance.currency_symbol`
 * from the FinanceSettings settings class).
 */
export function useCurrency() {
    const { finance } = usePage<{
        finance?: { currency_symbol: string };
    }>().props;

    const symbol = finance?.currency_symbol ?? '₱';

    return {
        symbol,
        format: (amount: number | string) =>
            `${symbol}${formatCurrency(amount)}`,
    };
}
