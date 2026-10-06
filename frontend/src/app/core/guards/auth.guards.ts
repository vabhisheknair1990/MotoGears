import { inject } from '@angular/core';
import { CanActivateFn, CanMatchFn, Router } from '@angular/router';
import { AuthStore } from '../state/auth.store';

/*
 * Route guards only improve navigation UX. Every protected API endpoint is
 * authorised again by Laravel (Sanctum + role/permission middleware + policies).
 */

export const authGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthStore);
  return auth.isLoggedIn() ? true : inject(Router).createUrlTree(['/login'], { queryParams: { returnUrl: state.url } });
};

export const guestGuard: CanActivateFn = () => {
  const auth = inject(AuthStore);
  return auth.isLoggedIn() ? inject(Router).createUrlTree([auth.isStaff() ? '/admin' : '/account']) : true;
};

export const adminGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthStore);
  const router = inject(Router);
  if (!auth.isLoggedIn()) {
    return router.createUrlTree(['/admin/login'], { queryParams: { returnUrl: state.url } });
  }
  return auth.isStaff() ? true : router.createUrlTree(['/admin/login'], { queryParams: { denied: 1 } });
};

export const adminGuestGuard: CanActivateFn = () => {
  const auth = inject(AuthStore);
  return auth.isLoggedIn() && auth.isStaff() ? inject(Router).createUrlTree(['/admin']) : true;
};

/** Route data: `{ permission: 'orders.view' }` or an array (any of). */
export const permissionGuard: CanActivateFn = (route) => {
  const auth = inject(AuthStore);
  const needed = route.data['permission'] as string | string[] | undefined;
  return auth.hasPermission(needed) ? true : inject(Router).createUrlTree(['/admin/forbidden']);
};

export const staffMatch: CanMatchFn = () => inject(AuthStore).isStaff();
