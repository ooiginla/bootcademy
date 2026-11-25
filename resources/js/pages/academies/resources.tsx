import { Head, Link, useForm } from '@inertiajs/react';
import {
    Check,
    Copy,
    ExternalLink,
    FileText,
    FolderOpen,
    Search,
    Upload,
} from 'lucide-react';
import { useRef } from 'react';
import type { FormEvent } from 'react';
import DeleteRecordButton from '@/components/delete-record-button';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useClipboard } from '@/hooks/use-clipboard';

type Resource = {
    id: string;
    name: string;
    mime_type: string;
    extension: string;
    size_bytes: number;
    public_url: string;
    uploaded_at: string | null;
    is_image: boolean;
};

type ResourcePage = {
    data: Resource[];
    from: number | null;
    to: number | null;
    next_page_url: string | null;
    prev_page_url: string | null;
};

function readableSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function uploadedDate(value: string | null): string {
    if (!value) return 'Upload date unavailable';

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function AcademyResources({
    resources,
    filters,
}: {
    resources: ResourcePage;
    filters: { search: string };
}) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [copiedUrl, copy] = useClipboard();
    const upload = useForm<{ resource: File | null }>({ resource: null });

    function submitUpload(event: FormEvent) {
        event.preventDefault();
        upload.post('/app/resources', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                upload.reset();
                if (fileInput.current) fileInput.current.value = '';
            },
        });
    }

    return (
        <>
            <Head title="Resources" />
            <main className="mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-8">
                <div className="mb-7">
                    <p className="text-sm font-medium text-primary">
                        Website assets
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold">
                        Resource Library
                    </h1>
                    <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                        Keep approved images and downloads in one place, then
                        copy their public links into your academy website.
                    </p>
                </div>

                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Upload className="size-5 text-primary" />
                            Upload a resource
                        </CardTitle>
                        <CardDescription>
                            Files uploaded here are public to anyone with the
                            link. Do not upload child records, consent forms,
                            private documents, or confidential information.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submitUpload}
                            className="flex flex-col gap-4 sm:flex-row sm:items-end"
                        >
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="resource">Choose file</Label>
                                <Input
                                    ref={fileInput}
                                    id="resource"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt"
                                    onChange={(event) =>
                                        upload.setData(
                                            'resource',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    Images, PDF, Office, CSV, or text files up
                                    to 20 MB.
                                </p>
                                <InputError message={upload.errors.resource} />
                            </div>
                            <Button
                                type="submit"
                                disabled={
                                    !upload.data.resource || upload.processing
                                }
                            >
                                {upload.processing ? <Spinner /> : <Upload />}
                                {upload.processing ? 'Uploading…' : 'Upload'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-lg font-semibold">
                            Your resources
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {resources.data.length === 0
                                ? 'No matching files'
                                : `Showing ${resources.from}–${resources.to}`}
                        </p>
                    </div>
                    <form
                        method="get"
                        action="/app/resources"
                        className="flex gap-2"
                    >
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                name="search"
                                defaultValue={filters.search}
                                aria-label="Search resources"
                                placeholder="Search resources"
                                maxLength={100}
                                className="w-64 pl-9"
                            />
                        </div>
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>
                </div>

                {resources.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center py-14 text-center">
                            <FolderOpen className="mb-4 size-10 text-muted-foreground" />
                            <h2 className="font-semibold">
                                {filters.search
                                    ? 'No resources match your search'
                                    : 'No resources yet'}
                            </h2>
                            <p className="mt-2 max-w-md text-sm text-muted-foreground">
                                {filters.search
                                    ? 'Try a different file name or clear the search.'
                                    : 'Upload your first approved website image or download above.'}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {resources.data.map((resource) => (
                            <Card
                                key={resource.id}
                                className="overflow-hidden py-0"
                            >
                                <div className="flex aspect-[16/9] items-center justify-center border-b bg-muted/40">
                                    {resource.is_image ? (
                                        <img
                                            src={resource.public_url}
                                            alt=""
                                            loading="lazy"
                                            className="h-full w-full object-cover"
                                        />
                                    ) : (
                                        <FileText className="size-12 text-muted-foreground" />
                                    )}
                                </div>
                                <CardContent className="space-y-4 py-5">
                                    <div className="min-w-0">
                                        <h3
                                            className="truncate font-medium"
                                            title={resource.name}
                                        >
                                            {resource.name}
                                        </h3>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {resource.extension.toUpperCase()} ·{' '}
                                            {readableSize(resource.size_bytes)}{' '}
                                            ·{' '}
                                            {uploadedDate(resource.uploaded_at)}
                                        </p>
                                    </div>
                                    <p
                                        className="truncate rounded-md bg-muted px-3 py-2 font-mono text-xs text-muted-foreground"
                                        title={resource.public_url}
                                    >
                                        {resource.public_url}
                                    </p>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={() =>
                                                copy(resource.public_url)
                                            }
                                            aria-label={`Copy public URL for ${resource.name}`}
                                        >
                                            {copiedUrl ===
                                            resource.public_url ? (
                                                <Check />
                                            ) : (
                                                <Copy />
                                            )}
                                            {copiedUrl === resource.public_url
                                                ? 'Copied'
                                                : 'Copy URL'}
                                        </Button>
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="icon"
                                        >
                                            <a
                                                href={resource.public_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                aria-label={`Open ${resource.name}`}
                                            >
                                                <ExternalLink />
                                            </a>
                                        </Button>
                                        <div className="ml-auto">
                                            <DeleteRecordButton
                                                action={`/app/resources/${resource.id}`}
                                                label="resource"
                                                name={resource.name}
                                                description="The file and its public link will stop working. Places where you used this URL will need a replacement."
                                                size="icon"
                                                variant="ghost"
                                            />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {(resources.prev_page_url || resources.next_page_url) && (
                    <nav
                        aria-label="Resource pages"
                        className="mt-6 flex justify-end gap-2"
                    >
                        {resources.prev_page_url && (
                            <Button asChild variant="outline">
                                <Link href={resources.prev_page_url}>
                                    Previous
                                </Link>
                            </Button>
                        )}
                        {resources.next_page_url && (
                            <Button asChild variant="outline">
                                <Link href={resources.next_page_url}>Next</Link>
                            </Button>
                        )}
                    </nav>
                )}
            </main>
        </>
    );
}
