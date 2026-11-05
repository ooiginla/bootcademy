import { Head, router } from '@inertiajs/react';
import { Building2, MailCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Invitation = {
    id: number;
    academy_name: string;
    academy_type: string;
    role_label: string;
};

export default function AcademyInvitations({
    invitations,
}: {
    invitations: Invitation[];
}) {
    return (
        <>
            <Head title="Academy invitations" />
            <main className="mx-auto w-full max-w-3xl p-4 sm:p-6 lg:p-8">
                <div className="mb-7">
                    <p className="text-sm font-medium text-primary">
                        Your access
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                        Academy invitations
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Accept an invitation to add that academy to your
                        workspace switcher.
                    </p>
                </div>

                {invitations.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center">
                            <MailCheck className="mx-auto size-8 text-muted-foreground" />
                            <h2 className="mt-4 font-semibold">
                                No pending invitations
                            </h2>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {invitations.map((invitation) => (
                            <Card key={invitation.id}>
                                <CardContent className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                                    <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                                        <Building2 className="size-5" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <h2 className="truncate font-medium">
                                            {invitation.academy_name}
                                        </h2>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {invitation.academy_type} ·{' '}
                                            {invitation.role_label}
                                        </p>
                                    </div>
                                    <Button
                                        onClick={() =>
                                            router.post(
                                                `/app/invitations/${invitation.id}/accept`,
                                            )
                                        }
                                    >
                                        Accept invitation
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </main>
        </>
    );
}
