export const VAT_RATE = 0.12;

/** Convert the given amount to NET (VAT-exclusive), mirroring App\Enums\PriceType::toNet(). */
export function toNet(
    amount: number,
    priceType: string,
    vatRate: number = VAT_RATE,
): number {
    return priceType === 'VAT_INCLUSIVE' ? amount / (1 + vatRate) : amount;
}

/** Convert the given amount to GROSS (VAT-inclusive), mirroring App\Enums\PriceType::toGross(). */
export function toGross(
    amount: number,
    priceType: string,
    vatRate: number = VAT_RATE,
): number {
    return priceType === 'VAT_EXCLUSIVE' ? amount * (1 + vatRate) : amount;
}

/** Extract the VAT amount from the given total, mirroring App\Enums\PriceType::vatAmount(). */
export function vatAmount(
    amount: number,
    priceType: string,
    vatRate: number = VAT_RATE,
): number {
    if (priceType === 'VAT_INCLUSIVE') {
        return amount - amount / (1 + vatRate);
    }

    if (priceType === 'VAT_EXCLUSIVE') {
        return amount * vatRate;
    }

    return 0;
}

export type PriceBreakdown = {
    rawTotal: number;
    netTotal: number;
    grossTotal: number;
    vatTotal: number;
};

/** Compute the Net/Gross/VAT breakdown for a document's raw total (sum of qty * price). */
export function priceBreakdown(
    rawTotal: number,
    priceType: string,
    vatRate: number = VAT_RATE,
): PriceBreakdown {
    return {
        rawTotal,
        netTotal: toNet(rawTotal, priceType, vatRate),
        grossTotal: toGross(rawTotal, priceType, vatRate),
        vatTotal: vatAmount(rawTotal, priceType, vatRate),
    };
}
