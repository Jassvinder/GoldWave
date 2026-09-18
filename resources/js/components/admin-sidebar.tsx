import {
    Boxes,
    FileText,
    History,
    LayoutGrid,
    ShoppingCart,
    Store as StoreIcon,
} from 'lucide-react';
import { PortalSidebar } from '@/components/portal-sidebar';
import { dashboard } from '@/routes';
import { index as inventoryIndex } from '@/routes/admin/inventory';
import { show as showProfile } from '@/routes/admin/profile';
import { index as reportsIndex } from '@/routes/admin/reports';
import { index as salesIndex } from '@/routes/admin/sales';
import { index as transactionsIndex } from '@/routes/admin/transactions';
import type { NavGroup } from '@/types';

const sections: NavGroup[] = [
    {
        label: 'Store Operations',
        items: [
            { title: 'Store Dashboard', href: dashboard(), icon: LayoutGrid },
            { title: 'Store Profile', href: showProfile(), icon: StoreIcon },
            {
                title: 'Repurchases / Sales',
                href: salesIndex(),
                icon: ShoppingCart,
            },
            { title: 'Inventory', href: inventoryIndex(), icon: Boxes },
            {
                title: 'Store Transactions',
                href: transactionsIndex(),
                icon: History,
            },
            { title: 'Store Reports', href: reportsIndex(), icon: FileText },
        ],
    },
];

/** INSTRUCTIONS.md's Admin / Store Owner Portal (T-016) navigation — A01-A06. Renders via the shared `PortalSidebar` (T-100). */
export function AdminSidebar() {
    return <PortalSidebar sections={sections} />;
}
