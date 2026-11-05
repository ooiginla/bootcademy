export function normalizeMoneyInput(value: string): string {
    const cleaned = value.replace(/,/g, '').replace(/[^\d.]/g, '');
    const [whole = '', ...decimalParts] = cleaned.split('.');
    const decimal = decimalParts.join('').slice(0, 2);

    return decimalParts.length > 0 ? `${whole}.${decimal}` : whole;
}

export function formatMoneyInput(value: string): string {
    const normalized = normalizeMoneyInput(value);
    const [whole, decimal] = normalized.split('.');
    const grouped = (whole || '0').replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    return decimal === undefined ? grouped : `${grouped}.${decimal}`;
}

export function formatMoneyAmount(value: string | number): string {
    const amount = Number(String(value).replace(/,/g, ''));

    return Number.isFinite(amount)
        ? amount.toLocaleString('en-NG', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          })
        : '0.00';
}

export function currencySymbol(currency: string): string {
    try {
        return (
            new Intl.NumberFormat('en-NG', {
                style: 'currency',
                currency,
                currencyDisplay: 'narrowSymbol',
            })
                .formatToParts(0)
                .find((part) => part.type === 'currency')?.value ?? currency
        );
    } catch {
        return currency;
    }
}

export function formatCurrency(currency: string, value: string | number) {
    return `${currencySymbol(currency)}${formatMoneyAmount(value)}`;
}

export function sumMoney(values: string[]): string {
    return values
        .reduce(
            (total, value) => total + Number(normalizeMoneyInput(value) || '0'),
            0,
        )
        .toFixed(2);
}
