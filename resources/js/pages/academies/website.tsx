import { Form, Head, useForm } from '@inertiajs/react';
import {
    ArrowUpRight,
    CheckCircle2,
    ChevronDown,
    Globe2,
    LayoutTemplate,
    Palette,
    Save,
    ShoppingBag,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { AcademyTheme } from '@/types/auth';

type FieldDefinition = {
    label: string;
    type:
        | 'text'
        | 'textarea'
        | 'lines'
        | 'select'
        | 'color'
        | 'toggle'
        | 'number';
    default: string | boolean;
    help: string;
    options?: Record<string, string>;
    min?: number;
    max?: number;
    step?: number;
};
type SectionDefinition = {
    label: string;
    description: string;
    enabled: boolean;
    title: string;
    fields: Record<string, FieldDefinition>;
};
type SectionValue = Record<string, string | boolean | null>;
type Website = {
    subdomain: string;
    theme_key: string;
    is_published: boolean;
    sections: Record<string, SectionValue>;
    preview_url: string;
    public_url: string;
};

function readableForeground(background: string): string {
    if (!/^#[0-9A-Fa-f]{6}$/.test(background)) return '#111827';

    const channels = [1, 3, 5].map((start) => {
        const value =
            Number.parseInt(background.slice(start, start + 2), 16) / 255;
        return value <= 0.04045
            ? value / 12.92
            : ((value + 0.055) / 1.055) ** 2.4;
    });
    const luminance =
        0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];

    return 1.05 / (luminance + 0.05) >= (luminance + 0.05) / 0.05
        ? '#FFFFFF'
        : '#111827';
}

function OptionalColourInput({
    id,
    name,
    label,
    initialValue,
}: {
    id: string;
    name: string;
    label: string;
    initialValue: string;
}) {
    const [value, setValue] = useState(initialValue);
    const pickerValue = /^#[0-9A-F]{6}$/i.test(value) ? value : '#FFFFFF';

    return (
        <div className="flex flex-wrap gap-2">
            <input
                aria-label={`Choose ${label.toLowerCase()}`}
                type="color"
                value={pickerValue}
                onChange={(event) => setValue(event.target.value.toUpperCase())}
                className="h-10 w-14 cursor-pointer rounded-md border bg-background p-1"
            />
            <Input
                id={id}
                name={name}
                value={value}
                onChange={(event) => setValue(event.target.value.toUpperCase())}
                maxLength={7}
                pattern="#[0-9A-Fa-f]{6}"
                placeholder="Use theme default"
                className="min-w-44 flex-1"
            />
            {value && (
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => setValue('')}
                >
                    Use theme default
                </Button>
            )}
        </div>
    );
}

export default function AcademyWebsite({
    website,
    definitions,
    theme,
    tenant_domain,
    store_module,
}: {
    website: Website;
    definitions: Record<string, SectionDefinition>;
    theme: AcademyTheme;
    tenant_domain: string;
    store_module?: {
        enabled: boolean;
        product_count: number;
        active_product_count: number;
        order_count: number;
        manage_url: string;
        preview_url: string;
    };
}) {
    const themeForm = useForm({
        primary_color: theme.primary,
        secondary_color: theme.secondary,
        heading_color: theme.heading,
    });
    const enabledCount = Object.values(website.sections).filter(
        (section) => section.enabled,
    ).length;

    function submitTheme(event: FormEvent) {
        event.preventDefault();
        themeForm.patch('/app/settings/academy/theme', {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Public website" />
            <main className="mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-8">
                <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Website studio
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold">
                            Public website
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                            Publish a branded academy website using the first
                            Bootcademy theme. Enable only sections that contain
                            approved, accurate public information.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <a
                            href={website.preview_url}
                            target="_blank"
                            rel="noreferrer"
                        >
                            Preview website
                            <ArrowUpRight className="size-4" />
                        </a>
                    </Button>
                </div>

                <Card className="mb-6">
                    <CardContent className="grid gap-6 pt-6 lg:grid-cols-[1fr_0.8fr]">
                        <div>
                            <div className="flex items-center gap-2">
                                <Palette className="size-5 text-primary" />
                                <h2 className="font-semibold">
                                    Academy website colours
                                </h2>
                            </div>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Set the public website's main action, accent and
                                heading colours. Individual sections can
                                override their background and text colours
                                below.
                            </p>
                            <form
                                onSubmit={submitTheme}
                                className="mt-5 grid gap-5 sm:grid-cols-3"
                            >
                                {(
                                    [
                                        ['primary_color', 'Primary colour'],
                                        ['secondary_color', 'Secondary colour'],
                                        [
                                            'heading_color',
                                            'Headings text colour',
                                        ],
                                    ] as const
                                ).map(([key, label]) => (
                                    <div key={key} className="grid gap-2">
                                        <Label htmlFor={`website-${key}`}>
                                            {label}
                                        </Label>
                                        <div className="flex gap-2">
                                            <input
                                                aria-label={`Choose ${label.toLowerCase()}`}
                                                type="color"
                                                value={themeForm.data[key]}
                                                onChange={(event) =>
                                                    themeForm.setData(
                                                        key,
                                                        event.target.value.toUpperCase(),
                                                    )
                                                }
                                                className="h-10 w-12 cursor-pointer rounded-md border bg-background p-1"
                                            />
                                            <Input
                                                id={`website-${key}`}
                                                value={themeForm.data[key]}
                                                onChange={(event) =>
                                                    themeForm.setData(
                                                        key,
                                                        event.target.value.toUpperCase(),
                                                    )
                                                }
                                                maxLength={7}
                                            />
                                        </div>
                                        <InputError
                                            message={themeForm.errors[key]}
                                        />
                                    </div>
                                ))}
                                <div className="sm:col-span-3">
                                    <Button
                                        type="submit"
                                        disabled={
                                            themeForm.processing ||
                                            !themeForm.isDirty
                                        }
                                    >
                                        {themeForm.processing
                                            ? 'Saving…'
                                            : 'Save academy colours'}
                                    </Button>
                                </div>
                            </form>
                        </div>
                        <div className="grid grid-cols-3 self-start overflow-hidden rounded-2xl border">
                            <div
                                className="p-4"
                                style={{
                                    backgroundColor:
                                        themeForm.data.primary_color,
                                    color: readableForeground(
                                        themeForm.data.primary_color,
                                    ),
                                }}
                            >
                                <Palette className="size-5" />
                                <p className="mt-8 text-sm font-medium">
                                    Primary
                                </p>
                            </div>
                            <div
                                className="p-4"
                                style={{
                                    backgroundColor:
                                        themeForm.data.secondary_color,
                                    color: readableForeground(
                                        themeForm.data.secondary_color,
                                    ),
                                }}
                            >
                                <CheckCircle2 className="size-5" />
                                <p className="mt-8 text-sm font-medium">
                                    Secondary
                                </p>
                            </div>
                            <div className="bg-white p-4">
                                <span
                                    className="text-lg font-bold"
                                    style={{
                                        color: themeForm.data.heading_color,
                                    }}
                                >
                                    Aa
                                </span>
                                <p
                                    className="mt-8 text-sm font-medium"
                                    style={{
                                        color: themeForm.data.heading_color,
                                    }}
                                >
                                    Headings
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Form
                    action="/app/settings/website"
                    method="patch"
                    disableWhileProcessing
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardContent className="grid gap-6 pt-6 lg:grid-cols-[1fr_0.9fr]">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <Globe2 className="size-5 text-primary" />
                                            <h2 className="font-semibold">
                                                Address and publishing
                                            </h2>
                                        </div>
                                        <div className="mt-5 grid gap-2">
                                            <Label htmlFor="website-subdomain">
                                                Academy subdomain
                                            </Label>
                                            <div className="flex overflow-hidden rounded-md border bg-background focus-within:ring-2 focus-within:ring-ring">
                                                <Input
                                                    id="website-subdomain"
                                                    name="subdomain"
                                                    defaultValue={
                                                        website.subdomain
                                                    }
                                                    required
                                                    pattern="[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?"
                                                    className="rounded-none border-0 shadow-none focus-visible:ring-0"
                                                />
                                                <span className="flex items-center border-l bg-muted px-3 text-sm text-muted-foreground">
                                                    .{tenant_domain}
                                                </span>
                                            </div>
                                            <InputError
                                                message={errors.subdomain}
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                Live address:{' '}
                                                {website.public_url}
                                            </p>
                                        </div>
                                        <div className="mt-5 flex items-start gap-3 rounded-xl border p-4">
                                            <input
                                                type="hidden"
                                                name="is_published"
                                                value="0"
                                            />
                                            <input
                                                id="website-published"
                                                type="checkbox"
                                                name="is_published"
                                                value="1"
                                                defaultChecked={
                                                    website.is_published
                                                }
                                                className="mt-1 size-4 accent-primary"
                                            />
                                            <div>
                                                <Label htmlFor="website-published">
                                                    Publish this website
                                                </Label>
                                                <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                                    Turning this off makes the
                                                    public website unavailable.
                                                    Your content remains saved.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <div className="flex items-center gap-2">
                                            <LayoutTemplate className="size-5 text-primary" />
                                            <h2 className="font-semibold">
                                                Website theme
                                            </h2>
                                        </div>
                                        <label className="mt-5 block cursor-pointer rounded-2xl border-2 border-primary bg-primary/5 p-4">
                                            <input
                                                type="radio"
                                                name="theme_key"
                                                value="launch"
                                                defaultChecked
                                                className="sr-only"
                                            />
                                            <div className="overflow-hidden rounded-xl border bg-white shadow-sm">
                                                <div className="h-3 bg-primary" />
                                                <div className="grid grid-cols-[1.2fr_0.8fr] gap-2 p-3">
                                                    <div className="space-y-2">
                                                        <div className="h-2 w-12 rounded bg-primary/30" />
                                                        <div className="h-3 w-full rounded bg-slate-800" />
                                                        <div className="h-2 w-4/5 rounded bg-slate-200" />
                                                    </div>
                                                    <div className="rounded-lg bg-primary/15" />
                                                </div>
                                            </div>
                                            <div className="mt-3 flex items-center justify-between">
                                                <div>
                                                    <p className="font-medium">
                                                        Launch
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Editorial, modern and
                                                        mobile-first
                                                    </p>
                                                </div>
                                                <CheckCircle2 className="size-5 text-primary" />
                                            </div>
                                        </label>
                                        <InputError
                                            message={errors.theme_key}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            {store_module && (
                                <Card className="overflow-hidden border-primary/25">
                                    <CardContent className="grid gap-5 p-5 sm:p-6 lg:grid-cols-[1fr_auto] lg:items-center">
                                        <div className="flex items-start gap-4">
                                            <div className="grid size-12 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                                                <ShoppingBag className="size-6" />
                                            </div>
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h2 className="text-lg font-semibold">
                                                        Online Store module
                                                    </h2>
                                                    <Badge
                                                        variant={
                                                            store_module.enabled
                                                                ? 'default'
                                                                : 'outline'
                                                        }
                                                    >
                                                        {store_module.enabled
                                                            ? 'Visible on website'
                                                            : 'Hidden from website'}
                                                    </Badge>
                                                </div>
                                                <p className="mt-1 max-w-2xl text-sm leading-6 text-muted-foreground">
                                                    Your built-in academy
                                                    storefront includes the
                                                    public Store menu, product
                                                    catalogue, cart and
                                                    checkout. Enable the Store
                                                    section below, save, then
                                                    preview it here.
                                                </p>
                                                <div className="mt-3 flex flex-wrap gap-4 text-xs text-muted-foreground">
                                                    <span>
                                                        <strong className="text-foreground">
                                                            {
                                                                store_module.active_product_count
                                                            }
                                                        </strong>{' '}
                                                        active products
                                                    </span>
                                                    <span>
                                                        <strong className="text-foreground">
                                                            {
                                                                store_module.product_count
                                                            }
                                                        </strong>{' '}
                                                        total products
                                                    </span>
                                                    <span>
                                                        <strong className="text-foreground">
                                                            {
                                                                store_module.order_count
                                                            }
                                                        </strong>{' '}
                                                        orders
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap gap-2 lg:justify-end">
                                            <Button asChild variant="outline">
                                                <a
                                                    href={
                                                        store_module.manage_url
                                                    }
                                                >
                                                    Manage products
                                                </a>
                                            </Button>
                                            <Button asChild variant="outline">
                                                <a href="#section-store">
                                                    Configure Store
                                                </a>
                                            </Button>
                                            {store_module.enabled && (
                                                <Button asChild>
                                                    <a
                                                        href={
                                                            store_module.preview_url
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        Preview Store{' '}
                                                        <ArrowUpRight className="size-4" />
                                                    </a>
                                                </Button>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="text-lg font-semibold">
                                        Website sections
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Open an accordion to edit its heading
                                        and content.
                                    </p>
                                </div>
                                <Badge variant="secondary">
                                    {enabledCount} of{' '}
                                    {Object.keys(definitions).length} enabled
                                </Badge>
                            </div>

                            <div className="space-y-3">
                                {Object.entries(definitions).map(
                                    ([sectionKey, definition], index) => {
                                        const values =
                                            website.sections[sectionKey];

                                        return (
                                            <details
                                                key={sectionKey}
                                                id={`section-${sectionKey}`}
                                                className="group rounded-xl border bg-card shadow-xs"
                                                open={
                                                    sectionKey === 'store' ||
                                                    index === 0
                                                }
                                            >
                                                <summary className="flex cursor-pointer list-none items-center justify-between gap-4 p-4 sm:p-5">
                                                    <div>
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <h3 className="font-semibold">
                                                                {
                                                                    definition.label
                                                                }
                                                            </h3>
                                                            <Badge
                                                                variant={
                                                                    values.enabled
                                                                        ? 'default'
                                                                        : 'outline'
                                                                }
                                                            >
                                                                {values.enabled
                                                                    ? 'Visible'
                                                                    : 'Hidden'}
                                                            </Badge>
                                                        </div>
                                                        <p className="mt-1 text-sm text-muted-foreground">
                                                            {
                                                                definition.description
                                                            }
                                                        </p>
                                                    </div>
                                                    <ChevronDown className="size-5 shrink-0 text-muted-foreground transition-transform group-open:rotate-180" />
                                                </summary>
                                                <div className="border-t p-4 sm:p-5">
                                                    <div className="mb-5 flex items-start gap-3 rounded-lg bg-muted/60 p-4">
                                                        <input
                                                            type="hidden"
                                                            name={`sections[${sectionKey}][enabled]`}
                                                            value="0"
                                                        />
                                                        <input
                                                            id={`${sectionKey}-enabled`}
                                                            type="checkbox"
                                                            name={`sections[${sectionKey}][enabled]`}
                                                            value="1"
                                                            defaultChecked={Boolean(
                                                                values.enabled,
                                                            )}
                                                            className="mt-1 size-4 accent-primary"
                                                        />
                                                        <div>
                                                            <Label
                                                                htmlFor={`${sectionKey}-enabled`}
                                                            >
                                                                Show this
                                                                section
                                                            </Label>
                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                Hidden sections
                                                                are not rendered
                                                                or linked from
                                                                the public menu.
                                                            </p>
                                                        </div>
                                                    </div>

                                                    <div className="grid gap-5">
                                                        <div className="grid gap-2">
                                                            <Label
                                                                htmlFor={`${sectionKey}-title`}
                                                            >
                                                                Public section
                                                                name
                                                            </Label>
                                                            <Input
                                                                id={`${sectionKey}-title`}
                                                                name={`sections[${sectionKey}][title]`}
                                                                defaultValue={String(
                                                                    values.title ??
                                                                        definition.title,
                                                                )}
                                                                required
                                                            />
                                                            <InputError
                                                                message={
                                                                    errors[
                                                                        `sections.${sectionKey}.title`
                                                                    ]
                                                                }
                                                            />
                                                        </div>

                                                        {Object.entries(
                                                            definition.fields,
                                                        ).map(
                                                            ([
                                                                fieldKey,
                                                                field,
                                                            ]) => (
                                                                <div
                                                                    key={
                                                                        fieldKey
                                                                    }
                                                                    className="grid gap-2"
                                                                >
                                                                    <Label
                                                                        htmlFor={`${sectionKey}-${fieldKey}`}
                                                                    >
                                                                        {
                                                                            field.label
                                                                        }
                                                                    </Label>
                                                                    {field.type ===
                                                                    'toggle' ? (
                                                                        <div className="flex items-center gap-3 rounded-lg border bg-muted/30 p-3">
                                                                            <input
                                                                                type="hidden"
                                                                                name={`sections[${sectionKey}][${fieldKey}]`}
                                                                                value="0"
                                                                            />
                                                                            <input
                                                                                id={`${sectionKey}-${fieldKey}`}
                                                                                type="checkbox"
                                                                                name={`sections[${sectionKey}][${fieldKey}]`}
                                                                                value="1"
                                                                                defaultChecked={
                                                                                    values[
                                                                                        fieldKey
                                                                                    ] ===
                                                                                        true ||
                                                                                    values[
                                                                                        fieldKey
                                                                                    ] ===
                                                                                        '1'
                                                                                }
                                                                                className="size-4 accent-primary"
                                                                            />
                                                                            <span className="text-sm text-muted-foreground">
                                                                                Enabled
                                                                            </span>
                                                                        </div>
                                                                    ) : field.type ===
                                                                      'color' ? (
                                                                        <OptionalColourInput
                                                                            id={`${sectionKey}-${fieldKey}`}
                                                                            name={`sections[${sectionKey}][${fieldKey}]`}
                                                                            label={
                                                                                field.label
                                                                            }
                                                                            initialValue={String(
                                                                                values[
                                                                                    fieldKey
                                                                                ] ??
                                                                                    '',
                                                                            )}
                                                                        />
                                                                    ) : field.type ===
                                                                      'number' ? (
                                                                        <Input
                                                                            id={`${sectionKey}-${fieldKey}`}
                                                                            type="number"
                                                                            name={`sections[${sectionKey}][${fieldKey}]`}
                                                                            defaultValue={String(
                                                                                values[
                                                                                    fieldKey
                                                                                ] ??
                                                                                    field.default,
                                                                            )}
                                                                            min={
                                                                                field.min
                                                                            }
                                                                            max={
                                                                                field.max
                                                                            }
                                                                            step={
                                                                                field.step
                                                                            }
                                                                        />
                                                                    ) : field.type ===
                                                                      'select' ? (
                                                                        <select
                                                                            id={`${sectionKey}-${fieldKey}`}
                                                                            name={`sections[${sectionKey}][${fieldKey}]`}
                                                                            defaultValue={String(
                                                                                values[
                                                                                    fieldKey
                                                                                ] ??
                                                                                    field.default,
                                                                            )}
                                                                            className="h-10 rounded-md border border-input bg-transparent px-3 text-sm"
                                                                        >
                                                                            {Object.entries(
                                                                                field.options ??
                                                                                    {},
                                                                            ).map(
                                                                                ([
                                                                                    value,
                                                                                    label,
                                                                                ]) => (
                                                                                    <option
                                                                                        key={
                                                                                            value
                                                                                        }
                                                                                        value={
                                                                                            value
                                                                                        }
                                                                                    >
                                                                                        {
                                                                                            label
                                                                                        }
                                                                                    </option>
                                                                                ),
                                                                            )}
                                                                        </select>
                                                                    ) : field.type ===
                                                                      'text' ? (
                                                                        <Input
                                                                            id={`${sectionKey}-${fieldKey}`}
                                                                            name={`sections[${sectionKey}][${fieldKey}]`}
                                                                            defaultValue={String(
                                                                                values[
                                                                                    fieldKey
                                                                                ] ??
                                                                                    '',
                                                                            )}
                                                                        />
                                                                    ) : (
                                                                        <textarea
                                                                            id={`${sectionKey}-${fieldKey}`}
                                                                            name={`sections[${sectionKey}][${fieldKey}]`}
                                                                            defaultValue={String(
                                                                                values[
                                                                                    fieldKey
                                                                                ] ??
                                                                                    '',
                                                                            )}
                                                                            rows={
                                                                                field.type ===
                                                                                'lines'
                                                                                    ? 6
                                                                                    : 4
                                                                            }
                                                                            className="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                                                        />
                                                                    )}
                                                                    {field.help && (
                                                                        <p className="text-xs leading-5 text-muted-foreground">
                                                                            {
                                                                                field.help
                                                                            }
                                                                        </p>
                                                                    )}
                                                                    <InputError
                                                                        message={
                                                                            errors[
                                                                                `sections.${sectionKey}.${fieldKey}`
                                                                            ]
                                                                        }
                                                                    />
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                </div>
                                            </details>
                                        );
                                    },
                                )}
                            </div>

                            <div className="sticky bottom-4 z-10 flex items-center justify-between gap-4 rounded-xl border bg-background/95 p-3 shadow-lg backdrop-blur">
                                <p className="hidden text-sm text-muted-foreground sm:block">
                                    Changes appear on the public website after
                                    saving.
                                </p>
                                <Button
                                    type="submit"
                                    size="lg"
                                    disabled={processing}
                                    className="ml-auto"
                                >
                                    {processing ? (
                                        <Spinner />
                                    ) : (
                                        <Save className="size-4" />
                                    )}
                                    Save website
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </main>
        </>
    );
}
