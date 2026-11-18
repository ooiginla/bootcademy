import { Head, useForm } from '@inertiajs/react';
import { Building2, ImageIcon, Languages, Upload } from 'lucide-react';
import { useRef } from 'react';
import type { FormEvent } from 'react';
import BankDetailsCard from '@/components/bank-details-card';
import type { BankDetails, Settlement } from '@/components/bank-details-card';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    branding: {
        name: string;
        academy_type: string;
        country_code: string | null;
        timezone: string;
        currency: string;
        logo_url: string | null;
        terminology: {
            singular: string;
            plural: string;
            staff_singular: string;
            staff_plural: string;
            guardian_singular: string;
            guardian_plural: string;
            collect_guardian_information: boolean;
        };
        bank_details: BankDetails;
        settlement: Settlement;
    };
    uses_paystack?: boolean;
    banks?: Array<{ code: string; name: string }>;
    countries: Array<{
        code: string;
        name: string;
        timezone: string;
        currency: string;
    }>;
    timezones: string[];
    currencies: Array<{ code: string; name: string }>;
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50';

export default function AcademyBranding({
    branding,
    uses_paystack = false,
    banks = [],
    countries,
    timezones,
    currencies,
}: Props) {
    const logoInput = useRef<HTMLInputElement>(null);
    const profileForm = useForm({
        name: branding.name,
        academy_type: branding.academy_type,
        country_code: branding.country_code ?? '',
        timezone: branding.timezone,
        currency: branding.currency,
    });
    const logoForm = useForm<{ logo: File | null }>({ logo: null });
    const terminologyForm = useForm({
        member_label_singular: branding.terminology.singular,
        member_label_plural: branding.terminology.plural,
        staff_label_singular: branding.terminology.staff_singular,
        staff_label_plural: branding.terminology.staff_plural,
        guardian_label_singular: branding.terminology.guardian_singular,
        guardian_label_plural: branding.terminology.guardian_plural,
        collect_guardian_information:
            branding.terminology.collect_guardian_information,
    });

    function submitLogo(event: FormEvent) {
        event.preventDefault();
        logoForm.post('/app/settings/academy/logo', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                logoForm.reset();
                if (logoInput.current) logoInput.current.value = '';
            },
        });
    }

    function submitProfile(event: FormEvent) {
        event.preventDefault();
        profileForm.patch('/app/settings/academy/profile', {
            preserveScroll: true,
        });
    }

    function selectCountry(code: string) {
        profileForm.setData('country_code', code);
        const selected = countries.find((country) => country.code === code);
        if (selected) {
            profileForm.setData((data) => ({
                ...data,
                country_code: code,
                timezone: selected.timezone,
                currency: selected.currency,
            }));
        }
    }

    function submitTerminology(event: FormEvent) {
        event.preventDefault();
        terminologyForm.patch('/app/settings/academy/terminology', {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Academy settings" />
            <main className="mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-8">
                <div className="mb-7">
                    <p className="text-sm font-medium text-primary">
                        Academy settings
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                        Academy identity
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Make {branding.name} instantly recognisable to every
                        authorised member.
                    </p>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card className="xl:col-span-2">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Building2 className="size-5 text-primary" />
                                <h2 className="font-semibold">
                                    Academy profile
                                </h2>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                Keep the academy identity, country, timezone and
                                financial display currency current. Updating the
                                name does not change its public URL.
                            </p>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={submitProfile}
                                className="max-w-2xl space-y-5"
                            >
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="academy_name">
                                            Academy name
                                        </Label>
                                        <Input
                                            id="academy_name"
                                            value={profileForm.data.name}
                                            onChange={(event) =>
                                                profileForm.setData(
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                            maxLength={160}
                                            autoComplete="organization"
                                            required
                                        />
                                        <InputError
                                            message={profileForm.errors.name}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="academy_type">
                                            Academy type
                                        </Label>
                                        <Input
                                            id="academy_type"
                                            value={
                                                profileForm.data.academy_type
                                            }
                                            onChange={(event) =>
                                                profileForm.setData(
                                                    'academy_type',
                                                    event.target.value,
                                                )
                                            }
                                            maxLength={80}
                                            required
                                        />
                                        <InputError
                                            message={
                                                profileForm.errors.academy_type
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="country_code">
                                            Country
                                        </Label>
                                        <select
                                            id="country_code"
                                            value={
                                                profileForm.data.country_code
                                            }
                                            onChange={(event) =>
                                                selectCountry(
                                                    event.target.value,
                                                )
                                            }
                                            className={selectClass}
                                            required
                                        >
                                            <option value="">
                                                Select a country
                                            </option>
                                            {countries.map((country) => (
                                                <option
                                                    key={country.code}
                                                    value={country.code}
                                                >
                                                    {country.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={
                                                profileForm.errors.country_code
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="academy_timezone">
                                            Timezone
                                        </Label>
                                        <select
                                            id="academy_timezone"
                                            value={profileForm.data.timezone}
                                            onChange={(event) =>
                                                profileForm.setData(
                                                    'timezone',
                                                    event.target.value,
                                                )
                                            }
                                            className={selectClass}
                                            required
                                        >
                                            {timezones.map((timezone) => (
                                                <option
                                                    key={timezone}
                                                    value={timezone}
                                                >
                                                    {timezone}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={
                                                profileForm.errors.timezone
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="academy_currency">
                                            Currency
                                        </Label>
                                        <select
                                            id="academy_currency"
                                            value={profileForm.data.currency}
                                            onChange={(event) =>
                                                profileForm.setData(
                                                    'currency',
                                                    event.target.value,
                                                )
                                            }
                                            className={selectClass}
                                            required
                                        >
                                            {currencies.map((currency) => (
                                                <option
                                                    key={currency.code}
                                                    value={currency.code}
                                                >
                                                    {currency.code} —{' '}
                                                    {currency.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={
                                                profileForm.errors.currency
                                            }
                                        />
                                    </div>
                                </div>
                                <Button
                                    type="submit"
                                    disabled={
                                        profileForm.processing ||
                                        !profileForm.isDirty
                                    }
                                >
                                    {profileForm.processing
                                        ? 'Saving…'
                                        : 'Save academy profile'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <h2 className="font-semibold">Academy logo</h2>
                            <p className="text-sm text-muted-foreground">
                                PNG, JPG or WebP. Maximum 2 MB and 2048 × 2048
                                pixels.
                            </p>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitLogo} className="space-y-5">
                                <label
                                    className="flex cursor-pointer items-center gap-4 rounded-2xl border border-dashed p-5 transition-colors hover:bg-muted/50"
                                    htmlFor="logo"
                                >
                                    <span className="grid size-14 shrink-0 place-items-center overflow-hidden rounded-2xl bg-primary text-primary-foreground">
                                        {branding.logo_url ? (
                                            <img
                                                src={branding.logo_url}
                                                alt={`${branding.name} logo`}
                                                className="size-full object-cover"
                                            />
                                        ) : (
                                            <ImageIcon
                                                className="size-6"
                                                aria-hidden="true"
                                            />
                                        )}
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block font-medium">
                                            Choose a new logo
                                        </span>
                                        <span className="mt-1 block text-sm text-muted-foreground">
                                            The existing logo remains until the
                                            upload succeeds.
                                        </span>
                                    </span>
                                    <Upload
                                        className="ml-auto size-5 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                </label>
                                <input
                                    ref={logoInput}
                                    id="logo"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    className="sr-only"
                                    onChange={(event) =>
                                        logoForm.setData(
                                            'logo',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                {logoForm.data.logo && (
                                    <p className="text-sm text-muted-foreground">
                                        Selected: {logoForm.data.logo.name}
                                    </p>
                                )}
                                <InputError message={logoForm.errors.logo} />
                                <Button
                                    type="submit"
                                    disabled={
                                        !logoForm.data.logo ||
                                        logoForm.processing
                                    }
                                >
                                    {logoForm.processing
                                        ? 'Uploading…'
                                        : 'Upload logo'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card className="xl:col-span-2">
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Languages className="size-5 text-primary" />
                                <h2 className="font-semibold">
                                    Member and staff terminology
                                </h2>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                Choose the words this academy uses in the
                                interface. Database tables and API parameter
                                names remain unchanged.
                            </p>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={submitTerminology}
                                className="space-y-5"
                            >
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="member_label_singular">
                                            Singular label
                                        </Label>
                                        <Input
                                            id="member_label_singular"
                                            value={
                                                terminologyForm.data
                                                    .member_label_singular
                                            }
                                            onChange={(event) =>
                                                terminologyForm.setData(
                                                    'member_label_singular',
                                                    event.target.value,
                                                )
                                            }
                                            maxLength={50}
                                            placeholder="Student"
                                        />
                                        <InputError
                                            message={
                                                terminologyForm.errors
                                                    .member_label_singular
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="member_label_plural">
                                            Plural label
                                        </Label>
                                        <Input
                                            id="member_label_plural"
                                            value={
                                                terminologyForm.data
                                                    .member_label_plural
                                            }
                                            onChange={(event) =>
                                                terminologyForm.setData(
                                                    'member_label_plural',
                                                    event.target.value,
                                                )
                                            }
                                            maxLength={50}
                                            placeholder="Students"
                                        />
                                        <InputError
                                            message={
                                                terminologyForm.errors
                                                    .member_label_plural
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="staff_label_singular">
                                            Coach/instructor singular label
                                        </Label>
                                        <Input
                                            id="staff_label_singular"
                                            value={
                                                terminologyForm.data
                                                    .staff_label_singular
                                            }
                                            onChange={(event) =>
                                                terminologyForm.setData(
                                                    'staff_label_singular',
                                                    event.target.value,
                                                )
                                            }
                                            maxLength={50}
                                            placeholder="Coach"
                                        />
                                        <InputError
                                            message={
                                                terminologyForm.errors
                                                    .staff_label_singular
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="staff_label_plural">
                                            Coach/instructor plural label
                                        </Label>
                                        <Input
                                            id="staff_label_plural"
                                            value={
                                                terminologyForm.data
                                                    .staff_label_plural
                                            }
                                            onChange={(event) =>
                                                terminologyForm.setData(
                                                    'staff_label_plural',
                                                    event.target.value,
                                                )
                                            }
                                            maxLength={50}
                                            placeholder="Coaches"
                                        />
                                        <InputError
                                            message={
                                                terminologyForm.errors
                                                    .staff_label_plural
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="guardian_label_singular">
                                            Guardian/contact singular label
                                        </Label>
                                        <Input
                                            id="guardian_label_singular"
                                            value={
                                                terminologyForm.data
                                                    .guardian_label_singular
                                            }
                                            onChange={(event) =>
                                                terminologyForm.setData(
                                                    'guardian_label_singular',
                                                    event.target.value,
                                                )
                                            }
                                            maxLength={50}
                                            placeholder="Guardian"
                                        />
                                        <InputError
                                            message={
                                                terminologyForm.errors
                                                    .guardian_label_singular
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="guardian_label_plural">
                                            Guardian/contact plural label
                                        </Label>
                                        <Input
                                            id="guardian_label_plural"
                                            value={
                                                terminologyForm.data
                                                    .guardian_label_plural
                                            }
                                            onChange={(event) =>
                                                terminologyForm.setData(
                                                    'guardian_label_plural',
                                                    event.target.value,
                                                )
                                            }
                                            maxLength={50}
                                            placeholder="Guardians"
                                        />
                                        <InputError
                                            message={
                                                terminologyForm.errors
                                                    .guardian_label_plural
                                            }
                                        />
                                    </div>
                                </div>
                                <label className="flex items-start gap-3 rounded-xl border bg-muted/30 p-4">
                                    <input
                                        type="checkbox"
                                        checked={
                                            terminologyForm.data
                                                .collect_guardian_information
                                        }
                                        onChange={(event) =>
                                            terminologyForm.setData(
                                                'collect_guardian_information',
                                                event.target.checked,
                                            )
                                        }
                                        className="mt-1 size-4"
                                    />
                                    <span>
                                        <span className="block text-sm font-medium">
                                            Collect guardian/contact information
                                            on applications
                                        </span>
                                        <span className="mt-1 block text-sm text-muted-foreground">
                                            Turn this off for adult programmes
                                            or academies that do not require a
                                            next of kin during application.
                                        </span>
                                    </span>
                                </label>
                                <div className="rounded-xl border bg-muted/40 p-4 text-sm">
                                    <p className="font-medium">Preview</p>
                                    <p className="mt-1 text-muted-foreground">
                                        Add{' '}
                                        {terminologyForm.data
                                            .member_label_singular ||
                                            'Student'}{' '}
                                        ·{' '}
                                        {terminologyForm.data
                                            .member_label_plural ||
                                            'Students'}{' '}
                                        directory · Active{' '}
                                        {(
                                            terminologyForm.data
                                                .member_label_plural ||
                                            'Students'
                                        ).toLocaleLowerCase()}
                                        {' · '}
                                        {terminologyForm.data
                                            .staff_label_plural ||
                                            'Coaches'}{' '}
                                        directory
                                        {' · '}
                                        {terminologyForm.data
                                            .guardian_label_plural ||
                                            'Guardians'}
                                    </p>
                                </div>
                                <Button
                                    type="submit"
                                    disabled={
                                        terminologyForm.processing ||
                                        !terminologyForm.isDirty
                                    }
                                >
                                    {terminologyForm.processing
                                        ? 'Saving…'
                                        : 'Save terminology'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <BankDetailsCard
                        academyName={branding.name}
                        bankDetails={branding.bank_details}
                        settlement={branding.settlement}
                        usesPaystack={uses_paystack}
                        banks={banks}
                    />
                </div>
            </main>
        </>
    );
}
