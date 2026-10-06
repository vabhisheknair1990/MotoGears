import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { environment } from '../../../environments/environment';
import { cart, envelope, user } from '../../../testing/fixtures';
import { AuthStore } from '../state/auth.store';
import { AuthService } from './auth.service';

describe('AuthService', () => {
  let auth: AuthService;
  let store: AuthStore;
  let http: HttpTestingController;
  const url = (p: string) => `${environment.apiUrl}/${p}`;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({ providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()] });
    auth = TestBed.inject(AuthService);
    store = TestBed.inject(AuthStore);
    http = TestBed.inject(HttpTestingController);
    vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
  });

  it('stores the Sanctum token and user after login, then syncs cart and wishlist', () => {
    let name = '';
    auth.login('customer@example.com', 'password').subscribe((u) => (name = u.name));
    const req = http.expectOne(url('auth/login'));
    expect(req.request.body).toEqual({ email: 'customer@example.com', password: 'password', remember: false });
    req.flush(envelope({ token: '1|abc', token_type: 'Bearer', expires_at: '2026-12-31', user: user() }));
    expect(name).toBe('Aarav Menon');
    expect(store.isLoggedIn()).toBe(true);
    expect(store.token()).toBe('1|abc');
    http.expectOne(url('cart')).flush(envelope(cart()));
    http.expectOne(url('wishlist')).flush(envelope({ items: [] }));
  });

  it('logout revokes the token and clears local state even if the API fails', () => {
    store.setSession('1|abc', user());
    auth.logout();
    http.expectOne(url('auth/logout')).flush({ message: 'x' }, { status: 500, statusText: 'err' });
    expect(store.isLoggedIn()).toBe(false);
    expect(localStorage.getItem('mg.token')).toBeNull();
  });

  it('clears an invalid stored token on restore', () => {
    store.setSession('stale', user());
    auth.restoreSession().subscribe();
    http.expectOne(url('me')).flush({ message: 'Unauthenticated.' }, { status: 401, statusText: 'Unauthorized' });
    expect(store.token()).toBeNull();
  });
});
