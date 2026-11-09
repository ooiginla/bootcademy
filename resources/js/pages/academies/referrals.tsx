import { Head, router, useForm } from '@inertiajs/react';
import { BadgeCheck, Gift, Plus, RefreshCw, Share2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/money';

type Option = { id: number; name: string };
type Referral = {
    id: number;
    referrer_name: string;
    referred_name: string;
    application_reference: string | null;
    status: string;
    source: string;
    notes: string | null;
    attributed_at: string;
    reward_count: number;
};
type Reward = {
    id: number;
    referrer_name: string;
    referred_name: string;
    status: string;
    currency: string;
    qualifying_amount: string;
    reward_amount: string;
    payment_reference: string | null;
    invoice_number: string | null;
    earned_at: string;
    payout_reference: string | null;
};
type Settings = {
    is_enabled: boolean;
    qualifying_event: string;
    reward_type: string;
    reward_value: string;
    eligible_payment_scope: string;
    currency: string;
};
const tabs = ['Referrals', 'Rewards', 'Settings'] as const;

export default function Referrals({
    referrals,
    rewards,
    settings,
    players,
    applications,
    can_configure,
    can_manage_rewards,
}: {
    referrals: Referral[];
    rewards: Reward[];
    settings: Settings;
    players: Option[];
    applications: Option[];
    can_configure: boolean;
    can_manage_rewards: boolean;
}) {
    const [tab, setTab] = useState<(typeof tabs)[number]>('Referrals');
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [paidReward, setPaidReward] = useState<Reward | null>(null);
    const referral = useForm({
        referrer_player_id: '',
        referred_type: 'application',
        referred_id: '',
        notes: '',
    });
    const config = useForm({
        is_enabled: settings.is_enabled,
        qualifying_event: settings.qualifying_event,
        reward_type: settings.reward_type,
        reward_value: settings.reward_value,
        eligible_payment_scope: settings.eligible_payment_scope,
    });
    const payout = useForm({ payout_reference: '', payout_notes: '' });
    const referredOptions =
        referral.data.referred_type === 'application' ? applications : players;
    const filtered = useMemo(
        () =>
            referredOptions
                .filter((x) =>
                    x.name.toLowerCase().includes(search.toLowerCase()),
                )
                .slice(0, 100),
        [referredOptions, search],
    );
    const submitReferral = (e: React.FormEvent) => {
        e.preventDefault();
        referral.post('/app/referrals', {
            onSuccess: () => {
                setOpen(false);
                referral.reset();
                setSearch('');
            },
        });
    };
    const saveConfig = (e: React.FormEvent) => {
        e.preventDefault();
        config.patch('/app/referrals/settings');
    };
    const savePayout = (e: React.FormEvent) => {
        e.preventDefault();
        if (!paidReward) return;
        payout.patch(`/app/referrals/rewards/${paidReward.id}/paid`, {
            onSuccess: () => {
                setPaidReward(null);
                payout.reset();
            },
        });
    };
    return (
        <>
            <Head title="Referrals" />
            <main className="mx-auto w-full max-w-7xl min-w-0 space-y-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Growth
                        </p>
                        <h1 className="text-2xl font-bold">
                            Referrals and rewards
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Attribute referrals, evaluate configured
                            qualification rules and retain an auditable reward
                            ledger.
                        </p>
                    </div>
                    {can_configure && (
                        <Dialog open={open} onOpenChange={setOpen}>
                            <DialogTrigger asChild>
                                <Button>
                                    <Plus className="size-4" />
                                    Add referral
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>
                                        Add referral attribution
                                    </DialogTitle>
                                </DialogHeader>
                                <form
                                    onSubmit={submitReferral}
                                    className="space-y-4"
                                >
                                    <div>
                                        <Label>Referrer</Label>
                                        <select
                                            className="h-10 w-full rounded-md border bg-background px-3"
                                            value={
                                                referral.data.referrer_player_id
                                            }
                                            onChange={(e) =>
                                                referral.setData(
                                                    'referrer_player_id',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                        >
                                            <option value="">
                                                Select existing student/player
                                            </option>
                                            {players.map((x) => (
                                                <option key={x.id} value={x.id}>
                                                    {x.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <Label>Person referred</Label>
                                        <div className="mb-2 flex gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant={
                                                    referral.data
                                                        .referred_type ===
                                                    'application'
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                onClick={() => {
                                                    referral.setData(
                                                        'referred_type',
                                                        'application',
                                                    );
                                                    referral.setData(
                                                        'referred_id',
                                                        '',
                                                    );
                                                }}
                                            >
                                                Applicant
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant={
                                                    referral.data
                                                        .referred_type ===
                                                    'player'
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                onClick={() => {
                                                    referral.setData(
                                                        'referred_type',
                                                        'player',
                                                    );
                                                    referral.setData(
                                                        'referred_id',
                                                        '',
                                                    );
                                                }}
                                            >
                                                Existing student/player
                                            </Button>
                                        </div>
                                        <Input
                                            placeholder="Search by name or application reference"
                                            value={search}
                                            onChange={(e) =>
                                                setSearch(e.target.value)
                                            }
                                        />
                                        <select
                                            className="mt-2 h-10 w-full rounded-md border bg-background px-3"
                                            value={referral.data.referred_id}
                                            onChange={(e) =>
                                                referral.setData(
                                                    'referred_id',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                        >
                                            <option value="">
                                                Select result
                                            </option>
                                            {filtered.map((x) => (
                                                <option key={x.id} value={x.id}>
                                                    {x.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <Label>Notes</Label>
                                        <textarea
                                            className="min-h-24 w-full rounded-md border bg-background p-3 text-sm"
                                            value={referral.data.notes}
                                            onChange={(e) =>
                                                referral.setData(
                                                    'notes',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        disabled={referral.processing}
                                    >
                                        Save referral
                                    </Button>
                                </form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>
                <div className="flex w-full min-w-0 gap-2 overflow-x-auto border-b">
                    {tabs.map((x) => (
                        <button
                            key={x}
                            onClick={() => setTab(x)}
                            className={`border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap ${tab === x ? 'border-primary text-primary' : 'border-transparent text-muted-foreground'}`}
                        >
                            {x}
                        </button>
                    ))}
                </div>
                {tab === 'Referrals' && (
                    <Table
                        headers={[
                            'Referrer',
                            'Referred person',
                            'Attributed',
                            'Status',
                            'Rewards',
                        ]}
                        empty="No referrals have been attributed."
                        rows={referrals.map((x) => [
                            x.referrer_name,
                            <span>
                                {x.referred_name}
                                {x.application_reference && (
                                    <small className="block text-muted-foreground">
                                        {x.application_reference}
                                    </small>
                                )}
                            </span>,
                            new Date(x.attributed_at).toLocaleDateString(),
                            <Status value={x.status} />,
                            String(x.reward_count),
                        ])}
                    />
                )}
                {tab === 'Rewards' && (
                    <div className="space-y-4">
                        <div className="flex justify-end">
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post('/app/referrals/reconcile')
                                }
                            >
                                <RefreshCw className="size-4" />
                                Evaluate rewards
                            </Button>
                        </div>
                        <Table
                            headers={[
                                'Referrer / referred',
                                'Qualifying event',
                                'Reward',
                                'Earned',
                                'Status',
                                '',
                            ]}
                            empty="No rewards have been earned under the configured rule."
                            rows={rewards.map((x) => [
                                <span>
                                    <strong>{x.referrer_name}</strong>
                                    <small className="block text-muted-foreground">
                                        Referred {x.referred_name}
                                    </small>
                                </span>,
                                <span>
                                    {x.payment_reference ??
                                        x.invoice_number ??
                                        'Enrollment'}
                                    {Number(x.qualifying_amount) > 0 && (
                                        <small className="block text-muted-foreground">
                                            Base{' '}
                                            {formatCurrency(
                                                x.currency,
                                                x.qualifying_amount,
                                            )}
                                        </small>
                                    )}
                                </span>,
                                <strong>
                                    {formatCurrency(
                                        x.currency,
                                        x.reward_amount,
                                    )}
                                </strong>,
                                new Date(x.earned_at).toLocaleDateString(),
                                <Status value={x.status} />,
                                can_manage_rewards ? (
                                    <div className="flex justify-end gap-2">
                                        {x.status === 'pending' && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    router.patch(
                                                        `/app/referrals/rewards/${x.id}/approve`,
                                                    )
                                                }
                                            >
                                                <BadgeCheck className="size-4" />
                                                Approve
                                            </Button>
                                        )}
                                        {x.status === 'approved' && (
                                            <Button
                                                size="sm"
                                                onClick={() => setPaidReward(x)}
                                            >
                                                Record payout
                                            </Button>
                                        )}
                                    </div>
                                ) : null,
                            ])}
                        />
                    </div>
                )}
                {tab === 'Settings' && (
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Gift className="size-5" />
                                <h2 className="font-semibold">Reward rule</h2>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                Rules apply prospectively when rewards are
                                evaluated. Existing ledger amounts are never
                                rewritten.
                            </p>
                        </CardHeader>
                        <CardContent>
                            {can_configure ? (
                                <form
                                    onSubmit={saveConfig}
                                    className="max-w-2xl space-y-5"
                                >
                                    <label className="flex items-start gap-3 rounded-xl border p-4">
                                        <input
                                            type="checkbox"
                                            className="mt-1 size-4"
                                            checked={config.data.is_enabled}
                                            onChange={(e) =>
                                                config.setData(
                                                    'is_enabled',
                                                    e.target.checked,
                                                )
                                            }
                                        />
                                        <span>
                                            <strong className="block text-sm">
                                                Enable referral rewards
                                            </strong>
                                            <span className="text-sm text-muted-foreground">
                                                Attribution can still be
                                                recorded while rewards are
                                                disabled.
                                            </span>
                                        </span>
                                    </label>
                                    <div>
                                        <Label>Qualifying event</Label>
                                        <select
                                            className="h-10 w-full rounded-md border bg-background px-3"
                                            value={config.data.qualifying_event}
                                            onChange={(e) =>
                                                config.setData(
                                                    'qualifying_event',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <option value="first_verified_payment">
                                                First verified payment
                                            </option>
                                            <option value="programme_invoice_paid">
                                                Programme invoice fully paid
                                            </option>
                                            <option value="enrollment">
                                                Enrollment completed
                                            </option>
                                        </select>
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <Label>Reward type</Label>
                                            <select
                                                className="h-10 w-full rounded-md border bg-background px-3"
                                                value={config.data.reward_type}
                                                onChange={(e) =>
                                                    config.setData(
                                                        'reward_type',
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                <option value="percentage">
                                                    Percentage of qualifying
                                                    amount
                                                </option>
                                                <option value="fixed">
                                                    Fixed amount
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <Label>
                                                {config.data.reward_type ===
                                                'percentage'
                                                    ? 'Percentage'
                                                    : 'Amount (' +
                                                      settings.currency +
                                                      ')'}
                                            </Label>
                                            <Input
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                value={config.data.reward_value}
                                                onChange={(e) =>
                                                    config.setData(
                                                        'reward_value',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            />
                                        </div>
                                    </div>
                                    {config.data.qualifying_event !==
                                        'enrollment' && (
                                        <div>
                                            <Label>Eligible payments</Label>
                                            <select
                                                className="h-10 w-full rounded-md border bg-background px-3"
                                                value={
                                                    config.data
                                                        .eligible_payment_scope
                                                }
                                                onChange={(e) =>
                                                    config.setData(
                                                        'eligible_payment_scope',
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                <option value="programme">
                                                    Programme fees only
                                                </option>
                                                <option value="all">
                                                    All verified academy fees
                                                </option>
                                            </select>
                                        </div>
                                    )}
                                    <Button
                                        type="submit"
                                        disabled={config.processing}
                                    >
                                        Save reward rule
                                    </Button>
                                </form>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Only an academy Owner/Admin can change the
                                    referral rule.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}
            </main>
            <Dialog
                open={paidReward !== null}
                onOpenChange={(value) => !value && setPaidReward(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Record reward payout</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={savePayout} className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            Record the external transfer or cash reference. This
                            does not initiate a bank payment.
                        </p>
                        <div>
                            <Label>Payout reference</Label>
                            <Input
                                value={payout.data.payout_reference}
                                onChange={(e) =>
                                    payout.setData(
                                        'payout_reference',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                        </div>
                        <div>
                            <Label>Notes</Label>
                            <textarea
                                className="min-h-24 w-full rounded-md border bg-background p-3"
                                value={payout.data.payout_notes}
                                onChange={(e) =>
                                    payout.setData(
                                        'payout_notes',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <Button type="submit" disabled={payout.processing}>
                            Mark as paid
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
function Status({ value }: { value: string }) {
    const tone =
        value === 'paid'
            ? 'bg-emerald-100 text-emerald-800'
            : value === 'approved' || value === 'qualified'
              ? 'bg-blue-100 text-blue-800'
              : 'bg-amber-100 text-amber-800';
    return (
        <span
            className={`rounded-full px-2 py-1 text-xs font-medium capitalize ${tone}`}
        >
            {value.replaceAll('_', ' ')}
        </span>
    );
}
function Table({
    headers,
    rows,
    empty,
}: {
    headers: string[];
    rows: React.ReactNode[][];
    empty: string;
}) {
    return (
        <Card>
            <CardContent className="p-0">
                {rows.length === 0 ? (
                    <div className="py-14 text-center text-sm text-muted-foreground">
                        <Share2 className="mx-auto mb-3 size-8" />
                        {empty}
                    </div>
                ) : (
                    <div className="w-full min-w-0 overflow-x-auto">
                        <table className="w-full min-w-max text-sm">
                            <thead className="border-b bg-muted/40 text-left">
                                <tr>
                                    {headers.map((x) => (
                                        <th
                                            key={x}
                                            className="px-4 py-3 font-medium"
                                        >
                                            {x}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {rows.map((row, i) => (
                                    <tr key={i}>
                                        {row.map((cell, j) => (
                                            <td
                                                key={j}
                                                className="px-4 py-3 align-top"
                                            >
                                                {cell}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
