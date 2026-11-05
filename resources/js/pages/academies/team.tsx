import { Form, Head } from '@inertiajs/react';
import { Mail, Pencil, ShieldCheck, UserPlus, UsersRound } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { ListSearch } from '@/components/list-search';
import PasswordInput from '@/components/password-input';
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

type RoleOption = { value: string; label: string };
type BranchOption = { id: number; name: string; is_active: boolean };
type Member = {
    id: number;
    user_id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    status: 'active' | 'invited' | 'suspended';
    is_current_user: boolean;
    branches: BranchOption[];
};
type PendingInvitation = {
    id: number;
    email: string;
    role_label: string;
    expires_at: string;
    branches: BranchOption[];
};

const roleBadgeClasses: Record<string, string> = {
    owner_admin:
        'border-violet-300 bg-violet-50 text-violet-800 dark:border-violet-800 dark:bg-violet-950/40 dark:text-violet-200',
    finance_officer:
        'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200',
    branch_manager:
        'border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-200',
    coach: 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
    guardian:
        'border-pink-300 bg-pink-50 text-pink-800 dark:border-pink-800 dark:bg-pink-950/40 dark:text-pink-200',
    read_only_auditor:
        'border-slate-300 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200',
};

function RoleSelect({
    id,
    roles,
    defaultValue = 'coach',
    error,
}: {
    id: string;
    roles: RoleOption[];
    defaultValue?: string;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>Academy role</Label>
            <select
                id={id}
                name="role"
                required
                defaultValue={defaultValue}
                className="h-10 rounded-md border bg-background px-3 text-sm"
            >
                {roles.map((role) => (
                    <option key={role.value} value={role.value}>
                        {role.label}
                    </option>
                ))}
            </select>
            <InputError message={error} />
        </div>
    );
}

function BranchAccessFields({
    branches,
    selected = [],
    error,
}: {
    branches: BranchOption[];
    selected?: BranchOption[];
    error?: string;
}) {
    const selectedIds = new Set(selected.map((branch) => branch.id));

    return (
        <fieldset>
            <legend className="text-sm font-medium">Branch access</legend>
            <p className="mt-1 text-xs text-muted-foreground">
                Owner/Admin always has every branch. Other roles receive only
                the selected branches.
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
                                defaultChecked={selectedIds.has(branch.id)}
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
            <InputError message={error} />
        </fieldset>
    );
}

function InviteMemberDialog({
    roles,
    branches,
}: {
    roles: RoleOption[];
    branches: BranchOption[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Mail className="size-4" />
                    Invite member
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Invite team member</DialogTitle>
                    <DialogDescription>
                        Email a private signup link that expires and can only be
                        used once.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action="/app/settings/team"
                    method="post"
                    className="space-y-5"
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    onError={() => setOpen(true)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="invite-email">
                                    Email address
                                </Label>
                                <Input
                                    id="invite-email"
                                    name="email"
                                    type="email"
                                    required
                                    placeholder="team@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>
                            <RoleSelect
                                id="invite-role"
                                roles={roles}
                                error={errors.role}
                            />
                            <BranchAccessFields
                                branches={branches}
                                error={errors.branch_ids}
                            />
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Send invitation
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function CreateMemberDialog({
    roles,
    branches,
}: {
    roles: RoleOption[];
    branches: BranchOption[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <UserPlus className="size-4" />
                    Create account
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Create active account</DialogTitle>
                    <DialogDescription>
                        Create immediate access with a temporary password the
                        member must replace after first login.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action="/app/settings/team/create"
                    method="post"
                    className="space-y-5"
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    onError={() => setOpen(true)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="create-name">Full name</Label>
                                <Input
                                    id="create-name"
                                    name="name"
                                    required
                                    autoComplete="off"
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="create-email">
                                    Email address
                                </Label>
                                <Input
                                    id="create-email"
                                    name="email"
                                    type="email"
                                    required
                                    autoComplete="off"
                                />
                                <InputError message={errors.email} />
                            </div>
                            <RoleSelect
                                id="create-role"
                                roles={roles}
                                error={errors.role}
                            />
                            <BranchAccessFields
                                branches={branches}
                                error={errors.branch_ids}
                            />
                            <div className="grid gap-2">
                                <Label htmlFor="create-password">
                                    Temporary password
                                </Label>
                                <PasswordInput
                                    id="create-password"
                                    name="password"
                                    required
                                    autoComplete="new-password"
                                />
                                <InputError message={errors.password} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="create-password-confirmation">
                                    Confirm temporary password
                                </Label>
                                <PasswordInput
                                    id="create-password-confirmation"
                                    name="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Create member
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function EditMemberDialog({
    member,
    roles,
    branches,
}: {
    member: Member;
    roles: RoleOption[];
    branches: BranchOption[];
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
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit {member.name}</DialogTitle>
                    <DialogDescription>
                        Change this member's academy role or suspend their
                        access.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/app/settings/team/${member.id}`}
                    method="patch"
                    className="space-y-5"
                    onSuccess={() => setOpen(false)}
                    onError={() => setOpen(true)}
                >
                    {({ processing, errors }) => (
                        <>
                            <RoleSelect
                                id={`role-${member.id}`}
                                roles={roles}
                                defaultValue={member.role}
                                error={errors.role}
                            />
                            <BranchAccessFields
                                branches={branches}
                                selected={member.branches}
                                error={errors.branch_ids}
                            />
                            <div className="grid gap-2">
                                <Label htmlFor={`status-${member.id}`}>
                                    Access status
                                </Label>
                                <select
                                    id={`status-${member.id}`}
                                    name="status"
                                    defaultValue={member.status}
                                    className="h-10 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                                <InputError message={errors.status} />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Save changes
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function AcademyTeam({
    team,
    roles,
    branches,
    pendingInvitations = [],
}: {
    team: Member[];
    roles: RoleOption[];
    branches: BranchOption[];
    pendingInvitations?: PendingInvitation[];
}) {
    const [search, setSearch] = useState('');
    const term = search.trim().toLocaleLowerCase();
    const filteredTeam = team.filter((member) =>
        [member.name, member.email, member.role_label].some((value) =>
            value.toLocaleLowerCase().includes(term),
        ),
    );
    const filteredInvitations = pendingInvitations.filter((invitation) =>
        [invitation.email, invitation.role_label].some((value) =>
            value.toLocaleLowerCase().includes(term),
        ),
    );

    return (
        <>
            <Head title="Team and roles" />
            <main className="mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-8">
                <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Academy settings
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Team and roles
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                            Invite people to create their own account or create
                            an active account immediately.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <InviteMemberDialog roles={roles} branches={branches} />
                        <CreateMemberDialog roles={roles} branches={branches} />
                    </div>
                </div>

                <ListSearch
                    value={search}
                    onChange={setSearch}
                    placeholder="Filter team by name…"
                    label="Filter team by name"
                />

                {filteredInvitations.length > 0 && (
                    <section className="mt-7 mb-7">
                        <div className="mb-3 flex items-center gap-2">
                            <Mail className="size-4 text-muted-foreground" />
                            <h2 className="font-semibold">
                                Pending invitations
                            </h2>
                            <Badge variant="secondary">
                                {filteredInvitations.length}
                            </Badge>
                        </div>
                        <div className="grid gap-3 md:grid-cols-2">
                            {filteredInvitations.map((invitation) => (
                                <Card
                                    key={`invitation-${invitation.id}`}
                                    className="border-dashed py-0"
                                >
                                    <CardContent className="flex items-start justify-between gap-3 p-4">
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {invitation.email}
                                            </p>
                                            <div className="mt-2 flex flex-wrap gap-1">
                                                {invitation.branches.map(
                                                    (branch) => (
                                                        <Badge
                                                            key={branch.id}
                                                            variant="outline"
                                                            className="rounded-full"
                                                        >
                                                            {branch.name}
                                                        </Badge>
                                                    ),
                                                )}
                                                {invitation.branches.length ===
                                                    0 && (
                                                    <span className="text-xs text-muted-foreground">
                                                        No branch access
                                                    </span>
                                                )}
                                            </div>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {invitation.role_label} ·
                                                Expires{' '}
                                                {new Date(
                                                    invitation.expires_at,
                                                ).toLocaleDateString()}
                                            </p>
                                        </div>
                                        <Badge variant="secondary">
                                            Email sent
                                        </Badge>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </section>
                )}

                <section className="mt-7">
                    <div className="mb-4 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <UsersRound className="size-5 text-muted-foreground" />
                            <h2 className="font-semibold">Academy team</h2>
                        </div>
                        <Badge variant="secondary">
                            {filteredTeam.length}{' '}
                            {filteredTeam.length === 1 ? 'member' : 'members'}
                        </Badge>
                    </div>
                    {filteredTeam.length === 0 ? (
                        <Card>
                            <CardContent className="py-12 text-center">
                                <h3 className="font-semibold">
                                    No matching team members
                                </h3>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Try a different name, email or role.
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2">
                            {filteredTeam.map((member) => (
                                <Card key={member.id} className="py-0">
                                    <CardContent className="p-5">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">
                                                    {member.name}
                                                </p>
                                                <p className="truncate text-sm text-muted-foreground">
                                                    {member.email}
                                                </p>
                                            </div>
                                            <Badge
                                                variant={
                                                    member.status === 'active'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                                className="capitalize"
                                            >
                                                {member.status}
                                            </Badge>
                                        </div>
                                        <div className="mt-4 flex flex-wrap items-center gap-2">
                                            <Badge
                                                variant="outline"
                                                className={`rounded-full ${roleBadgeClasses[member.role] ?? roleBadgeClasses.read_only_auditor}`}
                                            >
                                                <ShieldCheck className="size-3" />
                                                {member.role_label}
                                            </Badge>
                                            {member.is_current_user && (
                                                <span className="text-xs text-muted-foreground">
                                                    Your access
                                                </span>
                                            )}
                                            {member.status === 'invited' && (
                                                <span className="text-xs text-muted-foreground">
                                                    Awaiting acceptance
                                                </span>
                                            )}
                                        </div>
                                        <div className="mt-3 flex flex-wrap gap-1">
                                            {member.role === 'owner_admin' ? (
                                                <Badge
                                                    variant="outline"
                                                    className="rounded-full"
                                                >
                                                    All branches
                                                </Badge>
                                            ) : member.branches.length > 0 ? (
                                                member.branches.map(
                                                    (branch) => (
                                                        <Badge
                                                            key={branch.id}
                                                            variant="outline"
                                                            className="rounded-full"
                                                        >
                                                            {branch.name}
                                                        </Badge>
                                                    ),
                                                )
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    No branch access
                                                </span>
                                            )}
                                        </div>
                                        {!member.is_current_user &&
                                            member.status !== 'invited' && (
                                                <div className="mt-5">
                                                    <EditMemberDialog
                                                        member={member}
                                                        roles={roles}
                                                        branches={branches}
                                                    />
                                                </div>
                                            )}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </section>
            </main>
        </>
    );
}
