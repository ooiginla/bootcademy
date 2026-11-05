import { Form, Head, Link } from '@inertiajs/react';
import { ClipboardList, Eye, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
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
import { useAcademyTerminology } from '@/hooks/use-academy-terminology';
import {
    currencySymbol,
    formatCurrency,
    formatMoneyInput,
    normalizeMoneyInput,
} from '@/lib/money';
import {
    CustomPlayerFieldInput,
    type CustomField,
    type Option,
} from './players';

export type AcademyApplication = {
    id: number;
    reference: string;
    source: string;
    external_reference: string | null;
    internal_identifier: string | null;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    date_of_birth: string | null;
    age: number | null;
    gender: string | null;
    phone: string | null;
    whatsapp_number: string | null;
    home_address: string | null;
    city: string | null;
    state_region: string | null;
    country: string | null;
    guardian_name: string;
    guardian_first_name: string | null;
    guardian_last_name: string | null;
    guardian_email: string | null;
    guardian_phone: string | null;
    guardian_whatsapp_number: string | null;
    guardian_relationship: string | null;
    has_guardian_signature: boolean;
    guardian_consent_at: string | null;
    privacy_policy_version: string | null;
    programme_id: number | null;
    programme_cycle_id: number | null;
    programme_name: string | null;
    cycle_name: string | null;
    cycle_id?: number | null;
    branch_id: number | null;
    branch_name: string | null;
    custom_fields: Record<string, string | number | boolean | null>;
    status: string;
    review_notes: string | null;
    created_at: string;
    invoices: Invoice[];
};
export type Invoice = {
    id: number;
    number: string;
    purpose: 'application' | 'programme' | 'custom';
    fee_name?: string;
    status: string;
    currency: string;
    cycle_type: 'fixed' | 'rolling';
    cycle_starts_on: string | null;
    cycle_ends_on: string | null;
    cycle_duration: number | null;
    cycle_duration_unit: 'days' | 'weeks' | 'months' | null;
    total: string;
    paid: string;
    written_off?: string;
    balance: string;
    installments: Array<{
        name: string;
        amount: string;
        paid: string;
        due_on: string | null;
        due_offset_days: number | null;
    }>;
};
export type BankDetails = {
    bank_name: string | null;
    account_name: string | null;
    account_number: string | null;
};
export type ProgrammeCycleOption = Option & {
    programme_id: number;
    cycle_id: number;
    cycle_name: string;
    branch_id: number | null;
    branch_name: string | null;
};
type Paginator = {
    data: AcademyApplication[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm';
const statusLabel: Record<string, string> = {
    submitted: 'Submitted',
    under_review: 'Under review',
    accepted: 'Accepted',
    rejected: 'Rejected',
    enrolled: 'Enrolled',
};

export function InvoiceAmountBadges({ invoice }: { invoice: Invoice }) {
    const hasBalance = Number(invoice.balance) > 0;

    return (
        <div
            className="mt-2 flex flex-wrap gap-2"
            aria-label={`Invoice ${invoice.number} payment summary`}
        >
            <Badge variant="outline" className="font-mono">
                {invoice.number}
            </Badge>
            <Badge
                variant="outline"
                className="border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
            >
                Billed {formatCurrency(invoice.currency, invoice.total)}
            </Badge>
            <Badge
                variant="outline"
                className="border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
            >
                Paid {formatCurrency(invoice.currency, invoice.paid)}
            </Badge>
            <Badge
                variant="outline"
                className={
                    hasBalance
                        ? 'border-amber-300 bg-amber-100 text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200'
                        : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                }
            >
                Owing {formatCurrency(invoice.currency, invoice.balance)}
            </Badge>
        </div>
    );
}

export function ApplicationDialog({
    programmes,
    branches,
    customFields,
    countries,
    programmeCycles,
    activeCycles,
    application,
}: {
    programmes: Option[];
    branches: Option[];
    customFields: CustomField[];
    countries: string[];
    programmeCycles: ProgrammeCycleOption[];
    activeCycles: Option[];
    application?: AcademyApplication;
}) {
    const [open, setOpen] = useState(false);
    const [programmeCycleId, setProgrammeCycleId] = useState(
        String(application?.programme_cycle_id ?? ''),
    );
    const [cycleId, setCycleId] = useState(
        String(application?.cycle_id ?? activeCycles[0]?.id ?? ''),
    );
    const [branchId, setBranchId] = useState(
        String(application?.branch_id ?? ''),
    );
    const editing = application !== undefined;
    const cycleOfferings = programmeCycles
        .filter((offering) => String(offering.cycle_id) === cycleId)
        .filter((offering, index, offerings) => {
            const scoped = offerings.find(
                (candidate) =>
                    candidate.programme_id === offering.programme_id &&
                    String(candidate.branch_id ?? '') === branchId,
            );
            const selected =
                scoped ??
                offerings.find(
                    (candidate) =>
                        candidate.programme_id === offering.programme_id &&
                        candidate.branch_id === null,
                );
            return (
                selected?.id === offering.id &&
                offerings.findIndex(
                    (candidate) => candidate.id === offering.id,
                ) === index
            );
        });
    const terminology = useAcademyTerminology();
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {editing ? (
                    <Button variant="outline" size="sm">
                        <Pencil className="size-4" /> Edit
                    </Button>
                ) : (
                    <Button>
                        <Plus className="size-4" />
                        Add application
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {editing ? 'Edit' : 'New'} {terminology.singularLower}{' '}
                        application
                    </DialogTitle>
                    <DialogDescription>
                        {editing
                            ? 'Correct application and guardian information without changing its review or payment history.'
                            : 'Capture a prospect for review. Once accepted, the applicant becomes an active player while any programme balance remains payable.'}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={
                        editing
                            ? `/app/applications/${application.id}`
                            : '/app/applications'
                    }
                    method={editing ? 'patch' : 'post'}
                    encType="multipart/form-data"
                    className="space-y-6"
                    resetOnSuccess={!editing}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <fieldset>
                                <legend className="font-medium">
                                    Applicant
                                </legend>
                                <div className="mt-3 grid gap-4 sm:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label>First name</Label>
                                        <Input
                                            name="first_name"
                                            defaultValue={
                                                application?.first_name ?? ''
                                            }
                                            required
                                        />
                                        <InputError
                                            message={errors.first_name}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Middle name</Label>
                                        <Input
                                            name="middle_name"
                                            defaultValue={
                                                application?.middle_name ?? ''
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Last name</Label>
                                        <Input
                                            name="last_name"
                                            defaultValue={
                                                application?.last_name ?? ''
                                            }
                                            required
                                        />
                                        <InputError
                                            message={errors.last_name}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Date of birth</Label>
                                        <Input
                                            name="date_of_birth"
                                            type="date"
                                            defaultValue={
                                                application?.date_of_birth ?? ''
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
                                                application?.internal_identifier ??
                                                ''
                                            }
                                            placeholder="e.g. LRN-001"
                                        />
                                        <InputError
                                            message={errors.internal_identifier}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Gender (optional)</Label>
                                        <select
                                            name="gender"
                                            className={selectClass}
                                            defaultValue={
                                                application?.gender ?? ''
                                            }
                                        >
                                            <option value="">
                                                Not selected
                                            </option>
                                            <option value="Male">Male</option>
                                            <option value="Female">
                                                Female
                                            </option>
                                        </select>
                                        <InputError message={errors.gender} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Phone number (optional)</Label>
                                        <Input
                                            name="phone"
                                            type="tel"
                                            defaultValue={
                                                application?.phone ?? ''
                                            }
                                        />
                                        <InputError message={errors.phone} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>
                                            WhatsApp number (optional)
                                        </Label>
                                        <Input
                                            name="whatsapp_number"
                                            type="tel"
                                            defaultValue={
                                                application?.whatsapp_number ??
                                                ''
                                            }
                                        />
                                        <InputError
                                            message={errors.whatsapp_number}
                                        />
                                    </div>
                                    <div className="grid gap-2 sm:col-span-3">
                                        <Label>Home address (optional)</Label>
                                        <Input
                                            name="home_address"
                                            defaultValue={
                                                application?.home_address ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors.home_address}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>City (optional)</Label>
                                        <Input
                                            name="city"
                                            defaultValue={
                                                application?.city ?? ''
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>State (optional)</Label>
                                        <Input
                                            name="state_region"
                                            defaultValue={
                                                application?.state_region ?? ''
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Country</Label>
                                        <select
                                            name="country"
                                            className={selectClass}
                                            defaultValue={
                                                application?.country ??
                                                'Nigeria'
                                            }
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
                                        <InputError message={errors.country} />
                                    </div>
                                </div>
                            </fieldset>
                            <fieldset>
                                <legend className="font-medium">
                                    Guardian contact
                                </legend>
                                <div className="mt-3 grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label>First name</Label>
                                        <Input
                                            name="guardian_first_name"
                                            defaultValue={
                                                application?.guardian_first_name ??
                                                ''
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Last name</Label>
                                        <Input
                                            name="guardian_last_name"
                                            defaultValue={
                                                application?.guardian_last_name ??
                                                ''
                                            }
                                        />
                                        <InputError
                                            message={errors.guardian_last_name}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Email</Label>
                                        <Input
                                            name="guardian_email"
                                            type="email"
                                            defaultValue={
                                                application?.guardian_email ??
                                                ''
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Phone</Label>
                                        <Input
                                            name="guardian_phone"
                                            type="tel"
                                            defaultValue={
                                                application?.guardian_phone ??
                                                ''
                                            }
                                        />
                                        <InputError
                                            message={errors.guardian_phone}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Relationship</Label>
                                        <Input
                                            name="guardian_relationship"
                                            defaultValue={
                                                application?.guardian_relationship ??
                                                'Parent'
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>WhatsApp number</Label>
                                        <Input
                                            name="guardian_whatsapp_number"
                                            type="tel"
                                            defaultValue={
                                                application?.guardian_whatsapp_number ??
                                                ''
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors.guardian_whatsapp_number
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label>Signature (optional)</Label>
                                        <Input
                                            name="guardian_signature"
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            JPG, PNG or WebP, up to 2 MB.
                                        </p>
                                        <InputError
                                            message={errors.guardian_signature}
                                        />
                                    </div>
                                </div>
                            </fieldset>
                            <fieldset>
                                <legend className="font-medium">
                                    Cycle and programme preference
                                </legend>
                                <div className="mt-3 grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label>Active cycle</Label>
                                        <select
                                            name="academy_cycle_id"
                                            className={selectClass}
                                            value={cycleId}
                                            disabled={
                                                editing &&
                                                application.invoices.length > 0
                                            }
                                            onChange={(event) => {
                                                setCycleId(event.target.value);
                                                setProgrammeCycleId('');
                                            }}
                                            required={activeCycles.length > 0}
                                        >
                                            <option value="">
                                                No active cycle selected
                                            </option>
                                            {activeCycles.map((cycle) => (
                                                <option
                                                    key={cycle.id}
                                                    value={cycle.id}
                                                >
                                                    {cycle.name}
                                                </option>
                                            ))}
                                            {application?.cycle_id &&
                                                !activeCycles.some(
                                                    (cycle) =>
                                                        cycle.id ===
                                                        application.cycle_id,
                                                ) && (
                                                    <option
                                                        value={
                                                            application.cycle_id
                                                        }
                                                    >
                                                        {application.cycle_name}
                                                    </option>
                                                )}
                                        </select>
                                        {editing &&
                                            application.invoices.length > 0 && (
                                                <input
                                                    type="hidden"
                                                    name="academy_cycle_id"
                                                    value={cycleId}
                                                />
                                            )}
                                        <p className="text-xs text-muted-foreground">
                                            The cycle determines the available
                                            programmes and application fee.
                                        </p>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Programme offering</Label>
                                        {programmeCycles.length > 0 ? (
                                            <>
                                                <select
                                                    name="programme_cycle_id"
                                                    className={selectClass}
                                                    value={programmeCycleId}
                                                    disabled={
                                                        editing &&
                                                        application.invoices
                                                            .length > 0
                                                    }
                                                    onChange={(event) =>
                                                        setProgrammeCycleId(
                                                            event.target.value,
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        Not selected
                                                    </option>
                                                    {cycleOfferings.map(
                                                        (item) => (
                                                            <option
                                                                key={item.id}
                                                                value={item.id}
                                                            >
                                                                {item.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                                <input
                                                    type="hidden"
                                                    name="programme_id"
                                                    value={
                                                        programmeCycles.find(
                                                            (item) =>
                                                                String(
                                                                    item.id,
                                                                ) ===
                                                                programmeCycleId,
                                                        )?.programme_id ?? ''
                                                    }
                                                />
                                                {editing &&
                                                    application.invoices
                                                        .length > 0 && (
                                                        <input
                                                            type="hidden"
                                                            name="programme_cycle_id"
                                                            value={
                                                                programmeCycleId
                                                            }
                                                        />
                                                    )}
                                            </>
                                        ) : (
                                            <>
                                                <select
                                                    name="programme_id"
                                                    className={selectClass}
                                                    defaultValue={
                                                        application?.programme_id ??
                                                        ''
                                                    }
                                                    disabled={
                                                        editing &&
                                                        application.invoices
                                                            .length > 0
                                                    }
                                                >
                                                    <option value="">
                                                        Not selected
                                                    </option>
                                                    {programmes.map((item) => (
                                                        <option
                                                            key={item.id}
                                                            value={item.id}
                                                        >
                                                            {item.name}
                                                        </option>
                                                    ))}
                                                </select>
                                                {editing &&
                                                    application.invoices
                                                        .length > 0 && (
                                                        <input
                                                            type="hidden"
                                                            name="programme_id"
                                                            value={
                                                                application.programme_id ??
                                                                ''
                                                            }
                                                        />
                                                    )}
                                            </>
                                        )}
                                        <InputError
                                            message={errors.programme_id}
                                        />
                                        <InputError
                                            message={errors.programme_cycle_id}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Branch</Label>
                                        <select
                                            name="branch_id"
                                            className={selectClass}
                                            value={branchId}
                                            disabled={
                                                editing &&
                                                application.invoices.length > 0
                                            }
                                            onChange={(event) => {
                                                setBranchId(event.target.value);
                                                setProgrammeCycleId('');
                                            }}
                                        >
                                            <option value="">
                                                Not selected
                                            </option>
                                            {branches.map((item) => (
                                                <option
                                                    key={item.id}
                                                    value={item.id}
                                                >
                                                    {item.name}
                                                </option>
                                            ))}
                                        </select>
                                        {editing &&
                                            application.invoices.length > 0 && (
                                                <input
                                                    type="hidden"
                                                    name="branch_id"
                                                    value={branchId}
                                                />
                                            )}
                                        <p className="text-xs text-muted-foreground">
                                            Branch-specific fees are used when
                                            configured; otherwise the academy
                                            default applies.
                                        </p>
                                        <InputError
                                            message={errors.branch_id}
                                        />
                                    </div>
                                </div>
                            </fieldset>
                            {customFields.length > 0 && (
                                <fieldset>
                                    <legend className="font-medium">
                                        Additional information
                                    </legend>
                                    <div className="mt-3 grid gap-4 sm:grid-cols-2">
                                        {customFields.map((field) => (
                                            <CustomPlayerFieldInput
                                                key={field.id}
                                                field={field}
                                                value={
                                                    application?.custom_fields[
                                                        field.key
                                                    ] ?? null
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
                                        : 'Create application'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export function RecordPaymentDialog({
    invoice,
    bankDetails,
    trigger,
}: {
    invoice: Invoice;
    bankDetails?: BankDetails;
    trigger?: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [amount, setAmount] = useState(invoice.balance);
    const [method, setMethod] = useState('bank_transfer');
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button size="sm" disabled={invoice.status === 'paid'}>
                        Record payment
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Record verified payment</DialogTitle>
                    <DialogDescription>
                        {invoice.number} · Outstanding{' '}
                        {formatCurrency(invoice.currency, invoice.balance)}.
                        This records money already received; it does not
                        initiate an online charge.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/app/finance/invoices/${invoice.id}/payments`}
                    method="post"
                    className="space-y-4"
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label>{`Amount (${currencySymbol(invoice.currency)})`}</Label>
                                <input
                                    type="hidden"
                                    name="amount"
                                    value={amount}
                                />
                                <Input
                                    aria-label="Amount"
                                    value={formatMoneyInput(amount)}
                                    onChange={(event) =>
                                        setAmount(
                                            normalizeMoneyInput(
                                                event.target.value,
                                            ),
                                        )
                                    }
                                    inputMode="decimal"
                                    required
                                />
                                <InputError message={errors.amount} />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>Method</Label>
                                    <select
                                        name="method"
                                        className={selectClass}
                                        value={method}
                                        onChange={(event) =>
                                            setMethod(event.target.value)
                                        }
                                    >
                                        <option value="bank_transfer">
                                            Bank transfer
                                        </option>
                                        <option value="cash">Cash</option>
                                        <option value="pos">POS</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Received on</Label>
                                    <Input
                                        name="received_on"
                                        type="date"
                                        defaultValue={new Date()
                                            .toISOString()
                                            .slice(0, 10)}
                                        required
                                    />
                                </div>
                            </div>
                            {(method === 'bank_transfer' ||
                                method === 'cheque') && (
                                <div className="rounded-lg border bg-muted/40 p-4 text-sm">
                                    <p className="font-medium">
                                        Academy bank account
                                    </p>
                                    {bankDetails?.bank_name &&
                                    bankDetails.account_name &&
                                    bankDetails.account_number ? (
                                        <dl className="mt-2 grid gap-1 sm:grid-cols-3">
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Bank
                                                </dt>
                                                <dd>{bankDetails.bank_name}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Account name
                                                </dt>
                                                <dd>
                                                    {bankDetails.account_name}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Account number
                                                </dt>
                                                <dd className="font-mono font-medium">
                                                    {bankDetails.account_number}
                                                </dd>
                                            </div>
                                        </dl>
                                    ) : (
                                        <p className="mt-1 text-muted-foreground">
                                            No bank account has been configured
                                            in Academy Settings.
                                        </p>
                                    )}
                                </div>
                            )}
                            <div className="grid gap-2">
                                <Label>Bank/reference number (optional)</Label>
                                <Input name="external_reference" />
                                <InputError
                                    message={errors.external_reference}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label>Notes (optional)</Label>
                                <textarea
                                    name="notes"
                                    rows={3}
                                    className="rounded-md border bg-background px-3 py-2 text-sm"
                                />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}Record payment
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export function CustomizeInstallmentsDialog({
    invoice,
    trigger,
}: {
    invoice: Invoice;
    trigger?: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [rows, setRows] = useState(
        invoice.installments.map((item) => ({
            name: item.name,
            amount: item.amount,
            due_on: item.due_on ?? '',
            due_offset_days: item.due_offset_days?.toString() ?? '',
        })),
    );
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button variant="outline" size="sm">
                        Customize payment plan
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Student-specific payment plan</DialogTitle>
                    <DialogDescription>
                        Use one row for a full-payment plan or several rows for
                        installments. The amounts for {invoice.number} must
                        total {formatCurrency(invoice.currency, invoice.total)}.
                        It cannot be changed after payment starts.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/app/finance/invoices/${invoice.id}/installments`}
                    method="patch"
                    className="space-y-4"
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            {rows.map((row, index) => (
                                <div
                                    key={index}
                                    className="grid gap-3 rounded-lg border p-3 sm:grid-cols-2"
                                >
                                    <div className="grid gap-1">
                                        <Label>Name</Label>
                                        <Input
                                            name={`installments[${index}][name]`}
                                            value={row.name}
                                            onChange={(event) =>
                                                setRows((current) =>
                                                    current.map(
                                                        (item, itemIndex) =>
                                                            itemIndex === index
                                                                ? {
                                                                      ...item,
                                                                      name: event
                                                                          .target
                                                                          .value,
                                                                  }
                                                                : item,
                                                    ),
                                                )
                                            }
                                            required
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label>{`Amount (${currencySymbol(invoice.currency)})`}</Label>
                                        <input
                                            type="hidden"
                                            name={`installments[${index}][amount]`}
                                            value={row.amount}
                                        />
                                        <Input
                                            aria-label={`Installment ${index + 1} amount`}
                                            value={formatMoneyInput(row.amount)}
                                            onChange={(event) =>
                                                setRows((current) =>
                                                    current.map(
                                                        (item, itemIndex) =>
                                                            itemIndex === index
                                                                ? {
                                                                      ...item,
                                                                      amount: normalizeMoneyInput(
                                                                          event
                                                                              .target
                                                                              .value,
                                                                      ),
                                                                  }
                                                                : item,
                                                    ),
                                                )
                                            }
                                            inputMode="decimal"
                                            required
                                        />
                                    </div>
                                    {invoice.cycle_type === 'fixed' ? (
                                        <div className="grid gap-1">
                                            <Label>Due date</Label>
                                            <Input
                                                name={`installments[${index}][due_on]`}
                                                type="date"
                                                min={
                                                    invoice.cycle_starts_on ??
                                                    undefined
                                                }
                                                max={
                                                    invoice.cycle_ends_on ??
                                                    undefined
                                                }
                                                value={row.due_on}
                                                onChange={(event) =>
                                                    setRows((current) =>
                                                        current.map(
                                                            (
                                                                item,
                                                                itemIndex,
                                                            ) =>
                                                                itemIndex ===
                                                                index
                                                                    ? {
                                                                          ...item,
                                                                          due_on: event
                                                                              .target
                                                                              .value,
                                                                      }
                                                                    : item,
                                                        ),
                                                    )
                                                }
                                                required
                                            />
                                        </div>
                                    ) : (
                                        <div className="grid gap-1">
                                            <Label>Days after activation</Label>
                                            <Input
                                                name={`installments[${index}][due_offset_days]`}
                                                type="number"
                                                min={0}
                                                value={row.due_offset_days}
                                                onChange={(event) =>
                                                    setRows((current) =>
                                                        current.map(
                                                            (
                                                                item,
                                                                itemIndex,
                                                            ) =>
                                                                itemIndex ===
                                                                index
                                                                    ? {
                                                                          ...item,
                                                                          due_offset_days:
                                                                              event
                                                                                  .target
                                                                                  .value,
                                                                      }
                                                                    : item,
                                                        ),
                                                    )
                                                }
                                                required
                                            />
                                        </div>
                                    )}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        disabled={rows.length === 1}
                                        onClick={() =>
                                            setRows((current) =>
                                                current.filter(
                                                    (_, itemIndex) =>
                                                        itemIndex !== index,
                                                ),
                                            )
                                        }
                                    >
                                        Remove
                                    </Button>
                                </div>
                            ))}
                            <InputError message={errors.installments} />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setRows((current) => [
                                        ...current,
                                        {
                                            name: '',
                                            amount: '',
                                            due_on: '',
                                            due_offset_days: '',
                                        },
                                    ])
                                }
                            >
                                Add installment
                            </Button>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}Save schedule
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export function ReviewDialog({
    application,
    programmes,
    branches,
    customFields,
}: {
    application: AcademyApplication;
    programmes: Option[];
    branches: Option[];
    customFields: CustomField[];
}) {
    const [open, setOpen] = useState(false);
    const terminal = ['rejected', 'enrolled'].includes(application.status);
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Eye className="size-4" />
                    Review
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {application.first_name} {application.middle_name}{' '}
                        {application.last_name}
                    </DialogTitle>
                    <DialogDescription>
                        {application.reference} · Submitted{' '}
                        {application.created_at}
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 rounded-lg border p-4 text-sm sm:grid-cols-2">
                    <div>
                        <p className="text-xs text-muted-foreground uppercase">
                            Applicant details
                        </p>
                        <p className="font-medium">
                            {application.internal_identifier ??
                                'No internal ID'}
                        </p>
                        <p>
                            {application.date_of_birth ?? 'No date of birth'}
                            {application.age !== null
                                ? ` · ${application.age} years old`
                                : ''}
                            {application.gender
                                ? ` · ${application.gender}`
                                : ''}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground uppercase">
                            Submission source
                        </p>
                        <p className="font-medium">
                            {application.source === 'api'
                                ? 'Website API'
                                : 'Academy admin'}
                        </p>
                        <p>
                            {application.external_reference ??
                                'No external reference'}
                        </p>
                        {application.guardian_consent_at && (
                            <p>
                                Consent recorded · Policy{' '}
                                {application.privacy_policy_version ??
                                    'not specified'}
                            </p>
                        )}
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground uppercase">
                            Applicant contact
                        </p>
                        <p className="font-medium">
                            {application.phone ?? 'No phone'}
                        </p>
                        <p>
                            WhatsApp:{' '}
                            {application.whatsapp_number ?? 'Not supplied'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground uppercase">
                            Address
                        </p>
                        <p className="font-medium">
                            {application.home_address ?? 'Not supplied'}
                        </p>
                        <p>
                            {[
                                application.city,
                                application.state_region,
                                application.country,
                            ]
                                .filter(Boolean)
                                .join(', ') || 'No location supplied'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground uppercase">
                            Guardian
                        </p>
                        <p className="font-medium">
                            {application.guardian_name || 'Not supplied'}
                        </p>
                        <p>
                            {[
                                application.guardian_email,
                                application.guardian_phone,
                            ]
                                .filter(Boolean)
                                .join(' · ') || 'No contact supplied'}
                        </p>
                        <p>
                            {application.guardian_relationship ?? 'Guardian'}
                            {' · WhatsApp: '}
                            {application.guardian_whatsapp_number ??
                                'Not supplied'}
                            {' · Signature: '}
                            {application.has_guardian_signature
                                ? 'Supplied'
                                : 'Not supplied'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground uppercase">
                            Preference
                        </p>
                        <p className="font-medium">
                            {application.programme_name ?? 'No programme'}
                        </p>
                        <p>{application.branch_name ?? 'No branch'}</p>
                        {application.cycle_name && (
                            <p>Cycle: {application.cycle_name}</p>
                        )}
                    </div>
                    {customFields
                        .filter(
                            (field) =>
                                application.custom_fields[field.key] != null,
                        )
                        .map((field) => (
                            <div key={field.id}>
                                <p className="text-xs text-muted-foreground uppercase">
                                    {field.label}
                                </p>
                                <p>
                                    {String(
                                        application.custom_fields[field.key],
                                    )}
                                </p>
                            </div>
                        ))}
                </div>
                {application.invoices.length > 0 && (
                    <div className="space-y-3">
                        <h3 className="font-medium">Invoices and payments</h3>
                        {application.invoices.map((invoice) => (
                            <div
                                key={invoice.id}
                                className="rounded-lg border p-4 text-sm"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="font-medium">
                                            {invoice.purpose === 'application'
                                                ? 'Application fee'
                                                : 'Programme fees'}{' '}
                                            · {invoice.number}
                                        </p>
                                        <InvoiceAmountBadges
                                            invoice={invoice}
                                        />
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge variant="secondary">
                                            {invoice.status.replace('_', ' ')}
                                        </Badge>
                                        <RecordPaymentDialog
                                            invoice={invoice}
                                        />
                                    </div>
                                </div>
                                {invoice.purpose === 'programme' &&
                                    invoice.paid === '0.00' && (
                                        <div className="mt-3">
                                            <CustomizeInstallmentsDialog
                                                invoice={invoice}
                                            />
                                        </div>
                                    )}
                                {invoice.installments.length > 1 && (
                                    <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                        {invoice.installments.map(
                                            (installment) => (
                                                <p
                                                    key={installment.name}
                                                    className="rounded bg-muted/50 px-3 py-2"
                                                >
                                                    {installment.name}: paid{' '}
                                                    {formatCurrency(
                                                        invoice.currency,
                                                        installment.paid,
                                                    )}{' '}
                                                    of{' '}
                                                    {formatCurrency(
                                                        invoice.currency,
                                                        installment.amount,
                                                    )}
                                                    {installment.due_on
                                                        ? ` · due ${installment.due_on}`
                                                        : ''}
                                                </p>
                                            ),
                                        )}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
                {terminal ? (
                    <p className="rounded-lg bg-muted p-4 text-sm">
                        This application is{' '}
                        {statusLabel[application.status].toLocaleLowerCase()}{' '}
                        and cannot be changed.
                    </p>
                ) : (
                    <Form
                        action={`/app/applications/${application.id}/review`}
                        method="patch"
                        className="space-y-4"
                        onSuccess={() => setOpen(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label>Programme for enrolment</Label>
                                        <select
                                            name="programme_id"
                                            className={selectClass}
                                            defaultValue={
                                                application.programme_id ?? ''
                                            }
                                        >
                                            <option value="">
                                                Select programme
                                            </option>
                                            {programmes.map((item) => (
                                                <option
                                                    key={item.id}
                                                    value={item.id}
                                                >
                                                    {item.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.programme_id}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Branch</Label>
                                        <select
                                            name="branch_id"
                                            className={selectClass}
                                            defaultValue={
                                                application.branch_id ?? ''
                                            }
                                        >
                                            <option value="">
                                                Not assigned
                                            </option>
                                            {branches.map((item) => (
                                                <option
                                                    key={item.id}
                                                    value={item.id}
                                                >
                                                    {item.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Review notes</Label>
                                    <textarea
                                        name="review_notes"
                                        defaultValue={
                                            application.review_notes ?? ''
                                        }
                                        rows={3}
                                        maxLength={2000}
                                        className="rounded-md border bg-background px-3 py-2 text-sm"
                                    />
                                    <InputError message={errors.review_notes} />
                                    <InputError message={errors.decision} />
                                </div>
                                <DialogFooter className="flex-wrap">
                                    {application.status === 'submitted' && (
                                        <Button
                                            type="submit"
                                            name="decision"
                                            value="review"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            Start review
                                        </Button>
                                    )}
                                    {['submitted', 'under_review'].includes(
                                        application.status,
                                    ) && (
                                        <Button
                                            type="submit"
                                            name="decision"
                                            value="accept"
                                            disabled={processing}
                                        >
                                            Accept
                                        </Button>
                                    )}
                                    {application.status === 'accepted' && (
                                        <Button
                                            type="submit"
                                            name="decision"
                                            value="enrol"
                                            disabled={processing}
                                        >
                                            Create player profile
                                        </Button>
                                    )}
                                    <Button
                                        type="submit"
                                        name="decision"
                                        value="reject"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        Reject
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}

export default function Applications({
    applications,
    programmes,
    branches,
    countries,
    programme_cycles,
    active_cycles,
    custom_fields,
    filters,
}: {
    applications: Paginator;
    programmes: Option[];
    branches: Option[];
    countries: string[];
    programme_cycles: ProgrammeCycleOption[];
    active_cycles: Option[];
    custom_fields: CustomField[];
    filters: { search: string; status: string };
}) {
    const terminology = useAcademyTerminology();
    return (
        <>
            <Head title="Applications" />
            <main className="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
                <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Admissions
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold">
                            Applications and prospects
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Review prospects before creating active{' '}
                            {terminology.singularLower} profiles and programme
                            enrolments.
                        </p>
                    </div>
                    <ApplicationDialog
                        programmes={programmes}
                        branches={branches}
                        customFields={custom_fields}
                        countries={countries}
                        programmeCycles={programme_cycles}
                        activeCycles={active_cycles}
                    />
                </div>
                <Form
                    action="/app/applications"
                    method="get"
                    className="mb-5 flex flex-wrap items-end gap-2"
                >
                    {() => (
                        <>
                            <div className="grid min-w-64 gap-1">
                                <Label>Search</Label>
                                <Input
                                    type="search"
                                    name="search"
                                    defaultValue={filters.search}
                                    placeholder="Name, reference, email or phone"
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label>Status</Label>
                                <select
                                    name="status"
                                    defaultValue={filters.status}
                                    className={selectClass}
                                >
                                    <option value="all">All statuses</option>
                                    {Object.entries(statusLabel).map(
                                        ([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </div>
                            <Button type="submit" variant="outline">
                                Filter
                            </Button>
                        </>
                    )}
                </Form>
                <Card className="overflow-hidden py-0">
                    <CardContent className="p-0">
                        {applications.data.length === 0 ? (
                            <div className="flex flex-col items-center py-14 text-center">
                                <ClipboardList className="size-9 text-muted-foreground" />
                                <h2 className="mt-4 font-semibold">
                                    No applications found
                                </h2>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Create a prospect or change the current
                                    filters.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[960px] text-left text-sm">
                                    <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Applicant
                                            </th>
                                            <th className="px-4 py-3">
                                                Guardian
                                            </th>
                                            <th className="px-4 py-3">
                                                Programme
                                            </th>
                                            <th className="px-4 py-3">
                                                Submitted
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
                                        {applications.data.map(
                                            (application) => (
                                                <tr
                                                    key={application.id}
                                                    className="border-b last:border-0"
                                                >
                                                    <td className="px-4 py-3">
                                                        <p className="font-medium">
                                                            {
                                                                application.first_name
                                                            }{' '}
                                                            {
                                                                application.middle_name
                                                            }{' '}
                                                            {
                                                                application.last_name
                                                            }
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {
                                                                application.reference
                                                            }
                                                            {application.source ===
                                                                'api' &&
                                                                ' · API'}
                                                        </p>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <p>
                                                            {application.guardian_name ||
                                                                'Not supplied'}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {application.guardian_phone ??
                                                                application.guardian_email}
                                                        </p>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <p>
                                                            {application.programme_name ??
                                                                'Not selected'}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {application.branch_name ??
                                                                'No branch'}
                                                        </p>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {application.created_at}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge
                                                            variant={
                                                                application.status ===
                                                                'rejected'
                                                                    ? 'destructive'
                                                                    : application.status ===
                                                                        'enrolled'
                                                                      ? 'default'
                                                                      : 'secondary'
                                                            }
                                                        >
                                                            {
                                                                statusLabel[
                                                                    application
                                                                        .status
                                                                ]
                                                            }
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <Button
                                                            asChild
                                                            variant="outline"
                                                            size="sm"
                                                        >
                                                            <Link
                                                                href={`/app/applications/${application.id}`}
                                                            >
                                                                <Eye className="size-4" />
                                                                Details
                                                            </Link>
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {applications.last_page > 1 && (
                            <div className="flex items-center justify-between border-t px-4 py-3 text-sm">
                                <p>
                                    Showing {applications.from}–
                                    {applications.to} of {applications.total}
                                </p>
                                <div className="flex gap-2">
                                    {applications.prev_page_url && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                href={
                                                    applications.prev_page_url
                                                }
                                            >
                                                Previous
                                            </Link>
                                        </Button>
                                    )}
                                    {applications.next_page_url && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                href={
                                                    applications.next_page_url
                                                }
                                            >
                                                Next
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
