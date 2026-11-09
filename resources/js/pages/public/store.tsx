import { useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Minus,
    Package,
    Plus,
    ShoppingBag,
    ShoppingCart,
} from 'lucide-react';
import type { CSSProperties } from 'react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import PublicSiteShell from '@/components/public-site/public-site-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatCurrency } from '@/lib/money';
import type { PublicAcademy, PublicSite } from '@/types/public-site';

type Variant = {
    id: number;
    name: string;
    price: string;
    attributes: Record<string, string>;
    track_inventory: boolean;
    availability: Record<string, number>;
};
type Product = {
    id: number;
    name: string;
    description: string | null;
    category: string | null;
    tags: string[];
    currency: string;
    price: string;
    image_url: string | null;
    variants: Variant[];
};
type CartLine = { product: Product; variant: Variant; quantity: number };
type CheckoutStep = 1 | 2 | 3 | 4;

const checkoutSteps = ['Cart', 'Details', 'Notes', 'Payment'] as const;
const isOutOfStock = (variant: Variant) =>
    variant.track_inventory &&
    Object.values(variant.availability).reduce(
        (total, quantity) => total + quantity,
        0,
    ) < 1;

export default function Store({
    academy,
    site,
    base_url,
    products,
    categories,
    locations,
    bank_details,
    paystack_ready,
}: {
    academy: PublicAcademy;
    site: PublicSite;
    base_url: string;
    products: Product[];
    categories: Array<{ id: number; name: string; slug: string }>;
    locations: Array<{ id: number; name: string }>;
    bank_details: {
        bank_name: string | null;
        account_name: string | null;
        account_number: string | null;
    };
    paystack_ready: boolean;
}) {
    const [category, setCategory] = useState('all');
    const [query, setQuery] = useState('');
    const [cart, setCart] = useState<CartLine[]>([]);
    const [cartOpen, setCartOpen] = useState(false);
    const [step, setStep] = useState<CheckoutStep>(1);
    const [stepError, setStepError] = useState('');
    const [submissionError, setSubmissionError] = useState('');
    const checkout = useForm({
        customer_name: '',
        customer_email: '',
        customer_phone: '',
        member_name: '',
        branch_id: '',
        delivery_method: 'pickup',
        delivery_address: '',
        notes: '',
        payment_option: paystack_ready
            ? 'paystack'
            : bank_details.account_number
              ? 'bank_transfer'
              : 'pay_later',
        items: [] as Array<{ variant_id: number; quantity: number }>,
    });
    const shown = products.filter(
        (product) =>
            (category === 'all' || product.category === category) &&
            product.name.toLowerCase().includes(query.toLowerCase()),
    );
    const cartCount = cart.reduce((total, line) => total + line.quantity, 0);
    const cartTotal = useMemo(
        () =>
            cart.reduce(
                (sum, line) => sum + Number(line.variant.price) * line.quantity,
                0,
            ),
        [cart],
    );

    const add = (product: Product, variant: Variant) => {
        setCart((current) => {
            const found = current.find(
                (line) => line.variant.id === variant.id,
            );
            return found
                ? current.map((line) =>
                      line.variant.id === variant.id
                          ? { ...line, quantity: line.quantity + 1 }
                          : line,
                  )
                : [...current, { product, variant, quantity: 1 }];
        });
        toast.success(`${product.name} added to cart.`);
        setStep(1);
        setStepError('');
        setCartOpen(true);
    };
    const setQuantity = (variantId: number, quantity: number) =>
        setCart((current) =>
            current.map((line) =>
                line.variant.id === variantId
                    ? { ...line, quantity: Math.max(1, quantity) }
                    : line,
            ),
        );
    const remove = (variantId: number) =>
        setCart((current) =>
            current.filter((line) => line.variant.id !== variantId),
        );
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        setSubmissionError('');
        checkout.transform((data) => ({
            ...data,
            branch_id: data.branch_id || null,
            items: cart.map((line) => ({
                variant_id: line.variant.id,
                quantity: line.quantity,
            })),
        }));
        checkout.post(`${base_url}/store/orders`, {
            onSuccess: () => {
                setCart([]);
                setCartOpen(false);
                setStep(1);
                checkout.reset();
            },
            onError: (errors) => {
                const firstError = Object.values(errors).find(
                    (error): error is string =>
                        typeof error === 'string' && error.trim() !== '',
                );
                const errorMessage =
                    firstError ??
                    'The order could not be placed. Review the highlighted details and try again.';
                setSubmissionError(errorMessage);
                setStep(4);
                setCartOpen(true);
                toast.error(errorMessage);
            },
        });
    };
    const continueFromDetails = () => {
        if (
            !checkout.data.customer_name.trim() ||
            !checkout.data.customer_email.includes('@') ||
            !checkout.data.customer_phone.trim()
        ) {
            const error =
                'Enter your name, a valid email and phone number before continuing.';
            setStepError(error);
            toast.error(error);
            return;
        }
        if (
            checkout.data.delivery_method === 'delivery' &&
            !checkout.data.delivery_address.trim()
        ) {
            const error = 'Enter the delivery address before continuing.';
            setStepError(error);
            toast.error(error);
            return;
        }
        setStepError('');
        setStep(3);
    };
    const openCart = () => {
        setStep(1);
        setStepError('');
        setCartOpen(true);
    };

    return (
        <PublicSiteShell
            academy={academy}
            site={site}
            baseUrl={base_url}
            title={`Store · ${academy.name}`}
        >
            <main className="min-h-[70vh] bg-[#f7f7f2]">
                <section className="bg-slate-950 px-5 py-16 text-white sm:px-8">
                    <div className="mx-auto flex max-w-7xl flex-wrap items-end justify-between gap-6">
                        <div>
                            <p className="text-xs font-bold tracking-[.2em] text-[var(--site-primary)] uppercase">
                                Official academy store
                            </p>
                            <h1 className="mt-3 text-4xl font-black tracking-tight sm:text-5xl">
                                {String(site.sections.store?.title ?? 'Store')}
                            </h1>
                            <p className="mt-4 max-w-xl text-white/65">
                                {String(site.sections.store?.intro ?? '')}
                            </p>
                        </div>
                        <Button
                            onClick={openCart}
                            className="rounded-full bg-[var(--site-primary)] text-[var(--site-primary-fg)] hover:opacity-90"
                        >
                            <ShoppingCart className="size-4" /> Cart (
                            {cartCount})
                        </Button>
                    </div>
                </section>
                <section className="mx-auto max-w-7xl px-5 py-10 sm:px-8">
                    <div className="mb-8 flex flex-wrap gap-3">
                        <Input
                            className="max-w-sm bg-white"
                            placeholder="Search products"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                        />
                        <select
                            className="h-10 rounded-md border bg-white px-3"
                            value={category}
                            onChange={(event) =>
                                setCategory(event.target.value)
                            }
                        >
                            <option value="all">All categories</option>
                            {categories.map((item) => (
                                <option key={item.id} value={item.name}>
                                    {item.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    {shown.length === 0 ? (
                        <div className="rounded-3xl border bg-white p-14 text-center">
                            <Package className="mx-auto size-10 text-slate-400" />
                            <h2 className="mt-4 text-xl font-bold">
                                No products found
                            </h2>
                            <p className="mt-2 text-slate-500">
                                Try another search or check back soon.
                            </p>
                        </div>
                    ) : (
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {shown.map((product) => (
                                <article
                                    key={product.id}
                                    className="overflow-hidden rounded-3xl border bg-white shadow-sm"
                                >
                                    <div className="grid aspect-[4/3] place-items-center bg-slate-100">
                                        {product.image_url ? (
                                            <img
                                                src={product.image_url}
                                                alt={product.name}
                                                className="size-full object-cover"
                                            />
                                        ) : (
                                            <ShoppingBag className="size-14 text-slate-300" />
                                        )}
                                    </div>
                                    <div className="p-6">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-xs font-bold text-[var(--site-primary)] uppercase">
                                                    {product.category ??
                                                        'Merchandise'}
                                                </p>
                                                <h2 className="mt-1 text-xl font-black">
                                                    {product.name}
                                                </h2>
                                            </div>
                                            <strong>
                                                {formatCurrency(
                                                    product.currency,
                                                    product.price,
                                                )}
                                            </strong>
                                        </div>
                                        {product.description && (
                                            <p className="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">
                                                {product.description}
                                            </p>
                                        )}
                                        <div className="mt-5 grid gap-2">
                                            {product.variants.map((variant) => (
                                                <Button
                                                    key={variant.id}
                                                    variant="outline"
                                                    onClick={() =>
                                                        add(product, variant)
                                                    }
                                                    disabled={isOutOfStock(
                                                        variant,
                                                    )}
                                                >
                                                    {isOutOfStock(variant) ? (
                                                        <>
                                                            Out of stock
                                                            {variant.name !==
                                                            'Default'
                                                                ? ` · ${variant.name}`
                                                                : ''}
                                                        </>
                                                    ) : (
                                                        <>
                                                            <Plus className="size-4" />{' '}
                                                            Add{' '}
                                                            {variant.name !==
                                                            'Default'
                                                                ? variant.name
                                                                : ''}
                                                            {variant.price !==
                                                            product.price
                                                                ? ` · ${formatCurrency(product.currency, variant.price)}`
                                                                : ''}
                                                        </>
                                                    )}
                                                </Button>
                                            ))}
                                        </div>
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </section>
            </main>

            <Sheet open={cartOpen} onOpenChange={setCartOpen}>
                <SheetContent
                    className="w-full gap-0 p-0 sm:max-w-xl"
                    style={
                        {
                            '--site-primary': academy.theme.primary,
                            '--site-primary-fg':
                                academy.theme.primary_foreground,
                        } as CSSProperties
                    }
                >
                    <SheetHeader className="border-b px-6 py-6 pr-14">
                        <SheetTitle className="text-2xl font-black">
                            Checkout
                        </SheetTitle>
                        <SheetDescription>
                            Complete your purchase
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid grid-cols-4 border-b px-5 py-5">
                        {checkoutSteps.map((label, index) => {
                            const number = (index + 1) as CheckoutStep;
                            const active = step === number;
                            const complete = step > number;
                            return (
                                <div
                                    key={label}
                                    className="flex flex-col items-center gap-2 text-center"
                                >
                                    <span
                                        className={`grid size-9 place-items-center rounded-full text-sm font-bold ${active ? 'bg-[var(--site-primary)] text-[var(--site-primary-fg)]' : complete ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'}`}
                                    >
                                        {complete ? (
                                            <Check className="size-4" />
                                        ) : (
                                            number
                                        )}
                                    </span>
                                    <span
                                        className={`text-xs font-medium sm:text-sm ${active ? 'text-[var(--site-primary)]' : 'text-slate-500'}`}
                                    >
                                        {label}
                                    </span>
                                </div>
                            );
                        })}
                    </div>

                    {cart.length === 0 ? (
                        <div className="grid flex-1 place-items-center p-8 text-center">
                            <div>
                                <ShoppingCart className="mx-auto size-10 text-slate-300" />
                                <p className="mt-4 font-bold">
                                    Your cart is empty
                                </p>
                                <p className="mt-1 text-sm text-slate-500">
                                    Add an item from the store to begin.
                                </p>
                            </div>
                        </div>
                    ) : (
                        <form
                            onSubmit={submit}
                            className="flex min-h-0 flex-1 flex-col"
                        >
                            <div className="flex-1 overflow-y-auto p-5 sm:p-6">
                                {step === 1 && (
                                    <div className="space-y-4">
                                        {cart.map((line) => (
                                            <article
                                                key={line.variant.id}
                                                className="flex gap-4 rounded-2xl border p-4"
                                            >
                                                <div className="grid size-24 shrink-0 place-items-center overflow-hidden rounded-xl bg-slate-100">
                                                    {line.product.image_url ? (
                                                        <img
                                                            src={
                                                                line.product
                                                                    .image_url
                                                            }
                                                            alt=""
                                                            className="size-full object-cover"
                                                        />
                                                    ) : (
                                                        <ShoppingBag className="size-8 text-slate-300" />
                                                    )}
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <h3 className="font-bold">
                                                        {line.product.name}
                                                    </h3>
                                                    <p className="text-sm text-slate-500">
                                                        {line.variant.name} ·{' '}
                                                        {formatCurrency(
                                                            line.product
                                                                .currency,
                                                            line.variant.price,
                                                        )}
                                                    </p>
                                                    <div className="mt-4 flex flex-wrap items-center gap-3">
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="outline"
                                                            aria-label={`Reduce ${line.product.name} quantity`}
                                                            onClick={() =>
                                                                setQuantity(
                                                                    line.variant
                                                                        .id,
                                                                    line.quantity -
                                                                        1,
                                                                )
                                                            }
                                                        >
                                                            <Minus className="size-3" />
                                                        </Button>
                                                        <span className="min-w-6 text-center font-bold">
                                                            {line.quantity}
                                                        </span>
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="outline"
                                                            aria-label={`Increase ${line.product.name} quantity`}
                                                            onClick={() =>
                                                                setQuantity(
                                                                    line.variant
                                                                        .id,
                                                                    line.quantity +
                                                                        1,
                                                                )
                                                            }
                                                        >
                                                            <Plus className="size-3" />
                                                        </Button>
                                                        <button
                                                            type="button"
                                                            className="ml-auto text-sm font-medium text-red-600 hover:underline"
                                                            onClick={() =>
                                                                remove(
                                                                    line.variant
                                                                        .id,
                                                                )
                                                            }
                                                        >
                                                            Remove
                                                        </button>
                                                    </div>
                                                </div>
                                            </article>
                                        ))}
                                    </div>
                                )}

                                {step === 2 && (
                                    <div className="space-y-5">
                                        <div>
                                            <h3 className="text-lg font-bold">
                                                Customer and fulfilment details
                                            </h3>
                                            <p className="text-sm text-slate-500">
                                                We will use these details for
                                                your receipt and order updates.
                                            </p>
                                        </div>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <Label>Name</Label>
                                                <Input
                                                    required
                                                    value={
                                                        checkout.data
                                                            .customer_name
                                                    }
                                                    onChange={(event) =>
                                                        checkout.setData(
                                                            'customer_name',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <Label>Email</Label>
                                                <Input
                                                    required
                                                    type="email"
                                                    value={
                                                        checkout.data
                                                            .customer_email
                                                    }
                                                    onChange={(event) =>
                                                        checkout.setData(
                                                            'customer_email',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <Label>Phone</Label>
                                                <Input
                                                    required
                                                    value={
                                                        checkout.data
                                                            .customer_phone
                                                    }
                                                    onChange={(event) =>
                                                        checkout.setData(
                                                            'customer_phone',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <Label>
                                                    {academy.member_singular}{' '}
                                                    name (optional)
                                                </Label>
                                                <Input
                                                    value={
                                                        checkout.data
                                                            .member_name
                                                    }
                                                    onChange={(event) =>
                                                        checkout.setData(
                                                            'member_name',
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder="Who is this order for?"
                                                />
                                            </div>
                                            <div className="sm:col-span-2">
                                                <Label>
                                                    Preferred pickup / service
                                                    location (optional)
                                                </Label>
                                                <select
                                                    className="h-10 w-full rounded-md border bg-background px-3"
                                                    value={
                                                        checkout.data.branch_id
                                                    }
                                                    onChange={(event) =>
                                                        checkout.setData(
                                                            'branch_id',
                                                            event.target.value,
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        No location preference
                                                    </option>
                                                    {locations.map((item) => (
                                                        <option
                                                            key={item.id}
                                                            value={item.id}
                                                        >
                                                            {item.name}
                                                        </option>
                                                    ))}
                                                </select>
                                                <p className="mt-1 text-xs text-slate-500">
                                                    This tells the academy where
                                                    you are closest to or hope
                                                    to collect. Stock is still
                                                    fulfilled from combined
                                                    inventory across all
                                                    branches.
                                                </p>
                                            </div>
                                            <div>
                                                <Label>Delivery method</Label>
                                                <select
                                                    className="h-10 w-full rounded-md border bg-background px-3"
                                                    value={
                                                        checkout.data
                                                            .delivery_method
                                                    }
                                                    onChange={(event) =>
                                                        checkout.setData(
                                                            'delivery_method',
                                                            event.target.value,
                                                        )
                                                    }
                                                >
                                                    <option value="pickup">
                                                        Pick up
                                                    </option>
                                                    <option value="delivery">
                                                        Delivery
                                                    </option>
                                                </select>
                                            </div>
                                            {checkout.data.delivery_method ===
                                                'delivery' && (
                                                <div>
                                                    <Label>
                                                        Delivery address
                                                    </Label>
                                                    <Input
                                                        required
                                                        value={
                                                            checkout.data
                                                                .delivery_address
                                                        }
                                                        onChange={(event) =>
                                                            checkout.setData(
                                                                'delivery_address',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                </div>
                                            )}
                                        </div>
                                        {stepError && (
                                            <p
                                                role="alert"
                                                className="rounded-lg bg-red-50 p-3 text-sm text-red-700"
                                            >
                                                {stepError}
                                            </p>
                                        )}
                                    </div>
                                )}

                                {step === 3 && (
                                    <div className="space-y-5">
                                        <div>
                                            <h3 className="text-lg font-bold">
                                                Order notes
                                            </h3>
                                            <p className="text-sm text-slate-500">
                                                Add any sizing, collection or
                                                delivery instruction for the
                                                academy.
                                            </p>
                                        </div>
                                        <div>
                                            <Label>Note (optional)</Label>
                                            <textarea
                                                className="mt-1 min-h-40 w-full rounded-xl border bg-background p-3"
                                                maxLength={1000}
                                                value={checkout.data.notes}
                                                onChange={(event) =>
                                                    checkout.setData(
                                                        'notes',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="e.g. Jersey name, preferred collection time or delivery instruction"
                                            />
                                            <p className="mt-1 text-right text-xs text-slate-400">
                                                {checkout.data.notes.length}
                                                /1000
                                            </p>
                                        </div>
                                    </div>
                                )}

                                {step === 4 && (
                                    <div className="space-y-5">
                                        <div>
                                            <h3 className="text-lg font-bold">
                                                Choose payment
                                            </h3>
                                            <p className="text-sm text-slate-500">
                                                Your order is created when you
                                                continue. Stock and all totals
                                                are rechecked by the server.
                                            </p>
                                        </div>
                                        {paystack_ready && (
                                            <label className="flex cursor-pointer gap-3 rounded-xl border p-4 has-checked:border-[var(--site-primary)] has-checked:bg-[color-mix(in_srgb,var(--site-primary)_8%,white)]">
                                                <input
                                                    type="radio"
                                                    name="payment-option"
                                                    value="paystack"
                                                    checked={
                                                        checkout.data
                                                            .payment_option ===
                                                        'paystack'
                                                    }
                                                    onChange={(event) =>
                                                        checkout.setData(
                                                            'payment_option',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                                <span>
                                                    <strong className="block">
                                                        Pay online with Paystack
                                                    </strong>
                                                    <span className="text-sm text-slate-500">
                                                        Secure card, bank or
                                                        transfer payment.
                                                    </span>
                                                </span>
                                            </label>
                                        )}
                                        {bank_details.account_number && (
                                            <label className="flex cursor-pointer gap-3 rounded-xl border p-4 has-checked:border-[var(--site-primary)] has-checked:bg-[color-mix(in_srgb,var(--site-primary)_8%,white)]">
                                                <input
                                                    type="radio"
                                                    name="payment-option"
                                                    value="bank_transfer"
                                                    checked={
                                                        checkout.data
                                                            .payment_option ===
                                                        'bank_transfer'
                                                    }
                                                    onChange={(event) =>
                                                        checkout.setData(
                                                            'payment_option',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                                <span>
                                                    <strong className="block">
                                                        Academy bank transfer
                                                    </strong>
                                                    <span className="text-sm text-slate-500">
                                                        {bank_details.bank_name}{' '}
                                                        ·{' '}
                                                        {
                                                            bank_details.account_name
                                                        }{' '}
                                                        ·{' '}
                                                        {
                                                            bank_details.account_number
                                                        }
                                                    </span>
                                                </span>
                                            </label>
                                        )}
                                        <label className="flex cursor-pointer gap-3 rounded-xl border p-4 has-checked:border-[var(--site-primary)] has-checked:bg-[color-mix(in_srgb,var(--site-primary)_8%,white)]">
                                            <input
                                                type="radio"
                                                name="payment-option"
                                                value="pay_later"
                                                checked={
                                                    checkout.data
                                                        .payment_option ===
                                                    'pay_later'
                                                }
                                                onChange={(event) =>
                                                    checkout.setData(
                                                        'payment_option',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <span>
                                                <strong className="block">
                                                    Place order without paying
                                                    now
                                                </strong>
                                                <span className="text-sm text-slate-500">
                                                    The academy receives the
                                                    order as unpaid.
                                                </span>
                                            </span>
                                        </label>
                                        {Object.values(checkout.errors).map(
                                            (error) => (
                                                <p
                                                    key={error}
                                                    className="rounded-lg bg-red-50 p-3 text-sm text-red-700"
                                                >
                                                    {error}
                                                </p>
                                            ),
                                        )}
                                    </div>
                                )}
                            </div>

                            <div className="border-t bg-white p-5 sm:p-6">
                                {submissionError && (
                                    <p
                                        role="alert"
                                        className="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-medium text-red-700"
                                    >
                                        Order not placed: {submissionError}
                                    </p>
                                )}
                                <div className="mb-4 space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <span>Subtotal</span>
                                        <strong>
                                            {formatCurrency(
                                                academy.currency,
                                                cartTotal.toFixed(2),
                                            )}
                                        </strong>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>Shipping</span>
                                        <strong>
                                            {formatCurrency(
                                                academy.currency,
                                                '0.00',
                                            )}
                                        </strong>
                                    </div>
                                    <div className="flex justify-between pt-1 text-lg font-black text-[var(--site-primary)]">
                                        <span>Total</span>
                                        <span>
                                            {formatCurrency(
                                                academy.currency,
                                                cartTotal.toFixed(2),
                                            )}
                                        </span>
                                    </div>
                                </div>
                                <div className="flex gap-3">
                                    {step > 1 && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => {
                                                setStepError('');
                                                setStep(
                                                    (step - 1) as CheckoutStep,
                                                );
                                            }}
                                        >
                                            <ArrowLeft className="size-4" />
                                            Back
                                        </Button>
                                    )}
                                    {step === 1 && (
                                        <Button
                                            type="button"
                                            className="flex-1 border border-[var(--site-primary)] bg-[var(--site-primary)] text-[var(--site-primary-fg)] shadow-md hover:opacity-90"
                                            onClick={() => setStep(2)}
                                        >
                                            Checkout
                                        </Button>
                                    )}
                                    {step === 2 && (
                                        <Button
                                            type="button"
                                            className="flex-1 bg-[var(--site-primary)] text-[var(--site-primary-fg)]"
                                            onClick={continueFromDetails}
                                        >
                                            Continue to notes
                                        </Button>
                                    )}
                                    {step === 3 && (
                                        <Button
                                            type="button"
                                            className="flex-1 bg-[var(--site-primary)] text-[var(--site-primary-fg)]"
                                            onClick={() => setStep(4)}
                                        >
                                            Continue to payment
                                        </Button>
                                    )}
                                    {step === 4 && (
                                        <Button
                                            className="flex-1 bg-[var(--site-primary)] text-[var(--site-primary-fg)]"
                                            disabled={checkout.processing}
                                        >
                                            {checkout.processing
                                                ? 'Placing order…'
                                                : checkout.data
                                                        .payment_option ===
                                                    'paystack'
                                                  ? 'Place order and continue to Paystack'
                                                  : 'Place order'}
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </form>
                    )}
                </SheetContent>
            </Sheet>
        </PublicSiteShell>
    );
}
