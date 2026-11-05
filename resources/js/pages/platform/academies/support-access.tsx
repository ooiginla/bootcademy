import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, ShieldAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    managedAcademy: {
        id: number;
        name: string;
    };
};

export default function SupportAccess({ managedAcademy }: Props) {
    return (
        <>
            <Head title={`Support access · ${managedAcademy.name}`} />
            <main className="mx-auto w-full max-w-3xl p-4 sm:p-6 lg:p-8">
                <Button variant="ghost" className="mb-5 -ml-3" asChild>
                    <Link href="/platform/academies">
                        <ArrowLeft className="size-4" />
                        All academies
                    </Link>
                </Button>
                <Card>
                    <CardHeader>
                        <span className="grid size-11 place-items-center rounded-xl bg-amber-500/10 text-amber-700 dark:text-amber-400">
                            <ShieldAlert className="size-5" />
                        </span>
                        <h1 className="mt-3 text-xl font-semibold">
                            Act as owner of {managedAcademy.name}
                        </h1>
                        <p className="text-sm leading-6 text-muted-foreground">
                            You keep your Platform Super Admin identity. Owner
                            access is prominently marked, applies to this
                            academy until you exit, and is fully audited.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action={`/platform/academies/${managedAcademy.id}/support-access`}
                            method="post"
                            className="space-y-5"
                        >
                            {({ processing }) => (
                                <>
                                    <div className="rounded-xl border border-amber-500/30 bg-amber-500/5 p-4 text-sm">
                                        <p className="font-medium text-amber-900 dark:text-amber-200">
                                            Full owner access
                                        </p>
                                        <p className="mt-1 text-muted-foreground">
                                            There is no time limit. Access
                                            remains active until you explicitly
                                            exit Support Mode. Entry and
                                            activity are recorded against your
                                            Super Admin account.
                                        </p>
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}Enter Support
                                        Mode
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
