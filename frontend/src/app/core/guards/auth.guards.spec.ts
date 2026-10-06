import { TestBed } from '@angular/core/testing';
import { ActivatedRouteSnapshot, provideRouter, RouterStateSnapshot, UrlTree } from '@angular/router';
import { user } from '../../../testing/fixtures';
import { AuthStore } from '../state/auth.store';
import { adminGuard, authGuard, guestGuard, permissionGuard } from './auth.guards';

const state = (url: string) => ({ url }) as RouterStateSnapshot;
const route = (data: Record<string, unknown> = {}) => ({ data }) as unknown as ActivatedRouteSnapshot;
const run = <T>(fn: () => T) => TestBed.runInInjectionContext(fn);
const path = (r: unknown) => (r instanceof UrlTree ? r.toString() : r);

describe('route guards', () => {
  let store: AuthStore;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({ providers: [provideRouter([])] });
    store = TestBed.inject(AuthStore);
  });

  it('authGuard redirects guests to /login with a return URL', () => {
    expect(path(run(() => authGuard(route(), state('/checkout'))))).toBe('/login?returnUrl=%2Fcheckout');
    store.setSession('t', user());
    expect(run(() => authGuard(route(), state('/checkout')))).toBe(true);
  });

  it('guestGuard sends signed-in users to their home', () => {
    expect(run(() => guestGuard(route(), state('/login')))).toBe(true);
    store.setSession('t', user());
    expect(path(run(() => guestGuard(route(), state('/login'))))).toBe('/account');
    store.setSession('t', user({ is_staff: true }));
    expect(path(run(() => guestGuard(route(), state('/login'))))).toBe('/admin');
  });

  it('adminGuard only admits staff', () => {
    expect(path(run(() => adminGuard(route(), state('/admin/orders'))))).toBe('/admin/login?returnUrl=%2Fadmin%2Forders');
    store.setSession('t', user());
    expect(path(run(() => adminGuard(route(), state('/admin'))))).toBe('/admin/login?denied=1');
    store.setSession('t', user({ is_staff: true }));
    expect(run(() => adminGuard(route(), state('/admin')))).toBe(true);
  });

  it('permissionGuard checks route data against the user permissions', () => {
    store.setSession('t', user({ is_staff: true, permissions: ['orders.view'] }));
    expect(run(() => permissionGuard(route({ permission: ['orders.view', 'orders.manage'] }), state('/admin/orders')))).toBe(true);
    expect(path(run(() => permissionGuard(route({ permission: 'products.manage' }), state('/admin/products'))))).toBe('/admin/forbidden');
    store.setSession('t', user({ is_staff: true, permissions: ['*'] }));
    expect(run(() => permissionGuard(route({ permission: 'products.manage' }), state('/admin/products')))).toBe(true);
  });
});
