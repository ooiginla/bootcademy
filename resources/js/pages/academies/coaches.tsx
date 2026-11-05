import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Plus, UserRoundCog, UsersRound } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { useCoachTerminology } from '@/hooks/use-coach-terminology';
import { CustomFieldDialog, type CustomField, type Paginator } from './players';

type Profile = {
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
    years_of_experience: number | null;
    biography: string | null;
    custom_fields: Record<string, string | number | boolean | null>;
};
type Coach = {
    membership_id: number;
    name: string;
    email: string;
    status: string;
    branches: string[];
    profile: Profile | null;
};
type Tab = 'coaches' | 'fields';

const fieldClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

function TabLink({
    tab,
    active,
    children,
}: {
    tab: Tab;
    active: Tab;
    children: ReactNode;
}) {
    return (
        <Link
            href={`/app/coaches?tab=${tab}`}
            role="tab"
            aria-selected={active === tab}
            className={`rounded-md px-4 py-2 text-sm font-medium ${active === tab ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'}`}
        >
            {children}
        </Link>
    );
}

function CustomInput({
    field,
    value,
    id,
}: {
    field: CustomField;
    value: string | number | boolean | null | undefined;
    id: string;
}) {
    const name = `custom_fields[${field.key}]`;
    if (field.type === 'textarea') {
        return (
            <textarea
                id={id}
                name={name}
                defaultValue={String(value ?? '')}
                className={`${fieldClass} min-h-24 py-2`}
            />
        );
    }
    if (field.type === 'select') {
        return (
            <select
                id={id}
                name={name}
                defaultValue={String(value ?? '')}
                className={fieldClass}
            >
                <option value="">Select an option</option>
                {field.options.map((option) => (
                    <option key={option}>{option}</option>
                ))}
            </select>
        );
    }
    if (field.type === 'boolean') {
        return (
            <label
                htmlFor={id}
                className="flex h-9 items-center gap-2 rounded-md border px-3 text-sm"
            >
                <input type="hidden" name={name} value="0" />
                <input
                    id={id}
                    type="checkbox"
                    name={name}
                    value="1"
                    defaultChecked={
                        value === true || value === 1 || value === '1'
                    }
                />
                Yes
            </label>
        );
    }
    return (
        <Input
            id={id}
            name={name}
            type={
                field.type === 'number'
                    ? 'number'
                    : field.type === 'date'
                      ? 'date'
                      : 'text'
            }
            defaultValue={String(value ?? '')}
        />
    );
}

function CoachDialog({
    coach,
    customFields,
    countries,
}: {
    coach: Coach;
    customFields: CustomField[];
    countries: string[];
}) {
    const terminology = useCoachTerminology();
    const [open, setOpen] = useState(false);
    const nameParts = coach.name.trim().split(/\s+/);
    const profile = coach.profile;
    const activeFields = customFields.filter((field) => field.is_active);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    variant="ghost"
                    aria-label={`Edit ${coach.name}`}
                >
                    <Pencil className="size-4" />
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        Edit {terminology.singularLower} biodata
                    </DialogTitle>
                    <DialogDescription>
                        Academy-specific personal and professional information
                        for {coach.email}.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/app/coaches/${coach.membership_id}`}
                    method="patch"
                    className="space-y-6"
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="coach-first-name">
                                        First name
                                    </Label>
                                    <Input
                                        id="coach-first-name"
                                        name="first_name"
                                        defaultValue={
                                            profile?.first_name ??
                                            nameParts[0] ??
                                            ''
                                        }
                                        required
                                    />
                                    <InputError message={errors.first_name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="coach-middle-name">
                                        Middle name
                                    </Label>
                                    <Input
                                        id="coach-middle-name"
                                        name="middle_name"
                                        defaultValue={
                                            profile?.middle_name ?? ''
                                        }
                                    />
                                    <InputError message={errors.middle_name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="coach-last-name">
                                        Last name
                                    </Label>
                                    <Input
                                        id="coach-last-name"
                                        name="last_name"
                                        defaultValue={
                                            profile?.last_name ??
                                            nameParts.slice(1).join(' ')
                                        }
                                        required
                                    />
                                    <InputError message={errors.last_name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="coach-date-of-birth">
                                        Date of birth
                                    </Label>
                                    <Input
                                        id="coach-date-of-birth"
                                        name="date_of_birth"
                                        type="date"
                                        defaultValue={
                                            profile?.date_of_birth ?? ''
                                        }
                                    />
                                    <InputError
                                        message={errors.date_of_birth}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="coach-gender">Gender</Label>
                                    <select
                                        id="coach-gender"
                                        name="gender"
                                        defaultValue={profile?.gender ?? ''}
                                        className={fieldClass}
                                    >
                                        <option value="">Not specified</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                    <InputError message={errors.gender} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="coach-experience">
                                        Years of experience
                                    </Label>
                                    <Input
                                        id="coach-experience"
                                        name="years_of_experience"
                                        type="number"
                                        min="0"
                                        max="80"
                                        defaultValue={
                                            profile?.years_of_experience ?? ''
                                        }
                                    />
                                    <InputError
                                        message={errors.years_of_experience}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="coach-phone">
                                        Phone number
                                    </Label>
                                    <Input
                                        id="coach-phone"
                                        name="phone"
                                        defaultValue={profile?.phone ?? ''}
                                    />
                                    <InputError message={errors.phone} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="coach-whatsapp">
                                        WhatsApp number
                                    </Label>
                                    <Input
                                        id="coach-whatsapp"
                                        name="whatsapp_number"
                                        defaultValue={
                                            profile?.whatsapp_number ?? ''
                                        }
                                    />
                                    <InputError
                                        message={errors.whatsapp_number}
                                    />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="coach-address">
                                        Home address
                                    </Label>
                                    <Input
                                        id="coach-address"
                                        name="home_address"
                                        defaultValue={
                                            profile?.home_address ?? ''
                                        }
                                    />
                                    <InputError message={errors.home_address} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="coach-city">City</Label>
                                    <Input
                                        id="coach-city"
                                        name="city"
                                        defaultValue={profile?.city ?? ''}
                                    />
                                    <InputError message={errors.city} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="coach-state">State</Label>
                                    <Input
                                        id="coach-state"
                                        name="state_region"
                                        defaultValue={
                                            profile?.state_region ?? ''
                                        }
                                    />
                                    <InputError message={errors.state_region} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="coach-country">
                                        Country
                                    </Label>
                                    <select
                                        id="coach-country"
                                        name="country"
                                        defaultValue={
                                            profile?.country ?? 'Nigeria'
                                        }
                                        className={fieldClass}
                                    >
                                        {countries.map((country) => (
                                            <option key={country}>
                                                {country}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.country} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="coach-biography">
                                        Biography / professional summary
                                    </Label>
                                    <textarea
                                        id="coach-biography"
                                        name="biography"
                                        defaultValue={profile?.biography ?? ''}
                                        className={`${fieldClass} min-h-28 py-2`}
                                    />
                                    <InputError message={errors.biography} />
                                </div>
                            </div>
                            {activeFields.length > 0 && (
                                <div>
                                    <h3 className="mb-3 font-medium">
                                        Additional academy information
                                    </h3>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {activeFields.map((field) => (
                                            <div
                                                className={`grid gap-2 ${field.type === 'textarea' ? 'sm:col-span-2' : ''}`}
                                                key={field.id}
                                            >
                                                <Label
                                                    htmlFor={`coach-custom-${field.key}`}
                                                >
                                                    {field.label}
                                                    {field.is_required
                                                        ? ' *'
                                                        : ''}
                                                </Label>
                                                <CustomInput
                                                    id={`coach-custom-${field.key}`}
                                                    field={field}
                                                    value={
                                                        profile?.custom_fields[
                                                            field.key
                                                        ]
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `custom_fields.${field.key}`
                                                        ]
                                                    }
                                                />
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}Save biodata
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function Coaches({
    coaches,
    custom_fields,
    active_tab,
    filters,
    countries,
}: {
    coaches: Paginator<Coach>;
    custom_fields: CustomField[];
    active_tab: Tab;
    filters: { search: string };
    countries: string[];
}) {
    const terminology = useCoachTerminology();
    return (
        <>
            <Head title={terminology.plural} />
            <main className="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Academy operations
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold">
                            {terminology.plural}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Manage professional biodata and academy-specific
                            information for {terminology.pluralLower}.
                        </p>
                    </div>
                    {active_tab === 'coaches' ? (
                        <Button asChild>
                            <Link href="/app/settings/team">
                                <Plus className="size-4" />
                                Add through Team
                            </Link>
                        </Button>
                    ) : (
                        <CustomFieldDialog
                            entityType="coach"
                            subjectLabel={terminology.singularLower}
                        />
                    )}
                </div>
                <div
                    role="tablist"
                    aria-label={`${terminology.singular} management sections`}
                    className="mb-6 flex w-fit gap-1 rounded-lg border bg-muted/40 p-1"
                >
                    <TabLink tab="coaches" active={active_tab}>
                        {terminology.plural} directory
                    </TabLink>
                    <TabLink tab="fields" active={active_tab}>
                        Custom fields
                    </TabLink>
                </div>

                {active_tab === 'coaches' ? (
                    <section className="space-y-4">
                        <Form
                            action="/app/coaches"
                            method="get"
                            className="flex max-w-xl gap-2"
                        >
                            {() => (
                                <>
                                    <Input
                                        type="search"
                                        name="search"
                                        aria-label={`Search ${terminology.pluralLower}`}
                                        placeholder={`Search ${terminology.pluralLower} by name, email or phone`}
                                        defaultValue={filters.search}
                                    />
                                    <Button type="submit" variant="outline">
                                        Search
                                    </Button>
                                    {filters.search && (
                                        <Button asChild variant="ghost">
                                            <Link href="/app/coaches">
                                                Clear
                                            </Link>
                                        </Button>
                                    )}
                                </>
                            )}
                        </Form>
                        <Card className="overflow-hidden py-0">
                            <CardContent className="p-0">
                                {coaches.data.length === 0 ? (
                                    <div className="py-14 text-center">
                                        <UsersRound className="mx-auto size-9 text-muted-foreground" />
                                        <h2 className="mt-4 font-semibold">
                                            No {terminology.pluralLower} found
                                        </h2>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            Create or assign a{' '}
                                            {terminology.singular} from Team and
                                            roles.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full min-w-[900px] text-left text-sm">
                                            <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                                <tr>
                                                    <th className="px-4 py-3">
                                                        {terminology.singular}
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Contact
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Location
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Branches
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Experience
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Status
                                                    </th>
                                                    <th className="px-4 py-3 text-right">
                                                        Actions
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {coaches.data.map((coach) => (
                                                    <tr
                                                        key={
                                                            coach.membership_id
                                                        }
                                                        className="border-b last:border-0"
                                                    >
                                                        <td className="px-4 py-3">
                                                            <p className="font-medium">
                                                                {coach.name}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {coach.profile
                                                                    ? 'Biodata completed'
                                                                    : 'Biodata pending'}
                                                            </p>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <p>{coach.email}</p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {coach.profile
                                                                    ?.phone ??
                                                                    'No phone number'}
                                                            </p>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {[
                                                                coach.profile
                                                                    ?.city,
                                                                coach.profile
                                                                    ?.state_region,
                                                                coach.profile
                                                                    ?.country,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(', ') ||
                                                                '—'}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {coach.branches.join(
                                                                ', ',
                                                            ) || 'All branches'}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {coach.profile
                                                                ?.years_of_experience ==
                                                            null
                                                                ? '—'
                                                                : `${coach.profile.years_of_experience} years`}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <Badge
                                                                variant={
                                                                    coach.status ===
                                                                    'active'
                                                                        ? 'secondary'
                                                                        : 'outline'
                                                                }
                                                                className="capitalize"
                                                            >
                                                                {coach.status}
                                                            </Badge>
                                                        </td>
                                                        <td className="px-4 py-3 text-right">
                                                            <CoachDialog
                                                                coach={coach}
                                                                customFields={
                                                                    custom_fields
                                                                }
                                                                countries={
                                                                    countries
                                                                }
                                                            />
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                                {coaches.last_page > 1 && (
                                    <div className="flex justify-end gap-2 border-t p-4">
                                        {coaches.prev_page_url && (
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link
                                                    href={coaches.prev_page_url}
                                                >
                                                    Previous
                                                </Link>
                                            </Button>
                                        )}
                                        {coaches.next_page_url && (
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link
                                                    href={coaches.next_page_url}
                                                >
                                                    Next
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </section>
                ) : (
                    <Card className="overflow-hidden py-0">
                        <CardContent className="p-0">
                            {custom_fields.length === 0 ? (
                                <div className="py-14 text-center">
                                    <UserRoundCog className="mx-auto size-9 text-muted-foreground" />
                                    <h2 className="mt-4 font-semibold">
                                        No custom {terminology.singularLower}{' '}
                                        fields
                                    </h2>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        Add fields for certifications,
                                        specialities or other academy
                                        requirements.
                                    </p>
                                </div>
                            ) : (
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-3">Field</th>
                                            <th className="px-4 py-3">
                                                API key
                                            </th>
                                            <th className="px-4 py-3">Type</th>
                                            <th className="px-4 py-3">
                                                Status
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {custom_fields.map((field) => (
                                            <tr
                                                key={field.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="px-4 py-3 font-medium">
                                                    {field.label}
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs">
                                                    {field.key}
                                                </td>
                                                <td className="px-4 py-3 capitalize">
                                                    {field.type}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {field.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                    {field.is_required
                                                        ? ' · Required'
                                                        : ''}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <CustomFieldDialog
                                                        field={field}
                                                        entityType="coach"
                                                        subjectLabel={
                                                            terminology.singularLower
                                                        }
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </CardContent>
                    </Card>
                )}
            </main>
        </>
    );
}
