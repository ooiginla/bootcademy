import { Form, Head, Link } from '@inertiajs/react';
import { CalendarRange, Eye, Pencil, Plus } from 'lucide-react';
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

export type AcademyCycle = {
    id: number;
    name: string;
    type: 'fixed' | 'rolling';
    type_label: string;
    starts_on: string | null;
    ends_on: string | null;
    duration_value: number | null;
    duration_unit: 'days' | 'weeks' | 'months' | null;
    status: 'draft' | 'active' | 'closed';
    programme_cycles_count: number;
    enrollments_count: number;
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm';

function CycleDialog({ cycle }: { cycle?: AcademyCycle }) {
    const [open, setOpen] = useState(false);
    const [type, setType] = useState<'fixed' | 'rolling'>(
        cycle?.type ?? 'fixed',
    );
    const editing = Boolean(cycle);
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {editing ? (
                    <Button variant="outline" size="sm">
                        <Pencil className="size-4" /> Edit
                    </Button>
                ) : (
                    <Button>
                        <Plus className="size-4" /> Add cycle
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {editing ? 'Edit' : 'Create'} billing cycle
                    </DialogTitle>
                    <DialogDescription>
                        Fixed cycles share an end date. Rolling cycles calculate
                        each student’s end date from activation.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={
                        editing
                            ? `/app/finance/cycles/${cycle?.id}`
                            : '/app/finance/cycles'
                    }
                    method={editing ? 'patch' : 'post'}
                    className="space-y-4"
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="cycle-name">Cycle name</Label>
                                <Input
                                    id="cycle-name"
                                    name="name"
                                    defaultValue={cycle?.name ?? ''}
                                    placeholder="2026 Academic Year"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="cycle-type">
                                        Cycle type
                                    </Label>
                                    <select
                                        id="cycle-type"
                                        name="type"
                                        value={type}
                                        onChange={(event) =>
                                            setType(
                                                event.target.value as
                                                    | 'fixed'
                                                    | 'rolling',
                                            )
                                        }
                                        className={selectClass}
                                    >
                                        <option value="fixed">
                                            Fixed dates
                                        </option>
                                        <option value="rolling">
                                            Rolling duration
                                        </option>
                                    </select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="cycle-status">Status</Label>
                                    <select
                                        id="cycle-status"
                                        name="status"
                                        defaultValue={cycle?.status ?? 'draft'}
                                        className={selectClass}
                                    >
                                        <option value="draft">Draft</option>
                                        <option value="active">Active</option>
                                        <option value="closed">Closed</option>
                                    </select>
                                    <p className="text-xs text-muted-foreground">
                                        Activating this closes the previously
                                        active cycle.
                                    </p>
                                </div>
                            </div>
                            {type === 'fixed' ? (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="cycle-starts-on">
                                            Starts on
                                        </Label>
                                        <Input
                                            id="cycle-starts-on"
                                            name="starts_on"
                                            type="date"
                                            defaultValue={
                                                cycle?.starts_on ?? ''
                                            }
                                            required
                                        />
                                        <InputError
                                            message={errors.starts_on}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="cycle-ends-on">
                                            Ends on
                                        </Label>
                                        <Input
                                            id="cycle-ends-on"
                                            name="ends_on"
                                            type="date"
                                            defaultValue={cycle?.ends_on ?? ''}
                                            required
                                        />
                                        <InputError message={errors.ends_on} />
                                    </div>
                                </div>
                            ) : (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="cycle-duration-value">
                                            Duration
                                        </Label>
                                        <Input
                                            id="cycle-duration-value"
                                            name="duration_value"
                                            type="number"
                                            min={1}
                                            max={3650}
                                            defaultValue={
                                                cycle?.duration_value ?? 2
                                            }
                                            required
                                        />
                                        <InputError
                                            message={errors.duration_value}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="cycle-duration-unit">
                                            Duration unit
                                        </Label>
                                        <select
                                            id="cycle-duration-unit"
                                            name="duration_unit"
                                            defaultValue={
                                                cycle?.duration_unit ?? 'weeks'
                                            }
                                            className={selectClass}
                                        >
                                            <option value="days">Days</option>
                                            <option value="weeks">Weeks</option>
                                            <option value="months">
                                                Months
                                            </option>
                                        </select>
                                        <InputError
                                            message={errors.duration_unit}
                                        />
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Each student receives this duration from
                                        their activation date.
                                    </p>
                                </div>
                            )}
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}Save cycle
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function Cycles({ cycles }: { cycles: AcademyCycle[] }) {
    return (
        <>
            <Head title="Billing cycles" />
            <main className="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
                <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Academy finance
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold">
                            Billing and academic cycles
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                            Preserve programme membership, fees and balances by
                            period. Changing a future cycle never rewrites
                            previous records.
                        </p>
                    </div>
                    <CycleDialog />
                </div>
                <Card className="overflow-hidden py-0">
                    <CardContent className="p-0">
                        {cycles.length === 0 ? (
                            <div className="py-14 text-center">
                                <CalendarRange className="mx-auto size-9 text-muted-foreground" />
                                <h2 className="mt-4 font-semibold">
                                    No billing cycles yet
                                </h2>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Create a fixed academic period or a rolling
                                    membership cycle.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[840px] text-left text-sm">
                                    <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-3">Cycle</th>
                                            <th className="px-4 py-3">
                                                Timing
                                            </th>
                                            <th className="px-4 py-3">
                                                Programmes
                                            </th>
                                            <th className="px-4 py-3">
                                                Enrollments
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
                                        {cycles.map((cycle) => (
                                            <tr
                                                key={cycle.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="px-4 py-3">
                                                    <p className="font-medium">
                                                        {cycle.name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {cycle.type_label}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {cycle.type === 'fixed'
                                                        ? `${cycle.starts_on} – ${cycle.ends_on}`
                                                        : `${cycle.duration_value} ${cycle.duration_unit} from activation`}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {
                                                        cycle.programme_cycles_count
                                                    }
                                                </td>
                                                <td className="px-4 py-3">
                                                    {cycle.enrollments_count}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge
                                                        variant={
                                                            cycle.status ===
                                                            'active'
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {cycle.status}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            asChild
                                                            variant="outline"
                                                            size="sm"
                                                        >
                                                            <Link
                                                                href={`/app/finance/cycles/${cycle.id}`}
                                                            >
                                                                <Eye className="size-4" />
                                                                Details
                                                            </Link>
                                                        </Button>
                                                        <CycleDialog
                                                            cycle={cycle}
                                                        />
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
            </main>
        </>
    );
}
