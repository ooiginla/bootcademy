import { Form, Head } from '@inertiajs/react';
import {
    Braces,
    Check,
    Clipboard,
    KeyRound,
    Plus,
    ShieldOff,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type ApiKey = {
    id: number;
    name: string;
    prefix: string;
    last_used_at: string | null;
    created_at: string | null;
    revoked_at: string | null;
};

function CreateKeyDialog() {
    const [open, setOpen] = useState(false);
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus className="size-4" />
                    Create API key
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create API key</DialogTitle>
                    <DialogDescription>
                        Use a descriptive name for the website or integration.
                        The secret is displayed only once.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action="/app/settings/api"
                    method="post"
                    className="space-y-4"
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="key-name">
                                    Integration name
                                </Label>
                                <Input
                                    id="key-name"
                                    name="name"
                                    placeholder="Academy website"
                                    required
                                    maxLength={100}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Create key
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function ApiIntegrations({
    api_keys,
    new_api_secret,
}: {
    api_keys: ApiKey[];
    new_api_secret: string | null;
}) {
    const [copied, setCopied] = useState(false);
    async function copySecret() {
        if (!new_api_secret) return;
        await navigator.clipboard.writeText(new_api_secret);
        setCopied(true);
    }

    return (
        <>
            <Head title="API integrations" />
            <main className="mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-8">
                <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Academy settings
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold">
                            API integrations
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                            Securely receive applicant registrations from your
                            academy website without creating active student
                            profiles prematurely.
                        </p>
                    </div>
                    <CreateKeyDialog />
                </div>

                {new_api_secret && (
                    <div className="mb-6 rounded-xl border border-amber-500/40 bg-amber-500/5 p-5">
                        <h2 className="font-semibold">Copy this secret now</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            It is stored as a one-way hash and cannot be shown
                            again.
                        </p>
                        <div className="mt-4 flex flex-col gap-2 sm:flex-row">
                            <Input
                                value={new_api_secret}
                                readOnly
                                aria-label="New API secret"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                onClick={copySecret}
                            >
                                {copied ? (
                                    <Check className="size-4" />
                                ) : (
                                    <Clipboard className="size-4" />
                                )}
                                {copied ? 'Copied' : 'Copy'}
                            </Button>
                        </div>
                    </div>
                )}

                <div className="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
                    <Card className="overflow-hidden py-0">
                        <CardHeader className="border-b pt-6">
                            <CardTitle>API keys</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            {api_keys.length === 0 ? (
                                <div className="py-14 text-center">
                                    <KeyRound className="mx-auto size-9 text-muted-foreground" />
                                    <h2 className="mt-4 font-semibold">
                                        No API keys
                                    </h2>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        Create a key for your first website
                                        integration.
                                    </p>
                                </div>
                            ) : (
                                <div className="divide-y">
                                    {api_keys.map((apiKey) => (
                                        <div
                                            key={apiKey.id}
                                            className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium">
                                                        {apiKey.name}
                                                    </p>
                                                    <Badge
                                                        variant={
                                                            apiKey.revoked_at
                                                                ? 'destructive'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {apiKey.revoked_at
                                                            ? 'Revoked'
                                                            : 'Active'}
                                                    </Badge>
                                                </div>
                                                <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                    {apiKey.prefix}••••••••
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {apiKey.last_used_at
                                                        ? `Last used ${new Date(apiKey.last_used_at).toLocaleString()}`
                                                        : 'Never used'}
                                                </p>
                                            </div>
                                            {!apiKey.revoked_at && (
                                                <Form
                                                    action={`/app/settings/api/${apiKey.id}/revoke`}
                                                    method="patch"
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            variant="outline"
                                                            size="sm"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            <ShieldOff className="size-4" />
                                                            Revoke
                                                        </Button>
                                                    )}
                                                </Form>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Braces className="size-5" /> API workflow
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            <p>
                                Send the key as{' '}
                                <code>Authorization: Bearer YOUR_KEY</code>.
                                Never place it in browser JavaScript; requests
                                should come from your website server.
                            </p>
                            <div className="rounded-lg bg-muted p-3 font-mono text-xs">
                                <p>GET /api/v1/configuration</p>
                                <p>POST /api/v1/applications</p>
                                <p>
                                    GET
                                    /api/v1/applications/status?external_reference=...
                                </p>
                            </div>
                            <p className="text-muted-foreground">
                                Every submission needs a unique external
                                reference, explicit guardian consent and the
                                privacy-policy version shown on the source form.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </main>
        </>
    );
}
