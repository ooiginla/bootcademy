import { Head, useForm } from '@inertiajs/react';
import { CheckSquare, MessageSquareQuote, Star } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ReviewField } from '@/types/reviews';

type Answer = string | number | boolean | string[];
type PublicReviewData = {
    title: string;
    description: string | null;
    audience_label: string;
    status: string;
    collect_respondent_name: boolean;
    fields: ReviewField[];
    academy: string;
};

const controlClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

export default function PublicReview({
    review,
    submit_url: submitUrl,
}: {
    review: PublicReviewData;
    submit_url: string;
}) {
    const initialAnswers = Object.fromEntries(
        review.fields.map((field) => [
            field.id,
            field.type === 'multi_select' ||
            (field.type === 'checkbox' && field.options.length > 0)
                ? []
                : field.type === 'checkbox'
                  ? false
                  : '',
        ]),
    ) as Record<string, Answer>;
    const form = useForm({
        respondent_name: '',
        answers: initialAnswers,
    });
    const errors = form.errors as Record<string, string>;

    function setAnswer(fieldId: string, answer: Answer) {
        form.setData('answers', { ...form.data.answers, [fieldId]: answer });
    }

    function toggleMulti(fieldId: string, option: string, checked: boolean) {
        const current = form.data.answers[fieldId];
        const selected = Array.isArray(current) ? current : [];
        setAnswer(
            fieldId,
            checked
                ? [...selected, option]
                : selected.filter((item) => item !== option),
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(submitUrl);
    }

    return (
        <>
            <Head title={review.title} />
            <main className="min-h-screen bg-muted/30 px-4 py-10 sm:py-16">
                <div className="mx-auto max-w-2xl">
                    <div className="mb-6 text-center">
                        <span className="mx-auto grid size-12 place-items-center rounded-2xl bg-primary text-primary-foreground">
                            <MessageSquareQuote className="size-6" />
                        </span>
                        <p className="mt-4 text-sm font-medium text-primary">
                            {review.academy}
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                            {review.title}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            For {review.audience_label.toLocaleLowerCase()}
                        </p>
                        {review.description && (
                            <p className="mx-auto mt-4 max-w-xl text-sm leading-6">
                                {review.description}
                            </p>
                        )}
                    </div>

                    {review.status === 'closed' ? (
                        <Card>
                            <CardContent className="grid min-h-52 place-items-center p-8 text-center">
                                <div>
                                    <CheckSquare className="mx-auto size-9 text-muted-foreground" />
                                    <h2 className="mt-4 font-semibold">
                                        This review is closed
                                    </h2>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        It is no longer accepting responses.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    ) : (
                        <form onSubmit={submit} className="space-y-5">
                            {review.collect_respondent_name && (
                                <Card>
                                    <CardContent className="grid gap-2 p-5">
                                        <Label htmlFor="respondent_name">
                                            Your name
                                        </Label>
                                        <Input
                                            id="respondent_name"
                                            value={form.data.respondent_name}
                                            onChange={(event) =>
                                                form.setData(
                                                    'respondent_name',
                                                    event.target.value,
                                                )
                                            }
                                            required
                                            maxLength={160}
                                            autoComplete="name"
                                        />
                                        <InputError
                                            message={errors.respondent_name}
                                        />
                                    </CardContent>
                                </Card>
                            )}

                            {review.fields.map((field, index) => {
                                const value = form.data.answers[field.id];
                                const error = errors[`answers.${field.id}`];
                                return (
                                    <Card key={field.id}>
                                        <CardHeader>
                                            <h2 className="font-semibold">
                                                {index + 1}. {field.label}{' '}
                                                {field.required && (
                                                    <span className="text-destructive">
                                                        *
                                                    </span>
                                                )}
                                            </h2>
                                            {field.description && (
                                                <p className="text-sm text-muted-foreground">
                                                    {field.description}
                                                </p>
                                            )}
                                        </CardHeader>
                                        <CardContent>
                                            {field.type === 'long_text' ? (
                                                <textarea
                                                    value={String(value)}
                                                    onChange={(event) =>
                                                        setAnswer(
                                                            field.id,
                                                            event.target.value,
                                                        )
                                                    }
                                                    required={field.required}
                                                    rows={5}
                                                    maxLength={5000}
                                                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                                />
                                            ) : field.type === 'select' ? (
                                                <select
                                                    value={String(value)}
                                                    onChange={(event) =>
                                                        setAnswer(
                                                            field.id,
                                                            event.target.value,
                                                        )
                                                    }
                                                    required={field.required}
                                                    className={controlClass}
                                                >
                                                    <option value="">
                                                        Select an option
                                                    </option>
                                                    {field.options.map(
                                                        (option) => (
                                                            <option
                                                                key={option}
                                                                value={option}
                                                            >
                                                                {option}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            ) : field.type === 'radio' ? (
                                                <div className="space-y-3">
                                                    {field.options.map(
                                                        (option) => (
                                                            <label
                                                                key={option}
                                                                className="flex items-center gap-3 text-sm"
                                                            >
                                                                <input
                                                                    type="radio"
                                                                    name={`answer-${field.id}`}
                                                                    value={
                                                                        option
                                                                    }
                                                                    checked={
                                                                        value ===
                                                                        option
                                                                    }
                                                                    onChange={() =>
                                                                        setAnswer(
                                                                            field.id,
                                                                            option,
                                                                        )
                                                                    }
                                                                    required={
                                                                        field.required
                                                                    }
                                                                    className="size-4"
                                                                />
                                                                {option}
                                                            </label>
                                                        ),
                                                    )}
                                                </div>
                                            ) : field.type === 'multi_select' ||
                                              (field.type === 'checkbox' &&
                                                  field.options.length > 0) ? (
                                                <div className="space-y-3">
                                                    {field.options.map(
                                                        (option) => (
                                                            <label
                                                                key={option}
                                                                className="flex items-center gap-3 text-sm"
                                                            >
                                                                <input
                                                                    type="checkbox"
                                                                    checked={
                                                                        Array.isArray(
                                                                            value,
                                                                        ) &&
                                                                        value.includes(
                                                                            option,
                                                                        )
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        toggleMulti(
                                                                            field.id,
                                                                            option,
                                                                            event
                                                                                .target
                                                                                .checked,
                                                                        )
                                                                    }
                                                                    className="size-4"
                                                                />
                                                                {option}
                                                            </label>
                                                        ),
                                                    )}
                                                </div>
                                            ) : field.type === 'checkbox' ? (
                                                <label className="flex items-center gap-3 text-sm">
                                                    <input
                                                        type="checkbox"
                                                        checked={value === true}
                                                        onChange={(event) =>
                                                            setAnswer(
                                                                field.id,
                                                                event.target
                                                                    .checked,
                                                            )
                                                        }
                                                        required={
                                                            field.required
                                                        }
                                                        className="size-4"
                                                    />
                                                    Yes
                                                </label>
                                            ) : field.type === 'rating' ? (
                                                <div className="flex flex-wrap gap-2">
                                                    {Array.from(
                                                        {
                                                            length: field.max_rating,
                                                        },
                                                        (_, rating) =>
                                                            rating + 1,
                                                    ).map((rating) => (
                                                        <Button
                                                            key={rating}
                                                            type="button"
                                                            size="icon"
                                                            variant={
                                                                Number(value) >=
                                                                rating
                                                                    ? 'default'
                                                                    : 'outline'
                                                            }
                                                            aria-label={`${rating} star rating`}
                                                            onClick={() =>
                                                                setAnswer(
                                                                    field.id,
                                                                    rating,
                                                                )
                                                            }
                                                        >
                                                            <Star className="size-4" />
                                                        </Button>
                                                    ))}
                                                </div>
                                            ) : (
                                                <Input
                                                    type={
                                                        field.type === 'email'
                                                            ? 'email'
                                                            : field.type ===
                                                                'number'
                                                              ? 'number'
                                                              : field.type ===
                                                                  'date'
                                                                ? 'date'
                                                                : field.type ===
                                                                    'phone'
                                                                  ? 'tel'
                                                                  : 'text'
                                                    }
                                                    value={String(value)}
                                                    onChange={(event) =>
                                                        setAnswer(
                                                            field.id,
                                                            event.target.value,
                                                        )
                                                    }
                                                    required={field.required}
                                                    maxLength={1000}
                                                />
                                            )}
                                            <InputError
                                                message={error}
                                                className="mt-2"
                                            />
                                        </CardContent>
                                    </Card>
                                );
                            })}

                            <Button
                                type="submit"
                                size="lg"
                                className="w-full"
                                disabled={form.processing}
                            >
                                {form.processing
                                    ? 'Submitting…'
                                    : 'Submit response'}
                            </Button>
                            <p className="text-center text-xs text-muted-foreground">
                                Your response is shared only with authorised
                                academy administrators.
                            </p>
                        </form>
                    )}
                </div>
            </main>
        </>
    );
}
