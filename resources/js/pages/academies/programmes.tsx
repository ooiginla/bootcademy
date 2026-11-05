import { Form, Head, Link } from '@inertiajs/react';
import { BookOpenText, MapPin, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { ListSearch } from '@/components/list-search';
import {
    ProgrammePublicProfileFields,
    type ProgrammePublicProfile,
} from '@/components/programme-public-profile-fields';
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

export type BranchOption = { id: number; name: string; is_active: boolean };
export type Programme = ProgrammePublicProfile & {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    is_active: boolean;
    branches: BranchOption[];
};

function ProgrammeFields({
    programme,
    branches,
    errors,
}: {
    programme?: Programme;
    branches: BranchOption[];
    errors: Record<string, string>;
}) {
    const terminology = useAcademyTerminology();
    const prefix = programme ? `programme-${programme.id}` : 'new-programme';
    const selectedBranches = new Set(
        programme?.branches.map((branch) => branch.id) ?? [],
    );

    return (
        <div className="space-y-5">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor={`${prefix}-name`}>Programme name</Label>
                    <Input
                        id={`${prefix}-name`}
                        name="name"
                        defaultValue={programme?.name}
                        required
                        maxLength={160}
                        placeholder="e.g. Beginner French"
                    />
                    <InputError message={errors.name} />
                </div>
                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor={`${prefix}-code`}>Programme code</Label>
                    <Input
                        id={`${prefix}-code`}
                        name="code"
                        defaultValue={programme?.code ?? ''}
                        maxLength={32}
                        placeholder="Optional, e.g. FR-BEG"
                    />
                    <InputError message={errors.code} />
                </div>
                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor={`${prefix}-description`}>Description</Label>
                    <textarea
                        id={`${prefix}-description`}
                        name="description"
                        defaultValue={programme?.description ?? ''}
                        maxLength={2000}
                        rows={4}
                        className="rounded-md border bg-background px-3 py-2 text-sm"
                        placeholder={`Describe what ${terminology.pluralLower} receive from this programme.`}
                    />
                    <InputError message={errors.description} />
                </div>
            </div>

            <fieldset>
                <legend className="text-sm font-medium">
                    Offered at branches
                </legend>
                <p className="mt-1 text-xs text-muted-foreground">
                    Optional. A programme can be assigned to branches later.
                </p>
                <div className="mt-3 grid gap-2 sm:grid-cols-2">
                    {branches.length === 0 ? (
                        <p className="rounded-lg border border-dashed p-3 text-sm text-muted-foreground sm:col-span-2">
                            No branches are available yet.
                        </p>
                    ) : (
                        branches.map((branch) => (
                            <label
                                key={branch.id}
                                className="flex items-center gap-3 rounded-lg border p-3 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    name="branch_ids[]"
                                    value={branch.id}
                                    defaultChecked={selectedBranches.has(
                                        branch.id,
                                    )}
                                    className="size-4 accent-primary"
                                />
                                <span className="min-w-0 flex-1 truncate">
                                    {branch.name}
                                </span>
                                {!branch.is_active && (
                                    <span className="text-xs text-muted-foreground">
                                        Inactive
                                    </span>
                                )}
                            </label>
                        ))
                    )}
                </div>
                <InputError message={errors.branch_ids} />
            </fieldset>

            {programme ? (
                <>
                    <label className="flex items-center gap-3 rounded-lg border p-3 text-sm">
                        <input type="hidden" name="is_active" value="0" />
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            defaultChecked={programme.is_active}
                            className="size-4 accent-primary"
                        />
                        Active programme
                    </label>
                    <ProgrammePublicProfileFields
                        programme={programme}
                        errors={errors}
                    />
                </>
            ) : (
                <input type="hidden" name="is_active" value="1" />
            )}
        </div>
    );
}

export function ProgrammeDialog({
    programme,
    branches,
}: {
    programme?: Programme;
    branches: BranchOption[];
}) {
    const [open, setOpen] = useState(false);
    const editing = Boolean(programme);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={editing ? 'outline' : 'default'}
                    size={editing ? 'sm' : 'default'}
                >
                    {editing ? (
                        <Pencil className="size-4" />
                    ) : (
                        <Plus className="size-4" />
                    )}
                    {editing ? 'Edit' : 'Add programme'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle>
                        {editing ? `Edit ${programme?.name}` : 'Add programme'}
                    </DialogTitle>
                    <DialogDescription>
                        {editing
                            ? 'Update operations and the public programme page.'
                            : 'Create an offering for this academy. Fees are configured separately.'}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={
                        editing
                            ? `/app/programmes/${programme?.id}`
                            : '/app/programmes'
                    }
                    method={editing ? 'patch' : 'post'}
                    className="space-y-5"
                    resetOnSuccess={!editing}
                    onSuccess={() => setOpen(false)}
                    onError={() => setOpen(true)}
                >
                    {({ processing, errors }) => (
                        <>
                            <ProgrammeFields
                                programme={programme}
                                branches={branches}
                                errors={errors}
                            />
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
                                        : 'Create programme'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function AcademyProgrammes({
    programmes,
    branches,
    can_manage,
}: {
    programmes: Programme[];
    branches: BranchOption[];
    can_manage: boolean;
}) {
    const terminology = useAcademyTerminology();
    const [search, setSearch] = useState('');
    const term = search.trim().toLocaleLowerCase();
    const filteredProgrammes = programmes.filter((programme) =>
        [programme.name, programme.code]
            .filter(Boolean)
            .some((value) => value?.toLocaleLowerCase().includes(term)),
    );

    return (
        <>
            <Head title="Programmes" />
            <main className="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
                <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Academy operations
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Programmes
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                            Open a programme to view its details and
                            branch-filtered {terminology.singularLower}{' '}
                            directory.
                        </p>
                    </div>
                    {can_manage && <ProgrammeDialog branches={branches} />}
                </div>
                <ListSearch
                    value={search}
                    onChange={setSearch}
                    placeholder="Filter programmes by name…"
                    label="Filter programmes by name"
                />
                <div className="mt-6 mb-4 flex items-center justify-between">
                    <h2 className="font-semibold">Academy programmes</h2>
                    <Badge variant="secondary">
                        {filteredProgrammes.length}{' '}
                        {filteredProgrammes.length === 1
                            ? 'programme'
                            : 'programmes'}
                    </Badge>
                </div>
                <Card className="overflow-hidden py-0">
                    <CardContent className="p-0">
                        {filteredProgrammes.length === 0 ? (
                            <div className="flex flex-col items-center py-14 text-center">
                                <BookOpenText className="size-9 text-muted-foreground" />
                                <h3 className="mt-4 font-semibold">
                                    {programmes.length === 0
                                        ? 'No programmes yet'
                                        : 'No matching programmes'}
                                </h3>
                                <p className="mt-2 max-w-sm text-sm text-muted-foreground">
                                    {programmes.length === 0
                                        ? 'Add your first programme to prepare for groups, schedules, enrollment and fees.'
                                        : 'Try a different programme name.'}
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[800px] text-left text-sm">
                                    <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">
                                                Programme
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Branches
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Description
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Status
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {filteredProgrammes.map((programme) => (
                                            <tr
                                                key={programme.id}
                                                className="border-b last:border-0 hover:bg-muted/30"
                                            >
                                                <td className="px-4 py-3">
                                                    <Link
                                                        href={`/app/programmes/${programme.id}`}
                                                        className="font-medium text-primary hover:underline"
                                                    >
                                                        {programme.name}
                                                    </Link>
                                                    <p className="text-xs text-muted-foreground">
                                                        {programme.code ??
                                                            'No code'}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex max-w-72 flex-wrap gap-1">
                                                        {programme.branches
                                                            .length ? (
                                                            programme.branches.map(
                                                                (branch) => (
                                                                    <Badge
                                                                        key={
                                                                            branch.id
                                                                        }
                                                                        variant="outline"
                                                                    >
                                                                        <MapPin className="size-3" />
                                                                        {
                                                                            branch.name
                                                                        }
                                                                    </Badge>
                                                                ),
                                                            )
                                                        ) : (
                                                            <span className="text-muted-foreground">
                                                                No branches
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="max-w-sm px-4 py-3 text-muted-foreground">
                                                    <p className="line-clamp-2">
                                                        {programme.description ??
                                                            'No description'}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge
                                                        variant={
                                                            programme.is_active
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {programme.is_active
                                                            ? 'Active'
                                                            : 'Inactive'}
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
                                                                href={`/app/programmes/${programme.id}`}
                                                            >
                                                                View
                                                            </Link>
                                                        </Button>
                                                        {can_manage && (
                                                            <ProgrammeDialog
                                                                programme={
                                                                    programme
                                                                }
                                                                branches={
                                                                    branches
                                                                }
                                                            />
                                                        )}
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
