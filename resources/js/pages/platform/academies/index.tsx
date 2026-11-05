import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
    Palette,
    ReceiptText,
    Search,
    ShieldCheck,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { AcademyTheme } from '@/types/auth';

type Academy = {
    id: number;
    name: string;
    academy_type: string;
    memberships_count: number;
    theme: AcademyTheme;
};

type Props = {
    academies: {
        data: Academy[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { search: string };
};

export default function PlatformAcademies({ academies, filters }: Props) {
    return (
        <>
            <Head title="Platform academies" />
            <main className="mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-8">
                <div className="mb-7 flex items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Platform control plane
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Academy themes
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Manage academy identity without entering or exposing
                            its operational records.
                        </p>
                    </div>
                    <span className="hidden rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground sm:block">
                        Super Admin
                    </span>
                </div>

                <Form
                    action="/platform/academies"
                    method="get"
                    className="mb-6 flex w-full max-w-lg gap-2"
                >
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Filter academies by name…"
                            aria-label="Filter academies by name"
                            className="pl-9"
                        />
                    </div>
                    <Button type="submit" variant="outline">
                        Filter
                    </Button>
                    {filters.search && (
                        <Button variant="ghost" asChild>
                            <Link href="/platform/academies">Clear</Link>
                        </Button>
                    )}
                </Form>

                {academies.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center p-10 text-center">
                            <Building2
                                className="size-8 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <h2 className="mt-4 font-semibold">
                                {filters.search
                                    ? 'No matching academies'
                                    : 'No academies yet'}
                            </h2>
                            <p className="mt-2 text-sm text-muted-foreground">
                                {filters.search
                                    ? 'Try a different academy name.'
                                    : 'Academies will appear here after signup or onboarding.'}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {academies.data.map((academy) => (
                            <Card key={academy.id} className="py-0">
                                <CardContent className="flex items-center gap-4 p-4 sm:p-5">
                                    <span
                                        className="grid size-11 shrink-0 place-items-center rounded-xl"
                                        style={{
                                            backgroundColor:
                                                academy.theme.primary,
                                            color: academy.theme
                                                .primary_foreground,
                                        }}
                                    >
                                        <Palette
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <h2 className="truncate font-medium">
                                            {academy.name}
                                        </h2>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {academy.academy_type} ·{' '}
                                            {academy.memberships_count} member
                                            {academy.memberships_count === 1
                                                ? ''
                                                : 's'}
                                        </p>
                                    </div>
                                    <div
                                        className="hidden items-center gap-1 sm:flex"
                                        aria-label="Current theme colours"
                                    >
                                        <span
                                            className="size-5 rounded-full border"
                                            style={{
                                                backgroundColor:
                                                    academy.theme.primary,
                                            }}
                                        />
                                        <span
                                            className="size-5 rounded-full border"
                                            style={{
                                                backgroundColor:
                                                    academy.theme.secondary,
                                            }}
                                        />
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={`/platform/academies/${academy.id}/support-access`}
                                            >
                                                <ShieldCheck
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Act as owner
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={`/platform/academies/${academy.id}/subscription`}
                                            >
                                                <ReceiptText
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Plan
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={`/platform/academies/${academy.id}/branding`}
                                            >
                                                Edit theme
                                                <ArrowRight
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Link>
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {academies.last_page > 1 && (
                    <nav
                        className="mt-6 flex items-center justify-between"
                        aria-label="Academy pages"
                    >
                        <Button
                            variant="outline"
                            disabled={!academies.prev_page_url}
                            asChild={Boolean(academies.prev_page_url)}
                        >
                            {academies.prev_page_url ? (
                                <Link href={academies.prev_page_url}>
                                    Previous
                                </Link>
                            ) : (
                                <span>Previous</span>
                            )}
                        </Button>
                        <span className="text-sm text-muted-foreground">
                            Page {academies.current_page} of{' '}
                            {academies.last_page}
                        </span>
                        <Button
                            variant="outline"
                            disabled={!academies.next_page_url}
                            asChild={Boolean(academies.next_page_url)}
                        >
                            {academies.next_page_url ? (
                                <Link href={academies.next_page_url}>Next</Link>
                            ) : (
                                <span>Next</span>
                            )}
                        </Button>
                    </nav>
                )}
            </main>
        </>
    );
}
