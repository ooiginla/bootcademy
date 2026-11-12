import { Link, router, usePage } from '@inertiajs/react';
import {
    Building2,
    Banknote,
    BookOpenText,
    Check,
    ChevronsUpDown,
    LayoutDashboard,
    Mail,
    Megaphone,
    ClipboardList,
    Braces,
    CalendarRange,
    CalendarDays,
    MapPin,
    Settings2,
    ReceiptText,
    Share2,
    HandHeart,
    ScrollText,
    UserRound,
    Dumbbell,
    Globe2,
    WalletCards,
    ShoppingBag,
    UsersRound,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { NavItem } from '@/types';
import { useAcademyTerminology } from '@/hooks/use-academy-terminology';
import { useCoachTerminology } from '@/hooks/use-coach-terminology';

export function AppSidebar() {
    const terminology = useAcademyTerminology();
    const coachTerminology = useCoachTerminology();
    const { academy, academies, platform, pending_invitations_count } =
        usePage().props;
    const mainNavItems: NavItem[] = academy
        ? [
              {
                  title: 'Dashboard',
                  href: '/app/dashboard',
                  icon: LayoutDashboard,
              },
              {
                  title: 'Branches',
                  href: '/app/branches',
                  icon: MapPin,
              },
              {
                  title: 'Programmes',
                  href: '/app/programmes',
                  icon: BookOpenText,
              },
              {
                  title: 'Sessions',
                  href: '/app/sessions',
                  icon: CalendarDays,
              },
              ...(academy.can_manage_finance
                  ? [
                        {
                            title: 'Academy cycles',
                            href: '/app/finance/cycles',
                            icon: CalendarRange,
                        },
                        {
                            title: 'Invoices',
                            href: '/app/finance/invoices',
                            icon: ReceiptText,
                        },
                        {
                            title: 'Payments',
                            href: '/app/finance/payments',
                            icon: Banknote,
                        },
                        {
                            title: 'Expenses',
                            href: '/app/finance/expenses',
                            icon: WalletCards,
                        },
                        {
                            title: 'Scholarships',
                            href: '/app/scholarships',
                            icon: HandHeart,
                        },
                    ]
                  : []),
              ...(academy.can_manage
                  ? [
                        {
                            title: 'Applications',
                            href: '/app/applications',
                            icon: ClipboardList,
                        },
                        {
                            title: terminology.plural,
                            href: '/app/players',
                            icon: UserRound,
                        },
                        {
                            title: coachTerminology.plural,
                            href: '/app/coaches',
                            icon: Dumbbell,
                        },
                    ]
                  : []),
              ...(academy.can_manage || academy.can_manage_finance
                  ? [
                        {
                            title: 'Communications',
                            href: '/app/communications',
                            icon: Megaphone,
                        },
                    ]
                  : []),
              ...(academy.can_manage || academy.can_manage_finance
                  ? [
                        {
                            title: 'Referrals',
                            href: '/app/referrals',
                            icon: Share2,
                        },
                        {
                            title: 'Store',
                            href: '/app/store',
                            icon: ShoppingBag,
                        },
                    ]
                  : []),
              ...(academy.can_manage
                  ? [
                        {
                            title: 'Settings',
                            href: '/app/settings/academy',
                            icon: Settings2,
                        },
                        {
                            title: 'Public website',
                            href: '/app/settings/website',
                            icon: Globe2,
                        },
                        {
                            title: 'Team and roles',
                            href: '/app/settings/team',
                            icon: UsersRound,
                        },
                        {
                            title: 'API integrations',
                            href: '/app/settings/api',
                            icon: Braces,
                        },
                        {
                            title: 'Subscription',
                            href: '/app/settings/subscription',
                            icon: ReceiptText,
                        },
                    ]
                  : []),
              ...(academy.can_view_audit_log
                  ? [
                        {
                            title: 'Audit log',
                            href: '/app/settings/audit-log',
                            icon: ScrollText,
                        },
                    ]
                  : []),
          ]
        : [];

    if (pending_invitations_count > 0) {
        mainNavItems.push({
            title: `Invitations (${pending_invitations_count})`,
            href: '/app/invitations',
            icon: Mail,
        });
    }

    if (platform.is_super_admin) {
        mainNavItems.push({
            title: 'Platform academies',
            href: '/platform/academies',
            icon: Building2,
        });
        mainNavItems.push({
            title: 'Platform plans',
            href: '/platform/plans',
            icon: ReceiptText,
        });
        mainNavItems.push({
            title: 'Platform settings',
            href: '/platform/settings',
            icon: Settings2,
        });
    }

    const homeHref = academy
        ? '/app/dashboard'
        : platform.is_super_admin
          ? '/platform/academies'
          : '/app/onboarding';

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        {academies.length > 1 && academy ? (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <SidebarMenuButton size="lg">
                                        <AppLogo />
                                        <ChevronsUpDown className="ml-auto size-4" />
                                    </SidebarMenuButton>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    className="w-64"
                                    align="start"
                                >
                                    <DropdownMenuLabel>
                                        Switch academy
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    {academies.map((item) => (
                                        <DropdownMenuItem
                                            key={item.id}
                                            onSelect={() =>
                                                router.post(
                                                    `/app/academies/${item.id}/activate`,
                                                )
                                            }
                                        >
                                            <span className="truncate">
                                                {item.name}
                                            </span>
                                            {item.id === academy.id && (
                                                <Check className="ml-auto size-4" />
                                            )}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        ) : (
                            <SidebarMenuButton size="lg" asChild>
                                <Link href={homeHref} prefetch>
                                    <AppLogo />
                                </Link>
                            </SidebarMenuButton>
                        )}
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
