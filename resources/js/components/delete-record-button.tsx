import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

type DeleteRecordButtonProps = {
    /** DELETE endpoint for the record, e.g. `/app/branches/12`. */
    action: string;
    /** Lower-case record type shown to the user, e.g. "branch". */
    label: string;
    /** Name of the specific record, e.g. "Oniwaya". */
    name: string;
    /** Extra sentence explaining what deleting this record means. */
    description?: string;
    /** Action word for the dialog title and confirm button; defaults to "Delete". */
    verb?: string;
    /** Trigger button text; defaults to the verb. */
    triggerLabel?: string;
    size?: 'sm' | 'default' | 'icon';
    variant?: 'destructive' | 'outline' | 'ghost';
};

/**
 * Confirms and submits a soft delete. The server refuses while anything still
 * belongs to the record, and that reason is shown inside the dialog.
 */
export default function DeleteRecordButton({
    action,
    label,
    name,
    description,
    verb = 'Delete',
    triggerLabel = verb,
    size = 'sm',
    variant = 'outline',
}: DeleteRecordButtonProps) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant={variant}
                    size={size}
                    aria-label={`${verb} ${label} ${name}`}
                    title={`${verb} ${label}`}
                    className={
                        variant === 'destructive'
                            ? undefined
                            : 'text-destructive hover:text-destructive'
                    }
                >
                    <Trash2 className="size-4" />
                    {size === 'icon' ? null : triggerLabel}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogTitle>
                    {verb} {label} “{name}”?
                </DialogTitle>
                <DialogDescription>
                    {description ??
                        `The ${label} will be removed from lists and forms. Its history is kept in the audit log.`}
                </DialogDescription>
                <Form
                    action={action}
                    method="delete"
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <InputError
                                message={errors.delete}
                                className="rounded-md border border-destructive/30 bg-destructive/5 p-3"
                            />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    {processing ? <Spinner /> : null}
                                    {verb} {label}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
