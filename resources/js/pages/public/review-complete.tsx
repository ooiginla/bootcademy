import { Head } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';

export default function ReviewComplete({
    title,
    academy,
}: {
    title: string;
    academy: string;
}) {
    return (
        <>
            <Head title="Response submitted" />
            <main className="grid min-h-screen place-items-center bg-muted/30 p-4">
                <Card className="w-full max-w-lg">
                    <CardContent className="p-8 text-center sm:p-12">
                        <CheckCircle2 className="mx-auto size-12 text-emerald-600" />
                        <p className="mt-5 text-sm font-medium text-primary">
                            {academy}
                        </p>
                        <h1 className="mt-2 text-2xl font-semibold">
                            Response submitted
                        </h1>
                        <p className="mt-3 text-sm leading-6 text-muted-foreground">
                            Thank you for completing “{title}”. Your feedback
                            has been received.
                        </p>
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
