import {
    AlertCircle,
    CheckCircle2,
    ChevronRight,
    CreditCard,
    FileText,
    LockKeyhole,
    ReceiptText,
    ShieldCheck,
    UserRound,
    UsersRound,
} from 'lucide-react';
import { type FormEvent, useMemo, useState } from 'react';
import PublicSiteShell from '@/components/public-site/public-site-shell';
import { formatCurrency } from '@/lib/money';
import type {
    PublicAcademy,
    PublicOffering,
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
    offerings,
    custom_fields,
    countries,
}: {
    academy: PublicAcademy;
    site: PublicSite;
    base_url: string;
    offerings: PublicOffering[];
    custom_fields: CustomField[];
    countries: string[];
}) {
    const [programmeId, setProgrammeId] = useState('');
    const [offeringId, setOfferingId] = useState('');
    const [paymentPlan, setPaymentPlan] = useState('full');
    const [previewNotice, setPreviewNotice] = useState(false);
    const programmes = useMemo(
        () =>
            Array.from(
                new Map(
                    offerings.map((offering) => [
                        offering.programme_id,
                        offering.programme_name ?? 'Programme',
                    ]),
                ),
            ),
        [offerings],
    );
    const programmeOfferings = offerings.filter(
        (offering) => String(offering.programme_id) === programmeId,
    );
    const selectedOffering = offerings.find(
        (offering) => String(offering.id) === offeringId,
    );

    function submitPreview(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setPreviewNotice(true);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

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
                            Select a programme and active cycle, review the
                            available fees and payment arrangements, then
                            provide the applicant and guardian details.
                        </p>
                    </div>
                </section>

                <section className="px-5 py-12 sm:px-8 lg:py-20">
                    <div className="mx-auto max-w-7xl">
                        {previewNotice && (
                            <div
                                role="status"
                                className="mb-8 flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950"
                            >
                                <AlertCircle className="mt-0.5 size-5 shrink-0" />
                                <div>
                                    <p className="font-bold">
                                        Application form preview only
                                    </p>
                                    <p className="mt-1 text-sm leading-6">
                                        No information was submitted or saved.
                                        Public application submission will be
                                        connected in the next implementation
                                        phase.
                                    </p>
                                </div>
                            </div>
                        )}

                        <form
                            onSubmit={submitPreview}
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
                                                Programme and cycle
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
                                                    setOfferingId('');
                                                    setPaymentPlan('full');
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
                                            label="Cycle and location"
                                            name="programme_cycle_id"
                                            required
                                        >
                                            <select
                                                name="programme_cycle_id"
                                                aria-label="Cycle and location"
                                                required
                                                value={offeringId}
                                                disabled={!programmeId}
                                                onChange={(event) => {
                                                    setOfferingId(
                                                        event.target.value,
                                                    );
                                                    setPaymentPlan('full');
                                                }}
                                                className={inputClass}
                                            >
                                                <option value="">
                                                    {programmeId
                                                        ? 'Select a cycle'
                                                        : 'Choose a programme first'}
                                                </option>
                                                {programmeOfferings.map(
                                                    (offering) => (
                                                        <option
                                                            key={offering.id}
                                                            value={offering.id}
                                                        >
                                                            {
                                                                offering.cycle_name
                                                            }{' '}
                                                            ·{' '}
                                                            {
                                                                offering.branch_name
                                                            }
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        </Field>
                                    </div>

                                    {selectedOffering && (
                                        <div className="mt-7 border-t pt-7">
                                            <h3 className="text-sm font-black">
                                                Choose a payment plan
                                            </h3>
                                            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                                <label
                                                    className={`cursor-pointer rounded-2xl border-2 p-4 ${paymentPlan === 'full' ? 'border-[var(--site-primary)] bg-[var(--site-secondary)]/35' : 'border-slate-200'}`}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="payment_plan"
                                                        value="full"
                                                        checked={
                                                            paymentPlan ===
                                                            'full'
                                                        }
                                                        onChange={() =>
                                                            setPaymentPlan(
                                                                'full',
                                                            )
                                                        }
                                                        className="sr-only"
                                                    />
                                                    <span className="flex items-center justify-between gap-3">
                                                        <span>
                                                            <span className="block font-bold">
                                                                Pay in full
                                                            </span>
                                                            <span className="mt-1 block text-sm text-slate-600">
                                                                One programme
                                                                fee payment
                                                            </span>
                                                        </span>
                                                        <CreditCard className="size-5 text-[var(--site-primary)]" />
                                                    </span>
                                                </label>
                                                {selectedOffering.installments
                                                    .length > 0 && (
                                                    <label
                                                        className={`cursor-pointer rounded-2xl border-2 p-4 ${paymentPlan === 'installments' ? 'border-[var(--site-primary)] bg-[var(--site-secondary)]/35' : 'border-slate-200'}`}
                                                    >
                                                        <input
                                                            type="radio"
                                                            name="payment_plan"
                                                            value="installments"
                                                            checked={
                                                                paymentPlan ===
                                                                'installments'
                                                            }
                                                            onChange={() =>
                                                                setPaymentPlan(
                                                                    'installments',
                                                                )
                                                            }
                                                            className="sr-only"
                                                        />
                                                        <span className="flex items-center justify-between gap-3">
                                                            <span>
                                                                <span className="block font-bold">
                                                                    Pay in
                                                                    installments
                                                                </span>
                                                                <span className="mt-1 block text-sm text-slate-600">
                                                                    {
                                                                        selectedOffering
                                                                            .installments
                                                                            .length
                                                                    }{' '}
                                                                    scheduled
                                                                    payments
                                                                </span>
                                                            </span>
                                                            <ReceiptText className="size-5 text-[var(--site-primary)]" />
                                                        </span>
                                                    </label>
                                                )}
                                            </div>
                                            {paymentPlan === 'installments' && (
                                                <div className="mt-4 divide-y rounded-2xl border px-4">
                                                    {selectedOffering.installments.map(
                                                        (
                                                            installment,
                                                            index,
                                                        ) => (
                                                            <div
                                                                key={`${installment.name}-${index}`}
                                                                className="flex items-center justify-between gap-4 py-3 text-sm"
                                                            >
                                                                <span>
                                                                    <strong>
                                                                        {
                                                                            installment.name
                                                                        }
                                                                    </strong>
                                                                    {(installment.due_on ||
                                                                        installment.due_offset_days) && (
                                                                        <span className="ml-2 text-xs text-slate-500">
                                                                            {installment.due_on ??
                                                                                `${installment.due_offset_days} days after enrollment`}
                                                                        </span>
                                                                    )}
                                                                </span>
                                                                <strong>
                                                                    {formatCurrency(
                                                                        selectedOffering.currency,
                                                                        installment.amount,
                                                                    )}
                                                                </strong>
                                                            </div>
                                                        ),
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    )}
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
                                        <Field label="Country" name="country">
                                            <select
                                                name="country"
                                                defaultValue="Nigeria"
                                                className={inputClass}
                                            >
                                                {countries.map((country) => (
                                                    <option
                                                        key={country}
                                                        value={country}
                                                    >
                                                        {country}
                                                    </option>
                                                ))}
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
                                                        className={labelClass}
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
                                                                Select an option
                                                            </option>
                                                            {field.options.map(
                                                                (option) => (
                                                                    <option
                                                                        key={
                                                                            option
                                                                        }
                                                                        value={
                                                                            option
                                                                        }
                                                                    >
                                                                        {option}
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
                                                            type={field.type}
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
                                    <div className="flex items-center gap-3">
                                        <span className="grid size-11 place-items-center rounded-2xl bg-[var(--site-secondary)] text-[var(--site-secondary-fg)]">
                                            <UsersRound className="size-5" />
                                        </span>
                                        <div>
                                            <p className="text-xs font-bold text-[var(--site-primary)]">
                                                Final step
                                            </p>
                                            <h2 className="text-xl font-black">
                                                Guardian information
                                            </h2>
                                        </div>
                                    </div>
                                    <p className="mt-3 text-sm leading-6 text-slate-600">
                                        Complete this section when the applicant
                                        requires a parent, guardian or emergency
                                        contact.
                                    </p>
                                    <div className="mt-7 grid gap-5 sm:grid-cols-2">
                                        <Field
                                            label="Guardian first name"
                                            name="guardian_first_name"
                                        />
                                        <Field
                                            label="Guardian last name"
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
                                    <label className="mt-7 flex items-start gap-3 rounded-2xl bg-slate-50 p-4 text-sm leading-6">
                                        <input
                                            type="checkbox"
                                            name="guardian_consent"
                                            required
                                            className="mt-1 size-4 shrink-0 accent-[var(--site-primary)]"
                                        />
                                        <span>
                                            I confirm that the information is
                                            accurate and that the academy may
                                            process it for this application.
                                        </span>
                                    </label>
                                </section>
                            </div>

                            <aside className="space-y-4 lg:sticky lg:top-28">
                                <div className="rounded-3xl bg-slate-950 p-6 text-white shadow-xl">
                                    <div className="flex items-center gap-2">
                                        <ReceiptText className="size-5 text-[var(--site-primary)]" />
                                        <h2 className="font-black">
                                            Application summary
                                        </h2>
                                    </div>
                                    {selectedOffering ? (
                                        <div className="mt-6">
                                            <p className="text-sm font-bold">
                                                {
                                                    selectedOffering.programme_name
                                                }
                                            </p>
                                            <p className="mt-1 text-xs text-white/50">
                                                {selectedOffering.cycle_name} ·{' '}
                                                {selectedOffering.branch_name}
                                            </p>
                                            <div className="mt-6 divide-y divide-white/10 border-y border-white/10">
                                                <div className="flex justify-between gap-4 py-3 text-sm">
                                                    <span className="text-white/55">
                                                        Application fee
                                                    </span>
                                                    <strong>
                                                        {formatCurrency(
                                                            selectedOffering.currency,
                                                            selectedOffering.application_fee,
                                                        )}
                                                    </strong>
                                                </div>
                                                <div className="flex justify-between gap-4 py-3 text-sm">
                                                    <span className="text-white/55">
                                                        Programme fees
                                                    </span>
                                                    <strong>
                                                        {formatCurrency(
                                                            selectedOffering.currency,
                                                            selectedOffering.total,
                                                        )}
                                                    </strong>
                                                </div>
                                            </div>
                                            {selectedOffering.fee_items.length >
                                                0 && (
                                                <div className="mt-5 grid gap-2">
                                                    {selectedOffering.fee_items.map(
                                                        (item) => (
                                                            <div
                                                                key={item.name}
                                                                className="flex justify-between gap-4 text-xs text-white/50"
                                                            >
                                                                <span>
                                                                    {item.name}
                                                                </span>
                                                                <span>
                                                                    {formatCurrency(
                                                                        selectedOffering.currency,
                                                                        item.amount,
                                                                    )}
                                                                </span>
                                                            </div>
                                                        ),
                                                    )}
                                                </div>
                                            )}
                                            <p className="mt-6 flex items-start gap-2 text-xs leading-5 text-white/45">
                                                <CheckCircle2 className="mt-0.5 size-3.5 shrink-0" />
                                                Selected plan:{' '}
                                                {paymentPlan === 'full'
                                                    ? 'Pay in full'
                                                    : 'Installments'}
                                            </p>
                                        </div>
                                    ) : (
                                        <p className="mt-5 text-sm leading-6 text-white/50">
                                            Select a programme and cycle to see
                                            fees and payment arrangements.
                                        </p>
                                    )}
                                </div>

                                <div className="rounded-3xl border bg-white p-5">
                                    <p className="flex items-center gap-2 text-sm font-bold">
                                        <ShieldCheck className="size-4 text-[var(--site-primary)]" />
                                        Your information
                                    </p>
                                    <p className="mt-2 text-xs leading-5 text-slate-500">
                                        This is currently a UI preview. No form
                                        data is transmitted or retained.
                                    </p>
                                </div>

                                <button
                                    type="submit"
                                    disabled={!selectedOffering}
                                    className="flex w-full items-center justify-between rounded-full bg-[var(--site-primary)] px-6 py-4 text-sm font-black text-[var(--site-primary-fg)] shadow-lg disabled:cursor-not-allowed disabled:opacity-45"
                                >
                                    Preview application
                                    <ChevronRight className="size-4" />
                                </button>
                            </aside>
                        </form>
                    </div>
                </section>
            </main>
        </PublicSiteShell>
    );
}
