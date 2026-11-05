import { ArrowRight, BookOpen, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import PublicSiteShell from '@/components/public-site/public-site-shell';
import type {
    PublicAcademy,
    PublicProgramme,
    PublicSite,
} from '@/types/public-site';

export default function PublicProgrammes({
    academy,
    site,
    base_url,
    programmes,
}: {
    academy: PublicAcademy;
    site: PublicSite;
    base_url: string;
    programmes: PublicProgramme[];
}) {
    const [search, setSearch] = useState('');
    const filtered = useMemo(() => {
        const value = search.trim().toLowerCase();
        return value
            ? programmes.filter((programme) =>
                  `${programme.name} ${programme.code} ${programme.description ?? ''}`
                      .toLowerCase()
                      .includes(value),
              )
            : programmes;
    }, [programmes, search]);

    return (
        <PublicSiteShell
            academy={academy}
            site={site}
            baseUrl={base_url}
            title={`${site.sections.programmes?.title ?? 'Programmes'} · ${academy.name}`}
        >
            <main>
                <section className="relative overflow-hidden bg-slate-950 px-5 py-20 text-white sm:px-8 lg:py-28">
                    <div
                        className="absolute -top-40 right-0 size-[34rem] rounded-full opacity-30 blur-3xl"
                        style={{ backgroundColor: academy.theme.primary }}
                    />
                    <div className="relative mx-auto max-w-7xl">
                        <p className="text-xs font-extrabold tracking-[0.2em] text-[var(--site-primary)] uppercase">
                            Find your pathway
                        </p>
                        <h1 className="mt-4 max-w-4xl text-5xl font-black tracking-[-0.05em] text-balance sm:text-6xl">
                            {String(
                                site.sections.programmes?.title ??
                                    'Our programmes',
                            )}
                        </h1>
                        <p className="mt-6 max-w-2xl text-lg leading-8 text-white/65">
                            {String(site.sections.programmes?.intro ?? '')}
                        </p>
                    </div>
                </section>

                <section className="px-5 py-16 sm:px-8 lg:py-24">
                    <div className="mx-auto max-w-7xl">
                        <label className="relative block max-w-md">
                            <span className="sr-only">Search programmes</span>
                            <Search className="absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400" />
                            <input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search programmes"
                                className="h-12 w-full rounded-full border border-black/10 bg-white pr-4 pl-12 text-sm shadow-sm outline-none focus:ring-2 focus:ring-[var(--site-primary)]"
                            />
                        </label>

                        {filtered.length ? (
                            <div className="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                                {filtered.map((programme, index) => (
                                    <a
                                        key={programme.id}
                                        href={programme.url}
                                        className="group flex min-h-80 flex-col rounded-3xl border border-black/5 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-xl"
                                    >
                                        <div className="flex items-center justify-between gap-4">
                                            <span className="grid size-12 place-items-center rounded-2xl bg-[var(--site-secondary)] text-[var(--site-secondary-fg)]">
                                                <BookOpen className="size-5" />
                                            </span>
                                            <span className="text-xs font-black text-slate-300">
                                                {String(index + 1).padStart(
                                                    2,
                                                    '0',
                                                )}
                                            </span>
                                        </div>
                                        <p className="mt-8 text-xs font-black tracking-[0.16em] text-[var(--site-primary)] uppercase">
                                            {programme.code || academy.type}
                                        </p>
                                        <h2 className="mt-3 text-2xl font-black tracking-tight">
                                            {programme.name}
                                        </h2>
                                        <p className="mt-3 line-clamp-4 text-sm leading-7 text-slate-600">
                                            {programme.description ||
                                                'View available cycles, locations and programme fees.'}
                                        </p>
                                        <span className="mt-auto flex items-center gap-2 pt-7 text-sm font-extrabold">
                                            View programme
                                            <ArrowRight className="size-4 transition group-hover:translate-x-1" />
                                        </span>
                                    </a>
                                ))}
                            </div>
                        ) : (
                            <div className="mt-10 rounded-3xl border border-dashed p-12 text-center">
                                <BookOpen className="mx-auto size-9 text-slate-300" />
                                <h2 className="mt-4 font-bold">
                                    No programmes found
                                </h2>
                                <p className="mt-2 text-sm text-slate-500">
                                    Try another search or check back for new
                                    academy programmes.
                                </p>
                            </div>
                        )}
                    </div>
                </section>
            </main>
        </PublicSiteShell>
    );
}
