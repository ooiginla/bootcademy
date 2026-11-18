import { Head, Link } from '@inertiajs/react';
import { Eye, FilePenLine, MessageSquareQuote, Plus } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type ReviewListItem = {
    id: number;
    title: string;
    audience_label: string;
    status: string;
    status_label: string;
    field_count: number;
    responses_count: number;
    last_response_at: string | null;
    updated_at: string | null;
};

export default function ReviewsIndex({
    reviews,
}: {
    reviews: ReviewListItem[];
}) {
    return (
        <>
            <Head title="Reviews" />
            <main className="mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-8">
                <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Engagement
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Reviews
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Build feedback forms for parents, guardians,
                            students or players and review submitted responses.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/app/reviews/create">
                            <Plus className="size-4" /> Create review
                        </Link>
                    </Button>
                </div>

                {reviews.length === 0 ? (
                    <Card>
                        <CardContent className="grid min-h-64 place-items-center p-8 text-center">
                            <div>
                                <MessageSquareQuote className="mx-auto size-10 text-muted-foreground" />
                                <h2 className="mt-4 font-semibold">
                                    No reviews yet
                                </h2>
                                <p className="mt-2 max-w-md text-sm text-muted-foreground">
                                    Create a review, choose its audience and add
                                    the questions you want answered.
                                </p>
                                <Button asChild className="mt-5">
                                    <Link href="/app/reviews/create">
                                        Create your first review
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4">
                        {reviews.map((review) => (
                            <Card key={review.id}>
                                <CardContent className="flex flex-col gap-5 p-5 md:flex-row md:items-center md:justify-between">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="truncate font-semibold">
                                                {review.title}
                                            </h2>
                                            <Badge
                                                variant={
                                                    review.status ===
                                                    'published'
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                            >
                                                {review.status_label}
                                            </Badge>
                                        </div>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {review.audience_label} ·{' '}
                                            {review.field_count} fields ·{' '}
                                            {review.responses_count} responses
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {review.last_response_at
                                                ? `Last response ${new Date(review.last_response_at).toLocaleString()}`
                                                : 'No responses submitted yet'}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 gap-2">
                                        <Button variant="outline" asChild>
                                            <Link
                                                href={`/app/reviews/${review.id}/edit`}
                                            >
                                                <FilePenLine className="size-4" />
                                                Edit
                                            </Link>
                                        </Button>
                                        <Button asChild>
                                            <Link
                                                href={`/app/reviews/${review.id}`}
                                            >
                                                <Eye className="size-4" /> View
                                            </Link>
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </main>
        </>
    );
}
