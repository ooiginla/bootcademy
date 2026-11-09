import { Form, Head, Link } from '@inertiajs/react';
import {
    Paperclip,
    Pencil,
    Plus,
    Download,
    Store,
    Tags,
    Trash2,
    WalletCards,
} from 'lucide-react';
import { useState } from 'react';
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
import { formatCurrency, formatMoneyInput } from '@/lib/money';

type Option = { id: number; name: string };
type Category = Option & { is_active: boolean; expenses_count: number };
type Vendor = Option & {
    email: string | null;
    phone: string | null;
    address: string | null;
    expenses_count: number;
};
type Attachment = { id: number; name: string; size: number };
type Expense = {
    id: number;
    incurred_on: string;
    category: string;
    description: string;
    vendor: string | null;
    reference: string | null;
    branch_name: string | null;
    cycle_name: string | null;
    currency: string;
    amount: string;
    source: 'manual' | 'write_off' | 'referral_reward';
    attachments: Attachment[];
};
type Paginator = {
    data: Expense[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm';

function CategoryDialog({ category }: { category?: Category }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size={category ? 'sm' : 'default'}>
                    {category ? (
                        <Pencil className="size-4" />
                    ) : (
                        <Tags className="size-4" />
                    )}
                    {category ? 'Edit' : 'Add category'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {category
                            ? 'Edit expense category'
                            : 'Add expense category'}
                    </DialogTitle>
                    <DialogDescription>
                        Categories are academy-specific and make expense reports
                        easier to understand.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={
                        category
                            ? `/app/finance/expense-categories/${category.id}`
                            : '/app/finance/expense-categories'
                    }
                    method={category ? 'patch' : 'post'}
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="category-name">Name</Label>
                                <Input
                                    id="category-name"
                                    name="name"
                                    maxLength={80}
                                    placeholder="e.g. Equipment"
                                    defaultValue={category?.name ?? ''}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {category
                                        ? 'Save changes'
                                        : 'Create category'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function VendorDialog({ vendor }: { vendor?: Vendor }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={vendor ? 'outline' : 'default'}
                    size={vendor ? 'sm' : 'default'}
                >
                    {vendor ? (
                        <Pencil className="size-4" />
                    ) : (
                        <Plus className="size-4" />
                    )}
                    {vendor ? 'Edit' : 'Add vendor'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {vendor ? 'Edit vendor' : 'Add vendor'}
                    </DialogTitle>
                    <DialogDescription>
                        Save frequently used payees for faster expense capture.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={
                        vendor
                            ? `/app/finance/expense-vendors/${vendor.id}`
                            : '/app/finance/expense-vendors'
                    }
                    method={vendor ? 'patch' : 'post'}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label
                                        htmlFor={`vendor-name-${vendor?.id ?? 'new'}`}
                                    >
                                        Name
                                    </Label>
                                    <Input
                                        id={`vendor-name-${vendor?.id ?? 'new'}`}
                                        name="name"
                                        defaultValue={vendor?.name ?? ''}
                                        maxLength={160}
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Email</Label>
                                    <Input
                                        name="email"
                                        type="email"
                                        defaultValue={vendor?.email ?? ''}
                                    />
                                    <InputError message={errors.email} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Phone</Label>
                                    <Input
                                        name="phone"
                                        defaultValue={vendor?.phone ?? ''}
                                        maxLength={40}
                                    />
                                    <InputError message={errors.phone} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label>Address</Label>
                                    <Input
                                        name="address"
                                        defaultValue={vendor?.address ?? ''}
                                        maxLength={255}
                                    />
                                    <InputError message={errors.address} />
                                </div>
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {vendor ? 'Save changes' : 'Create vendor'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function AddExpenseDialog({
    categories,
    vendors,
    branches,
    cycles,
    today,
}: {
    categories: Category[];
    vendors: Vendor[];
    branches: Option[];
    cycles: Option[];
    today: string;
}) {
    const [open, setOpen] = useState(false);
    const [amount, setAmount] = useState('');
    const activeCategories = categories.filter(
        (category) => category.is_active,
    );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button disabled={activeCategories.length === 0}>
                    <Plus className="size-4" /> Record expense
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Record expense</DialogTitle>
                    <DialogDescription>
                        Record an academy cost and optionally attach private
                        receipts or supporting documents.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action="/app/finance/expenses"
                    method="post"
                    resetOnSuccess
                    onSuccess={() => {
                        setAmount('');
                        setOpen(false);
                    }}
                    onError={() => setOpen(true)}
                    className="space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="expense-category">
                                        Category
                                    </Label>
                                    <select
                                        id="expense-category"
                                        name="expense_category_id"
                                        className={selectClass}
                                        required
                                        defaultValue=""
                                    >
                                        <option value="" disabled>
                                            Select category
                                        </option>
                                        {activeCategories.map((category) => (
                                            <option
                                                key={category.id}
                                                value={category.id}
                                            >
                                                {category.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.expense_category_id}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="expense-date">Date</Label>
                                    <Input
                                        id="expense-date"
                                        type="date"
                                        name="incurred_on"
                                        defaultValue={today}
                                        max={today}
                                        required
                                    />
                                    <InputError message={errors.incurred_on} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="expense-description">
                                        Description
                                    </Label>
                                    <Input
                                        id="expense-description"
                                        name="description"
                                        maxLength={200}
                                        placeholder="What was this expense for?"
                                        required
                                    />
                                    <InputError message={errors.description} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="expense-amount">
                                        Amount
                                    </Label>
                                    <Input
                                        id="expense-amount"
                                        name="amount"
                                        inputMode="decimal"
                                        value={amount}
                                        onChange={(event) =>
                                            setAmount(
                                                formatMoneyInput(
                                                    event.target.value,
                                                ),
                                            )
                                        }
                                        placeholder="0.00"
                                        required
                                    />
                                    <InputError message={errors.amount} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="expense-saved-vendor">
                                        Saved vendor (optional)
                                    </Label>
                                    <select
                                        id="expense-saved-vendor"
                                        name="expense_vendor_id"
                                        className={selectClass}
                                        defaultValue=""
                                    >
                                        <option value="">
                                            No saved vendor
                                        </option>
                                        {vendors.map((vendor) => (
                                            <option
                                                key={vendor.id}
                                                value={vendor.id}
                                            >
                                                {vendor.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.expense_vendor_id}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="expense-vendor">
                                        One-off vendor name (optional)
                                    </Label>
                                    <Input
                                        id="expense-vendor"
                                        name="vendor"
                                        maxLength={160}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        A typed name overrides the saved vendor
                                        for this expense only.
                                    </p>
                                    <InputError message={errors.vendor} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="expense-branch">
                                        Branch (optional)
                                    </Label>
                                    <select
                                        id="expense-branch"
                                        name="branch_id"
                                        className={selectClass}
                                        defaultValue=""
                                    >
                                        <option value="">Academy-wide</option>
                                        {branches.map((branch) => (
                                            <option
                                                key={branch.id}
                                                value={branch.id}
                                            >
                                                {branch.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.branch_id} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="expense-cycle">
                                        Cycle (optional)
                                    </Label>
                                    <select
                                        id="expense-cycle"
                                        name="academy_cycle_id"
                                        className={selectClass}
                                        defaultValue=""
                                    >
                                        <option value="">No cycle</option>
                                        {cycles.map((cycle) => (
                                            <option
                                                key={cycle.id}
                                                value={cycle.id}
                                            >
                                                {cycle.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.academy_cycle_id}
                                    />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="expense-reference">
                                        Reference (optional)
                                    </Label>
                                    <Input
                                        id="expense-reference"
                                        name="reference"
                                        maxLength={120}
                                        placeholder="Receipt, transfer or cheque reference"
                                    />
                                    <InputError message={errors.reference} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="expense-attachments">
                                        Attachments (optional)
                                    </Label>
                                    <Input
                                        id="expense-attachments"
                                        name="attachments[]"
                                        type="file"
                                        multiple
                                        accept=".pdf,.jpg,.jpeg,.png,.webp"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Up to 5 private PDF or image files, 5 MB
                                        each.
                                    </p>
                                    <InputError message={errors.attachments} />
                                    <InputError
                                        message={errors['attachments.0']}
                                    />
                                </div>
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}Record expense
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function Expenses({
    expenses,
    summary,
    categories,
    vendors,
    branches,
    cycles,
    filters,
    today,
    active_tab,
}: {
    expenses: Paginator;
    summary: {
        count: number;
        total: string;
        manual: string;
        write_offs: string;
        referral_rewards: string;
        currency: string;
    };
    categories: Category[];
    vendors: Vendor[];
    branches: Option[];
    cycles: Option[];
    filters: {
        category_id: number | null;
        branch_id: number | null;
        cycle_id: number | null;
        date_from: string | null;
        date_to: string | null;
    };
    today: string;
    active_tab: 'expenses' | 'categories' | 'vendors';
}) {
    return (
        <>
            <Head title="Expenses" />
            <main className="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
                <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Academy finance
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold">
                            Expenses
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Track academy costs, invoice write-offs and private
                            supporting documents.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {active_tab === 'expenses' && (
                            <>
                                <Button asChild variant="outline">
                                    <a href="/app/reports/expenses.csv">
                                        <Download className="size-4" />
                                        Export CSV
                                    </a>
                                </Button>
                                <AddExpenseDialog
                                    categories={categories}
                                    vendors={vendors}
                                    branches={branches}
                                    cycles={cycles}
                                    today={today}
                                />
                            </>
                        )}
                        {active_tab === 'categories' && <CategoryDialog />}
                        {active_tab === 'vendors' && <VendorDialog />}
                    </div>
                </div>

                <div className="mb-6 flex gap-1 border-b">
                    {[
                        ['expenses', 'Expenses'],
                        ['categories', 'Categories'],
                        ['vendors', 'Vendors'],
                    ].map(([value, label]) => (
                        <Button
                            key={value}
                            asChild
                            variant="ghost"
                            className={`rounded-b-none border-b-2 ${active_tab === value ? 'border-primary text-primary' : 'border-transparent'}`}
                        >
                            <Link
                                href={
                                    value === 'expenses'
                                        ? '/app/finance/expenses'
                                        : `/app/finance/expenses?tab=${value}`
                                }
                            >
                                {label}
                            </Link>
                        </Button>
                    ))}
                </div>

                {active_tab === 'expenses' && (
                    <>
                        {categories.length === 0 && (
                            <div className="mb-5 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                                Create at least one expense category before
                                recording a manual expense.
                            </div>
                        )}

                        <div className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                            {[
                                [
                                    'Total expenses',
                                    summary.total,
                                    'bg-violet-50',
                                ],
                                [
                                    'Manual expenses',
                                    summary.manual,
                                    'bg-blue-50',
                                ],
                                [
                                    'Invoice write-offs',
                                    summary.write_offs,
                                    'bg-red-50',
                                ],
                                [
                                    'Referral rewards',
                                    summary.referral_rewards,
                                    'bg-amber-50',
                                ],
                                [
                                    'Records',
                                    String(summary.count),
                                    'bg-emerald-50',
                                ],
                            ].map(([label, value, colour], index) => (
                                <Card key={label} className={colour}>
                                    <CardContent className="p-4">
                                        <p className="text-xs text-muted-foreground">
                                            {label}
                                        </p>
                                        <p className="mt-1 text-xl font-semibold">
                                            {index < 4
                                                ? formatCurrency(
                                                      summary.currency,
                                                      value,
                                                  )
                                                : value}
                                        </p>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        <Form
                            action="/app/finance/expenses"
                            method="get"
                            className="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-6 lg:items-end"
                        >
                            {() => (
                                <>
                                    <div className="grid gap-1">
                                        <Label>Category</Label>
                                        <select
                                            name="category_id"
                                            className={selectClass}
                                            defaultValue={
                                                filters.category_id ?? ''
                                            }
                                        >
                                            <option value="">
                                                All categories
                                            </option>
                                            {categories.map((category) => (
                                                <option
                                                    key={category.id}
                                                    value={category.id}
                                                >
                                                    {category.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="grid gap-1">
                                        <Label>Branch</Label>
                                        <select
                                            name="branch_id"
                                            className={selectClass}
                                            defaultValue={
                                                filters.branch_id ?? ''
                                            }
                                        >
                                            <option value="">
                                                All branches
                                            </option>
                                            {branches.map((branch) => (
                                                <option
                                                    key={branch.id}
                                                    value={branch.id}
                                                >
                                                    {branch.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="grid gap-1">
                                        <Label>Cycle</Label>
                                        <select
                                            name="cycle_id"
                                            className={selectClass}
                                            defaultValue={
                                                filters.cycle_id ?? ''
                                            }
                                        >
                                            <option value="">All cycles</option>
                                            {cycles.map((cycle) => (
                                                <option
                                                    key={cycle.id}
                                                    value={cycle.id}
                                                >
                                                    {cycle.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="grid gap-1">
                                        <Label>From</Label>
                                        <Input
                                            type="date"
                                            name="date_from"
                                            defaultValue={
                                                filters.date_from ?? ''
                                            }
                                            max={today}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label>To</Label>
                                        <Input
                                            type="date"
                                            name="date_to"
                                            defaultValue={filters.date_to ?? ''}
                                            max={today}
                                        />
                                    </div>
                                    <Button type="submit" variant="outline">
                                        Apply filters
                                    </Button>
                                </>
                            )}
                        </Form>

                        <Card className="overflow-hidden py-0">
                            <CardContent className="p-0">
                                {expenses.data.length === 0 ? (
                                    <div className="py-14 text-center">
                                        <WalletCards className="mx-auto size-9 text-muted-foreground" />
                                        <h2 className="mt-4 font-semibold">
                                            No expenses found
                                        </h2>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            Recorded expenses and invoice
                                            write-offs will appear here.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full min-w-[1050px] text-left text-sm">
                                            <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                                <tr>
                                                    <th className="px-4 py-3">
                                                        Date
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Expense
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Category
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Branch
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Cycle
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Source
                                                    </th>
                                                    <th className="px-4 py-3">
                                                        Evidence
                                                    </th>
                                                    <th className="px-4 py-3 text-right">
                                                        Amount
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {expenses.data.map(
                                                    (expense) => (
                                                        <tr
                                                            key={expense.id}
                                                            className="border-b align-top last:border-0"
                                                        >
                                                            <td className="px-4 py-3">
                                                                {
                                                                    expense.incurred_on
                                                                }
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <p className="font-medium">
                                                                    {
                                                                        expense.description
                                                                    }
                                                                </p>
                                                                <p className="text-xs text-muted-foreground">
                                                                    {[
                                                                        expense.vendor,
                                                                        expense.reference,
                                                                    ]
                                                                        .filter(
                                                                            Boolean,
                                                                        )
                                                                        .join(
                                                                            ' · ',
                                                                        ) ||
                                                                        '—'}
                                                                </p>
                                                            </td>
                                                            <td className="px-4 py-3 capitalize">
                                                                {
                                                                    expense.category
                                                                }
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                {expense.branch_name ??
                                                                    'Academy-wide'}
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                {expense.cycle_name ??
                                                                    '—'}
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <Badge
                                                                    variant={
                                                                        expense.source ===
                                                                        'write_off'
                                                                            ? 'destructive'
                                                                            : 'secondary'
                                                                    }
                                                                >
                                                                    {expense.source ===
                                                                    'write_off'
                                                                        ? 'Write-off'
                                                                        : expense.source ===
                                                                            'referral_reward'
                                                                          ? 'Referral reward'
                                                                          : 'Manual'}
                                                                </Badge>
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                {expense
                                                                    .attachments
                                                                    .length ===
                                                                0 ? (
                                                                    '—'
                                                                ) : (
                                                                    <div className="grid gap-1">
                                                                        {expense.attachments.map(
                                                                            (
                                                                                attachment,
                                                                            ) => (
                                                                                <Link
                                                                                    key={
                                                                                        attachment.id
                                                                                    }
                                                                                    href={`/app/finance/expense-attachments/${attachment.id}`}
                                                                                    className="flex max-w-40 items-center gap-1 truncate text-primary hover:underline"
                                                                                >
                                                                                    <Paperclip className="size-3 shrink-0" />
                                                                                    <span className="truncate">
                                                                                        {
                                                                                            attachment.name
                                                                                        }
                                                                                    </span>
                                                                                </Link>
                                                                            ),
                                                                        )}
                                                                    </div>
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-3 text-right font-semibold">
                                                                {formatCurrency(
                                                                    expense.currency,
                                                                    expense.amount,
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                                {expenses.last_page > 1 && (
                                    <div className="flex items-center justify-between border-t px-4 py-3 text-sm">
                                        <p>
                                            Showing {expenses.from}–
                                            {expenses.to} of {expenses.total}
                                        </p>
                                        <div className="flex gap-2">
                                            {expenses.prev_page_url && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={
                                                            expenses.prev_page_url
                                                        }
                                                    >
                                                        Previous
                                                    </Link>
                                                </Button>
                                            )}
                                            {expenses.next_page_url && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={
                                                            expenses.next_page_url
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
                    </>
                )}

                {active_tab === 'categories' && (
                    <Card className="overflow-hidden py-0">
                        <CardContent className="p-0">
                            {categories.length === 0 ? (
                                <div className="py-14 text-center">
                                    <Tags className="mx-auto size-9 text-muted-foreground" />
                                    <h2 className="mt-4 font-semibold">
                                        No expense categories
                                    </h2>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        Create a category before recording
                                        manual expenses.
                                    </p>
                                </div>
                            ) : (
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Category
                                            </th>
                                            <th className="px-4 py-3">
                                                Expenses
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {categories.map((category) => (
                                            <tr
                                                key={category.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="px-4 py-3 font-medium">
                                                    {category.name}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {category.expenses_count}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex justify-end gap-2">
                                                        <CategoryDialog
                                                            category={category}
                                                        />
                                                        <Form
                                                            action={`/app/finance/expense-categories/${category.id}`}
                                                            method="delete"
                                                            onSubmit={(
                                                                event,
                                                            ) => {
                                                                if (
                                                                    !window.confirm(
                                                                        `Delete the ${category.name} category?`,
                                                                    )
                                                                ) {
                                                                    event.preventDefault();
                                                                }
                                                            }}
                                                        >
                                                            {({
                                                                processing,
                                                                errors,
                                                            }) => (
                                                                <div>
                                                                    <Button
                                                                        type="submit"
                                                                        variant="destructive"
                                                                        size="sm"
                                                                        disabled={
                                                                            processing ||
                                                                            category.expenses_count >
                                                                                0
                                                                        }
                                                                        title={
                                                                            category.expenses_count >
                                                                            0
                                                                                ? 'Categories with expenses cannot be deleted'
                                                                                : 'Delete category'
                                                                        }
                                                                    >
                                                                        <Trash2 className="size-4" />{' '}
                                                                        Delete
                                                                    </Button>
                                                                    <InputError
                                                                        message={
                                                                            errors.category
                                                                        }
                                                                    />
                                                                </div>
                                                            )}
                                                        </Form>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </CardContent>
                    </Card>
                )}

                {active_tab === 'vendors' && (
                    <Card className="overflow-hidden py-0">
                        <CardContent className="p-0">
                            {vendors.length === 0 ? (
                                <div className="py-14 text-center">
                                    <Store className="mx-auto size-9 text-muted-foreground" />
                                    <h2 className="mt-4 font-semibold">
                                        No saved vendors
                                    </h2>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        Save recurring payees, or continue
                                        typing one-off names on expenses.
                                    </p>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[750px] text-left text-sm">
                                        <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                            <tr>
                                                <th className="px-4 py-3">
                                                    Vendor
                                                </th>
                                                <th className="px-4 py-3">
                                                    Contact
                                                </th>
                                                <th className="px-4 py-3">
                                                    Address
                                                </th>
                                                <th className="px-4 py-3">
                                                    Expenses
                                                </th>
                                                <th className="px-4 py-3 text-right">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {vendors.map((vendor) => (
                                                <tr
                                                    key={vendor.id}
                                                    className="border-b last:border-0"
                                                >
                                                    <td className="px-4 py-3 font-medium">
                                                        {vendor.name}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <p>
                                                            {vendor.email ??
                                                                '—'}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {vendor.phone}
                                                        </p>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {vendor.address ?? '—'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {vendor.expenses_count}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="flex justify-end gap-2">
                                                            <VendorDialog
                                                                vendor={vendor}
                                                            />
                                                            <Form
                                                                action={`/app/finance/expense-vendors/${vendor.id}`}
                                                                method="delete"
                                                                onSubmit={(
                                                                    event,
                                                                ) => {
                                                                    if (
                                                                        !window.confirm(
                                                                            `Delete ${vendor.name}? Existing expense names will remain unchanged.`,
                                                                        )
                                                                    ) {
                                                                        event.preventDefault();
                                                                    }
                                                                }}
                                                            >
                                                                {({
                                                                    processing,
                                                                }) => (
                                                                    <Button
                                                                        type="submit"
                                                                        variant="destructive"
                                                                        size="sm"
                                                                        disabled={
                                                                            processing
                                                                        }
                                                                    >
                                                                        <Trash2 className="size-4" />{' '}
                                                                        Delete
                                                                    </Button>
                                                                )}
                                                            </Form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </main>
        </>
    );
}
