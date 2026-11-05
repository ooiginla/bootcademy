import { Form, Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Download,
    FileSpreadsheet,
    Upload,
} from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useAcademyTerminology } from '@/hooks/use-academy-terminology';

type PreviewRow = {
    line: number;
    label: string;
    errors: string[];
    internal_identifier: string | null;
    date_of_birth: string | null;
    programme: string | null;
    branch: string | null;
    guardian: string;
};

type Preview = {
    file_name: string;
    total_rows: number;
    valid_rows: number;
    error_rows: number;
    file_errors: string[];
    can_import: boolean;
    rows: PreviewRow[];
    is_truncated: boolean;
};

export default function PlayerImport({
    token,
    preview,
    template_headers,
}: {
    token: string | null;
    preview: Preview | null;
    template_headers: string[];
}) {
    const terminology = useAcademyTerminology();
    return (
        <>
            <Head title={`Import ${terminology.plural}`} />
            <main className="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
                <Button asChild variant="ghost" className="mb-4 -ml-3">
                    <Link href="/app/players">
                        <ArrowLeft className="size-4" />
                        Back to {terminology.pluralLower}
                    </Link>
                </Button>
                <div className="mb-7">
                    <p className="text-sm font-medium text-primary">
                        Academy operations
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold">
                        Import {terminology.pluralLower} from CSV
                    </h1>
                    <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                        Preview and correct every error before importing. The
                        final import is atomic: either every row is saved or no
                        profiles are created.
                    </p>
                </div>

                {!preview || !token ? (
                    <Card className="max-w-3xl">
                        <CardHeader>
                            <CardTitle>Upload CSV file</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="rounded-lg border bg-muted/30 p-4 text-sm">
                                <p className="font-medium">
                                    Start with the template
                                </p>
                                <p className="mt-1 text-muted-foreground">
                                    It contains standard profile, guardian,
                                    programme and academy custom-field columns.
                                </p>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="mt-3"
                                >
                                    <a href="/app/players/import/template">
                                        <Download className="size-4" />
                                        Download CSV template
                                    </a>
                                </Button>
                            </div>
                            <Form
                                action="/app/players/import/preview"
                                method="post"
                                encType="multipart/form-data"
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="csv-file">
                                                CSV file
                                            </Label>
                                            <Input
                                                id="csv-file"
                                                name="csv_file"
                                                type="file"
                                                accept=".csv,text/csv"
                                                required
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                Maximum 2 MB and 500 data rows.
                                            </p>
                                            <InputError
                                                message={errors.csv_file}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing ? (
                                                <Spinner />
                                            ) : (
                                                <Upload className="size-4" />
                                            )}
                                            Preview import
                                        </Button>
                                    </>
                                )}
                            </Form>
                            <details className="text-sm text-muted-foreground">
                                <summary className="cursor-pointer font-medium text-foreground">
                                    Available columns ({template_headers.length}
                                    )
                                </summary>
                                <p className="mt-2 break-words">
                                    {template_headers.join(', ')}
                                </p>
                            </details>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-5">
                        <Card>
                            <CardContent className="flex flex-col gap-4 pt-6 sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex items-start gap-3">
                                    <FileSpreadsheet className="mt-0.5 size-6 text-primary" />
                                    <div>
                                        <p className="font-medium">
                                            {preview.file_name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {preview.total_rows} rows checked
                                        </p>
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Badge variant="secondary">
                                        {preview.valid_rows} valid
                                    </Badge>
                                    <Badge
                                        variant={
                                            preview.error_rows
                                                ? 'destructive'
                                                : 'secondary'
                                        }
                                    >
                                        {preview.error_rows} with errors
                                    </Badge>
                                </div>
                            </CardContent>
                        </Card>

                        {preview.file_errors.length > 0 && (
                            <div className="rounded-lg border border-destructive/40 bg-destructive/5 p-4">
                                <div className="flex items-center gap-2 font-medium text-destructive">
                                    <AlertTriangle className="size-4" />
                                    File errors
                                </div>
                                <ul className="mt-2 list-disc space-y-1 pl-5 text-sm">
                                    {preview.file_errors.map((error) => (
                                        <li key={error}>{error}</li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <Card className="overflow-hidden py-0">
                            <CardContent className="p-0">
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[960px] text-left text-sm">
                                        <thead className="border-b bg-muted/50 text-xs text-muted-foreground uppercase">
                                            <tr>
                                                <th className="px-4 py-3">
                                                    Row
                                                </th>
                                                <th className="px-4 py-3">
                                                    {terminology.singular}
                                                </th>
                                                <th className="px-4 py-3">
                                                    Birth date
                                                </th>
                                                <th className="px-4 py-3">
                                                    Guardian
                                                </th>
                                                <th className="px-4 py-3">
                                                    Programme / branch
                                                </th>
                                                <th className="px-4 py-3">
                                                    Result
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {preview.rows.map((row) => (
                                                <tr
                                                    key={row.line}
                                                    className="border-b align-top last:border-0"
                                                >
                                                    <td className="px-4 py-3">
                                                        {row.line}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <p className="font-medium">
                                                            {row.label}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {row.internal_identifier ??
                                                                'No internal ID'}
                                                        </p>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {row.date_of_birth ??
                                                            '—'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {row.guardian || '—'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {row.programme ?? '—'}
                                                        {row.branch
                                                            ? ` · ${row.branch}`
                                                            : ''}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {row.errors.length ===
                                                        0 ? (
                                                            <span className="inline-flex items-center gap-1 text-emerald-700">
                                                                <CheckCircle2 className="size-4" />{' '}
                                                                Valid
                                                            </span>
                                                        ) : (
                                                            <ul className="max-w-lg list-disc space-y-1 pl-4 text-destructive">
                                                                {row.errors.map(
                                                                    (error) => (
                                                                        <li
                                                                            key={
                                                                                error
                                                                            }
                                                                        >
                                                                            {
                                                                                error
                                                                            }
                                                                        </li>
                                                                    ),
                                                                )}
                                                            </ul>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                {preview.is_truncated && (
                                    <p className="border-t px-4 py-3 text-sm text-muted-foreground">
                                        Showing the first 100 rows. The full
                                        file was validated.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <div className="flex flex-wrap items-center gap-3">
                            {preview.can_import ? (
                                <Form
                                    action="/app/players/import/confirm"
                                    method="post"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="token"
                                                value={token}
                                            />
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                Import {preview.total_rows}{' '}
                                                profiles
                                            </Button>
                                            <InputError
                                                message={errors.csv_file}
                                            />
                                        </>
                                    )}
                                </Form>
                            ) : (
                                <Button asChild variant="outline">
                                    <a
                                        href={`/app/players/import/errors?token=${encodeURIComponent(token)}`}
                                    >
                                        <Download className="size-4" />
                                        Download error report
                                    </a>
                                </Button>
                            )}
                            <Form action="/app/players/import" method="delete">
                                {({ processing }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="token"
                                            value={token}
                                        />
                                        <Button
                                            type="submit"
                                            variant="ghost"
                                            disabled={processing}
                                        >
                                            Cancel and remove file
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </div>
                    </div>
                )}
            </main>
        </>
    );
}
