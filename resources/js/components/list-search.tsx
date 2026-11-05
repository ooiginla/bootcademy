import { Search, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export function ListSearch({
    value,
    onChange,
    placeholder = 'Filter by name…',
    label = 'Filter list by name',
}: {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    label?: string;
}) {
    return (
        <div className="relative w-full max-w-sm">
            <Search
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                type="search"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                aria-label={label}
                className="pr-9 pl-9"
            />
            {value && (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={() => onChange('')}
                    aria-label="Clear filter"
                    className="absolute top-1/2 right-1 size-8 -translate-y-1/2"
                >
                    <X className="size-4" />
                </Button>
            )}
        </div>
    );
}
