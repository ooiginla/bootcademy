import { Form, Link } from '@inertiajs/react';
import {
    BookOpenText,
    ChevronLeft,
    ChevronRight,
    Settings2,
    Search,
    Pencil,
    Plus,
    UsersRound,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { useAcademyTerminology } from '@/hooks/use-academy-terminology';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export type Option = { id: number; name: string };
export type Guardian = {
    id: number;
    first_name: string;
    last_name: string;
    email: string | null;
    phone: string;
    whatsapp_number: string | null;
    has_signature: boolean;
};
type GuardianSearchResult = {
    id: number;
    name: string;
    email: string | null;
    phone: string;
};
type LinkedGuardian = {
    id: number;
    name: string;
    email: string | null;
    phone: string;
    relationship: string;
};
export type CustomField = {
    id: number;
    key: string;
    label: string;
    type: 'text' | 'textarea' | 'number' | 'date' | 'boolean' | 'select';
    options: string[];
    is_required: boolean;
    is_sensitive: boolean;
    is_active: boolean;
    sort_order: number;
};
type Enrollment = {
    id: number;
    programme_id: number;
    programme_name: string;
    branch_id: number | null;
    branch_name: string | null;
    status: string;
    starts_on: string;
    ends_on: string | null;
};
export type Player = {
    id: number;
    internal_identifier: string | null;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    date_of_birth: string | null;
    gender: string | null;
    phone: string | null;
    whatsapp_number: string | null;
    home_address: string | null;
    city: string | null;
    state_region: string | null;
    country: string | null;
    status: string;
    guardians: LinkedGuardian[];
    enrollments: Enrollment[];
    custom_fields: Record<string, string | number | boolean | null>;
};
export type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const fieldClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

export function CustomFieldDialog({
    field,
    entityType = 'player',
    subjectLabel,
}: {
    field?: CustomField;
    entityType?: 'player' | 'coach';
    subjectLabel?: string;
}) {
    const [open, setOpen] = useState(false);
    const editing = Boolean(field);
    const terminology = useAcademyTerminology();
    const subject = subjectLabel ?? terminology.singularLower;
    const endpoint =
        entityType === 'coach'
            ? '/app/settings/coach-fields'
            : '/app/settings/player-fields';

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={editing ? 'ghost' : 'outline'}
                    size={editing ? 'sm' : 'default'}
                >
                    {editing ? (
                        <Pencil className="size-4" />
                    ) : (
                        <Settings2 className="size-4" />
                    )}
                    {editing ? 'Edit field' : 'Add custom field'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {editing
                            ? `Edit ${field?.label}`
                            : `Add custom ${subject} field`}
                    </DialogTitle>
                    <DialogDescription>
                        Use a stable API key. Sensitive fields are flagged so
                        future exports and role policies can treat them
                        carefully.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={editing ? `${endpoint}/${field?.id}` : endpoint}
                    method={editing ? 'patch' : 'post'}
                    className="space-y-5"
                    resetOnSuccess={!editing}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label>Label</Label>
                                    <Input
                                        name="label"
                                        defaultValue={field?.label}
                                        required
                                        placeholder="e.g. Playing position"
                                    />
                                    <InputError message={errors.label} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label>API key</Label>
                                    <Input
                                        name="key"
                                        defaultValue={field?.key}
                                        required
                                        pattern="[a-z][a-z0-9_]*"
                                        placeholder="playing_position"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Lowercase letters, numbers and
                                        underscores. Avoid changing it after
                                        integrations use it.
                                    </p>
                                    <InputError message={errors.key} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Field type</Label>
                                    <select
                                        name="type"
                                        defaultValue={field?.type ?? 'text'}
                                        className={fieldClass}
                                    >
                                        <option value="text">Short text</option>
                                        <option value="textarea">
                                            Long text
                                        </option>
                                        <option value="number">Number</option>
                                        <option value="date">Date</option>
                                        <option value="boolean">
                                            Yes / No
                                        </option>
                                        <option value="select">
                                            Select options
                                        </option>
                                    </select>
                                    <InputError message={errors.type} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Sort order</Label>
                                    <Input
                                        name="sort_order"
                                        type="number"
                                        min="0"
                                        max="65535"
                                        defaultValue={field?.sort_order ?? 0}
                                        required
                                    />
                                    <InputError message={errors.sort_order} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label>Options</Label>
                                    <Input
                                        name="options"
                                        defaultValue={
                                            field?.options.join(', ') ?? ''
                                        }
                                        placeholder="For select fields: Forward, Midfielder, Defender"
                                    />
                                    <InputError message={errors.options} />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <label className="flex items-center gap-3 rounded-lg border p-3 text-sm">
                                    <input
                                        type="hidden"
                                        name="is_required"
                                        value="0"
                                    />
                                    <input
                                        type="checkbox"
                                        name="is_required"
                                        value="1"
                                        defaultChecked={field?.is_required}
                                        className="size-4 accent-primary"
                                    />
                                    Required during profile creation
                                </label>
                                <label className="flex items-center gap-3 rounded-lg border p-3 text-sm">
                                    <input
                                        type="hidden"
                                        name="is_sensitive"
                                        value="0"
                                    />
                                    <input
                                        type="checkbox"
                                        name="is_sensitive"
                                        value="1"
                                        defaultChecked={field?.is_sensitive}
                                        className="size-4 accent-primary"
                                    />
                                    Sensitive information
                                </label>
                                <label className="flex items-center gap-3 rounded-lg border p-3 text-sm">
                                    <input
                                        type="hidden"
                                        name="is_active"
                                        value="0"
                                    />
                                    <input
                                        type="checkbox"
                                        name="is_active"
                                        value="1"
                                        defaultChecked={
                                            field?.is_active ?? true
                                        }
                                        className="size-4 accent-primary"
                                    />
                                    Active field
                                </label>
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {editing ? 'Save field' : 'Create field'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export function GuardianDialog({ guardian }: { guardian?: Guardian }) {
    const [open, setOpen] = useState(false);
    const editing = Boolean(guardian);
    const terminology = useAcademyTerminology();

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={editing ? 'ghost' : 'outline'}
                    size={editing ? 'sm' : 'default'}
                >
                    {editing ? (
                        <Pencil className="size-4" />
                    ) : (
                        <UsersRound className="size-4" />
                    )}
                    {editing ? 'Edit guardian' : 'Add guardian'}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {editing ? 'Edit guardian' : 'Add guardian'}
                    </DialogTitle>
                    <DialogDescription>
                        Store only the contact information the academy needs to
                        support the {terminology.singularLower}.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={
                        editing
                            ? `/app/guardians/${guardian?.id}`
                            : '/app/guardians'
                    }
                    method={editing ? 'patch' : 'post'}
                    className="space-y-5"
                    resetOnSuccess={!editing}
                    onSuccess={() => setOpen(false)}
                    encType="multipart/form-data"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`guardian-${guardian?.id ?? 'new'}-first`}
                                    >
                                        First name
                                    </Label>
                                    <Input
                                        id={`guardian-${guardian?.id ?? 'new'}-first`}
                                        name="first_name"
                                        defaultValue={guardian?.first_name}
                                        required
                                    />
                                    <InputError message={errors.first_name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor={`guardian-${guardian?.id ?? 'new'}-last`}
                                    >
                                        Last name
                                    </Label>
                                    <Input
                                        id={`guardian-${guardian?.id ?? 'new'}-last`}
                                        name="last_name"
                                        defaultValue={guardian?.last_name}
                                        required
                                    />
                                    <InputError message={errors.last_name} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label
                                        htmlFor={`guardian-${guardian?.id ?? 'new'}-phone`}
                                    >
                                        Phone
                                    </Label>
                                    <Input
                                        id={`guardian-${guardian?.id ?? 'new'}-phone`}
                                        name="phone"
                                        type="tel"
                                        defaultValue={guardian?.phone}
                                        required
                                    />
                                    <InputError message={errors.phone} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label>WhatsApp number (optional)</Label>
                                    <Input
                                        name="whatsapp_number"
                                        type="tel"
                                        defaultValue={
                                            guardian?.whatsapp_number ?? ''
                                        }
                                    />
                                    <InputError
                                        message={errors.whatsapp_number}
                                    />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label
                                        htmlFor={`guardian-${guardian?.id ?? 'new'}-email`}
                                    >
                                        Email (optional)
                                    </Label>
                                    <Input
                                        id={`guardian-${guardian?.id ?? 'new'}-email`}
                                        name="email"
                                        type="email"
                                        defaultValue={guardian?.email ?? ''}
                                    />
                                    <InputError message={errors.email} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label>Signature image (optional)</Label>
                                    <Input
                                        name="signature"
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Private image, maximum 2 MB. Uploading a
                                        new image replaces the current
                                        signature.
                                    </p>
                                    <InputError message={errors.signature} />
                                    {guardian?.has_signature && (
                                        <a
                                            href={`/app/guardians/${guardian.id}/signature`}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                                        >
                                            View current signature
                                        </a>
                                    )}
                                </div>
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {editing ? 'Save changes' : 'Add guardian'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function GuardianPicker({
    initialGuardians,
}: {
    initialGuardians: LinkedGuardian[];
}) {
    const [selected, setSelected] = useState(initialGuardians);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<GuardianSearchResult[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const term = query.trim();
        if (term.length < 2) {
            setResults([]);
            setLoading(false);
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setLoading(true);
            try {
                const response = await fetch(
                    `/app/guardians/search?q=${encodeURIComponent(term)}`,
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );
                if (response.ok) {
                    const payload = (await response.json()) as {
                        data: GuardianSearchResult[];
                    };
                    setResults(payload.data);
                }
            } catch (error) {
                if (
                    !(
                        error instanceof DOMException &&
                        error.name === 'AbortError'
                    )
                ) {
                    setResults([]);
                }
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [query]);

    const selectedIds = new Set(selected.map((guardian) => guardian.id));

    return (
        <div className="space-y-3">
            <div className="relative">
                <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                <Input
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    className="pl-9"
                    placeholder="Search guardian by name, email or phone…"
                    aria-label="Search guardians to link"
                />
            </div>
            {query.trim().length > 0 && query.trim().length < 2 && (
                <p className="text-xs text-muted-foreground">
                    Enter at least two characters.
                </p>
            )}
            {loading && (
                <p className="text-sm text-muted-foreground">
                    Searching guardians…
                </p>
            )}
            {!loading && query.trim().length >= 2 && (
                <div className="max-h-44 space-y-1 overflow-y-auto rounded-lg border p-1">
                    {results.filter((guardian) => !selectedIds.has(guardian.id))
                        .length === 0 ? (
                        <p className="p-3 text-sm text-muted-foreground">
                            No unselected guardians found.
                        </p>
                    ) : (
                        results
                            .filter((guardian) => !selectedIds.has(guardian.id))
                            .map((guardian) => (
                                <button
                                    key={guardian.id}
                                    type="button"
                                    onClick={() => {
                                        setSelected((current) => [
                                            ...current,
                                            {
                                                ...guardian,
                                                relationship: 'Parent',
                                            },
                                        ]);
                                        setQuery('');
                                    }}
                                    className="flex w-full items-center justify-between gap-3 rounded-md p-2 text-left text-sm hover:bg-muted"
                                >
                                    <span>
                                        <span className="block font-medium">
                                            {guardian.name}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {guardian.email ?? guardian.phone}
                                        </span>
                                    </span>
                                    <Plus className="size-4" />
                                </button>
                            ))
                    )}
                </div>
            )}
            <div className="space-y-2">
                {selected.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-3 text-sm text-muted-foreground">
                        No guardian selected. Search above to link one.
                    </p>
                ) : (
                    selected.map((guardian, index) => (
                        <div
                            key={guardian.id}
                            className="grid gap-2 rounded-lg border p-3 sm:grid-cols-[1fr_12rem_auto] sm:items-end"
                        >
                            <div>
                                <p className="text-sm font-medium">
                                    {guardian.name}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {guardian.email ?? guardian.phone}
                                </p>
                            </div>
                            <div className="grid gap-1">
                                <Label
                                    htmlFor={`guardian-relationship-${guardian.id}`}
                                >
                                    Relationship
                                </Label>
                                <Input
                                    id={`guardian-relationship-${guardian.id}`}
                                    name={`guardian_relationships[${guardian.id}]`}
                                    defaultValue={
                                        guardian.relationship || 'Parent'
                                    }
                                    aria-label={`Relationship to ${guardian.name}`}
                                />
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={`Remove ${guardian.name}`}
                                onClick={() =>
                                    setSelected((current) =>
                                        current.filter(
                                            (item) => item.id !== guardian.id,
                                        ),
                                    )
                                }
                            >
                                <X className="size-4" />
                            </Button>
                            <input
                                type="hidden"
                                name="guardian_ids[]"
                                value={guardian.id}
                            />
                            {index === 0 && (
                                <p className="text-xs text-muted-foreground sm:col-span-3">
                                    Primary guardian and emergency contact
                                </p>
                            )}
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}

export function Pagination({ paginator }: { paginator: Paginator<unknown> }) {
    if (paginator.last_page <= 1) return null;

    return (
        <div className="flex items-center justify-between gap-4 border-t px-4 py-3 text-sm">
            <p className="text-muted-foreground">
                Showing {paginator.from ?? 0}–{paginator.to ?? 0} of{' '}
                {paginator.total}
            </p>
            <div className="flex items-center gap-2">
                {paginator.prev_page_url ? (
                    <Button asChild variant="outline" size="sm">
                        <Link href={paginator.prev_page_url} preserveScroll>
                            <ChevronLeft className="size-4" />
                            Previous
                        </Link>
                    </Button>
                ) : (
                    <Button variant="outline" size="sm" disabled>
                        <ChevronLeft className="size-4" />
                        Previous
                    </Button>
                )}
                <span className="text-muted-foreground">
                    Page {paginator.current_page} of {paginator.last_page}
                </span>
                {paginator.next_page_url ? (
                    <Button asChild variant="outline" size="sm">
                        <Link href={paginator.next_page_url} preserveScroll>
                            Next
                            <ChevronRight className="size-4" />
                        </Link>
                    </Button>
                ) : (
                    <Button variant="outline" size="sm" disabled>
                        Next
                        <ChevronRight className="size-4" />
                    </Button>
                )}
            </div>
        </div>
    );
}

export function CustomPlayerFieldInput({
    field,
    value,
    error,
}: {
    field: CustomField;
    value: string | number | boolean | null | undefined;
    error?: string;
}) {
    const name = `custom_fields[${field.key}]`;
    return (
        <div
            className={`grid gap-2 ${field.type === 'textarea' ? 'sm:col-span-2' : ''}`}
        >
            <Label>
                {field.label}
                {field.is_required ? ' *' : ''}
                {field.is_sensitive ? ' (sensitive)' : ''}
            </Label>
            {field.type === 'textarea' ? (
                <textarea
                    name={name}
                    defaultValue={String(value ?? '')}
                    rows={3}
                    className="rounded-md border bg-background px-3 py-2 text-sm"
                    required={field.is_required}
                />
            ) : field.type === 'select' ? (
                <select
                    name={name}
                    defaultValue={String(value ?? '')}
                    className={fieldClass}
                    required={field.is_required}
                >
                    <option value="">Select an option</option>
                    {field.options.map((option) => (
                        <option key={option} value={option}>
                            {option}
                        </option>
                    ))}
                </select>
            ) : field.type === 'boolean' ? (
                <label className="flex items-center gap-3 rounded-lg border p-3 text-sm">
                    <input type="hidden" name={name} value="0" />
                    <input
                        type="checkbox"
                        name={name}
                        value="1"
                        defaultChecked={
                            value === true || value === 1 || value === '1'
                        }
                        className="size-4 accent-primary"
                    />
                    Yes
                </label>
            ) : (
                <Input
                    name={name}
                    type={
                        field.type === 'number'
                            ? 'number'
                            : field.type === 'date'
                              ? 'date'
                              : 'text'
                    }
                    step={field.type === 'number' ? 'any' : undefined}
                    defaultValue={String(value ?? '')}
                    required={field.is_required}
                />
            )}
            <InputError message={error} />
        </div>
    );
}

export function PlayerDialog({
    player,
    customFields,
}: {
    player?: Player;
    customFields: CustomField[];
}) {
    const [open, setOpen] = useState(false);
    const editing = Boolean(player);
    const terminology = useAcademyTerminology();

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={editing ? 'outline' : 'default'}
                    size={editing ? 'sm' : 'default'}
                >
                    {editing ? (
                        <Pencil className="size-4" />
                    ) : (
                        <Plus className="size-4" />
                    )}
                    {editing
                        ? 'Edit profile'
                        : `Add ${terminology.singularLower}`}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {editing
                            ? `Edit ${player?.first_name} ${player?.last_name}`
                            : `Add ${terminology.singularLower}`}
                    </DialogTitle>
                    <DialogDescription>
                        Profile details, lifecycle status and linked guardians.
                        Programme enrolments are managed from the profile card.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={
                        editing ? `/app/players/${player?.id}` : '/app/players'
                    }
                    method={editing ? 'patch' : 'post'}
                    className="space-y-5"
                    resetOnSuccess={!editing}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>First name</Label>
                                    <Input
                                        name="first_name"
                                        defaultValue={player?.first_name}
                                        required
                                    />
                                    <InputError message={errors.first_name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Middle name (optional)</Label>
                                    <Input
                                        name="middle_name"
                                        defaultValue={player?.middle_name ?? ''}
                                    />
                                    <InputError message={errors.middle_name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Last name</Label>
                                    <Input
                                        name="last_name"
                                        defaultValue={player?.last_name}
                                        required
                                    />
                                    <InputError message={errors.last_name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Date of birth (optional)</Label>
                                    <Input
                                        name="date_of_birth"
                                        type="date"
                                        defaultValue={
                                            player?.date_of_birth ?? ''
                                        }
                                    />
                                    <InputError
                                        message={errors.date_of_birth}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Internal ID (optional)</Label>
                                    <Input
                                        name="internal_identifier"
                                        defaultValue={
                                            player?.internal_identifier ?? ''
                                        }
                                        placeholder="e.g. LRN-001"
                                    />
                                    <InputError
                                        message={errors.internal_identifier}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Gender (optional)</Label>
                                    <Input
                                        name="gender"
                                        defaultValue={player?.gender ?? ''}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Phone number (optional)</Label>
                                    <Input
                                        name="phone"
                                        type="tel"
                                        defaultValue={player?.phone ?? ''}
                                    />
                                    <InputError message={errors.phone} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>WhatsApp number (optional)</Label>
                                    <Input
                                        name="whatsapp_number"
                                        type="tel"
                                        defaultValue={
                                            player?.whatsapp_number ?? ''
                                        }
                                    />
                                    <InputError
                                        message={errors.whatsapp_number}
                                    />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label>Home address (optional)</Label>
                                    <Input
                                        name="home_address"
                                        defaultValue={
                                            player?.home_address ?? ''
                                        }
                                    />
                                    <InputError message={errors.home_address} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>City (optional)</Label>
                                    <Input
                                        name="city"
                                        defaultValue={player?.city ?? ''}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>State (optional)</Label>
                                    <Input
                                        name="state_region"
                                        defaultValue={
                                            player?.state_region ?? ''
                                        }
                                    />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label>Country (optional)</Label>
                                    <Input
                                        name="country"
                                        defaultValue={player?.country ?? ''}
                                    />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label>Status</Label>
                                    <select
                                        name="status"
                                        defaultValue={
                                            player?.status ?? 'active'
                                        }
                                        className={fieldClass}
                                    >
                                        <option value="active">Active</option>
                                        <option value="paused">Paused</option>
                                        <option value="withdrawn">
                                            Withdrawn
                                        </option>
                                        <option value="graduated">
                                            Graduated
                                        </option>
                                    </select>
                                    <InputError message={errors.status} />
                                </div>
                            </div>
                            {!editing && (
                                <details className="rounded-lg border p-4">
                                    <summary className="cursor-pointer text-sm font-medium">
                                        Create and link a new guardian
                                    </summary>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        Optional. This creates the guardian as
                                        part of the {terminology.singularLower}{' '}
                                        profile, so you do not need to leave
                                        this form.
                                    </p>
                                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label>Guardian first name</Label>
                                            <Input name="new_guardian[first_name]" />
                                            <InputError
                                                message={
                                                    errors[
                                                        'new_guardian.first_name'
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label>Guardian last name</Label>
                                            <Input name="new_guardian[last_name]" />
                                            <InputError
                                                message={
                                                    errors[
                                                        'new_guardian.last_name'
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label>Guardian phone</Label>
                                            <Input
                                                name="new_guardian[phone]"
                                                type="tel"
                                            />
                                            <InputError
                                                message={
                                                    errors['new_guardian.phone']
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label>Guardian WhatsApp</Label>
                                            <Input
                                                name="new_guardian[whatsapp_number]"
                                                type="tel"
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label>Guardian email</Label>
                                            <Input
                                                name="new_guardian[email]"
                                                type="email"
                                            />
                                            <InputError
                                                message={
                                                    errors['new_guardian.email']
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label>Relationship</Label>
                                            <Input
                                                name="new_guardian[relationship]"
                                                defaultValue="Parent"
                                                placeholder="e.g. Mother, Father, Aunt"
                                            />
                                        </div>
                                    </div>
                                </details>
                            )}
                            <fieldset>
                                <legend className="text-sm font-medium">
                                    Linked guardians
                                </legend>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Search the guardian directory and add only
                                    the contacts linked to this{' '}
                                    {terminology.singularLower}.
                                </p>
                                <div className="mt-3">
                                    <GuardianPicker
                                        initialGuardians={
                                            player?.guardians ?? []
                                        }
                                    />
                                </div>
                                <InputError message={errors.guardian_ids} />
                            </fieldset>
                            {customFields.filter((field) => field.is_active)
                                .length > 0 && (
                                <fieldset>
                                    <legend className="text-sm font-medium">
                                        Additional academy information
                                    </legend>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        These fields are configured by this
                                        academy and use the same keys in API
                                        requests.
                                    </p>
                                    <div className="mt-3 grid gap-4 sm:grid-cols-2">
                                        {customFields
                                            .filter((field) => field.is_active)
                                            .map((field) => (
                                                <CustomPlayerFieldInput
                                                    key={field.id}
                                                    field={field}
                                                    value={
                                                        player?.custom_fields[
                                                            field.key
                                                        ]
                                                    }
                                                    error={
                                                        errors[
                                                            `custom_fields.${field.key}`
                                                        ]
                                                    }
                                                />
                                            ))}
                                    </div>
                                </fieldset>
                            )}
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {editing
                                        ? 'Save changes'
                                        : 'Create profile'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export function EnrollmentDialog({
    player,
    enrollment,
    programmes,
    branches,
}: {
    player: Player;
    enrollment?: Enrollment;
    programmes: Option[];
    branches: Option[];
}) {
    const [open, setOpen] = useState(false);
    const editing = Boolean(enrollment);
    const terminology = useAcademyTerminology();
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    {editing ? (
                        <Pencil className="size-4" />
                    ) : (
                        <BookOpenText className="size-4" />
                    )}
                    {editing ? 'Edit enrolment' : 'Add programme'}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {editing
                            ? 'Edit programme enrolment'
                            : `Enrol ${player.first_name}`}
                    </DialogTitle>
                    <DialogDescription>
                        A {terminology.singularLower} can have multiple
                        programme enrolments at the same time, but the same
                        programme and branch cannot be added twice in one cycle.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={
                        editing
                            ? `/app/players/${player.id}/enrollments/${enrollment?.id}`
                            : `/app/players/${player.id}/enrollments`
                    }
                    method={editing ? 'patch' : 'post'}
                    className="space-y-5"
                    resetOnSuccess={!editing}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label>Programme</Label>
                                    <select
                                        name="programme_id"
                                        defaultValue={
                                            enrollment?.programme_id ?? ''
                                        }
                                        className={fieldClass}
                                        required
                                    >
                                        <option value="" disabled>
                                            Select programme
                                        </option>
                                        {programmes.map((programme) => (
                                            <option
                                                key={programme.id}
                                                value={programme.id}
                                            >
                                                {programme.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.programme_id} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Branch (optional)</Label>
                                    <select
                                        name="branch_id"
                                        defaultValue={
                                            enrollment?.branch_id ?? ''
                                        }
                                        className={fieldClass}
                                    >
                                        <option value="">Not assigned</option>
                                        {branches.map((branch) => (
                                            <option
                                                key={branch.id}
                                                value={branch.id}
                                            >
                                                {branch.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.branch_id} />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label>Start date</Label>
                                        <Input
                                            name="starts_on"
                                            type="date"
                                            defaultValue={
                                                enrollment?.starts_on ??
                                                new Date()
                                                    .toISOString()
                                                    .slice(0, 10)
                                            }
                                            required
                                        />
                                        <InputError
                                            message={errors.starts_on}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>End date (optional)</Label>
                                        <Input
                                            name="ends_on"
                                            type="date"
                                            defaultValue={
                                                enrollment?.ends_on ?? ''
                                            }
                                        />
                                        <InputError message={errors.ends_on} />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Status</Label>
                                    <select
                                        name="status"
                                        defaultValue={
                                            enrollment?.status ?? 'active'
                                        }
                                        className={fieldClass}
                                    >
                                        <option value="active">Active</option>
                                        <option value="paused">Paused</option>
                                        <option value="withdrawn">
                                            Withdrawn
                                        </option>
                                        <option value="completed">
                                            Completed
                                        </option>
                                    </select>
                                    <InputError message={errors.status} />
                                </div>
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    disabled={
                                        processing || programmes.length === 0
                                    }
                                >
                                    {processing && <Spinner />}
                                    {editing ? 'Save changes' : 'Add enrolment'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
