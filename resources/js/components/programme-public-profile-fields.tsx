import { useForm } from '@inertiajs/react';
import { ImageIcon, Plus, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { RichTextEditor } from '@/components/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Tool = { name: string; logo_url: string | null };
type Faq = { question: string; answer_html: string };
type Testimonial = { quote_html: string; name: string; role: string | null };

export type ProgrammePublicProfile = {
    duration?: string | null;
    delivery_format?: string | null;
    programme_type?: string | null;
    public_timezone?: string | null;
    level?: string | null;
    outcomes_html?: string | null;
    skills_gained_html?: string | null;
    curriculum_html?: string | null;
    eligibility_html?: string | null;
    audience_html?: string | null;
    demand_html?: string | null;
    application_process_html?: string | null;
    tools_platforms?: Tool[];
    faqs?: Faq[];
    testimonials?: Testimonial[];
    image_url?: string | null;
};

export function ProgrammePublicProfileFields({
    programme,
    errors,
}: {
    programme: ProgrammePublicProfile & { id: number };
    errors: Record<string, string>;
}) {
    const prefix = `programme-${programme.id}-public`;
    const imageInput = useRef<HTMLInputElement>(null);
    const imageForm = useForm<{ image: File | null }>({ image: null });
    const [tools, setTools] = useState<Tool[]>(programme.tools_platforms ?? []);
    const [faqs, setFaqs] = useState<Faq[]>(programme.faqs ?? []);
    const [testimonials, setTestimonials] = useState<Testimonial[]>(
        programme.testimonials ?? [],
    );

    const richFields = [
        [
            'outcomes_html',
            'Outcome',
            'Describe the result learners should expect by the end of the programme.',
        ],
        [
            'skills_gained_html',
            'Skills gained',
            'List the practical skills learners will develop.',
        ],
        [
            'curriculum_html',
            'Curriculum',
            'Break the curriculum into modules, weeks or learning milestones.',
        ],
        [
            'eligibility_html',
            'Eligibility',
            'Explain prerequisites, age limits or entry requirements.',
        ],
        [
            'audience_html',
            'Who this is for',
            'Describe the ideal audience for this programme.',
        ],
        [
            'demand_html',
            'Why it pays off / demand',
            'Explain the opportunity, demand or value of completing this programme.',
        ],
        [
            'application_process_html',
            'Application process',
            'Explain each step from application to admission.',
        ],
    ] as const;

    return (
        <section className="space-y-6 border-t pt-6">
            <div>
                <p className="font-semibold">Public programme page</p>
                <p className="mt-1 text-sm text-muted-foreground">
                    Optional details shown to prospective applicants. Formatting
                    is cleaned before publishing.
                </p>
            </div>

            <div className="grid gap-4 rounded-2xl border bg-muted/20 p-4 sm:grid-cols-[220px_1fr] sm:items-center">
                <div className="aspect-[4/3] overflow-hidden rounded-xl border bg-background">
                    {programme.image_url ? (
                        <img
                            src={programme.image_url}
                            alt="Current programme hero"
                            className="size-full object-cover"
                        />
                    ) : (
                        <span className="grid size-full place-items-center text-muted-foreground">
                            <ImageIcon className="size-9" aria-hidden="true" />
                        </span>
                    )}
                </div>
                <div>
                    <p className="font-medium">Programme hero image</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Displayed on the right side of the public programme
                        header. Use a wide JPG, PNG or WebP up to 5 MB.
                    </p>
                    <input
                        ref={imageInput}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="sr-only"
                        aria-label="Programme hero image"
                        onChange={(event) =>
                            imageForm.setData(
                                'image',
                                event.target.files?.[0] ?? null,
                            )
                        }
                    />
                    {imageForm.data.image && (
                        <p className="mt-3 truncate text-xs text-muted-foreground">
                            Selected: {imageForm.data.image.name}
                        </p>
                    )}
                    <InputError message={imageForm.errors.image} />
                    <div className="mt-4 flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => imageInput.current?.click()}
                        >
                            <ImageIcon className="size-4" /> Choose image
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            disabled={
                                !imageForm.data.image || imageForm.processing
                            }
                            onClick={() =>
                                imageForm.post(
                                    `/app/programmes/${programme.id}/image`,
                                    {
                                        forceFormData: true,
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            imageForm.reset();
                                            if (imageInput.current)
                                                imageInput.current.value = '';
                                        },
                                    },
                                )
                            }
                        >
                            <Upload className="size-4" />{' '}
                            {imageForm.processing
                                ? 'Uploading…'
                                : 'Upload image'}
                        </Button>
                        {programme.image_url && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="text-destructive"
                                disabled={imageForm.processing}
                                onClick={() =>
                                    imageForm.delete(
                                        `/app/programmes/${programme.id}/image`,
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Trash2 className="size-4" /> Remove
                            </Button>
                        )}
                    </div>
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                {(
                    [
                        [
                            'duration',
                            'Duration',
                            programme.duration,
                            'e.g. 4 Weeks',
                        ],
                        [
                            'delivery_format',
                            'Format',
                            programme.delivery_format,
                            'e.g. Live, Online or Hybrid',
                        ],
                        [
                            'programme_type',
                            'Type',
                            programme.programme_type,
                            'e.g. Workshop, Course or Bootcamp',
                        ],
                        [
                            'public_timezone',
                            'Timezone',
                            programme.public_timezone ?? 'Africa/Lagos',
                            'e.g. Africa/Lagos',
                        ],
                        [
                            'level',
                            'Level',
                            programme.level,
                            'e.g. Beginner or Beginner to expert',
                        ],
                    ] as const
                ).map(([name, label, value, placeholder]) => (
                    <div key={name} className="grid gap-2">
                        <Label htmlFor={`${prefix}-${name}`}>{label}</Label>
                        <Input
                            id={`${prefix}-${name}`}
                            name={name}
                            defaultValue={value ?? ''}
                            placeholder={placeholder}
                        />
                        <InputError message={errors[name]} />
                    </div>
                ))}
            </div>

            <div className="space-y-5">
                {richFields.map(([name, label, placeholder]) => (
                    <RichTextEditor
                        key={name}
                        id={`${prefix}-${name}`}
                        name={name}
                        label={label}
                        defaultValue={programme[name]}
                        placeholder={placeholder}
                        error={errors[name]}
                    />
                ))}
            </div>

            <RepeaterHeader
                title="Tools & platforms"
                description="Add each tool and an optional public logo URL."
                onAdd={() => setTools([...tools, { name: '', logo_url: '' }])}
            />
            <div className="space-y-3">
                {tools.length === 0 && <EmptyRows text="No tools added yet." />}
                {tools.map((tool, index) => (
                    <div
                        key={index}
                        className="grid gap-3 rounded-lg border p-3 sm:grid-cols-[1fr_1.5fr_auto]"
                    >
                        <Input
                            name={`tools_platforms[${index}][name]`}
                            defaultValue={tool.name}
                            placeholder="e.g. Figma"
                            aria-label={`Tool ${index + 1} name`}
                        />
                        <Input
                            name={`tools_platforms[${index}][logo_url]`}
                            defaultValue={tool.logo_url ?? ''}
                            placeholder="https://example.com/logo.svg"
                            aria-label={`Tool ${index + 1} logo URL`}
                        />
                        <RemoveButton
                            label={`Remove tool ${index + 1}`}
                            onClick={() =>
                                setTools(
                                    tools.filter(
                                        (_, itemIndex) => itemIndex !== index,
                                    ),
                                )
                            }
                        />
                        <InputError
                            message={
                                errors[`tools_platforms.${index}.name`] ??
                                errors[`tools_platforms.${index}.logo_url`]
                            }
                        />
                    </div>
                ))}
            </div>

            <RepeaterHeader
                title="Frequently asked questions"
                description="Answer common questions before applicants need to ask."
                onAdd={() =>
                    setFaqs([...faqs, { question: '', answer_html: '' }])
                }
            />
            <div className="space-y-4">
                {faqs.length === 0 && <EmptyRows text="No FAQs added yet." />}
                {faqs.map((faq, index) => (
                    <div
                        key={index}
                        className="space-y-3 rounded-lg border p-4"
                    >
                        <div className="flex gap-2">
                            <Input
                                name={`faqs[${index}][question]`}
                                defaultValue={faq.question}
                                placeholder="e.g. Do I need prior experience?"
                                aria-label={`FAQ ${index + 1} question`}
                            />
                            <RemoveButton
                                label={`Remove FAQ ${index + 1}`}
                                onClick={() =>
                                    setFaqs(
                                        faqs.filter(
                                            (_, itemIndex) =>
                                                itemIndex !== index,
                                        ),
                                    )
                                }
                            />
                        </div>
                        <RichTextEditor
                            id={`${prefix}-faq-${index}`}
                            name={`faqs[${index}][answer_html]`}
                            label={`Answer ${index + 1}`}
                            defaultValue={faq.answer_html}
                            placeholder="Write a clear answer…"
                            error={
                                errors[`faqs.${index}.answer_html`] ??
                                errors[`faqs.${index}.question`]
                            }
                        />
                    </div>
                ))}
            </div>

            <RepeaterHeader
                title="Testimonials"
                description="Add genuine learner or guardian feedback."
                onAdd={() =>
                    setTestimonials([
                        ...testimonials,
                        { quote_html: '', name: '', role: '' },
                    ])
                }
            />
            <div className="space-y-4">
                {testimonials.length === 0 && (
                    <EmptyRows text="No testimonials added yet." />
                )}
                {testimonials.map((testimonial, index) => (
                    <div
                        key={index}
                        className="space-y-3 rounded-lg border p-4"
                    >
                        <RichTextEditor
                            id={`${prefix}-testimonial-${index}`}
                            name={`testimonials[${index}][quote_html]`}
                            label={`Testimonial ${index + 1}`}
                            defaultValue={testimonial.quote_html}
                            placeholder="Add the learner’s feedback…"
                            error={errors[`testimonials.${index}.quote_html`]}
                        />
                        <div className="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                            <Input
                                name={`testimonials[${index}][name]`}
                                defaultValue={testimonial.name}
                                placeholder="Person’s name"
                                aria-label={`Testimonial ${index + 1} name`}
                            />
                            <Input
                                name={`testimonials[${index}][role]`}
                                defaultValue={testimonial.role ?? ''}
                                placeholder="Role, cohort or company"
                                aria-label={`Testimonial ${index + 1} role`}
                            />
                            <RemoveButton
                                label={`Remove testimonial ${index + 1}`}
                                onClick={() =>
                                    setTestimonials(
                                        testimonials.filter(
                                            (_, itemIndex) =>
                                                itemIndex !== index,
                                        ),
                                    )
                                }
                            />
                        </div>
                        <InputError
                            message={
                                errors[`testimonials.${index}.name`] ??
                                errors[`testimonials.${index}.role`]
                            }
                        />
                    </div>
                ))}
            </div>
        </section>
    );
}

function RepeaterHeader({
    title,
    description,
    onAdd,
}: {
    title: string;
    description: string;
    onAdd: () => void;
}) {
    return (
        <div className="flex items-end justify-between gap-3 border-t pt-5">
            <div>
                <p className="font-medium">{title}</p>
                <p className="text-xs text-muted-foreground">{description}</p>
            </div>
            <Button type="button" variant="outline" size="sm" onClick={onAdd}>
                <Plus className="size-4" /> Add
            </Button>
        </div>
    );
}

function RemoveButton({
    label,
    onClick,
}: {
    label: string;
    onClick: () => void;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            aria-label={label}
            onClick={onClick}
        >
            <Trash2 className="size-4 text-destructive" />
        </Button>
    );
}

function EmptyRows({ text }: { text: string }) {
    return (
        <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
            {text}
        </p>
    );
}
