import { Form, usePage } from '@inertiajs/react';
import { useRecaptchaToken } from '@/lib/recaptcha';
import RecaptchaField from '@/components/recaptcha-field';
import {
    CheckCircle2,
    ChevronRight,
    FileText,
    LockKeyhole,
    ReceiptText,
    ShieldCheck,
    UserRound,
    UsersRound,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import PublicSiteShell from '@/components/public-site/public-site-shell';
import { formatCurrency } from '@/lib/money';
import type {
    PublicAcademy,
    PublicApplicationIntake,
    PublicSite,
} from '@/types/public-site';

type CustomField = {
    key: string;
    label: string;
    type: 'text' | 'textarea' | 'number' | 'date' | 'boolean' | 'select';
    options: string[];
    required: boolean;
    sensitive: boolean;
};

const inputClass =
    'h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none transition focus:border-[var(--site-primary)] focus:ring-2 focus:ring-[var(--site-primary)]/15';
const labelClass = 'text-sm font-bold text-slate-800';

function Field({
    label,
    name,
    type = 'text',
    required = false,
    children,
}: {
    label: string;
    name: string;
    type?: string;
    required?: boolean;
    children?: React.ReactNode;
}) {
    return (
        <label className="grid gap-2">
            <span className={labelClass}>
                {label} {required && <span className="text-red-600">*</span>}
            </span>
            {children ?? (
                <input
                    type={type}
                    name={name}
                    required={required}
                    className={inputClass}
                />
            )}
        </label>
    );
}

export default function PublicApplication({
    academy,
    site,
    base_url,
    intakes,
    custom_fields,
    locations,
    countries,
}: {
    academy: PublicAcademy;
    site: PublicSite;
    base_url: string;
    intakes: PublicApplicationIntake[];
    custom_fields: CustomField[];
    locations: Array<{ id: number; name: string }>;
    countries: string[];
}) {
    const [programmeId, setProgrammeId] = useState('');
    const [cycleId, setCycleId] = useState('');
    const [cohortId, setCohortId] = useState('');
    const [locationId, setLocationId] = useState('');
    const [intakeId, setIntakeId] = useState('');
    const [paymentPlanId, setPaymentPlanId] = useState('');
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const recaptcha = useRecaptchaToken('apply');
    const programmes = useMemo(
        () =>
            Array.from(
                new Map(
                    intakes.map((intake) => [
                        intake.programme_id,
                        intake.programme_name ?? 'Programme',
                    ]),
                ),
            ),
        [intakes],
    );
    const programmeIntakes = intakes.filter(
        (intake) => String(intake.programme_id) === programmeId,
    );
    const cycles = Array.from(
        new Map(
            programmeIntakes.map((intake) => [
                intake.cycle_id,
                intake.cycle_name,
            ]),
        ),
    );
    const cycleIntakes = programmeIntakes.filter(
        (intake) => String(intake.cycle_id) === cycleId,
    );
    const cohorts = Array.from(
        new Map(
            cycleIntakes.map((intake) => [
                intake.cohort_id,
                intake.cohort_name ?? 'Default cohort',
            ]),
        ),
    );
    const cohortIntakes = cycleIntakes.filter(
        (intake) => String(intake.cohort_id) === cohortId,
    );
    const generalLocationIntake = cohortIntakes.find(
        (intake) => intake.branch_id === null,
    );
    const locationOptions = locations
        .map((location) => ({
            id: String(location.id),
            name: location.name,
            intake:
                cohortIntakes.find(
                    (intake) => intake.branch_id === location.id,
                ) ?? generalLocationIntake,
        }))
        .filter(
            (
                option,
            ): option is {
                id: string;
                name: string;
                intake: PublicApplicationIntake;
            } => option.intake !== undefined,
        );
    if (locationOptions.length === 0 && generalLocationIntake) {
        locationOptions.push({
            id: 'all',
            name: 'All locations',
            intake: generalLocationIntake,
        });
    }
    const selectedIntake = intakes.find(
        (intake) => String(intake.id) === intakeId,
    );
    const selectedLocation = locationOptions.find(
        (location) => location.id === locationId,
    );
    const selectedPaymentPlan = selectedIntake?.payment_plans.find(
        (plan) => String(plan.id) === paymentPlanId,
    );
    const guardianSingular = academy.guardian_singular ?? 'Guardian';
    const collectGuardianInformation =
        academy.collect_guardian_information ?? true;

    return (
        <PublicSiteShell
            academy={academy}
            site={site}
            baseUrl={base_url}
            title={`Apply · ${academy.name}`}
        >
            <main>
                <section className="bg-slate-950 px-5 py-16 text-white sm:px-8 lg:py-20">
                    <div className="mx-auto max-w-7xl">
                        <p className="text-xs font-extrabold tracking-[0.2em] text-[var(--site-primary)] uppercase">
                            Admissions
                        </p>
                        <h1 className="mt-4 max-w-4xl text-5xl font-black tracking-[-0.05em] text-balance sm:text-6xl">
                            Apply to {academy.name}
                        </h1>
                        <p className="mt-6 max-w-2xl text-lg leading-8 text-white/65">
                            Select a programme, cycle, cohort and location,
                            review the applicable fees and payment arrangements,
                            then provide the applicant details
                            {collectGuardianInformation
                                ? ` and ${guardianSingular.toLowerCase()} information`
                                : ''}
                            .
                        </p>
                    </div>
                </section>

                <section className="px-5 py-12 sm:px-8 lg:py-20">
                    <div className="mx-auto max-w-7xl">
                        {Object.keys(errors).length > 0 && (
                            <div
                                role="alert"
                                className="mb-8 rounded-2xl border border-red-200 bg-red-50 p-5 text-red-950"
                            >
                                <p className="font-bold">
                                    Please review the highlighted information.
                                </p>
                                <p className="mt-1 text-sm">
                                    {Object.values(errors)[0]}
                                </p>
                            </div>
                        )}

                        {intakes.length === 0 ? (
                            <section className="mx-auto max-w-3xl rounded-3xl border border-amber-200 bg-amber-50 p-8 text-center shadow-sm">
                                <FileText className="mx-auto size-10 text-amber-600" />
                                <h2 className="mt-4 text-2xl font-black text-slate-950">
                                    Applications are currently closed
                                </h2>
                                <p className="mx-auto mt-3 max-w-xl text-sm leading-6 text-slate-600">
                                    {academy.name} is not currently receiving
                                    applications. Please check again later or
                                    contact the academy for more information.
                                </p>
                            </section>
                        ) : (
                            <Form
                                action={`${base_url}/apply`}
                                method="post"
                                onFinish={() => void recaptcha.refresh()}
                                className="grid items-start gap-8 lg:grid-cols-[1fr_360px]"
                            >
                                <div className="space-y-6">
                                    <section className="rounded-3xl border border-black/5 bg-white p-6 shadow-sm sm:p-8">
                                        <div className="flex items-center gap-3">
                                            <span className="grid size-11 place-items-center rounded-2xl bg-[var(--site-secondary)] text-[var(--site-secondary-fg)]">
                                                <FileText className="size-5" />
                                            </span>
                                            <div>
                                                <p className="text-xs font-bold text-[var(--site-primary)]">
                                                    Step 1
                                                </p>
                                                <h2 className="text-xl font-black">
                                                    Programme, cycle, cohort and
                                                    location
                                                </h2>
                                            </div>
                                        </div>
                                        <div className="mt-7 grid gap-5 sm:grid-cols-2">
                                            <Field
                                                label="Programme"
                                                name="programme_id"
                                                required
                                            >
                                                <select
                                                    name="programme_id"
                                                    aria-label="Programme"
                                                    required
                                                    value={programmeId}
                                                    onChange={(event) => {
                                                        setProgrammeId(
                                                            event.target.value,
                                                        );
                                                        setCycleId('');
                                                        setCohortId('');
                                                        setLocationId('');
                                                        setIntakeId('');
                                                        setPaymentPlanId('');
                                                    }}
                                                    className={inputClass}
                                                >
                                                    <option value="">
                                                        Select a programme
                                                    </option>
                                                    {programmes.map(
                                                        ([id, name]) => (
                                                            <option
                                                                key={id}
                                                                value={id}
                                                            >
                                                                {name}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </Field>
                                            <Field
                                                label="Cycle"
                                                name="cycle_id"
                                                required
                                            >
                                                <select
                                                    name="cycle_id"
                                                    aria-label="Cycle"
                                                    required
                                                    value={cycleId}
                                                    disabled={!programmeId}
                                                    onChange={(event) => {
                                                        setCycleId(
                                                            event.target.value,
                                                        );
                                                        setCohortId('');
                                                        setLocationId('');
                                                        setIntakeId('');
                                                        setPaymentPlanId('');
                                                    }}
                                                    className={inputClass}
                                                >
                                                    <option value="">
                                                        {programmeId
                                                            ? 'Select a cycle'
                                                            : 'Choose a programme first'}
                                                    </option>
                                                    {cycles.map(
                                                        ([id, name]) => (
                                                            <option
                                                                key={id}
                                                                value={id}
                                                            >
                                                                {name}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </Field>
                                            <Field
                                                label="Cohort"
                                                name="cohort_id"
                                                required
                                            >
                                                <select
                                                    name="cohort_id"
                                                    aria-label="Cohort"
                                                    required
                                                    value={cohortId}
                                                    disabled={!cycleId}
                                                    onChange={(event) => {
                                                        setCohortId(
                                                            event.target.value,
                                                        );
                                                        setLocationId('');
                                                        setIntakeId('');
                                                        setPaymentPlanId('');
                                                    }}
                                                    className={inputClass}
                                                >
                                                    <option value="">
                                                        {cycleId
                                                            ? 'Select a cohort'
                                                            : 'Choose a cycle first'}
                                                    </option>
                                                    {cohorts.map(
                                                        ([id, name]) => (
                                                            <option
                                                                key={id}
                                                                value={id ?? ''}
                                                            >
                                                                {name}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </Field>
                                            <Field
                                                label="Location"
                                                name="location_id"
                                                required
                                            >
                                                <select
                                                    name="location_id"
                                                    aria-label="Location"
                                                    required
                                                    value={locationId}
                                                    disabled={!cohortId}
                                                    onChange={(event) => {
                                                        const value =
                                                            event.target.value;
                                                        setLocationId(value);
                                                        const intake =
                                                            locationOptions.find(
                                                                (option) =>
                                                                    option.id ===
                                                                    value,
                                                            )?.intake;
                                                        setIntakeId(
                                                            String(
                                                                intake?.id ??
                                                                    '',
                                                            ),
                                                        );
                                                        const defaultPlan =
                                                            intake?.payment_plans.find(
                                                                (plan) =>
                                                                    plan.is_default,
                                                            ) ??
                                                            intake
                                                                ?.payment_plans[0];
                                                        setPaymentPlanId(
                                                            String(
                                                                defaultPlan?.id ??
                                                                    '',
                                                            ),
                                                        );
                                                    }}
                                                    className={inputClass}
                                                >
                                                    <option value="">
                                                        {cohortId
                                                            ? 'Select a location'
                                                            : 'Choose a cohort first'}
                                                    </option>
                                                    {locationOptions.map(
                                                        (location) => (
                                                            <option
                                                                key={
                                                                    location.id
                                                                }
                                                                value={
                                                                    location.id
                                                                }
                                                            >
                                                                {location.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </Field>
                                            <input
                                                type="hidden"
                                                name="application_intake_id"
                                                value={intakeId}
                                            />
                                            <input
                                                type="hidden"
                                                name="preferred_branch_id"
                                                value={
                                                    locationId === 'all'
                                                        ? ''
                                                        : locationId
                                                }
                                            />
                                            {selectedIntake?.payment_plans
                                                .length === 0 && (
                                                <input
                                                    type="hidden"
                                                    name="programme_cycle_payment_plan_id"
                                                    value=""
                                                />
                                            )}
                                        </div>
                                    </section>

                                    <section className="rounded-3xl border border-black/5 bg-white p-6 shadow-sm sm:p-8">
                                        <div className="flex items-center gap-3">
                                            <span className="grid size-11 place-items-center rounded-2xl bg-[var(--site-secondary)] text-[var(--site-secondary-fg)]">
                                                <UserRound className="size-5" />
                                            </span>
                                            <div>
                                                <p className="text-xs font-bold text-[var(--site-primary)]">
                                                    Step 2
                                                </p>
                                                <h2 className="text-xl font-black">
                                                    {academy.member_singular}{' '}
                                                    information
                                                </h2>
                                            </div>
                                        </div>
                                        <div className="mt-7 grid gap-5 sm:grid-cols-2">
                                            <Field
                                                label="First name"
                                                name="first_name"
                                                required
                                            />
                                            <Field
                                                label="Middle name"
                                                name="middle_name"
                                            />
                                            <Field
                                                label="Last name"
                                                name="last_name"
                                                required
                                            />
                                            <Field
                                                label="Date of birth"
                                                name="date_of_birth"
                                                type="date"
                                            />
                                            <Field label="Gender" name="gender">
                                                <select
                                                    name="gender"
                                                    className={inputClass}
                                                >
                                                    <option value="">
                                                        Select gender
                                                    </option>
                                                    <option value="Male">
                                                        Male
                                                    </option>
                                                    <option value="Female">
                                                        Female
                                                    </option>
                                                </select>
                                            </Field>
                                            <Field
                                                label="Email address"
                                                name="email"
                                                type="email"
                                                required
                                            />
                                            <Field
                                                label="Phone number"
                                                name="phone"
                                                type="tel"
                                            />
                                            <Field
                                                label="WhatsApp number"
                                                name="whatsapp_number"
                                                type="tel"
                                            />
                                            <div className="grid gap-2 sm:col-span-2">
                                                <label
                                                    htmlFor="home_address"
                                                    className={labelClass}
                                                >
                                                    Home address
                                                </label>
                                                <textarea
                                                    id="home_address"
                                                    name="home_address"
                                                    rows={3}
                                                    className={`${inputClass} h-auto py-3`}
                                                />
                                            </div>
                                            <Field label="City" name="city" />
                                            <Field
                                                label="State / Region"
                                                name="state_region"
                                            />
                                            <Field
                                                label="Country"
                                                name="country"
                                            >
                                                <select
                                                    name="country"
                                                    defaultValue="Nigeria"
                                                    className={inputClass}
                                                >
                                                    {countries.map(
                                                        (country) => (
                                                            <option
                                                                key={country}
                                                                value={country}
                                                            >
                                                                {country}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </Field>
                                        </div>
                                    </section>

                                    {custom_fields.length > 0 && (
                                        <section className="rounded-3xl border border-black/5 bg-white p-6 shadow-sm sm:p-8">
                                            <div className="flex items-center gap-3">
                                                <span className="grid size-11 place-items-center rounded-2xl bg-[var(--site-secondary)] text-[var(--site-secondary-fg)]">
                                                    <FileText className="size-5" />
                                                </span>
                                                <div>
                                                    <p className="text-xs font-bold text-[var(--site-primary)]">
                                                        Step 3
                                                    </p>
                                                    <h2 className="text-xl font-black">
                                                        Additional academy
                                                        information
                                                    </h2>
                                                </div>
                                            </div>
                                            <div className="mt-7 grid gap-5 sm:grid-cols-2">
                                                {custom_fields.map((field) => (
                                                    <div
                                                        key={field.key}
                                                        className={`grid gap-2 ${field.type === 'textarea' ? 'sm:col-span-2' : ''}`}
                                                    >
                                                        <label
                                                            htmlFor={`custom-${field.key}`}
                                                            className={
                                                                labelClass
                                                            }
                                                        >
                                                            {field.label}{' '}
                                                            {field.required && (
                                                                <span className="text-red-600">
                                                                    *
                                                                </span>
                                                            )}
                                                            {field.sensitive && (
                                                                <span className="ml-2 inline-flex items-center gap-1 text-xs font-normal text-slate-500">
                                                                    <LockKeyhole className="size-3" />
                                                                    Sensitive
                                                                </span>
                                                            )}
                                                        </label>
                                                        {field.type ===
                                                        'textarea' ? (
                                                            <textarea
                                                                id={`custom-${field.key}`}
                                                                name={`custom_fields[${field.key}]`}
                                                                required={
                                                                    field.required
                                                                }
                                                                rows={4}
                                                                className={`${inputClass} h-auto py-3`}
                                                            />
                                                        ) : field.type ===
                                                          'select' ? (
                                                            <select
                                                                id={`custom-${field.key}`}
                                                                name={`custom_fields[${field.key}]`}
                                                                required={
                                                                    field.required
                                                                }
                                                                className={
                                                                    inputClass
                                                                }
                                                            >
                                                                <option value="">
                                                                    Select an
                                                                    option
                                                                </option>
                                                                {field.options.map(
                                                                    (
                                                                        option,
                                                                    ) => (
                                                                        <option
                                                                            key={
                                                                                option
                                                                            }
                                                                            value={
                                                                                option
                                                                            }
                                                                        >
                                                                            {
                                                                                option
                                                                            }
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </select>
                                                        ) : field.type ===
                                                          'boolean' ? (
                                                            <label className="flex h-11 items-center gap-3 rounded-xl border px-3 text-sm">
                                                                <input
                                                                    id={`custom-${field.key}`}
                                                                    name={`custom_fields[${field.key}]`}
                                                                    type="checkbox"
                                                                    value="1"
                                                                    required={
                                                                        field.required
                                                                    }
                                                                    className="size-4 accent-[var(--site-primary)]"
                                                                />
                                                                Yes
                                                            </label>
                                                        ) : (
                                                            <input
                                                                id={`custom-${field.key}`}
                                                                name={`custom_fields[${field.key}]`}
                                                                type={
                                                                    field.type
                                                                }
                                                                required={
                                                                    field.required
                                                                }
                                                                className={
                                                                    inputClass
                                                                }
                                                            />
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        </section>
                                    )}

                                    <section className="rounded-3xl border border-black/5 bg-white p-6 shadow-sm sm:p-8">
                                        {collectGuardianInformation && (
                                            <>
                                                <div className="flex items-center gap-3">
                                                    <span className="grid size-11 place-items-center rounded-2xl bg-[var(--site-secondary)] text-[var(--site-secondary-fg)]">
                                                        <UsersRound className="size-5" />
                                                    </span>
                                                    <div>
                                                        <p className="text-xs font-bold text-[var(--site-primary)]">
                                                            Final step
                                                        </p>
                                                        <h2 className="text-xl font-black">
                                                            {guardianSingular}{' '}
                                                            information
                                                        </h2>
                                                    </div>
                                                </div>
                                                <p className="mt-3 text-sm leading-6 text-slate-600">
                                                    Complete this section when
                                                    the applicant requires a{' '}
                                                    {guardianSingular.toLowerCase()}{' '}
                                                    or emergency contact.
                                                </p>
                                                <div className="mt-7 grid gap-5 sm:grid-cols-2">
                                                    <Field
                                                        label={`${guardianSingular} first name`}
                                                        name="guardian_first_name"
                                                    />
                                                    <Field
                                                        label={`${guardianSingular} last name`}
                                                        name="guardian_last_name"
                                                    />
                                                    <Field
                                                        label="Relationship"
                                                        name="guardian_relationship"
                                                    />
                                                    <Field
                                                        label="Email address"
                                                        name="guardian_email"
                                                        type="email"
                                                    />
                                                    <Field
                                                        label="Phone number"
                                                        name="guardian_phone"
                                                        type="tel"
                                                    />
                                                    <Field
                                                        label="WhatsApp number"
                                                        name="guardian_whatsapp_number"
                                                        type="tel"
                                                    />
                                                    <Field
                                                        label="Signature image"
                                                        name="guardian_signature"
                                                        type="file"
                                                    />
                                                </div>
                                            </>
                                        )}
                                        <label className="mt-7 flex items-start gap-3 rounded-2xl bg-slate-50 p-4 text-sm leading-6">
                                            <input
                                                type="checkbox"
                                                name="consent_accepted"
                                                value="1"
                                                required
                                                className="mt-1 size-4 shrink-0 accent-[var(--site-primary)]"
                                            />
                                            <span>
                                                I confirm that the information
                                                is accurate and that the academy
                                                may process it for this
                                                application.
                                            </span>
                                        </label>
                                    </section>

                                    <RecaptchaField
                                        {...recaptcha}
                                        error={errors.recaptcha_token}
                                    />

                                    <button
                                        type="submit"
                                        disabled={
                                            !selectedIntake ||
                                            (selectedIntake.payment_plans
                                                .length > 0 &&
                                                !selectedPaymentPlan)
                                        }
                                        className="flex w-full items-center justify-between rounded-full bg-[var(--site-primary)] px-6 py-4 text-sm font-black text-[var(--site-primary-fg)] shadow-lg disabled:cursor-not-allowed disabled:opacity-45"
                                    >
                                        Preview application & fees
                                        <ChevronRight className="size-4" />
                                    </button>
                                </div>

                                <aside className="space-y-4 lg:sticky lg:top-28">
                                    <div className="rounded-3xl bg-slate-950 p-6 text-white shadow-xl">
                                        <div className="flex items-center gap-2">
                                            <ReceiptText className="size-5 text-[var(--site-primary)]" />
                                            <h2 className="font-black">
                                                Application summary
                                            </h2>
                                        </div>
                                        {selectedIntake ? (
                                            <div className="mt-6">
                                                <p className="text-sm font-bold">
                                                    {
                                                        selectedIntake.programme_name
                                                    }
                                                </p>
                                                <p className="mt-1 text-xs text-white/50">
                                                    {selectedIntake.cycle_name}
                                                    {selectedIntake.cohort_name
                                                        ? ` · ${selectedIntake.cohort_name}`
                                                        : ''}{' '}
                                                    ·{' '}
                                                    {selectedLocation?.name ??
                                                        selectedIntake.branch_name}
                                                </p>
                                                <div className="mt-6 divide-y divide-white/10 border-y border-white/10">
                                                    <div className="flex justify-between gap-4 py-3 text-sm">
                                                        <span className="text-white/55">
                                                            Application fee
                                                        </span>
                                                        <strong>
                                                            {formatCurrency(
                                                                selectedIntake.currency,
                                                                selectedIntake.application_fee,
                                                            )}
                                                        </strong>
                                                    </div>
                                                </div>
                                                {selectedIntake.payment_plans
                                                    .length > 0 && (
                                                    <fieldset className="mt-5">
                                                        <legend className="text-xs font-bold tracking-wide text-white/65 uppercase">
                                                            Choose a payment
                                                            plan
                                                        </legend>
                                                        <div className="mt-3 grid gap-2">
                                                            {selectedIntake.payment_plans.map(
                                                                (plan) => {
                                                                    const selected =
                                                                        plan.id ===
                                                                        selectedPaymentPlan?.id;

                                                                    return (
                                                                        <label
                                                                            key={
                                                                                plan.id
                                                                            }
                                                                            className={`cursor-pointer rounded-2xl border p-3 transition ${selected ? 'border-[var(--site-primary)] bg-white/10' : 'border-white/10 bg-white/[0.03] hover:border-white/25'}`}
                                                                        >
                                                                            <input
                                                                                type="radio"
                                                                                name="programme_cycle_payment_plan_id"
                                                                                value={
                                                                                    plan.id
                                                                                }
                                                                                checked={
                                                                                    selected
                                                                                }
                                                                                onChange={() =>
                                                                                    setPaymentPlanId(
                                                                                        String(
                                                                                            plan.id,
                                                                                        ),
                                                                                    )
                                                                                }
                                                                                className="sr-only"
                                                                                required
                                                                                aria-label={
                                                                                    plan.name
                                                                                }
                                                                            />
                                                                            <span className="block">
                                                                                <span>
                                                                                    <span className="flex items-center gap-2 text-sm font-bold">
                                                                                        {
                                                                                            plan.name
                                                                                        }
                                                                                        {plan.is_default && (
                                                                                            <span className="rounded-full bg-[var(--site-primary)] px-2 py-0.5 text-[10px] text-[var(--site-primary-fg)] ring-1 ring-white/30">
                                                                                                Recommended
                                                                                            </span>
                                                                                        )}
                                                                                    </span>
                                                                                    <span className="mt-1 block text-xs text-white/50">
                                                                                        {
                                                                                            plan
                                                                                                .installments
                                                                                                .length
                                                                                        }{' '}
                                                                                        {plan
                                                                                            .installments
                                                                                            .length ===
                                                                                        1
                                                                                            ? 'payment'
                                                                                            : 'payments'}
                                                                                    </span>
                                                                                    {plan.description && (
                                                                                        <span className="mt-1 block text-xs leading-5 text-white/70">
                                                                                            {
                                                                                                plan.description
                                                                                            }
                                                                                        </span>
                                                                                    )}
                                                                                </span>
                                                                            </span>
                                                                        </label>
                                                                    );
                                                                },
                                                            )}
                                                        </div>
                                                    </fieldset>
                                                )}
                                                {selectedPaymentPlan && (
                                                    <div className="mt-5 flex items-center gap-2 text-xs font-bold text-white/65">
                                                        <CheckCircle2 className="size-4 text-[var(--site-primary)]" />
                                                        {`${selectedPaymentPlan.name} breakdown`}
                                                    </div>
                                                )}
                                                {selectedPaymentPlan &&
                                                    selectedPaymentPlan.items
                                                        .length > 0 && (
                                                        <div className="mt-5 grid gap-2">
                                                            {selectedPaymentPlan.items.map(
                                                                (
                                                                    item,
                                                                    index,
                                                                ) => (
                                                                    <div
                                                                        key={`${item.name}-${index}`}
                                                                        className="flex justify-between gap-4 text-xs text-white/50"
                                                                    >
                                                                        <span>
                                                                            {
                                                                                item.name
                                                                            }
                                                                        </span>
                                                                        <span className="text-right font-bold text-white/90">
                                                                            {item.description ||
                                                                                'Included'}
                                                                        </span>
                                                                    </div>
                                                                ),
                                                            )}
                                                        </div>
                                                    )}
                                                {selectedPaymentPlan && (
                                                    <div className="mt-5 border-t border-white/10 pt-4">
                                                        <details>
                                                            <summary className="cursor-pointer text-xs font-bold text-white/80 underline decoration-white/35 underline-offset-4 hover:text-white">
                                                                View repayment
                                                                schedule
                                                            </summary>
                                                            <div className="mt-3 grid gap-3">
                                                                {selectedPaymentPlan.installments.map(
                                                                    (
                                                                        installment,
                                                                        index,
                                                                    ) => (
                                                                        <div
                                                                            key={`${installment.name}-${index}`}
                                                                            className="flex items-start justify-between gap-3 text-xs"
                                                                        >
                                                                            <span>
                                                                                <span className="block text-white/80">
                                                                                    {
                                                                                        installment.name
                                                                                    }
                                                                                </span>
                                                                                <span className="mt-0.5 block text-white/40">
                                                                                    {installment.due_on ??
                                                                                        (installment.due_offset_days ===
                                                                                        0
                                                                                            ? 'On activation'
                                                                                            : installment.due_offset_days !==
                                                                                                null
                                                                                              ? `${installment.due_offset_days} days after activation`
                                                                                              : 'Schedule confirmed by academy')}
                                                                                </span>
                                                                            </span>
                                                                            <strong className="shrink-0">
                                                                                {formatCurrency(
                                                                                    selectedIntake.currency,
                                                                                    installment.amount,
                                                                                )}
                                                                            </strong>
                                                                        </div>
                                                                    ),
                                                                )}
                                                            </div>
                                                        </details>
                                                    </div>
                                                )}
                                            </div>
                                        ) : (
                                            <p className="mt-5 text-sm leading-6 text-white/50">
                                                Select a programme and cycle to
                                                see fees and payment
                                                arrangements.
                                            </p>
                                        )}
                                    </div>

                                    <div className="rounded-3xl border bg-white p-5">
                                        <p className="flex items-center gap-2 text-sm font-bold">
                                            <ShieldCheck className="size-4 text-[var(--site-primary)]" />
                                            Your information
                                        </p>
                                        <p className="mt-2 text-xs leading-5 text-slate-500">
                                            Your selected payment plan is saved
                                            with the application. The next page
                                            lets you choose which amount to pay
                                            now.
                                        </p>
                                    </div>
                                </aside>
                            </Form>
                        )}
                    </div>
                </section>
            </main>
        </PublicSiteShell>
    );
}
