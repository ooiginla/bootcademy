import { useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Landmark } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type BankDetails = {
    bank_code: string | null;
    bank_name: string | null;
    account_name: string | null;
    account_number: string | null;
};

export type Settlement = {
    status: string;
    subaccount_code: string | null;
    error: string | null;
    synced_at: string | null;
    accepts_online_payments: boolean;
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

const statusLabels: Record<string, string> = {
    active: 'Active',
    inactive: 'Inactive',
    failed: 'Not accepted',
    none: 'Not set up',
};

export function SettlementPanel({ settlement }: { settlement: Settlement }) {
    const active = settlement.status === 'active';

    return (
        <div
            className={`rounded-lg border p-4 text-sm ${active ? 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-900 dark:bg-emerald-950/30' : 'bg-muted/40'}`}
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="flex items-center gap-2 font-medium">
                    {active ? (
                        <CheckCircle2 className="size-4 text-emerald-600" />
                    ) : (
                        <AlertTriangle className="size-4 text-amber-600" />
                    )}
                    Paystack settlement account
                </p>
                <Badge variant={active ? 'default' : 'outline'}>
                    {statusLabels[settlement.status] ?? settlement.status}
                </Badge>
            </div>
            <dl className="mt-3 grid gap-x-6 gap-y-2 sm:grid-cols-2">
                <div>
                    <dt className="text-xs text-muted-foreground">
                        Subaccount code
                    </dt>
                    <dd className="font-mono text-xs">
                        {settlement.subaccount_code ?? '—'}
                    </dd>
                </div>
                <div>
                    <dt className="text-xs text-muted-foreground">
                        Last updated with Paystack
                    </dt>
                    <dd className="text-xs">
                        {settlement.synced_at
                            ? new Date(settlement.synced_at).toLocaleString()
                            : '—'}
                    </dd>
                </div>
            </dl>
            <p className="mt-3 text-xs text-muted-foreground">
                {active
                    ? 'Online payments from parents settle straight to this account. Paystack pays out on its normal settlement schedule.'
                    : 'Online payments are off until Paystack accepts this academy’s settlement account. Parents can still pay by bank transfer.'}
            </p>
            {settlement.error && (
                <p className="mt-2 text-xs text-destructive" role="alert">
                    {settlement.error}
                </p>
            )}
        </div>
    );
}

export default function BankDetailsCard({
    academyName,
    bankDetails,
    settlement,
    usesPaystack,
    banks,
}: {
    academyName: string;
    bankDetails: BankDetails;
    settlement: Settlement;
    usesPaystack: boolean;
    banks: Array<{ code: string; name: string }>;
}) {
    const form = useForm({
        bank_code: bankDetails.bank_code ?? '',
        bank_name: bankDetails.bank_name ?? '',
        bank_account_name: bankDetails.account_name ?? '',
        bank_account_number: bankDetails.account_number ?? '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.patch('/app/settings/academy/bank-details', {
            preserveScroll: true,
        });
    }

    return (
        <Card className="xl:col-span-2">
            <CardHeader>
                <div className="flex items-center gap-2">
                    <Landmark className="size-5 text-primary" />
                    <h2 className="font-semibold">Bank details</h2>
                </div>
                <p className="text-sm text-muted-foreground">
                    {usesPaystack
                        ? 'Online payments from parents settle to this account, and it is shown in bank-transfer instructions. The account name is confirmed with the bank when you save.'
                        : 'Set the academy account used in bank-transfer payment instructions. All three fields are required when bank details are configured.'}
                </p>
            </CardHeader>
            <CardContent className="space-y-5">
                <form onSubmit={submit} className="space-y-5">
                    {usesPaystack ? (
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="bank_code">Bank</Label>
                                <select
                                    id="bank_code"
                                    className={selectClass}
                                    value={form.data.bank_code}
                                    onChange={(event) =>
                                        form.setData(
                                            'bank_code',
                                            event.target.value,
                                        )
                                    }
                                    disabled={banks.length === 0}
                                >
                                    <option value="">
                                        {banks.length === 0
                                            ? 'Bank list unavailable'
                                            : 'Select bank'}
                                    </option>
                                    {banks.map((bank) => (
                                        <option
                                            key={bank.code}
                                            value={bank.code}
                                        >
                                            {bank.name}
                                        </option>
                                    ))}
                                </select>
                                {banks.length === 0 && (
                                    <p className="text-xs text-muted-foreground">
                                        Paystack’s bank list could not be
                                        loaded. Refresh the page to try again.
                                    </p>
                                )}
                                <InputError message={form.errors.bank_code} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="bank_account_number">
                                    Account number
                                </Label>
                                <Input
                                    id="bank_account_number"
                                    value={form.data.bank_account_number}
                                    onChange={(event) =>
                                        form.setData(
                                            'bank_account_number',
                                            event.target.value.replace(
                                                /\D/g,
                                                '',
                                            ),
                                        )
                                    }
                                    maxLength={10}
                                    inputMode="numeric"
                                    autoComplete="off"
                                    placeholder="0123456789"
                                />
                                <InputError
                                    message={form.errors.bank_account_number}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="bank_account_name">
                                    Account name
                                </Label>
                                <Input
                                    id="bank_account_name"
                                    value={
                                        bankDetails.account_name ??
                                        'Confirmed when you save'
                                    }
                                    readOnly
                                    className="bg-muted/50"
                                />
                            </div>
                        </div>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="bank_name">Bank name</Label>
                                <Input
                                    id="bank_name"
                                    value={form.data.bank_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'bank_name',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={120}
                                    placeholder="Example Bank"
                                />
                                <InputError message={form.errors.bank_name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="bank_account_name">
                                    Account name
                                </Label>
                                <Input
                                    id="bank_account_name"
                                    value={form.data.bank_account_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'bank_account_name',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={160}
                                    placeholder={academyName}
                                />
                                <InputError
                                    message={form.errors.bank_account_name}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="bank_account_number">
                                    Account number
                                </Label>
                                <Input
                                    id="bank_account_number"
                                    value={form.data.bank_account_number}
                                    onChange={(event) =>
                                        form.setData(
                                            'bank_account_number',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={64}
                                    inputMode="numeric"
                                    autoComplete="off"
                                    placeholder="0123456789"
                                />
                                <InputError
                                    message={form.errors.bank_account_number}
                                />
                            </div>
                        </div>
                    )}
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
                        {form.processing
                            ? usesPaystack
                                ? 'Confirming with the bank…'
                                : 'Saving…'
                            : 'Save bank details'}
                    </Button>
                </form>
                {usesPaystack && <SettlementPanel settlement={settlement} />}
            </CardContent>
        </Card>
    );
}
