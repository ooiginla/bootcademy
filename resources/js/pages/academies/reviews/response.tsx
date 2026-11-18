import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import DeleteRecordButton from '@/components/delete-record-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import type { ReviewData } from '@/types/reviews';

function displayAnswer(answer: unknown): string {
    if (answer === null || answer === undefined || answer === '')
        return 'Not answered';
    if (Array.isArray(answer)) return answer.join(', ') || 'Not answered';
    if (typeof answer === 'boolean') return answer ? 'Yes' : 'No';
    if (typeof answer === 'string' || typeof answer === 'number') {
        return String(answer);
    }

    return JSON.stringify(answer) ?? 'Not answered';
}

export default function ReviewResponse({
    review,
    response,
}: {
    review: ReviewData;
    response: {
        id: number;
        respondent_name: string | null;
        submitted_at: string;
        answers: Record<string, unknown>;
    };
}) {
    return (
        <>
            <Head title={`Response to ${review.title}`} />
            <main className="mx-auto w-full max-w-4xl p-4 sm:p-6 lg:p-8">
                <Button variant="ghost" asChild className="mb-4 px-0">
                    <Link href={`/app/reviews/${review.id}?tab=responses`}>
                        <ArrowLeft className="size-4" /> Back to responses
                    </Link>
                </Button>
                <div className="mb-7 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Individual response
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            {response.respondent_name ?? 'Anonymous response'}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Submitted{' '}
                            {new Date(response.submitted_at).toLocaleString()} ·{' '}
                            {review.title}
                        </p>
                    </div>
                    <DeleteRecordButton
                        action={`/app/reviews/${review.id}/responses/${response.id}`}
                        label="review response"
                        name={response.respondent_name ?? 'Anonymous response'}
                        description="This permanently removes the submitted answers and updates the review summary. This cannot be undone."
                        variant="destructive"
                    />
                </div>
                <div className="space-y-4">
                    {review.fields.map((field) => (
                        <Card key={field.id}>
                            <CardHeader>
                                <h2 className="font-semibold">{field.label}</h2>
                                {field.description && (
                                    <p className="text-sm text-muted-foreground">
                                        {field.description}
                                    </p>
                                )}
                            </CardHeader>
                            <CardContent>
                                <p
                                    className={
                                        response.answers[field.id] === undefined
                                            ? 'text-sm text-muted-foreground'
                                            : 'text-sm whitespace-pre-wrap'
                                    }
                                >
                                    {displayAnswer(response.answers[field.id])}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </main>
        </>
    );
}
