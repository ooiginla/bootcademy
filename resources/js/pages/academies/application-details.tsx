import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ReceiptText, UserRound } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCurrency } from '@/lib/money';
import type { CustomField, Option } from './players';
import {
    ApplicationDialog,
    type AcademyApplication,
    type BankDetails,
    type Invoice,
    type ProgrammeCycleOption,
    InvoiceAmountBadges,
    RecordPaymentDialog,
    ReviewDialog,
} from './applications';

type ApplicationPayment = {
    id: number;
    reference: string;
    external_reference: string | null;
    currency: string;
    amount: string;
    method: string;
    status: string;
    received_on: string;
};
type DetailedInvoice = Invoice & {
    items: Array<{ description: string; amount: string }>;
};
type ApplicationDetails = AcademyApplication & {
    invoices: DetailedInvoice[];
    payments: ApplicationPayment[];
};

export default function ApplicationDetailsPage({
    application,
    programmes,
    branches,
    active_cycles,
    programme_cycles,
    countries,
    custom_fields,
    bank_details,
}: {
    application: ApplicationDetails;
    programmes: Option[];
    branches: Option[];
    active_cycles: Option[];
    programme_cycles: ProgrammeCycleOption[];
    countries: string[];
    custom_fields: CustomField[];
    bank_details: BankDetails;
}) {
    const fullName = [
        application.first_name,
        application.middle_name,
        application.last_name,
    ]
        .filter(Boolean)
        .join(' ');
    return (
        <>
            <Head title={fullName} />
            <main className="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
                <Button asChild variant="ghost" className="mb-4 -ml-3">
                    <Link href="/app/applications">
                        <ArrowLeft className="size-4" /> Back to applications
                    </Link>
                </Button>
                <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            {application.reference}
                        </p>
                        <div className="mt-1 flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold">
                                {fullName}
                            </h1>
                            <Badge variant="secondary">
                                {application.status.replace('_', ' ')}
                            </Badge>
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Submitted {application.created_at} ·{' '}
                            {application.source === 'api'
                                ? 'Website API'
                                : 'Academy admin'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {application.status !== 'enrolled' && (
                            <ApplicationDialog
                                application={application}
                                programmes={programmes}
                                branches={branches}
                                activeCycles={active_cycles}
                                programmeCycles={programme_cycles}
                                countries={countries}
                                customFields={custom_fields}
                            />
                        )}
                        <ReviewDialog
                            application={application}
                            programmes={programmes}
                            branches={branches}
                            customFields={custom_fields}
                        />
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <UserRound className="size-5 text-primary" />
                                Applicant information
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
                            <Detail
                                label="Date of birth"
                                value={application.date_of_birth}
                            />
                            <Detail
                                label="Age"
                                value={
                                    application.age
                                        ? `${application.age} years`
                                        : null
                                }
                            />
                            <Detail label="Gender" value={application.gender} />
                            <Detail
                                label="Internal ID"
                                value={application.internal_identifier}
                            />
                            <Detail label="Phone" value={application.phone} />
                            <Detail
                                label="WhatsApp"
                                value={application.whatsapp_number}
                            />
                            <Detail
                                label="Address"
                                value={application.home_address}
                            />
                            <Detail
                                label="Location"
                                value={[
                                    application.city,
                                    application.state_region,
                                    application.country,
                                ]
                                    .filter(Boolean)
                                    .join(', ')}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Guardian and application</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
                            <Detail
                                label="Guardian"
                                value={application.guardian_name}
                            />
                            <Detail
                                label="Relationship"
                                value={application.guardian_relationship}
                            />
                            <Detail
                                label="Email"
                                value={application.guardian_email}
                            />
                            <Detail
                                label="Phone"
                                value={application.guardian_phone}
                            />
                            <Detail
                                label="WhatsApp"
                                value={application.guardian_whatsapp_number}
                            />
                            <Detail
                                label="Signature"
                                value={
                                    application.has_guardian_signature
                                        ? 'Supplied'
                                        : 'Not supplied'
                                }
                            />
                            <Detail
                                label="Cycle"
                                value={application.cycle_name}
                            />
                            <Detail
                                label="Programme"
                                value={application.programme_name}
                            />
                            <Detail
                                label="Branch"
                                value={application.branch_name}
                            />
                        </CardContent>
                    </Card>
                </div>

                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ReceiptText className="size-5 text-primary" />
                            Fees and payments
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {application.invoices.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No fee invoice exists for this application. The
                                selected programme may not charge an application
                                fee yet.
                            </p>
                        ) : (
                            <div className="space-y-3">
                                {application.invoices.map((invoice) => (
                                    <div
                                        key={invoice.id}
                                        className="rounded-lg border p-4"
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <p className="font-medium">
                                                    {invoice.purpose ===
                                                    'application'
                                                        ? 'Application fee'
                                                        : 'Programme fees'}{' '}
                                                    ·{' '}
                                                    {application.programme_name}{' '}
                                                    · {application.cycle_name}
                                                </p>
                                                <InvoiceAmountBadges
                                                    invoice={invoice}
                                                />
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Badge variant="secondary">
                                                    {invoice.status.replace(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </Badge>
                                                <RecordPaymentDialog
                                                    invoice={invoice}
                                                    bankDetails={bank_details}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        <section>
                            <h2 className="mb-3 font-semibold">
                                Payment history
                            </h2>
                            {application.payments.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No verified payment has been captured.
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[720px] text-left text-sm">
                                        <thead className="border-b text-xs text-muted-foreground uppercase">
                                            <tr>
                                                <th className="py-2">Date</th>
                                                <th>Reference</th>
                                                <th>Method</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {application.payments.map(
                                                (payment) => (
                                                    <tr
                                                        key={payment.id}
                                                        className="border-b last:border-0"
                                                    >
                                                        <td className="py-3">
                                                            {
                                                                payment.received_on
                                                            }
                                                        </td>
                                                        <td>
                                                            {payment.external_reference ??
                                                                payment.reference}
                                                        </td>
                                                        <td>
                                                            {payment.method.replace(
                                                                '_',
                                                                ' ',
                                                            )}
                                                        </td>
                                                        <td>
                                                            {formatCurrency(
                                                                payment.currency,
                                                                payment.amount,
                                                            )}
                                                        </td>
                                                        <td>
                                                            {payment.status}
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                    </CardContent>
                </Card>
            </main>
        </>
    );
}

function Detail({
    label,
    value,
}: {
    label: string;
    value: string | number | null | undefined;
}) {
    return (
        <div>
            <p className="text-xs text-muted-foreground uppercase">{label}</p>
            <p className="mt-1">{value || 'Not supplied'}</p>
        </div>
    );
}
