import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    LockKeyhole,
    Plus,
    Trash2,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ReviewData, ReviewField } from '@/types/reviews';

type Option = { value: string; label: string };
type FieldTypeOption = Option & { needs_options: boolean };
type ReviewFormData = {
    title: string;
    description: string;
    audience: string;
    status: string;
    collect_respondent_name: boolean;
    fields: ReviewField[];
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs disabled:cursor-not-allowed disabled:opacity-50';

function blankField(id: string): ReviewField {
    return {
        id,
        label: '',
        description: null,
        type: 'short_text',
        required: false,
        options: [],
        max_rating: 5,
    };
}

export function ReviewOptionsTextarea({
    id,
    options,
    disabled,
    onChange,
}: {
    id: string;
    options: string[];
    disabled: boolean;
    onChange: (options: string[]) => void;
}) {
    const [draft, setDraft] = useState(() => options.join('\n'));

    return (
        <textarea
            id={id}
            value={draft}
            onChange={(event) => {
                const value = event.target.value;
                setDraft(value);
                onChange(value.split(/\r?\n/));
            }}
            disabled={disabled}
            rows={4}
            className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs disabled:opacity-50"
        />
    );
}

export default function ReviewForm({
    review,
    fields_locked: fieldsLocked,
    audiences,
    statuses,
    field_types: fieldTypes,
}: {
    review: ReviewData | null;
    fields_locked: boolean;
    audiences: Option[];
    statuses: Option[];
    field_types: FieldTypeOption[];
}) {
    const editing = review !== null;
    const form = useForm<ReviewFormData>({
        title: review?.title ?? '',
        description: review?.description ?? '',
        audience: review?.audience ?? 'guardians',
        status: review?.status ?? 'draft',
        collect_respondent_name: review?.collect_respondent_name ?? false,
        fields: review?.fields ?? [blankField('question-1')],
    });
    const errors = form.errors as Record<string, string>;

    function updateField(index: number, changes: Partial<ReviewField>) {
        form.setData(
            'fields',
            form.data.fields.map((field, fieldIndex) =>
                fieldIndex === index ? { ...field, ...changes } : field,
            ),
        );
    }

    function moveField(index: number, offset: number) {
        const destination = index + offset;
        if (destination < 0 || destination >= form.data.fields.length) return;
        const fields = [...form.data.fields];
        [fields[index], fields[destination]] = [
            fields[destination],
            fields[index],
        ];
        form.setData('fields', fields);
    }

    function addField() {
        form.setData('fields', [
            ...form.data.fields,
            blankField(`question-${Date.now()}`),
        ]);
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        if (editing) {
            form.patch(`/app/reviews/${review.id}`);
        } else {
            form.post('/app/reviews');
        }
    }

    return (
        <>
            <Head title={editing ? `Edit ${review.title}` : 'Create review'} />
            <main className="mx-auto w-full max-w-5xl p-4 sm:p-6 lg:p-8">
                <Button variant="ghost" asChild className="mb-4 px-0">
                    <Link
                        href={
                            editing
                                ? `/app/reviews/${review.id}`
                                : '/app/reviews'
                        }
                    >
                        <ArrowLeft className="size-4" /> Back to reviews
                    </Link>
                </Button>

                <div className="mb-7">
                    <p className="text-sm font-medium text-primary">
                        Review builder
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                        {editing ? 'Edit review' : 'Create review'}
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Build a focused feedback form and publish it when it is
                        ready to accept responses.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <h2 className="font-semibold">Review details</h2>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(event) =>
                                        form.setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={160}
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.title} />
                            </div>
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="description">Description</Label>
                                <textarea
                                    id="description"
                                    value={form.data.description}
                                    onChange={(event) =>
                                        form.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={3000}
                                    rows={4}
                                    className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                    placeholder="Explain what feedback you need and how it will be used."
                                />
                                <InputError message={errors.description} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="audience">Audience</Label>
                                <select
                                    id="audience"
                                    value={form.data.audience}
                                    onChange={(event) =>
                                        form.setData(
                                            'audience',
                                            event.target.value,
                                        )
                                    }
                                    className={selectClass}
                                >
                                    {audiences.map((audience) => (
                                        <option
                                            key={audience.value}
                                            value={audience.value}
                                        >
                                            {audience.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.audience} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="status">Status</Label>
                                <select
                                    id="status"
                                    value={form.data.status}
                                    onChange={(event) =>
                                        form.setData(
                                            'status',
                                            event.target.value,
                                        )
                                    }
                                    className={selectClass}
                                >
                                    {statuses.map((status) => (
                                        <option
                                            key={status.value}
                                            value={status.value}
                                        >
                                            {status.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.status} />
                            </div>
                            <label className="flex items-start gap-3 rounded-xl border bg-muted/30 p-4 sm:col-span-2">
                                <input
                                    type="checkbox"
                                    checked={form.data.collect_respondent_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'collect_respondent_name',
                                            event.target.checked,
                                        )
                                    }
                                    className="mt-1 size-4"
                                />
                                <span>
                                    <span className="block text-sm font-medium">
                                        Ask for the respondent’s name
                                    </span>
                                    <span className="mt-1 block text-sm text-muted-foreground">
                                        Leave this off for anonymous feedback
                                        and stronger data minimisation.
                                    </span>
                                </span>
                            </label>
                        </CardContent>
                    </Card>

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="font-semibold">Form fields</h2>
                            <p className="text-sm text-muted-foreground">
                                {form.data.fields.length} of 50 fields
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={
                                fieldsLocked || form.data.fields.length >= 50
                            }
                            onClick={addField}
                        >
                            <Plus className="size-4" /> Add field
                        </Button>
                    </div>

                    {fieldsLocked && (
                        <div className="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                            <LockKeyhole className="mt-0.5 size-4 shrink-0" />
                            Form fields are locked because responses have
                            already been submitted. You can still update the
                            review details and status.
                        </div>
                    )}
                    <InputError message={errors.fields} />

                    <div className="space-y-4">
                        {form.data.fields.map((field, index) => {
                            const type = fieldTypes.find(
                                (item) => item.value === field.type,
                            );
                            return (
                                <Card key={field.id}>
                                    <CardHeader className="flex-row items-center justify-between gap-4">
                                        <h3 className="font-semibold">
                                            Field {index + 1}
                                        </h3>
                                        <div className="flex gap-1">
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                aria-label="Move field up"
                                                disabled={
                                                    fieldsLocked || index === 0
                                                }
                                                onClick={() =>
                                                    moveField(index, -1)
                                                }
                                            >
                                                <ArrowUp className="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                aria-label="Move field down"
                                                disabled={
                                                    fieldsLocked ||
                                                    index ===
                                                        form.data.fields
                                                            .length -
                                                            1
                                                }
                                                onClick={() =>
                                                    moveField(index, 1)
                                                }
                                            >
                                                <ArrowDown className="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                aria-label="Remove field"
                                                disabled={
                                                    fieldsLocked ||
                                                    form.data.fields.length ===
                                                        1
                                                }
                                                onClick={() =>
                                                    form.setData(
                                                        'fields',
                                                        form.data.fields.filter(
                                                            (_, fieldIndex) =>
                                                                fieldIndex !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Trash2 className="size-4 text-destructive" />
                                            </Button>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2 sm:col-span-2">
                                            <Label
                                                htmlFor={`field-${index}-label`}
                                            >
                                                Question / label
                                            </Label>
                                            <Input
                                                id={`field-${index}-label`}
                                                value={field.label}
                                                onChange={(event) =>
                                                    updateField(index, {
                                                        label: event.target
                                                            .value,
                                                    })
                                                }
                                                disabled={fieldsLocked}
                                                maxLength={200}
                                                required
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `fields.${index}.label`
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`field-${index}-type`}
                                            >
                                                Field type
                                            </Label>
                                            <select
                                                id={`field-${index}-type`}
                                                value={field.type}
                                                onChange={(event) =>
                                                    updateField(index, {
                                                        type: event.target
                                                            .value,
                                                    })
                                                }
                                                disabled={fieldsLocked}
                                                className={selectClass}
                                            >
                                                {fieldTypes.map((item) => (
                                                    <option
                                                        key={item.value}
                                                        value={item.value}
                                                    >
                                                        {item.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <label className="flex items-center gap-3 self-end rounded-md border px-3 py-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={field.required}
                                                onChange={(event) =>
                                                    updateField(index, {
                                                        required:
                                                            event.target
                                                                .checked,
                                                    })
                                                }
                                                disabled={fieldsLocked}
                                                className="size-4"
                                            />
                                            Required field
                                        </label>
                                        <div className="grid gap-2 sm:col-span-2">
                                            <Label
                                                htmlFor={`field-${index}-description`}
                                            >
                                                Help text (optional)
                                            </Label>
                                            <Input
                                                id={`field-${index}-description`}
                                                value={field.description ?? ''}
                                                onChange={(event) =>
                                                    updateField(index, {
                                                        description:
                                                            event.target.value,
                                                    })
                                                }
                                                disabled={fieldsLocked}
                                                maxLength={500}
                                            />
                                        </div>
                                        {type?.needs_options && (
                                            <div className="grid gap-2 sm:col-span-2">
                                                <Label
                                                    htmlFor={`field-${index}-options`}
                                                >
                                                    Options (one per line)
                                                </Label>
                                                <ReviewOptionsTextarea
                                                    id={`field-${index}-options`}
                                                    options={field.options}
                                                    onChange={(options) =>
                                                        updateField(index, {
                                                            options,
                                                        })
                                                    }
                                                    disabled={fieldsLocked}
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `fields.${index}.options`
                                                        ]
                                                    }
                                                />
                                            </div>
                                        )}
                                        {field.type === 'rating' && (
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`field-${index}-rating`}
                                                >
                                                    Maximum rating
                                                </Label>
                                                <Input
                                                    id={`field-${index}-rating`}
                                                    type="number"
                                                    min={2}
                                                    max={10}
                                                    value={field.max_rating}
                                                    onChange={(event) =>
                                                        updateField(index, {
                                                            max_rating: Number(
                                                                event.target
                                                                    .value,
                                                            ),
                                                        })
                                                    }
                                                    disabled={fieldsLocked}
                                                />
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>

                    <div className="flex justify-center border-t pt-5">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={
                                fieldsLocked || form.data.fields.length >= 50
                            }
                            onClick={addField}
                        >
                            <Plus className="size-4" /> Add another field
                        </Button>
                    </div>

                    <div className="flex justify-end gap-3 border-t pt-6">
                        <Button type="button" variant="outline" asChild>
                            <Link href="/app/reviews">Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Saving…'
                                : editing
                                  ? 'Save review'
                                  : 'Create review'}
                        </Button>
                    </div>
                </form>
            </main>
        </>
    );
}
