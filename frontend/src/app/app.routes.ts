import { Routes } from '@angular/router';
import { adminGuard, adminGuestGuard, authGuard, guestGuard } from './core/guards/auth.guards';
import { StorefrontLayoutComponent } from './features/storefront/layout/storefront-layout.component';

export const routes: Routes = [
  {
    path: 'admin/login',
    canActivate: [adminGuestGuard],
    loadComponent: () => import('./features/admin/auth/admin-login.page').then((m) => m.AdminLoginPage),
    title: 'Admin login | MotoGears',
  },
  {
    path: 'admin/invoice/:id',
    canActivate: [adminGuard],
    loadComponent: () => import('./features/storefront/account/invoice.page').then((m) => m.InvoicePage),
    data: { admin: true },
    title: 'Invoice | MotoGears Admin',
  },
  {
    path: 'admin',
    canActivate: [adminGuard],
    loadChildren: () => import('./features/admin/admin.routes').then((m) => m.ADMIN_ROUTES),
  },
  {
    path: 'invoice/:id',
    canActivate: [authGuard],
    loadComponent: () => import('./features/storefront/account/invoice.page').then((m) => m.InvoicePage),
    title: 'Invoice | MotoGears',
  },
  {
    path: '',
    component: StorefrontLayoutComponent,
    children: [
      { path: '', loadComponent: () => import('./features/storefront/home/home.page').then((m) => m.HomePage) },
      { path: 'shop', loadComponent: () => import('./features/storefront/catalog/product-list.page').then((m) => m.ProductListPage), data: { mode: 'shop' } },
      { path: 'category/:slug', loadComponent: () => import('./features/storefront/catalog/product-list.page').then((m) => m.ProductListPage), data: { mode: 'category' } },
      { path: 'brand/:slug', loadComponent: () => import('./features/storefront/catalog/product-list.page').then((m) => m.ProductListPage), data: { mode: 'brand' } },
      { path: 'search', loadComponent: () => import('./features/storefront/catalog/product-list.page').then((m) => m.ProductListPage), data: { mode: 'search' } },
      { path: 'brands', loadComponent: () => import('./features/storefront/catalog/brands.page').then((m) => m.BrandsPage) },
      { path: 'products/:slug', loadComponent: () => import('./features/storefront/product/product-detail.page').then((m) => m.ProductDetailPage) },
      { path: 'vehicles', loadComponent: () => import('./features/storefront/vehicles/vehicles.page').then((m) => m.VehiclesPage) },
      { path: 'cart', loadComponent: () => import('./features/storefront/cart/cart.page').then((m) => m.CartPage) },
      { path: 'checkout', canActivate: [authGuard], loadComponent: () => import('./features/storefront/checkout/checkout.page').then((m) => m.CheckoutPage) },
      { path: 'order-success/:orderNumber', canActivate: [authGuard], loadComponent: () => import('./features/storefront/checkout/order-success.page').then((m) => m.OrderSuccessPage) },
      { path: 'account', canActivate: [authGuard], loadChildren: () => import('./features/storefront/account/account.routes').then((m) => m.ACCOUNT_ROUTES) },
      { path: 'login', canActivate: [guestGuard], loadComponent: () => import('./features/storefront/auth/login.page').then((m) => m.LoginPage) },
      { path: 'register', canActivate: [guestGuard], loadComponent: () => import('./features/storefront/auth/register.page').then((m) => m.RegisterPage) },
      { path: 'forgot-password', canActivate: [guestGuard], loadComponent: () => import('./features/storefront/auth/forgot-password.page').then((m) => m.ForgotPasswordPage) },
      { path: 'reset-password', loadComponent: () => import('./features/storefront/auth/reset-password.page').then((m) => m.ResetPasswordPage) },
      { path: 'contact', loadComponent: () => import('./features/storefront/content/contact.page').then((m) => m.ContactPage) },
      { path: 'about', loadComponent: () => import('./features/storefront/content/cms-page.page').then((m) => m.CmsPagePage), data: { slug: 'about' } },
      { path: 'page/:slug', loadComponent: () => import('./features/storefront/content/cms-page.page').then((m) => m.CmsPagePage) },
      { path: 'faq', loadComponent: () => import('./features/storefront/content/faq.page').then((m) => m.FaqPage) },
      { path: 'blog', loadComponent: () => import('./features/storefront/content/blog-list.page').then((m) => m.BlogListPage) },
      { path: 'blog/:slug', loadComponent: () => import('./features/storefront/content/blog-post.page').then((m) => m.BlogPostPage) },
      { path: '**', loadComponent: () => import('./features/storefront/content/not-found.page').then((m) => m.NotFoundPage) },
    ],
  },
];
