import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ReviewOptionsTextarea } from '@/pages/academies/reviews/form';

describe('Review options textarea', () => {
    it('preserves new lines while synchronising parsed options', () => {
        const onChange = vi.fn();
        render(
            <ReviewOptionsTextarea
                id="review-options"
                options={['Good']}
                disabled={false}
                onChange={onChange}
            />,
        );

        const textarea = screen.getByRole('textbox');
        fireEvent.change(textarea, { target: { value: 'Good\n' } });
        expect(textarea).toHaveValue('Good\n');
        expect(onChange).toHaveBeenLastCalledWith(['Good', '']);

        fireEvent.change(textarea, {
            target: { value: 'Good\nExcellent' },
        });
        expect(textarea).toHaveValue('Good\nExcellent');
        expect(onChange).toHaveBeenLastCalledWith(['Good', 'Excellent']);
    });
});
