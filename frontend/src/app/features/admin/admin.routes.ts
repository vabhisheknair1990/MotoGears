import { inject } from '@angular/core';
import { CanActivateFn, CanDeactivateFn, Router, Routes } from '@angular/router';
import { permissionGuard } from '../../core/guards/auth.guards';
import { ConfirmService } from '../../core/services/confirm.service';
import { AuthStore } from '../../core/state/auth.store';
import { firstAllowedAdminUrl } from './auth/admin-login.page';
import { AdminLayoutComponent } from './layout/admin-layout.component';

/** Staff without dashboard access are sent to the first section they can use. */
const dashboardGuard: CanActivateFn = () => {
  const auth = inject(AuthStore);
  return auth.hasPermission('dashboard.view') ? true : inject(Router).parseUrl(firstAllowedAdminUrl(auth));
};

/** Warns before leaving a form with unsaved changes. */
export const unsavedChangesGuard: CanDeactivateFn<{ hasUnsavedChanges(): boolean }> = (component) => {
  if (!component?.hasUnsavedChanges?.()) return true;
  return inject(ConfirmService).ask({ title: 'Discard unsaved changes?', message: 'You have edits that have not been saved. Leave this page anyway?', confirmLabel: 'Discard changes', danger: true });
};

const crud = (path: string, key: string, title: string, permission: string | string[]) => ({
  path,
  loadComponent: () => import('./crud/crud.page').then((m) => m.CrudPage),
  canActivate: [permissionGuard],
  data: { crud: key, title, permission },
});

/*
 * Every route below is also protected server-side: the matching API endpoints
 * check `permission:*` middleware, so hiding a link is only a UX nicety.
 */
export const ADMIN_ROUTES: Routes = [
  {
    path: '',
    component: AdminLayoutComponent,
    children: [
      { path: '', canActivate: [dashboardGuard], loadComponent: () => import('./dashboard/dashboard.page').then((m) => m.DashboardPage), data: { title: 'Dashboard' } },

      // Sales
      { path: 'orders', canActivate: [permissionGuard], data: { title: 'Orders', permission: ['orders.view', 'orders.manage'] }, loadComponent: () => import('./sales/orders.page').then((m) => m.OrdersPage) },
      { path: 'orders/:id', canActivate: [permissionGuard], data: { title: 'Order details', permission: ['orders.view', 'orders.manage'] }, loadComponent: () => import('./sales/order-detail.page').then((m) => m.OrderDetailPage) },
      { path: 'customers', canActivate: [permissionGuard], data: { title: 'Customers', permission: 'customers.view' }, loadComponent: () => import('./sales/customers.page').then((m) => m.CustomersPage) },
      { path: 'customers/:id', canActivate: [permissionGuard], data: { title: 'Customer', permission: 'customers.view' }, loadComponent: () => import('./sales/customer-detail.page').then((m) => m.CustomerDetailPage) },
      { path: 'reviews', canActivate: [permissionGuard], data: { title: 'Reviews', permission: 'reviews.manage' }, loadComponent: () => import('./sales/reviews.page').then((m) => m.ReviewsPage) },
      { path: 'reports', canActivate: [permissionGuard], data: { title: 'Reports', permission: 'reports.view' }, loadComponent: () => import('./system/reports.page').then((m) => m.ReportsPage) },

      // Catalog
      { path: 'products', canActivate: [permissionGuard], data: { title: 'Products', permission: 'products.manage' }, loadComponent: () => import('./catalog/products.page').then((m) => m.ProductsPage) },
      { path: 'products/import', canActivate: [permissionGuard], data: { title: 'Import products', permission: 'products.manage' }, loadComponent: () => import('./catalog/product-import.page').then((m) => m.ProductImportPage) },
      { path: 'products/create', canActivate: [permissionGuard], canDeactivate: [unsavedChangesGuard], data: { title: 'Add product', permission: 'products.manage' }, loadComponent: () => import('./catalog/product-form.page').then((m) => m.ProductFormPage) },
      { path: 'products/:id/edit', canActivate: [permissionGuard], canDeactivate: [unsavedChangesGuard], data: { title: 'Edit product', permission: 'products.manage' }, loadComponent: () => import('./catalog/product-form.page').then((m) => m.ProductFormPage) },
      { path: 'inventory', canActivate: [permissionGuard], data: { title: 'Inventory', permission: 'inventory.manage' }, loadComponent: () => import('./catalog/inventory.page').then((m) => m.InventoryPage) },
      { path: 'inventory/transactions', canActivate: [permissionGuard], data: { title: 'Stock movements', permission: 'inventory.manage' }, loadComponent: () => import('./catalog/stock-movements.page').then((m) => m.StockMovementsPage) },
      { path: 'inventory/:id', canActivate: [permissionGuard], data: { title: 'Stock record', permission: 'inventory.manage' }, loadComponent: () => import('./catalog/inventory-detail.page').then((m) => m.InventoryDetailPage) },
      crud('categories', 'categories', 'Categories', 'categories.manage'),
      crud('brands', 'brands', 'Brands', 'brands.manage'),
      crud('attributes', 'attributes', 'Attributes', 'products.manage'),
      {
        path: 'vehicles',
        canActivate: [permissionGuard],
        data: { title: 'Vehicles', permission: 'vehicles.manage' },
        loadComponent: () => import('./catalog/vehicles-shell.component').then((m) => m.VehiclesShellComponent),
        children: [
          { path: '', pathMatch: 'full', redirectTo: 'manufacturers' },
          crud('manufacturers', 'manufacturers', 'Manufacturers', 'vehicles.manage'),
          crud('models', 'models', 'Models', 'vehicles.manage'),
          crud('variants', 'variants', 'Variants', 'vehicles.manage'),
        ],
      },

      // Marketing & content
      crud('coupons', 'coupons', 'Coupons', 'coupons.manage'),
      crud('banners', 'banners', 'Banners', 'cms.manage'),
      crud('newsletter', 'newsletter', 'Newsletter', 'cms.manage'),
      crud('pages', 'pages', 'Pages', 'cms.manage'),
      crud('blog', 'blog', 'Blog', 'cms.manage'),
      crud('blog-categories', 'blog-categories', 'Blog categories', 'cms.manage'),
      crud('faqs', 'faqs', 'FAQs', 'cms.manage'),
      crud('testimonials', 'testimonials', 'Testimonials', 'cms.manage'),
      crud('enquiries', 'enquiries', 'Enquiries', 'cms.manage'),

      // System
      { path: 'settings', canActivate: [permissionGuard], data: { title: 'Settings', permission: 'settings.manage' }, loadComponent: () => import('./system/settings.page').then((m) => m.SettingsPage) },
      crud('staff', 'staff', 'Staff', 'users.manage'),
      { path: 'roles', canActivate: [permissionGuard], data: { title: 'Roles & permissions', permission: 'users.manage' }, loadComponent: () => import('./system/roles.page').then((m) => m.RolesPage) },
      crud('audit-log', 'audit-logs', 'Audit log', 'settings.manage'),

      { path: 'forbidden', data: { title: 'Access denied' }, loadComponent: () => import('./auth/forbidden.page').then((m) => m.ForbiddenPage) },
      { path: '**', data: { title: 'Not found' }, loadComponent: () => import('./auth/forbidden.page').then((m) => m.ForbiddenPage) },
    ],
  },
];
