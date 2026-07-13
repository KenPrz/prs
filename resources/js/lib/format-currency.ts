export function formatCurrency(amount: number | string): string {
    return Number(amount).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export function formatPHP(amount: number | string, symbol = '₱'): string {
    return `${symbol}${formatCurrency(amount)}`;
}
