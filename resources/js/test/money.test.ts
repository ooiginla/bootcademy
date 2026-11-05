import { describe, expect, it } from 'vitest';
import {
    currencySymbol,
    formatCurrency,
    formatMoneyInput,
    normalizeMoneyInput,
    sumMoney,
} from '@/lib/money';

describe('money presentation', () => {
    it('formats currencies and keeps submitted values free of separators', () => {
        expect(formatMoneyInput('150000.5')).toBe('150,000.5');
        expect(normalizeMoneyInput('150,000.50')).toBe('150000.50');
        expect(currencySymbol('NGN')).toBe('₦');
        expect(formatCurrency('NGN', '150000')).toBe('₦150,000.00');
        expect(formatCurrency('USD', '1250.5')).toBe('$1,250.50');
        expect(sumMoney(['5000', '150000'])).toBe('155000.00');
    });
});
