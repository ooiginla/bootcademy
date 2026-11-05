import { Form, Head, Link } from '@inertiajs/react';
import { BookOpenText, Eye, Upload, UserRound, UsersRound } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useAcademyTerminology } from '@/hooks/use-academy-terminology';
import {
    CustomFieldDialog,
    EnrollmentDialog,
    GuardianDialog,
    Pagination,
    PlayerDialog,
    type CustomField,
    type Guardian,
    type Option,
    type Paginator,
    type Player,
} from './players';

type Tab = 'students' | 'guardians' | 'fields';

function TabLink({
    tab,
    active,
    children,
}: {
    tab: Tab;
    active: Tab;
    children: ReactNode;
}) {
    return (
        <Link
            href={`/app/players?tab=${tab}`}
            role="tab"
            aria-selected={active === tab}
            className={`rounded-md px-4 py-2 text-sm font-medium transition-colors ${active === tab ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
        >
            {children}
        </Link>
    );
}

function DirectorySearch({
    tab,
    name,
    value,
    placeholder,
}: {
    tab: Tab;
    name: string;
    value: string;
    placeholder: string;
}) {
    return (
        <Form
            action="/app/players"
            method="get"
            className="flex max-w-xl gap-2"
        >
            {() => (
                <>
                    <input type="hidden" name="tab" value={tab} />
                    <Input
                        type="search"
                        name={name}
                        defaultValue={value}
                        placeholder={placeholder}
                        aria-label={placeholder}
                    />
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                    {value && (
                        <Button asChild type="button" variant="ghost">
                            <Link href={`/app/players?tab=${tab}`}>Clear</Link>
                        </Button>
                    )}
                </>
            )}
        </Form>
    );
}

function EmptyTable({
    icon: Icon,
    title,
    description,
}: {
    icon: typeof UserRound;
    title: string;
    description: string;
}) {
    return (
        <div className="flex flex-col items-center py-14 text-center">
            <Icon className="size-9 text-muted-foreground" />
            <h3 className="mt-4 font-semibold">{title}</h3>
            <p className="mt-2 max-w-sm text-sm text-muted-foreground">
                {description}
            </p>
        </div>
    );
}

function activeBranchNames(player: Player): string[] {
    return [
        ...new Set(
            player.enrollments
                .filter(
                    (enrollment) =>
                        enrollment.status === 'active' &&
                        enrollment.branch_name !== null,
                )
                .map((enrollment) => enrollment.branch_name as string),
        ),
    ];
}

export default function AcademyPlayersDirectory({
    players,
    guardians,
    programmes,
    branches,
    custom_fields,
    active_tab,
    filters,
}: {
    players: Paginator<Player>;
    guardians: Paginator<Guardian>;
    programmes: Option[];
    branches: Option[];
    custom_fields: CustomField[];
    active_tab: Tab;
    filters: { players_search: string; guardians_search: string };
}) {
    const terminology = useAcademyTerminology();
    return (
        <>
            <Head title={`${terminology.plural} and guardians`} />
            <main className="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Academy operations
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            {terminology.plural} and guardians
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                            Searchable, paginated directories for{' '}
                            {terminology.pluralLower}, guardian contacts and
                            academy-specific information.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {active_tab === 'students' && (
                            <>
                                <Button asChild variant="outline">
                                    <Link href="/app/players/import">
                                        <Upload className="size-4" />
                                        Import CSV
                                    </Link>
                                </Button>
                                <PlayerDialog customFields={custom_fields} />
                            </>
                        )}
                        {active_tab === 'guardians' && <GuardianDialog />}
                        {active_tab === 'fields' && <CustomFieldDialog />}
                    </div>
                </div>

                <div
                    role="tablist"
                    aria-label={`${terminology.singular} management sections`}
                    className="mb-6 flex w-fit flex-wrap gap-1 rounded-lg border bg-muted/40 p-1"
                >
                    <TabLink tab="students" active={active_tab}>
                        {terminology.plural}
                    </TabLink>
                    <TabLink tab="guardians" active={active_tab}>
                        Guardian directory
                    </TabLink>
                    <TabLink tab="fields" active={active_tab}>
                        Custom fields
                    </TabLink>
                </div>

                {active_tab === 'students' && (
                    <section role="tabpanel" className="space-y-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <DirectorySearch
                                tab="students"
                                name="players_search"
                                value={filters.players_search}
                                placeholder={`Search ${terminology.pluralLower} by name or ID`}
                            />
                            <Badge variant="secondary">
                                {players.total}{' '}
                                {players.total === 1
                                    ? terminology.singularLower
                                    : terminology.pluralLower}
                            </Badge>
                        </div>
                        <Card className="overflow-hidden py-0">
                            <CardContent className="p-0">
                                {players.data.length === 0 ? (
                                    <EmptyTable
                                        icon={UserRound}
                                        title={
                                            filters.players_search
                                                ? `No matching ${terminology.pluralLower}`
                                                : `No ${terminology.pluralLower} yet`
                                        }
                                        description={
                                            filters.players_search
                                                ? 'Try another name or internal ID.'
                                                : `Create the first ${terminology.singularLower} profile.`
                                        }
                                    />
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full min-w-[980px] text-left text-sm">
                                            <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                                <tr>
                                                    <th className="px-4 py-3 font-medium">
                                                        {terminology.singular}
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        Contact
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        Branch
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        Programmes
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
                                                {players.data.map((player) => (
                                                    <tr
                                                        key={player.id}
                                                        className="border-b last:border-0 hover:bg-muted/30"
                                                    >
                                                        <td className="px-4 py-3">
                                                            <p className="font-medium">
                                                                {
                                                                    player.first_name
                                                                }{' '}
                                                                {player.middle_name
                                                                    ? `${player.middle_name} `
                                                                    : ''}
                                                                {
                                                                    player.last_name
                                                                }
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {player.internal_identifier ??
                                                                    'No internal ID'}
                                                                {player.date_of_birth
                                                                    ? ` · ${player.date_of_birth}`
                                                                    : ''}
                                                            </p>
                                                        </td>
                                                        <td className="px-4 py-3 text-muted-foreground">
                                                            <p>
                                                                {player.phone ??
                                                                    'No phone'}
                                                            </p>
                                                            {player.city && (
                                                                <p className="text-xs">
                                                                    {
                                                                        player.city
                                                                    }
                                                                    {player.state_region
                                                                        ? `, ${player.state_region}`
                                                                        : ''}
                                                                </p>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {activeBranchNames(
                                                                player,
                                                            ).length === 0 ? (
                                                                <span className="text-muted-foreground">
                                                                    No branch
                                                                    assigned
                                                                </span>
                                                            ) : (
                                                                <div className="flex max-w-64 flex-wrap gap-1">
                                                                    {activeBranchNames(
                                                                        player,
                                                                    ).map(
                                                                        (
                                                                            branch,
                                                                        ) => (
                                                                            <Badge
                                                                                key={
                                                                                    branch
                                                                                }
                                                                                variant="outline"
                                                                            >
                                                                                {
                                                                                    branch
                                                                                }
                                                                            </Badge>
                                                                        ),
                                                                    )}
                                                                </div>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {player.enrollments
                                                                .length ===
                                                            0 ? (
                                                                <span className="text-muted-foreground">
                                                                    None
                                                                </span>
                                                            ) : (
                                                                <div className="space-y-1">
                                                                    {player.enrollments
                                                                        .slice(
                                                                            0,
                                                                            2,
                                                                        )
                                                                        .map(
                                                                            (
                                                                                enrollment,
                                                                            ) => (
                                                                                <p
                                                                                    key={
                                                                                        enrollment.id
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        enrollment.programme_name
                                                                                    }
                                                                                    <span className="text-xs text-muted-foreground">
                                                                                        {' '}
                                                                                        ·{' '}
                                                                                        {
                                                                                            enrollment.status
                                                                                        }
                                                                                    </span>
                                                                                </p>
                                                                            ),
                                                                        )}
                                                                    {player
                                                                        .enrollments
                                                                        .length >
                                                                        2 && (
                                                                        <p className="text-xs text-muted-foreground">
                                                                            +
                                                                            {player
                                                                                .enrollments
                                                                                .length -
                                                                                2}{' '}
                                                                            more
                                                                        </p>
                                                                    )}
                                                                </div>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <Badge
                                                                variant={
                                                                    player.status ===
                                                                    'active'
                                                                        ? 'default'
                                                                        : 'secondary'
                                                                }
                                                                className="capitalize"
                                                            >
                                                                {player.status}
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
                                                                        href={`/app/players/${player.id}`}
                                                                    >
                                                                        <Eye className="size-4" />
                                                                        Details
                                                                    </Link>
                                                                </Button>
                                                                <PlayerDialog
                                                                    player={
                                                                        player
                                                                    }
                                                                    customFields={
                                                                        custom_fields
                                                                    }
                                                                />
                                                                <EnrollmentDialog
                                                                    player={
                                                                        player
                                                                    }
                                                                    programmes={
                                                                        programmes
                                                                    }
                                                                    branches={
                                                                        branches
                                                                    }
                                                                />
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                                <Pagination paginator={players} />
                            </CardContent>
                        </Card>
                    </section>
                )}

                {active_tab === 'guardians' && (
                    <section role="tabpanel" className="space-y-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <DirectorySearch
                                tab="guardians"
                                name="guardians_search"
                                value={filters.guardians_search}
                                placeholder="Search guardians by name, email or phone"
                            />
                            <Badge variant="secondary">
                                {guardians.total}{' '}
                                {guardians.total === 1
                                    ? 'guardian'
                                    : 'guardians'}
                            </Badge>
                        </div>
                        <Card className="overflow-hidden py-0">
                            <CardContent className="p-0">
                                {guardians.data.length === 0 ? (
                                    <EmptyTable
                                        icon={UsersRound}
                                        title={
                                            filters.guardians_search
                                                ? 'No matching guardians'
                                                : 'No guardians yet'
                                        }
                                        description={
                                            filters.guardians_search
                                                ? 'Try another name, email or phone number.'
                                                : 'Add the first guardian contact.'
                                        }
                                    />
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full min-w-[760px] text-left text-sm">
                                            <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                                <tr>
                                                    <th className="px-4 py-3 font-medium">
                                                        Guardian
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        Phone
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        WhatsApp
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        Signature
                                                    </th>
                                                    <th className="px-4 py-3 text-right font-medium">
                                                        Actions
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {guardians.data.map(
                                                    (guardian) => (
                                                        <tr
                                                            key={guardian.id}
                                                            className="border-b last:border-0 hover:bg-muted/30"
                                                        >
                                                            <td className="px-4 py-3">
                                                                <p className="font-medium">
                                                                    {
                                                                        guardian.first_name
                                                                    }{' '}
                                                                    {
                                                                        guardian.last_name
                                                                    }
                                                                </p>
                                                                <p className="text-xs text-muted-foreground">
                                                                    {guardian.email ??
                                                                        'No email'}
                                                                </p>
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                {guardian.phone}
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                {guardian.whatsapp_number ??
                                                                    '—'}
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                {guardian.has_signature ? (
                                                                    <a
                                                                        className="font-medium text-primary hover:underline"
                                                                        href={`/app/guardians/${guardian.id}/signature`}
                                                                        target="_blank"
                                                                        rel="noreferrer"
                                                                    >
                                                                        View
                                                                    </a>
                                                                ) : (
                                                                    <span className="text-muted-foreground">
                                                                        Not
                                                                        uploaded
                                                                    </span>
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-3 text-right">
                                                                <GuardianDialog
                                                                    guardian={
                                                                        guardian
                                                                    }
                                                                />
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                                <Pagination paginator={guardians} />
                            </CardContent>
                        </Card>
                    </section>
                )}

                {active_tab === 'fields' && (
                    <section role="tabpanel" className="space-y-4">
                        <div className="flex items-end justify-between">
                            <div>
                                <h2 className="font-semibold">
                                    Custom {terminology.singularLower} fields
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Typed fields with stable keys for dashboard
                                    forms and API requests.
                                </p>
                            </div>
                            <Badge variant="secondary">
                                {custom_fields.length}
                            </Badge>
                        </div>
                        <Card className="overflow-hidden py-0">
                            <CardContent className="p-0">
                                {custom_fields.length === 0 ? (
                                    <EmptyTable
                                        icon={BookOpenText}
                                        title="No custom fields"
                                        description={`Add fields such as playing position, jersey size or consented health details for each ${terminology.singularLower}.`}
                                    />
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full min-w-[800px] text-left text-sm">
                                            <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                                <tr>
                                                    <th className="px-4 py-3 font-medium">
                                                        Label
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        API key
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        Type
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        Rules
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
                                                {custom_fields.map((field) => (
                                                    <tr
                                                        key={field.id}
                                                        className="border-b last:border-0 hover:bg-muted/30"
                                                    >
                                                        <td className="px-4 py-3 font-medium">
                                                            {field.label}
                                                        </td>
                                                        <td className="px-4 py-3 font-mono text-xs">
                                                            {field.key}
                                                        </td>
                                                        <td className="px-4 py-3 capitalize">
                                                            {field.type}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <div className="flex gap-1">
                                                                {field.is_required && (
                                                                    <Badge variant="outline">
                                                                        Required
                                                                    </Badge>
                                                                )}
                                                                {field.is_sensitive && (
                                                                    <Badge variant="destructive">
                                                                        Sensitive
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <Badge
                                                                variant={
                                                                    field.is_active
                                                                        ? 'default'
                                                                        : 'secondary'
                                                                }
                                                            >
                                                                {field.is_active
                                                                    ? 'Active'
                                                                    : 'Inactive'}
                                                            </Badge>
                                                        </td>
                                                        <td className="px-4 py-3 text-right">
                                                            <CustomFieldDialog
                                                                field={field}
                                                            />
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </section>
                )}
            </main>
        </>
    );
}
