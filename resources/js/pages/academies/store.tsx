import { Head, router, useForm } from '@inertiajs/react';
import {
    Boxes,
    CreditCard,
    PackagePlus,
    Plus,
    ReceiptText,
    ShoppingBag,
    ArrowRightLeft,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/money';

type Stock = {
    branch_id: number | null;
    branch: string;
    on_hand: number;
    reserved: number;
    available: number;
};
type Variant = {
    id: number;
    name: string;
    sku: string | null;
    attributes: Record<string, string>;
    price: string | null;
    is_active: boolean;
    stocks: Stock[];
};
type Product = {
    id: number;
    name: string;
    sku: string | null;
    description: string | null;
    status: string;
    currency: string;
    price: string;
    category_id: number | null;
    category: string | null;
    track_inventory: boolean;
    allow_backorder: boolean;
    inventory_total: number;
    tag_ids: number[];
    image_url: string | null;
    variants: Variant[];
};
type Order = {
    id: number;
    number: string;
    source: string;
    customer_name: string;
    customer_email: string | null;
    customer_phone: string | null;
    member_name: string | null;
    branch: string;
    currency: string;
    total: string;
    paid: string;
    payment_status: string;
    payment_preference: string;
    fulfillment_status: string;
    placed_at: string;
    items: Array<{
        name: string;
        variant: string;
        quantity: number;
        unit_price: string;
    }>;
};
const tabs = ['products', 'inventory', 'orders', 'categories'] as const;
const statusClass = (value: string) =>
    value === 'paid' || value === 'delivered' || value === 'active'
        ? 'bg-emerald-100 text-emerald-800'
        : value === 'cancelled' || value === 'archived'
          ? 'bg-red-100 text-red-700'
          : 'bg-amber-100 text-amber-800';

export default function Store({
    currency,
    can_manage_catalog,
    products,
    categories,
    tags,
    branches,
    orders,
    summary,
    active_tab,
}: {
    currency: string;
    can_manage_catalog: boolean;
    products: Product[];
    categories: Array<{
        id: number;
        name: string;
        description: string | null;
        products_count: number;
    }>;
    tags: Array<{ id: number; name: string; products_count: number }>;
    branches: Array<{ id: number; name: string }>;
    orders: Order[];
    summary: {
        products: number;
        stock_units: number;
        unpaid_orders: number;
        sales: string;
    };
    active_tab: (typeof tabs)[number];
}) {
    const [tab, setTab] = useState<(typeof tabs)[number]>(active_tab);
    const [productOpen, setProductOpen] = useState(false);
    const [editingProduct, setEditingProduct] = useState<Product | null>(null);
    const [imageProduct, setImageProduct] = useState<Product | null>(null);
    const [inventoryProduct, setInventoryProduct] = useState<Product | null>(
        null,
    );
    const [saleOpen, setSaleOpen] = useState(false);
    const [variantProduct, setVariantProduct] = useState<Product | null>(null);
    const [stockVariant, setStockVariant] = useState<{
        product: Product;
        variant: Variant;
    } | null>(null);
    const [isAddingInventory, setIsAddingInventory] = useState(false);
    const [transferVariant, setTransferVariant] = useState<{
        product: Product;
        variant: Variant;
    } | null>(null);
    const [paymentOrder, setPaymentOrder] = useState<Order | null>(null);
    const [query, setQuery] = useState('');
    const filteredProducts = products.filter(
        (p) =>
            p.name.toLowerCase().includes(query.toLowerCase()) ||
            (p.sku ?? '').toLowerCase().includes(query.toLowerCase()),
    );
    const product = useForm({
        name: '',
        sku: '',
        description: '',
        product_category_id: '',
        price: '',
        status: 'draft',
        track_inventory: true,
        allow_backorder: false,
        tag_ids: [] as number[],
        variant_name: 'Default',
        variant_sku: '',
        image: null as File | null,
    });
    const category = useForm({ name: '', description: '' });
    const tag = useForm({ name: '' });
    const variant = useForm({
        name: '',
        sku: '',
        price: '',
        attributes: {} as Record<string, string>,
    });
    const stock = useForm({
        branch_id: '',
        direction: 'add',
        quantity: '',
        reason_code: 'purchase',
        reason_details: '',
    });
    const transfer = useForm({
        from_branch_id: '',
        to_branch_id: '',
        quantity: '',
        reason_code: 'branch_restock',
        reason_details: '',
    });
    const payment = useForm({
        amount: '',
        method: 'cash',
        received_on: new Date().toISOString().slice(0, 10),
        external_reference: '',
        notes: '',
    });
    const productImage = useForm<{ image: File | null }>({ image: null });
    const allVariants = useMemo(
        () =>
            products.flatMap((p) =>
                p.variants.map((v) => ({ ...v, product: p })),
            ),
        [products],
    );
    const sale = useForm({
        customer_name: '',
        customer_email: '',
        customer_phone: '',
        member_name: '',
        branch_id: '',
        delivery_method: 'pickup',
        delivery_address: '',
        notes: '',
        items: [{ variant_id: '', quantity: 1 }],
        amount_paid: '',
        payment_method: 'cash',
        payment_reference: '',
        received_on: new Date().toISOString().slice(0, 10),
        mark_delivered: true,
    });
    const submitProduct = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            onSuccess: () => {
                setProductOpen(false);
                setEditingProduct(null);
                product.reset();
            },
        };
        if (editingProduct) {
            product.patch(`/app/store/products/${editingProduct.id}`, {
                ...options,
                forceFormData: true,
            });
        } else {
            product.post('/app/store/products', {
                ...options,
                forceFormData: true,
            });
        }
    };
    const openNewProduct = () => {
        setEditingProduct(null);
        product.reset();
        setProductOpen(true);
    };
    const openEditProduct = (item: Product) => {
        setEditingProduct(item);
        product.setData({
            name: item.name,
            sku: item.sku ?? '',
            description: item.description ?? '',
            product_category_id: item.category_id?.toString() ?? '',
            price: item.price,
            status: item.status,
            track_inventory: item.track_inventory,
            allow_backorder: item.allow_backorder,
            tag_ids: item.tag_ids,
            variant_name: '',
            variant_sku: '',
            image: null,
        });
        setProductOpen(true);
    };
    const submitSale = (e: React.FormEvent) => {
        e.preventDefault();
        sale.transform((d) => ({
            ...d,
            branch_id: d.branch_id || null,
            amount_paid: d.amount_paid || null,
            items: d.items
                .filter((x) => x.variant_id)
                .map((x) => ({
                    variant_id: Number(x.variant_id),
                    quantity: Number(x.quantity),
                })),
        }));
        sale.post('/app/store/orders', {
            onSuccess: () => {
                setSaleOpen(false);
                sale.reset();
            },
        });
    };
    const summaryCards: Array<[string, string | number, string, LucideIcon]> = [
        ['Products', summary.products, 'bg-blue-50 text-blue-800', ShoppingBag],
        [
            'Units on hand',
            summary.stock_units,
            'bg-violet-50 text-violet-800',
            Boxes,
        ],
        [
            'Orders owing',
            summary.unpaid_orders,
            'bg-amber-50 text-amber-800',
            ReceiptText,
        ],
        [
            'Verified sales',
            formatCurrency(currency, summary.sales),
            'bg-emerald-50 text-emerald-800',
            CreditCard,
        ],
    ];
    return (
        <>
            <Head title="Store" />
            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Commerce
                        </p>
                        <h1 className="text-2xl font-bold">
                            Store, inventory and orders
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Sell merchandise online or at the academy, reserve
                            stock safely and track payment separately from
                            delivery.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {can_manage_catalog && (
                            <Button variant="outline" onClick={openNewProduct}>
                                <PackagePlus className="size-4" /> Add product
                            </Button>
                        )}
                        <Button onClick={() => setSaleOpen(true)}>
                            <Plus className="size-4" /> Record offline sale
                        </Button>
                    </div>
                </div>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {summaryCards.map(([label, value, style, Icon]) => (
                        <Card key={label} className={style}>
                            <CardContent className="flex items-center justify-between p-5">
                                <div>
                                    <p className="text-sm opacity-70">
                                        {label}
                                    </p>
                                    <p className="mt-1 text-2xl font-black">
                                        {value}
                                    </p>
                                </div>
                                <Icon className="size-7 opacity-60" />
                            </CardContent>
                        </Card>
                    ))}
                </div>
                <div className="flex gap-6 overflow-x-auto border-b">
                    {tabs.map((name) => (
                        <button
                            key={name}
                            onClick={() => setTab(name)}
                            className={`border-b-2 px-1 py-3 text-sm font-semibold capitalize ${tab === name ? 'border-primary text-primary' : 'border-transparent text-muted-foreground'}`}
                        >
                            {name}
                        </button>
                    ))}
                </div>
                {tab === 'products' && (
                    <Card>
                        <CardHeader>
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <h2 className="text-lg font-bold">
                                    Product catalogue
                                </h2>
                                <Input
                                    className="max-w-xs"
                                    placeholder="Filter by product or SKU"
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                />
                            </div>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left">
                                        <th className="p-3">Product</th>
                                        <th className="p-3">Category</th>
                                        <th className="p-3">Price</th>
                                        <th className="p-3">Options</th>
                                        <th className="p-3">Inventory</th>
                                        <th className="p-3">Status</th>
                                        <th className="p-3 text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredProducts.map((p) => (
                                        <tr key={p.id} className="border-b">
                                            <td className="p-3">
                                                <div className="flex items-center gap-3">
                                                    <div className="grid size-11 shrink-0 place-items-center overflow-hidden rounded-lg bg-muted">
                                                        {p.image_url ? (
                                                            <img
                                                                src={
                                                                    p.image_url
                                                                }
                                                                alt=""
                                                                className="size-full object-cover"
                                                            />
                                                        ) : (
                                                            <ShoppingBag className="size-5 text-muted-foreground" />
                                                        )}
                                                    </div>
                                                    <div>
                                                        <strong>
                                                            {p.name}
                                                        </strong>
                                                        <p className="text-xs text-muted-foreground">
                                                            {p.sku || 'No SKU'}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="p-3">
                                                {p.category ?? 'Uncategorised'}
                                            </td>
                                            <td className="p-3">
                                                {formatCurrency(
                                                    p.currency,
                                                    p.price,
                                                )}
                                            </td>
                                            <td className="p-3">
                                                {p.variants.length}
                                            </td>
                                            <td className="p-3">
                                                <button
                                                    type="button"
                                                    className="font-bold text-primary underline decoration-primary/40 underline-offset-4 hover:decoration-primary focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                    aria-label={`View inventory details for ${p.name}: ${p.inventory_total} units`}
                                                    onClick={() =>
                                                        setInventoryProduct(p)
                                                    }
                                                >
                                                    {p.inventory_total}
                                                </button>
                                            </td>
                                            <td className="p-3">
                                                <span
                                                    className={`rounded-full px-2 py-1 text-xs font-bold ${statusClass(p.status)}`}
                                                >
                                                    {p.status}
                                                </span>
                                            </td>
                                            <td className="p-3 text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            openEditProduct(p)
                                                        }
                                                    >
                                                        Edit
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            setImageProduct(p)
                                                        }
                                                    >
                                                        Image
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            setVariantProduct(p)
                                                        }
                                                    >
                                                        Add option
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {filteredProducts.length === 0 && (
                                <p className="py-12 text-center text-muted-foreground">
                                    No products yet.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}
                {tab === 'inventory' && (
                    <Card>
                        <CardHeader>
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <h2 className="text-lg font-bold">
                                    Stock by product option and branch
                                </h2>
                                {can_manage_catalog && (
                                    <Button
                                        disabled={
                                            allVariants.length === 0 ||
                                            branches.length === 0
                                        }
                                        onClick={() => {
                                            const first = allVariants[0];
                                            if (!first) return;
                                            stock.reset();
                                            setIsAddingInventory(true);
                                            setStockVariant({
                                                product: first.product,
                                                variant: first,
                                            });
                                        }}
                                    >
                                        <PackagePlus className="size-4" /> Add
                                        inventory
                                    </Button>
                                )}
                            </div>
                            <p className="text-sm text-muted-foreground">
                                Only branch balances containing stock or
                                reservations are shown. Combined totals are
                                available from the Products tab.
                            </p>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left">
                                        <th className="p-3">
                                            Product / option
                                        </th>
                                        <th className="p-3">Location</th>
                                        <th className="p-3">On hand</th>
                                        <th className="p-3">Reserved</th>
                                        <th className="p-3">Available</th>
                                        <th className="p-3 text-right">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {allVariants.flatMap((v) =>
                                        v.stocks.map((s) => (
                                            <tr
                                                key={`${v.id}-${s.branch_id}`}
                                                className="border-b"
                                            >
                                                <td className="p-3">
                                                    <strong>
                                                        {v.product.name}
                                                    </strong>
                                                    <p className="text-xs text-muted-foreground">
                                                        {v.name}
                                                    </p>
                                                </td>
                                                <td className="p-3">
                                                    {s.branch}
                                                </td>
                                                <td className="p-3">
                                                    {s.on_hand}
                                                </td>
                                                <td className="p-3">
                                                    {s.reserved}
                                                </td>
                                                <td className="p-3 font-bold">
                                                    {s.available}
                                                </td>
                                                <td className="p-3 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => {
                                                                setIsAddingInventory(
                                                                    false,
                                                                );
                                                                setStockVariant(
                                                                    {
                                                                        product:
                                                                            v.product,
                                                                        variant:
                                                                            v,
                                                                    },
                                                                );
                                                                stock.setData(
                                                                    'branch_id',
                                                                    s.branch_id?.toString() ??
                                                                        '',
                                                                );
                                                            }}
                                                        >
                                                            Adjust
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => {
                                                                setTransferVariant(
                                                                    {
                                                                        product:
                                                                            v.product,
                                                                        variant:
                                                                            v,
                                                                    },
                                                                );
                                                                transfer.setData(
                                                                    'from_branch_id',
                                                                    s.branch_id?.toString() ??
                                                                        '',
                                                                );
                                                                transfer.setData(
                                                                    'to_branch_id',
                                                                    '',
                                                                );
                                                            }}
                                                        >
                                                            <ArrowRightLeft className="size-4" />{' '}
                                                            Transfer
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        )),
                                    )}
                                </tbody>
                            </table>
                            {allVariants.every(
                                (variant) => variant.stocks.length === 0,
                            ) && (
                                <p className="py-12 text-center text-muted-foreground">
                                    No inventory balances yet. Add stock to a
                                    product option to see it here.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}
                {tab === 'orders' && (
                    <Card>
                        <CardHeader>
                            <h2 className="text-lg font-bold">All orders</h2>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left">
                                        <th className="p-3">Order</th>
                                        <th className="p-3">Customer</th>
                                        <th className="p-3">Total / paid</th>
                                        <th className="p-3">Payment</th>
                                        <th className="p-3">Delivery</th>
                                        <th className="p-3 text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {orders.map((o) => (
                                        <tr
                                            key={o.id}
                                            className="border-b align-top"
                                        >
                                            <td className="p-3">
                                                <a
                                                    href={`/app/store/orders/${o.id}`}
                                                    className="font-bold text-primary underline-offset-4 hover:underline"
                                                >
                                                    {o.number}
                                                </a>
                                                <p className="text-xs text-muted-foreground">
                                                    {o.source} ·{' '}
                                                    {new Date(
                                                        o.placed_at,
                                                    ).toLocaleDateString()}
                                                </p>
                                            </td>
                                            <td className="p-3">
                                                {o.customer_name}
                                                <p className="text-xs text-muted-foreground">
                                                    {o.branch}
                                                </p>
                                                {o.member_name && (
                                                    <p className="text-xs text-muted-foreground">
                                                        For: {o.member_name}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="p-3">
                                                {formatCurrency(
                                                    o.currency,
                                                    o.total,
                                                )}
                                                <p className="text-xs text-muted-foreground">
                                                    Paid{' '}
                                                    {formatCurrency(
                                                        o.currency,
                                                        o.paid,
                                                    )}
                                                </p>
                                            </td>
                                            <td className="p-3">
                                                <span
                                                    className={`rounded-full px-2 py-1 text-xs font-bold ${statusClass(o.payment_status)}`}
                                                >
                                                    {o.payment_status.replace(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </span>
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    {o.payment_preference ===
                                                    'bank_transfer'
                                                        ? 'Bank transfer selected'
                                                        : o.payment_preference ===
                                                            'paystack'
                                                          ? 'Paystack selected'
                                                          : 'Pay later selected'}
                                                </p>
                                            </td>
                                            <td className="p-3">
                                                <select
                                                    value={
                                                        o.fulfillment_status ===
                                                        'cancelled'
                                                            ? 'cancelled'
                                                            : o.fulfillment_status
                                                    }
                                                    disabled={
                                                        o.fulfillment_status ===
                                                        'cancelled'
                                                    }
                                                    onChange={(e) =>
                                                        router.patch(
                                                            `/app/store/orders/${o.id}/fulfillment`,
                                                            {
                                                                status: e.target
                                                                    .value,
                                                            },
                                                        )
                                                    }
                                                    className="h-9 rounded-md border bg-background px-2"
                                                >
                                                    <option value="unfulfilled">
                                                        Unfulfilled
                                                    </option>
                                                    <option value="processing">
                                                        Processing
                                                    </option>
                                                    <option value="ready">
                                                        Ready
                                                    </option>
                                                    <option value="shipped">
                                                        Shipped
                                                    </option>
                                                    <option value="delivered">
                                                        Delivered
                                                    </option>
                                                    {o.fulfillment_status ===
                                                        'cancelled' && (
                                                        <option value="cancelled">
                                                            Cancelled
                                                        </option>
                                                    )}
                                                </select>
                                            </td>
                                            <td className="p-3 text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        asChild
                                                    >
                                                        <a
                                                            href={`/app/store/orders/${o.id}`}
                                                        >
                                                            View
                                                        </a>
                                                    </Button>
                                                    {o.payment_status !==
                                                        'paid' &&
                                                        o.fulfillment_status !==
                                                            'cancelled' && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    setPaymentOrder(
                                                                        o,
                                                                    )
                                                                }
                                                            >
                                                                Payment
                                                            </Button>
                                                        )}
                                                    {o.payment_status ===
                                                        'unpaid' &&
                                                        ![
                                                            'delivered',
                                                            'cancelled',
                                                        ].includes(
                                                            o.fulfillment_status,
                                                        ) && (
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                className="text-red-600"
                                                                onClick={() =>
                                                                    router.patch(
                                                                        `/app/store/orders/${o.id}/cancel`,
                                                                    )
                                                                }
                                                            >
                                                                Cancel
                                                            </Button>
                                                        )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {orders.length === 0 && (
                                <p className="py-12 text-center text-muted-foreground">
                                    No orders yet.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}
                {tab === 'categories' && (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <h2 className="text-lg font-bold">
                                    Categories
                                </h2>
                            </CardHeader>
                            <CardContent>
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        category.post('/app/store/categories', {
                                            onSuccess: () => category.reset(),
                                        });
                                    }}
                                    className="mb-5 flex gap-2"
                                >
                                    <Input
                                        required
                                        placeholder="e.g. Uniforms"
                                        value={category.data.name}
                                        onChange={(e) =>
                                            category.setData(
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <Button>Add</Button>
                                </form>
                                <div className="divide-y">
                                    {categories.map((x) => (
                                        <div
                                            key={x.id}
                                            className="flex justify-between py-3"
                                        >
                                            <span>{x.name}</span>
                                            <span className="text-sm text-muted-foreground">
                                                {x.products_count} products
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <h2 className="text-lg font-bold">Tags</h2>
                            </CardHeader>
                            <CardContent>
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        tag.post('/app/store/tags', {
                                            onSuccess: () => tag.reset(),
                                        });
                                    }}
                                    className="mb-5 flex gap-2"
                                >
                                    <Input
                                        required
                                        placeholder="e.g. New arrival"
                                        value={tag.data.name}
                                        onChange={(e) =>
                                            tag.setData('name', e.target.value)
                                        }
                                    />
                                    <Button>Add</Button>
                                </form>
                                <div className="flex flex-wrap gap-2">
                                    {tags.map((x) => (
                                        <span
                                            key={x.id}
                                            className="rounded-full bg-muted px-3 py-1 text-sm"
                                        >
                                            {x.name} · {x.products_count}
                                        </span>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </main>
            <Dialog open={productOpen} onOpenChange={setProductOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editingProduct ? 'Edit product' : 'Add product'}
                        </DialogTitle>
                    </DialogHeader>
                    <form
                        onSubmit={submitProduct}
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        <div>
                            <Label>Name</Label>
                            <Input
                                required
                                value={product.data.name}
                                onChange={(e) =>
                                    product.setData('name', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <Label>SKU</Label>
                            <Input
                                value={product.data.sku}
                                onChange={(e) =>
                                    product.setData('sku', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <Label>Category</Label>
                            <select
                                required
                                className="h-10 w-full rounded-md border bg-background px-3"
                                value={product.data.product_category_id}
                                onChange={(e) =>
                                    product.setData(
                                        'product_category_id',
                                        e.target.value,
                                    )
                                }
                            >
                                <option value="">Uncategorised</option>
                                {categories.map((x) => (
                                    <option key={x.id} value={x.id}>
                                        {x.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <Label>Price</Label>
                            <Input
                                required
                                inputMode="decimal"
                                placeholder="0.00"
                                value={product.data.price}
                                onChange={(e) =>
                                    product.setData('price', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <Label>Status</Label>
                            <select
                                className="h-10 w-full rounded-md border bg-background px-3"
                                value={product.data.status}
                                onChange={(e) =>
                                    product.setData('status', e.target.value)
                                }
                            >
                                <option value="draft">Draft</option>
                                <option value="active">Active / public</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div>
                            <Label>First option name</Label>
                            <Input
                                placeholder="Default, Small, Size 42..."
                                value={product.data.variant_name}
                                onChange={(e) =>
                                    product.setData(
                                        'variant_name',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="sm:col-span-2">
                            <Label>Description</Label>
                            <textarea
                                className="min-h-24 w-full rounded-md border bg-background p-3"
                                value={product.data.description}
                                onChange={(e) =>
                                    product.setData(
                                        'description',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="sm:col-span-2">
                            <Label htmlFor="new-product-image">
                                Product image{' '}
                                {editingProduct && '(optional replacement)'}
                            </Label>
                            <Input
                                id="new-product-image"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                onChange={(event) =>
                                    product.setData(
                                        'image',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <p className="mt-1 text-xs text-muted-foreground">
                                JPEG, PNG or WebP, up to 5 MB.
                            </p>
                        </div>
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={product.data.track_inventory}
                                onChange={(e) =>
                                    product.setData(
                                        'track_inventory',
                                        e.target.checked,
                                    )
                                }
                            />{' '}
                            Track stock
                        </label>
                        <label className="flex items-start gap-2">
                            <input
                                className="mt-1"
                                type="checkbox"
                                checked={product.data.allow_backorder}
                                onChange={(e) =>
                                    product.setData(
                                        'allow_backorder',
                                        e.target.checked,
                                    )
                                }
                            />
                            <span>
                                <span className="block">Allow backorders</span>
                                <span className="block text-xs text-muted-foreground">
                                    Accept orders when available stock is
                                    insufficient, then fulfil them after
                                    restocking.
                                </span>
                            </span>
                        </label>
                        <div className="sm:col-span-2">
                            <Label>Tags</Label>
                            <div className="mt-2 flex flex-wrap gap-3">
                                {tags.map((x) => (
                                    <label
                                        key={x.id}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={product.data.tag_ids.includes(
                                                x.id,
                                            )}
                                            onChange={(e) =>
                                                product.setData(
                                                    'tag_ids',
                                                    e.target.checked
                                                        ? [
                                                              ...product.data
                                                                  .tag_ids,
                                                              x.id,
                                                          ]
                                                        : product.data.tag_ids.filter(
                                                              (id) =>
                                                                  id !== x.id,
                                                          ),
                                                )
                                            }
                                        />{' '}
                                        {x.name}
                                    </label>
                                ))}
                            </div>
                        </div>
                        {Object.values(product.errors).map((x) => (
                            <p
                                key={x}
                                className="text-sm text-red-600 sm:col-span-2"
                            >
                                {x}
                            </p>
                        ))}
                        <Button
                            className="sm:col-span-2"
                            disabled={product.processing}
                        >
                            {editingProduct ? 'Save product' : 'Create product'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog
                open={!!inventoryProduct}
                onOpenChange={(open) => !open && setInventoryProduct(null)}
            >
                <DialogContent className="max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>
                            Inventory details · {inventoryProduct?.name}
                        </DialogTitle>
                    </DialogHeader>
                    <div className="rounded-xl bg-muted/50 p-4">
                        <p className="text-sm text-muted-foreground">
                            Total units across all branches
                        </p>
                        <p className="text-3xl font-bold">
                            {inventoryProduct?.inventory_total ?? 0}
                        </p>
                    </div>
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="p-3">Option</th>
                                    <th className="p-3">Location</th>
                                    <th className="p-3">On hand</th>
                                    <th className="p-3">Reserved</th>
                                    <th className="p-3">Available</th>
                                </tr>
                            </thead>
                            <tbody>
                                {inventoryProduct?.variants.flatMap((item) =>
                                    item.stocks.map((balance) => (
                                        <tr
                                            key={`${item.id}-${balance.branch_id}`}
                                            className="border-t"
                                        >
                                            <td className="p-3 font-medium">
                                                {item.name}
                                            </td>
                                            <td className="p-3">
                                                {balance.branch}
                                            </td>
                                            <td className="p-3">
                                                {balance.on_hand}
                                            </td>
                                            <td className="p-3">
                                                {balance.reserved}
                                            </td>
                                            <td className="p-3 font-bold">
                                                {balance.available}
                                            </td>
                                        </tr>
                                    )),
                                )}
                            </tbody>
                        </table>
                        {inventoryProduct?.variants.every(
                            (item) => item.stocks.length === 0,
                        ) && (
                            <p className="p-8 text-center text-muted-foreground">
                                This product has no branch inventory yet.
                            </p>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
            <Dialog
                open={!!imageProduct}
                onOpenChange={(open) => !open && setImageProduct(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Product image · {imageProduct?.name}
                        </DialogTitle>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (!imageProduct) return;
                            productImage.post(
                                `/app/store/products/${imageProduct.id}/image`,
                                {
                                    forceFormData: true,
                                    onSuccess: () => {
                                        setImageProduct(null);
                                        productImage.reset();
                                    },
                                },
                            );
                        }}
                    >
                        {imageProduct?.image_url && (
                            <img
                                src={imageProduct.image_url}
                                alt=""
                                className="aspect-video w-full rounded-xl object-cover"
                            />
                        )}
                        <div>
                            <Label htmlFor="product-image">Image</Label>
                            <Input
                                id="product-image"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                required
                                onChange={(event) =>
                                    productImage.setData(
                                        'image',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </div>
                        {productImage.errors.image && (
                            <p className="text-sm text-red-600">
                                {productImage.errors.image}
                            </p>
                        )}
                        <Button
                            className="w-full"
                            disabled={productImage.processing}
                        >
                            Upload image
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog
                open={!!variantProduct}
                onOpenChange={(open) => !open && setVariantProduct(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Add option to {variantProduct?.name}
                        </DialogTitle>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            if (!variantProduct) return;
                            variant.post(
                                `/app/store/products/${variantProduct.id}/variants`,
                                {
                                    onSuccess: () => {
                                        setVariantProduct(null);
                                        variant.reset();
                                    },
                                },
                            );
                        }}
                    >
                        <div>
                            <Label>Option name</Label>
                            <Input
                                required
                                placeholder="e.g. Medium / Blue"
                                value={variant.data.name}
                                onChange={(e) =>
                                    variant.setData('name', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <Label>SKU</Label>
                            <Input
                                value={variant.data.sku}
                                onChange={(e) =>
                                    variant.setData('sku', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <Label>Price override (optional)</Label>
                            <Input
                                value={variant.data.price}
                                onChange={(e) =>
                                    variant.setData('price', e.target.value)
                                }
                            />
                        </div>
                        <Button className="w-full">Add option</Button>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog
                open={!!stockVariant}
                onOpenChange={(open) => {
                    if (!open) {
                        setStockVariant(null);
                        setIsAddingInventory(false);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {isAddingInventory
                                ? 'Add inventory stock'
                                : `Adjust stock · ${stockVariant?.product.name} / ${stockVariant?.variant.name}`}
                        </DialogTitle>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            if (!stockVariant) return;
                            stock.post(
                                `/app/store/variants/${stockVariant.variant.id}/stock`,
                                {
                                    onSuccess: () => {
                                        setStockVariant(null);
                                        setIsAddingInventory(false);
                                        stock.reset();
                                    },
                                },
                            );
                        }}
                    >
                        {isAddingInventory && (
                            <div>
                                <Label>Product / option</Label>
                                <select
                                    className="h-10 w-full rounded-md border bg-background px-3"
                                    value={stockVariant?.variant.id ?? ''}
                                    onChange={(event) => {
                                        const selected = allVariants.find(
                                            (item) =>
                                                item.id ===
                                                Number(event.target.value),
                                        );
                                        if (selected) {
                                            setStockVariant({
                                                product: selected.product,
                                                variant: selected,
                                            });
                                        }
                                    }}
                                >
                                    {allVariants.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.product.name} · {item.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}
                        <div>
                            <Label>Location</Label>
                            <select
                                required
                                className="h-10 w-full rounded-md border bg-background px-3"
                                value={stock.data.branch_id}
                                onChange={(e) =>
                                    stock.setData('branch_id', e.target.value)
                                }
                            >
                                <option value="" disabled>
                                    Select branch
                                </option>
                                {branches.map((x) => (
                                    <option key={x.id} value={x.id}>
                                        {x.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <Label>Adjustment</Label>
                            <select
                                className="h-10 w-full rounded-md border bg-background px-3"
                                value={stock.data.direction}
                                onChange={(e) =>
                                    stock.setData('direction', e.target.value)
                                }
                            >
                                <option value="add">Add stock</option>
                                <option value="remove">Remove stock</option>
                            </select>
                        </div>
                        <div>
                            <Label>Quantity</Label>
                            <Input
                                required
                                type="number"
                                min="1"
                                placeholder="Enter a positive quantity"
                                value={stock.data.quantity}
                                onChange={(e) =>
                                    stock.setData('quantity', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <Label>Reason</Label>
                            <select
                                className="h-10 w-full rounded-md border bg-background px-3"
                                value={stock.data.reason_code}
                                onChange={(e) =>
                                    stock.setData('reason_code', e.target.value)
                                }
                            >
                                <option value="purchase">
                                    New purchase / restock
                                </option>
                                <option value="opening_stock">
                                    Opening stock
                                </option>
                                <option value="customer_return">
                                    Customer return
                                </option>
                                <option value="stock_count">
                                    Stock count correction
                                </option>
                                <option value="damaged">Damaged item</option>
                                <option value="expired">Expired item</option>
                                <option value="lost">
                                    Lost or missing item
                                </option>
                                <option value="supplier_return">
                                    Returned to supplier
                                </option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        {stock.data.reason_code === 'other' && (
                            <div>
                                <Label>Other reason</Label>
                                <Input
                                    required
                                    maxLength={500}
                                    value={stock.data.reason_details}
                                    onChange={(e) =>
                                        stock.setData(
                                            'reason_details',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Describe why the stock is changing"
                                />
                            </div>
                        )}
                        {Object.values(stock.errors).map((x) => (
                            <p key={x} className="text-sm text-red-600">
                                {x}
                            </p>
                        ))}
                        <Button className="w-full">
                            {isAddingInventory
                                ? 'Add inventory'
                                : 'Record adjustment'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog
                open={!!transferVariant}
                onOpenChange={(open) => !open && setTransferVariant(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Transfer stock · {transferVariant?.product.name} /{' '}
                            {transferVariant?.variant.name}
                        </DialogTitle>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (!transferVariant) return;
                            transfer.post(
                                `/app/store/variants/${transferVariant.variant.id}/stock-transfer`,
                                {
                                    onSuccess: () => {
                                        setTransferVariant(null);
                                        transfer.reset();
                                    },
                                },
                            );
                        }}
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>From location</Label>
                                <select
                                    required
                                    className="h-10 w-full rounded-md border bg-background px-3"
                                    value={transfer.data.from_branch_id}
                                    onChange={(e) =>
                                        transfer.setData(
                                            'from_branch_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="" disabled>
                                        Select source branch
                                    </option>
                                    {branches.map((branch) => (
                                        <option
                                            key={branch.id}
                                            value={branch.id}
                                        >
                                            {branch.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label>To location</Label>
                                <select
                                    required
                                    className="h-10 w-full rounded-md border bg-background px-3"
                                    value={transfer.data.to_branch_id}
                                    onChange={(e) =>
                                        transfer.setData(
                                            'to_branch_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="" disabled>
                                        Select destination branch
                                    </option>
                                    {branches.map((branch) => (
                                        <option
                                            key={branch.id}
                                            value={branch.id}
                                        >
                                            {branch.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div>
                            <Label>Quantity</Label>
                            <Input
                                required
                                type="number"
                                min="1"
                                value={transfer.data.quantity}
                                onChange={(e) =>
                                    transfer.setData('quantity', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <Label>Reason</Label>
                            <select
                                className="h-10 w-full rounded-md border bg-background px-3"
                                value={transfer.data.reason_code}
                                onChange={(e) =>
                                    transfer.setData(
                                        'reason_code',
                                        e.target.value,
                                    )
                                }
                            >
                                <option value="branch_restock">
                                    Branch restock
                                </option>
                                <option value="event_allocation">
                                    Event allocation
                                </option>
                                <option value="stock_rebalancing">
                                    Stock rebalancing
                                </option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        {transfer.data.reason_code === 'other' && (
                            <div>
                                <Label>Other reason</Label>
                                <Input
                                    required
                                    maxLength={500}
                                    value={transfer.data.reason_details}
                                    onChange={(e) =>
                                        transfer.setData(
                                            'reason_details',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                        )}
                        <p className="text-xs text-muted-foreground">
                            Reserved units cannot be transferred because they
                            are already committed to open orders.
                        </p>
                        {Object.values(transfer.errors).map((error) => (
                            <p key={error} className="text-sm text-red-600">
                                {error}
                            </p>
                        ))}
                        <Button
                            className="w-full"
                            disabled={transfer.processing}
                        >
                            <ArrowRightLeft className="size-4" /> Transfer stock
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog open={saleOpen} onOpenChange={setSaleOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Record offline sale</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitSale} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>Customer name</Label>
                                <Input
                                    required
                                    value={sale.data.customer_name}
                                    onChange={(e) =>
                                        sale.setData(
                                            'customer_name',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div>
                                <Label>Phone</Label>
                                <Input
                                    value={sale.data.customer_phone}
                                    onChange={(e) =>
                                        sale.setData(
                                            'customer_phone',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div>
                                <Label>Student / player name (optional)</Label>
                                <Input
                                    value={sale.data.member_name}
                                    onChange={(e) =>
                                        sale.setData(
                                            'member_name',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div>
                                <Label>
                                    Collection / customer location (optional)
                                </Label>
                                <select
                                    className="h-10 w-full rounded-md border bg-background px-3"
                                    value={sale.data.branch_id}
                                    onChange={(e) =>
                                        sale.setData(
                                            'branch_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        No location selected — use combined
                                        inventory
                                    </option>
                                    {branches.map((x) => (
                                        <option key={x.id} value={x.id}>
                                            {x.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label>Payment received</Label>
                                <Input
                                    placeholder="Leave blank if unpaid"
                                    value={sale.data.amount_paid}
                                    onChange={(e) =>
                                        sale.setData(
                                            'amount_paid',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                        </div>
                        <div>
                            <Label>Items</Label>
                            {sale.data.items.map((line, index) => (
                                <div key={index} className="mt-2 flex gap-2">
                                    <select
                                        required
                                        className="h-10 min-w-0 flex-1 rounded-md border bg-background px-3"
                                        value={line.variant_id}
                                        onChange={(e) =>
                                            sale.setData(
                                                'items',
                                                sale.data.items.map((x, i) =>
                                                    i === index
                                                        ? {
                                                              ...x,
                                                              variant_id:
                                                                  e.target
                                                                      .value,
                                                          }
                                                        : x,
                                                ),
                                            )
                                        }
                                    >
                                        <option value="">
                                            Select product option
                                        </option>
                                        {allVariants.map((v) => (
                                            <option key={v.id} value={v.id}>
                                                {v.product.name} · {v.name} ·{' '}
                                                {formatCurrency(
                                                    v.product.currency,
                                                    v.price ?? v.product.price,
                                                )}
                                            </option>
                                        ))}
                                    </select>
                                    <Input
                                        className="w-24"
                                        type="number"
                                        min="1"
                                        value={line.quantity}
                                        onChange={(e) =>
                                            sale.setData(
                                                'items',
                                                sale.data.items.map((x, i) =>
                                                    i === index
                                                        ? {
                                                              ...x,
                                                              quantity: Number(
                                                                  e.target
                                                                      .value,
                                                              ),
                                                          }
                                                        : x,
                                                ),
                                            )
                                        }
                                    />
                                </div>
                            ))}
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    sale.setData('items', [
                                        ...sale.data.items,
                                        { variant_id: '', quantity: 1 },
                                    ])
                                }
                            >
                                <Plus className="size-4" /> Add item
                            </Button>
                        </div>
                        {sale.data.amount_paid && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <Label>Payment method</Label>
                                    <select
                                        className="h-10 w-full rounded-md border bg-background px-3"
                                        value={sale.data.payment_method}
                                        onChange={(e) =>
                                            sale.setData(
                                                'payment_method',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="cash">Cash</option>
                                        <option value="transfer">
                                            Transfer
                                        </option>
                                        <option value="card">Card</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div>
                                    <Label>Payment reference</Label>
                                    <Input
                                        value={sale.data.payment_reference}
                                        onChange={(e) =>
                                            sale.setData(
                                                'payment_reference',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                        )}
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={sale.data.mark_delivered}
                                onChange={(e) =>
                                    sale.setData(
                                        'mark_delivered',
                                        e.target.checked,
                                    )
                                }
                            />{' '}
                            Items handed to customer now
                        </label>
                        {Object.values(sale.errors).map((x) => (
                            <p key={x} className="text-sm text-red-600">
                                {x}
                            </p>
                        ))}
                        <Button className="w-full">Record sale</Button>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog
                open={!!paymentOrder}
                onOpenChange={(open) => !open && setPaymentOrder(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Record payment · {paymentOrder?.number}
                        </DialogTitle>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            if (!paymentOrder) return;
                            payment.post(
                                `/app/store/orders/${paymentOrder.id}/payments`,
                                {
                                    onSuccess: () => {
                                        setPaymentOrder(null);
                                        payment.reset();
                                    },
                                },
                            );
                        }}
                    >
                        <div>
                            <Label>Amount</Label>
                            <Input
                                required
                                value={payment.data.amount}
                                onChange={(e) =>
                                    payment.setData('amount', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <Label>Method</Label>
                            <select
                                className="h-10 w-full rounded-md border bg-background px-3"
                                value={payment.data.method}
                                onChange={(e) =>
                                    payment.setData('method', e.target.value)
                                }
                            >
                                <option value="cash">Cash</option>
                                <option value="transfer">Transfer</option>
                                <option value="card">Card</option>
                                <option value="cheque">Cheque</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <Label>External reference</Label>
                            <Input
                                value={payment.data.external_reference}
                                onChange={(e) =>
                                    payment.setData(
                                        'external_reference',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        {Object.values(payment.errors).map((x) => (
                            <p key={x} className="text-sm text-red-600">
                                {x}
                            </p>
                        ))}
                        <Button className="w-full">
                            Record verified payment
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
