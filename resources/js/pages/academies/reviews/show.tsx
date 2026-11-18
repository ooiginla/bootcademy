import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    BarChart3,
    ExternalLink,
    FilePenLine,
    Inbox,
    Users,
} from 'lucide-react';
import DeleteRecordButton from '@/components/delete-record-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { ReviewData } from '@/types/reviews';

type FieldSummary = {
    field_id: string;
    label: string;
    type: string;
    answered: number;
    skipped: number;
    distribution?: Array<{
        label: string;
        count: number;
        percentage: number;
    }>;
    recent_answers?: unknown[];
};

type ResponseItem = {
    id: number;
    respondent_name: string | null;
    answered_count: number;
    submitted_at: string;
};

export default function ReviewShow({
    review,
    active_tab: activeTab,
    summary,
    responses,
    public_url: publicUrl,
}: {
    review: ReviewData;
    active_tab: 'summary' | 'responses';
    summary: FieldSummary[];
    responses: ResponseItem[];
    public_url: string;
}) {
    return (
        <>
            <Head title={review.title} />
            <main className="mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-8">
                <Button variant="ghost" asChild className="mb-4 px-0">
                    <Link href="/app/reviews">
                        <ArrowLeft className="size-4" /> Back to reviews
                    </Link>
                </Button>
                <div className="mb-7 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {review.title}
                            </h1>
                            <Badge
                                variant={
                                    review.status === 'published'
                                        ? 'default'
                                        : 'outline'
                                }
                            >
                                {review.status_label}
                            </Badge>
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {review.audience_label} · {review.fields.length}{' '}
                            fields · {review.responses_count} responses
                        </p>
                        {review.description && (
                            <p className="mt-3 max-w-3xl text-sm leading-6">
                                {review.description}
                            </p>
                        )}
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={`/app/reviews/${review.id}/edit`}>
                            <FilePenLine className="size-4" /> Edit review
                        </Link>
                    </Button>
                </div>

                <Card className="mb-6">
                    <CardHeader>
                        <h2 className="font-semibold">Response link</h2>
                        <p className="text-sm text-muted-foreground">
                            {review.status === 'published'
                                ? 'Share this link with the selected audience.'
                                : review.status === 'closed'
                                  ? 'This review is closed and no longer accepts responses.'
                                  : 'Publish the review before sharing this link.'}
                        </p>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3 sm:flex-row">
                        <Input value={publicUrl} readOnly />
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                void navigator.clipboard.writeText(publicUrl)
                            }
                        >
                            Copy link
                        </Button>
                        <Button asChild disabled={review.status === 'draft'}>
                            <a
                                href={publicUrl}
                                target="_blank"
                                rel="noreferrer"
                            >
                                Open <ExternalLink className="size-4" />
                            </a>
                        </Button>
                    </CardContent>
                </Card>

                <div className="mb-6 flex gap-2 border-b">
                    <Button
                        variant={
                            activeTab === 'summary' ? 'secondary' : 'ghost'
                        }
                        asChild
                        className="rounded-b-none"
                    >
                        <Link href={`/app/reviews/${review.id}`}>
                            <BarChart3 className="size-4" /> Summary
                        </Link>
                    </Button>
                    <Button
                        variant={
                            activeTab === 'responses' ? 'secondary' : 'ghost'
                        }
                        asChild
                        className="rounded-b-none"
                    >
                        <Link href={`/app/reviews/${review.id}?tab=responses`}>
                            <Users className="size-4" /> Responses (
                            {review.responses_count})
                        </Link>
                    </Button>
                </div>

                {activeTab === 'summary' ? (
                    <div className="grid gap-5 lg:grid-cols-2">
                        {summary.map((field) => (
                            <Card key={field.field_id}>
                                <CardHeader>
                                    <h2 className="font-semibold">
                                        {field.label}
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        {field.answered} answered ·{' '}
                                        {field.skipped} skipped
                                    </p>
                                </CardHeader>
                                <CardContent>
                                    {field.distribution ? (
                                        <div className="space-y-4">
                                            {field.distribution.length ===
                                                0 && (
                                                <p className="text-sm text-muted-foreground">
                                                    No answers yet.
                                                </p>
                                            )}
                                            {field.distribution.map((item) => (
                                                <div key={item.label}>
                                                    <div className="mb-1 flex justify-between gap-4 text-sm">
                                                        <span>
                                                            {item.label}
                                                        </span>
                                                        <span className="text-muted-foreground">
                                                            {item.count} (
                                                            {item.percentage}%)
                                                        </span>
                                                    </div>
                                                    <div className="h-2 overflow-hidden rounded-full bg-muted">
                                                        <div
                                                            className="h-full rounded-full bg-primary"
                                                            style={{
                                                                width: `${Math.min(100, item.percentage)}%`,
                                                            }}
                                                        />
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="space-y-2">
                                            {(field.recent_answers ?? [])
                                                .length === 0 && (
                                                <p className="text-sm text-muted-foreground">
                                                    No answers yet.
                                                </p>
                                            )}
                                            {(field.recent_answers ?? []).map(
                                                (answer, index) => (
                                                    <p
                                                        key={index}
                                                        className="rounded-lg bg-muted/50 p-3 text-sm"
                                                    >
                                                        {String(answer)}
                                                    </p>
                                                ),
                                            )}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : responses.length === 0 ? (
                    <Card>
                        <CardContent className="grid min-h-56 place-items-center text-center">
                            <div>
                                <Inbox className="mx-auto size-9 text-muted-foreground" />
                                <h2 className="mt-3 font-semibold">
                                    No responses yet
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Published responses will appear here in
                                    newest-first order.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="divide-y p-0">
                            {responses.map((response) => (
                                <div
                                    key={response.id}
                                    className="flex items-center justify-between gap-4 p-5 transition-colors hover:bg-muted/50"
                                >
                                    <Link
                                        href={`/app/reviews/${review.id}/responses/${response.id}`}
                                        className="min-w-0 flex-1"
                                    >
                                        <p className="font-medium">
                                            {response.respondent_name ??
                                                'Anonymous response'}
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {response.answered_count} of{' '}
                                            {review.fields.length} fields
                                            answered ·{' '}
                                            {new Date(
                                                response.submitted_at,
                                            ).toLocaleString()}
                                        </p>
                                    </Link>
                                    <DeleteRecordButton
                                        action={`/app/reviews/${review.id}/responses/${response.id}`}
                                        label="review response"
                                        name={
                                            response.respondent_name ??
                                            'Anonymous response'
                                        }
                                        description="This permanently removes the submitted answers and updates the review summary. This cannot be undone."
                                        size="icon"
                                        variant="ghost"
                                    />
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </main>
        </>
    );
}
