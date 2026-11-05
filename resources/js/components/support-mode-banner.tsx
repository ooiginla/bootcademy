import { Form, usePage } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

export function SupportModeBanner() {
    const { support_access } = usePage().props;

    if (!support_access) return null;

    return (
        <div className="mx-4 mt-4 flex flex-col gap-3 rounded-2xl border border-amber-500/40 bg-amber-50 px-4 py-3 text-amber-950 shadow-sm sm:mx-6 sm:flex-row sm:items-center lg:mx-8 dark:bg-amber-950/30 dark:text-amber-100">
            <ShieldAlert
                className="size-5 shrink-0 text-amber-700 dark:text-amber-400"
                aria-hidden="true"
            />
            <div className="min-w-0 flex-1">
                <p className="font-semibold">
                    Support Mode · {support_access.academy_name}
                </p>
                <p className="mt-0.5 truncate text-xs opacity-80">
                    Full owner access · Active until you exit
                </p>
            </div>
            <Form action="/platform/support-access" method="delete">
                {({ processing }) => (
                    <Button
                        type="submit"
                        size="sm"
                        variant="outline"
                        disabled={processing}
                        className="border-amber-600/40 bg-white/60 hover:bg-white dark:bg-transparent"
                    >
                        {processing && <Spinner />}
                        Exit Support Mode
                    </Button>
                )}
            </Form>
        </div>
    );
}
