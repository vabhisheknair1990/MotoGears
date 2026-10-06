import { Routes } from '@angular/router';
import { AccountLayoutComponent } from './account-layout.component';

export const ACCOUNT_ROUTES: Routes = [
  {
    path: '',
    component: AccountLayoutComponent,
    children: [
      { path: '', loadComponent: () => import('./dashboard.page').then((m) => m.AccountDashboardPage), title: 'My account | MotoGears' },
      { path: 'orders', loadComponent: () => import('./orders.page').then((m) => m.OrdersPage), title: 'My orders | MotoGears' },
      { path: 'orders/:id', loadComponent: () => import('./order-detail.page').then((m) => m.OrderDetailPage), title: 'Order details | MotoGears' },
      { path: 'profile', loadComponent: () => import('./profile.page').then((m) => m.ProfilePage), title: 'Profile | MotoGears' },
      { path: 'password', loadComponent: () => import('./password.page').then((m) => m.PasswordPage), title: 'Change password | MotoGears' },
      { path: 'addresses', loadComponent: () => import('./addresses.page').then((m) => m.AddressesPage), title: 'Addresses | MotoGears' },
      { path: 'wishlist', loadComponent: () => import('./wishlist.page').then((m) => m.WishlistPage), title: 'Wishlist | MotoGears' },
      { path: 'vehicles', loadComponent: () => import('./garage.page').then((m) => m.GaragePage), title: 'My garage | MotoGears' },
      { path: 'reviews', loadComponent: () => import('./reviews.page').then((m) => m.MyReviewsPage), title: 'My reviews | MotoGears' },
      { path: 'notifications', loadComponent: () => import('./notifications.page').then((m) => m.NotificationsPage), title: 'Notifications | MotoGears' },
    ],
  },
];
