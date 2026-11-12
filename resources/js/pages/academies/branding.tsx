import { Head, useForm } from '@inertiajs/react';
import {
    Building2,
    CheckCircle2,
    ImageIcon,
    Languages,
    Palette,
    Upload,
} from 'lucide-react';
import { useRef } from 'react';
import type { FormEvent } from 'react';
import BankDetailsCard from '@/components/bank-details-card';
import type { BankDetails, Settlement } from '@/components/bank-details-card';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AcademyTheme } from '@/types/auth';

type Props = {
    branding: {
        name: string;
        logo_url: string | null;
        theme: AcademyTheme;
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

export default function AcademyBranding({
    branding,
    uses_paystack = false,
    banks = [],
}: Props) {
    const logoInput = useRef<HTMLInputElement>(null);
    const profileForm = useForm({ name: branding.name });
    const logoForm = useForm<{ logo: File | null }>({ logo: null });
    const themeForm = useForm({
        primary_color: branding.theme.primary,
        secondary_color: branding.theme.secondary,
    });
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

    function submitTheme(event: FormEvent) {
        event.preventDefault();
        themeForm.patch('/app/settings/academy/theme', {
            preserveScroll: true,
        });
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
                                Update the academy name shown throughout the
                                platform. This does not change its public URL or
                                subdomain.
                            </p>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={submitProfile}
                                className="max-w-2xl space-y-5"
                            >
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
                                <Button
                                    type="submit"
                                    disabled={
                                        profileForm.processing ||
                                        !profileForm.isDirty
                                    }
                                >
                                    {profileForm.processing
                                        ? 'Saving…'
                                        : 'Save academy name'}
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

                    <Card>
                        <CardHeader>
                            <h2 className="font-semibold">Academy colours</h2>
                            <p className="text-sm text-muted-foreground">
                                These colours follow your team across
                                navigation, actions and dashboard highlights.
                            </p>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitTheme} className="space-y-5">
                                <div className="grid gap-2">
                                    <Label htmlFor="primary_color">
                                        Primary colour
                                    </Label>
                                    <div className="flex gap-3">
                                        <input
                                            aria-label="Choose primary colour"
                                            type="color"
                                            value={themeForm.data.primary_color}
                                            onChange={(event) =>
                                                themeForm.setData(
                                                    'primary_color',
                                                    event.target.value.toUpperCase(),
                                                )
                                            }
                                            className="h-10 w-14 cursor-pointer rounded-md border bg-background p-1"
                                        />
                                        <Input
                                            id="primary_color"
                                            value={themeForm.data.primary_color}
                                            onChange={(event) =>
                                                themeForm.setData(
                                                    'primary_color',
                                                    event.target.value.toUpperCase(),
                                                )
                                            }
                                            maxLength={7}
                                        />
                                    </div>
                                    <InputError
                                        message={themeForm.errors.primary_color}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="secondary_color">
                                        Secondary colour
                                    </Label>
                                    <div className="flex gap-3">
                                        <input
                                            aria-label="Choose secondary colour"
                                            type="color"
                                            value={
                                                themeForm.data.secondary_color
                                            }
                                            onChange={(event) =>
                                                themeForm.setData(
                                                    'secondary_color',
                                                    event.target.value.toUpperCase(),
                                                )
                                            }
                                            className="h-10 w-14 cursor-pointer rounded-md border bg-background p-1"
                                        />
                                        <Input
                                            id="secondary_color"
                                            value={
                                                themeForm.data.secondary_color
                                            }
                                            onChange={(event) =>
                                                themeForm.setData(
                                                    'secondary_color',
                                                    event.target.value.toUpperCase(),
                                                )
                                            }
                                            maxLength={7}
                                        />
                                    </div>
                                    <InputError
                                        message={
                                            themeForm.errors.secondary_color
                                        }
                                    />
                                </div>

                                <div className="grid grid-cols-2 overflow-hidden rounded-2xl border">
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
                                        <Palette
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                        <p className="mt-5 text-sm font-medium">
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
                                        <CheckCircle2
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                        <p className="mt-5 text-sm font-medium">
                                            Secondary
                                        </p>
                                    </div>
                                </div>

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
