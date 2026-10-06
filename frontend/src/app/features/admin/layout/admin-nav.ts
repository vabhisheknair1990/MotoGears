export interface NavItem { label: string; link: string; icon: string; permission?: string | string[]; badge?: 'open_orders' | 'pending_reviews' | 'new_enquiries'; exact?: boolean }
export interface NavSection { title: string | null; items: NavItem[] }

/** Sidebar structure. Items are hidden when the signed-in staff user lacks the permission. */
export const ADMIN_NAV: NavSection[] = [
  { title: null, items: [{ label: 'Dashboard', link: '/admin', icon: 'gauge', permission: 'dashboard.view', exact: true }] },
  {
    title: 'Sales',
    items: [
      { label: 'Orders', link: '/admin/orders', icon: 'package', permission: ['orders.view', 'orders.manage'], badge: 'open_orders' },
      { label: 'Customers', link: '/admin/customers', icon: 'users', permission: 'customers.view' },
      { label: 'Reviews', link: '/admin/reviews', icon: 'star', permission: 'reviews.manage', badge: 'pending_reviews' },
      { label: 'Reports', link: '/admin/reports', icon: 'bar-chart', permission: 'reports.view' },
    ],
  },
  {
    title: 'Catalog',
    items: [
      { label: 'Products', link: '/admin/products', icon: 'box', permission: 'products.manage' },
      { label: 'Inventory', link: '/admin/inventory', icon: 'layers', permission: 'inventory.manage' },
      { label: 'Categories', link: '/admin/categories', icon: 'grid', permission: 'categories.manage' },
      { label: 'Brands', link: '/admin/brands', icon: 'tag', permission: 'brands.manage' },
      { label: 'Attributes', link: '/admin/attributes', icon: 'sliders', permission: 'products.manage' },
      { label: 'Vehicles', link: '/admin/vehicles', icon: 'car', permission: 'vehicles.manage' },
    ],
  },
  {
    title: 'Marketing',
    items: [
      { label: 'Coupons', link: '/admin/coupons', icon: 'percent', permission: 'coupons.manage' },
      { label: 'Banners', link: '/admin/banners', icon: 'image', permission: 'cms.manage' },
      { label: 'Newsletter', link: '/admin/newsletter', icon: 'mail', permission: 'cms.manage' },
    ],
  },
  {
    title: 'Content',
    items: [
      { label: 'Pages', link: '/admin/pages', icon: 'file', permission: 'cms.manage' },
      { label: 'Blog', link: '/admin/blog', icon: 'edit', permission: 'cms.manage' },
      { label: 'FAQs', link: '/admin/faqs', icon: 'info', permission: 'cms.manage' },
      { label: 'Testimonials', link: '/admin/testimonials', icon: 'message', permission: 'cms.manage' },
      { label: 'Enquiries', link: '/admin/enquiries', icon: 'mail', permission: 'cms.manage', badge: 'new_enquiries' },
    ],
  },
  {
    title: 'System',
    items: [
      { label: 'Settings', link: '/admin/settings', icon: 'settings', permission: 'settings.manage' },
      { label: 'Staff', link: '/admin/staff', icon: 'user', permission: 'users.manage' },
      { label: 'Roles & permissions', link: '/admin/roles', icon: 'shield', permission: 'users.manage' },
      { label: 'Audit log', link: '/admin/audit-log', icon: 'history', permission: 'settings.manage' },
    ],
  },
];
