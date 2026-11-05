import { Form, Head } from '@inertiajs/react';
import {
    Building2,
    Clock3,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Plus,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { ListSearch } from '@/components/list-search';
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

type Branch = {
    id: number;
    name: string;
    code: string | null;
    address_line: string | null;
    city: string | null;
    state_region: string | null;
    country_code: string | null;
    timezone: string;
    contact_email: string | null;
    contact_phone: string | null;
    is_active: boolean;
};

function BranchFields({
    branch,
    academyTimezone,
    errors,
}: {
    branch?: Branch;
    academyTimezone: string;
    errors: Record<string, string>;
}) {
    const prefix = branch ? `branch-${branch.id}` : 'new-branch';

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor={`${prefix}-name`}>Branch name</Label>
                <Input
                    id={`${prefix}-name`}
                    name="name"
                    defaultValue={branch?.name}
                    required
                    maxLength={160}
                    placeholder="e.g. Central Campus"
                />
                <InputError message={errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-code`}>Branch code</Label>
                <Input
                    id={`${prefix}-code`}
                    name="code"
                    defaultValue={branch?.code ?? ''}
                    maxLength={32}
                    placeholder="Optional, e.g. CENTRAL"
                />
                <InputError message={errors.code} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-timezone`}>Timezone</Label>
                <Input
                    id={`${prefix}-timezone`}
                    name="timezone"
                    defaultValue={branch?.timezone ?? academyTimezone}
                    required
                    placeholder="Africa/Lagos"
                />
                <InputError message={errors.timezone} />
            </div>
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor={`${prefix}-address`}>Street address</Label>
                <Input
                    id={`${prefix}-address`}
                    name="address_line"
                    defaultValue={branch?.address_line ?? ''}
                    maxLength={255}
                />
                <InputError message={errors.address_line} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-city`}>City</Label>
                <Input
                    id={`${prefix}-city`}
                    name="city"
                    defaultValue={branch?.city ?? ''}
                    maxLength={120}
                />
                <InputError message={errors.city} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-state`}>State / region</Label>
                <Input
                    id={`${prefix}-state`}
                    name="state_region"
                    defaultValue={branch?.state_region ?? ''}
                    maxLength={120}
                />
                <InputError message={errors.state_region} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-country`}>Country code</Label>
                <Input
                    id={`${prefix}-country`}
                    name="country_code"
                    defaultValue={branch?.country_code ?? ''}
                    minLength={2}
                    maxLength={2}
                    placeholder="e.g. NG"
                />
                <InputError message={errors.country_code} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-phone`}>Contact phone</Label>
                <Input
                    id={`${prefix}-phone`}
                    name="contact_phone"
                    defaultValue={branch?.contact_phone ?? ''}
                    maxLength={40}
                    type="tel"
                />
                <InputError message={errors.contact_phone} />
            </div>
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor={`${prefix}-email`}>Contact email</Label>
                <Input
                    id={`${prefix}-email`}
                    name="contact_email"
                    defaultValue={branch?.contact_email ?? ''}
                    maxLength={255}
                    type="email"
                />
                <InputError message={errors.contact_email} />
            </div>
            {branch ? (
                <label className="flex items-center gap-3 rounded-lg border p-3 text-sm sm:col-span-2">
                    <input type="hidden" name="is_active" value="0" />
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        defaultChecked={branch.is_active}
                        className="size-4 accent-primary"
                    />
                    Active branch
                </label>
            ) : (
                <input type="hidden" name="is_active" value="1" />
            )}
        </div>
    );
}

function AddBranchDialog({ academyTimezone }: { academyTimezone: string }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus className="size-4" />
                    Add branch
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Add branch</DialogTitle>
                    <DialogDescription>
                        Add an academy location. Only the name and timezone are
                        required.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action="/app/branches"
                    method="post"
                    className="space-y-5"
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    onError={() => setOpen(true)}
                >
                    {({ processing, errors }) => (
                        <>
                            <BranchFields
                                academyTimezone={academyTimezone}
                                errors={errors}
                            />
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}Create branch
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function EditBranchDialog({
    branch,
    academyTimezone,
}: {
    branch: Branch;
    academyTimezone: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Pencil className="size-4" />
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Edit {branch.name}</DialogTitle>
                    <DialogDescription>
                        Update this location or mark it inactive while retaining
                        its history.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/app/branches/${branch.id}`}
                    method="patch"
                    className="space-y-5"
                    onSuccess={() => setOpen(false)}
                    onError={() => setOpen(true)}
                >
                    {({ processing, errors }) => (
                        <>
                            <BranchFields
                                branch={branch}
                                academyTimezone={academyTimezone}
                                errors={errors}
                            />
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}Save changes
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function branchLocation(branch: Branch): string {
    return [
        branch.address_line,
        branch.city,
        branch.state_region,
        branch.country_code,
    ]
        .filter(Boolean)
        .join(', ');
}

export default function AcademyBranches({
    branches,
    academy_timezone,
    can_manage,
}: {
    branches: Branch[];
    academy_timezone: string;
    can_manage: boolean;
}) {
    const [search, setSearch] = useState('');
    const term = search.trim().toLocaleLowerCase();
    const filteredBranches = branches.filter((branch) =>
        [branch.name, branch.code, branch.city, branch.state_region]
            .filter(Boolean)
            .some((value) => value?.toLocaleLowerCase().includes(term)),
    );

    return (
        <>
            <Head title="Branches" />
            <main className="mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-8">
                <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Academy operations
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Branches
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                            Manage every location independently while keeping
                            one academy-wide view. Inactive branches remain
                            available for historical records.
                        </p>
                    </div>
                    {can_manage && (
                        <AddBranchDialog academyTimezone={academy_timezone} />
                    )}
                </div>

                <ListSearch
                    value={search}
                    onChange={setSearch}
                    placeholder="Filter branches by name…"
                    label="Filter branches by name"
                />

                <div className="mt-6 mb-4 flex items-center justify-between">
                    <h2 className="font-semibold">Academy locations</h2>
                    <Badge variant="secondary">
                        {filteredBranches.length}{' '}
                        {filteredBranches.length === 1 ? 'branch' : 'branches'}
                    </Badge>
                </div>

                {filteredBranches.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center py-14 text-center">
                            <Building2 className="size-9 text-muted-foreground" />
                            <h3 className="mt-4 font-semibold">
                                {branches.length === 0
                                    ? 'No branches yet'
                                    : 'No matching branches'}
                            </h3>
                            <p className="mt-2 max-w-sm text-sm text-muted-foreground">
                                {branches.length === 0
                                    ? "Add the academy's first location to prepare for programmes, schedules and enrollment."
                                    : 'Try a different branch name.'}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {filteredBranches.map((branch) => {
                            const location = branchLocation(branch);
                            return (
                                <Card key={branch.id} className="py-0">
                                    <CardContent className="p-5">
                                        <div className="flex items-start gap-4">
                                            <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                                                <MapPin className="size-5" />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-start justify-between gap-2">
                                                    <div>
                                                        <h3 className="font-medium">
                                                            {branch.name}
                                                        </h3>
                                                        {branch.code && (
                                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                                {branch.code}
                                                            </p>
                                                        )}
                                                    </div>
                                                    <Badge
                                                        variant={
                                                            branch.is_active
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {branch.is_active
                                                            ? 'Active'
                                                            : 'Inactive'}
                                                    </Badge>
                                                </div>
                                                <div className="mt-4 space-y-2 text-sm text-muted-foreground">
                                                    <p className="flex items-start gap-2">
                                                        <MapPin className="mt-0.5 size-4 shrink-0" />
                                                        {location ||
                                                            'Location details not added'}
                                                    </p>
                                                    <p className="flex items-center gap-2">
                                                        <Clock3 className="size-4 shrink-0" />
                                                        {branch.timezone}
                                                    </p>
                                                    {branch.contact_email && (
                                                        <p className="flex items-center gap-2 truncate">
                                                            <Mail className="size-4 shrink-0" />
                                                            {
                                                                branch.contact_email
                                                            }
                                                        </p>
                                                    )}
                                                    {branch.contact_phone && (
                                                        <p className="flex items-center gap-2">
                                                            <Phone className="size-4 shrink-0" />
                                                            {
                                                                branch.contact_phone
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                                {can_manage && (
                                                    <div className="mt-5">
                                                        <EditBranchDialog
                                                            branch={branch}
                                                            academyTimezone={
                                                                academy_timezone
                                                            }
                                                        />
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </main>
        </>
    );
}
