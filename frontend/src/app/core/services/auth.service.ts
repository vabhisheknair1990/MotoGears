import { Injectable, inject } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, catchError, finalize, map, of, tap } from 'rxjs';
import { ApiService } from '../api/api.service';
import { silentNoRedirect } from '../api/http-context';
import { AuthPayload, User } from '../models/api.models';
import { AuthStore } from '../state/auth.store';
import { CartService } from './cart.service';
import { WishlistService } from './wishlist.service';

export interface RegisterInput { name: string; email: string; phone?: string | null; password: string; password_confirmation: string; marketing_opt_in?: boolean }

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly api = inject(ApiService);
  private readonly store = inject(AuthStore);
  private readonly router = inject(Router);
  private readonly cart = inject(CartService);
  private readonly wishlist = inject(WishlistService);

  login(email: string, password: string, remember = false): Observable<User> {
    return this.api.post<AuthPayload>('auth/login', { email, password, remember }, silentNoRedirect()).pipe(map((r) => this.onAuthenticated(r.data)));
  }

  adminLogin(email: string, password: string): Observable<User> {
    return this.api.post<AuthPayload>('admin/auth/login', { email, password }, silentNoRedirect()).pipe(map((r) => this.onAuthenticated(r.data, false)));
  }

  register(input: RegisterInput): Observable<User> {
    return this.api.post<AuthPayload>('auth/register', input, silentNoRedirect()).pipe(map((r) => this.onAuthenticated(r.data)));
  }

  logout(redirect = '/'): void {
    const done = () => {
      this.store.clear();
      this.cart.reset();
      this.wishlist.reset();
      void this.router.navigateByUrl(redirect);
    };
    if (!this.store.token()) {
      done();
      return;
    }
    this.api.post('auth/logout', {}, silentNoRedirect()).pipe(catchError(() => of(null)), finalize(done)).subscribe();
  }

  forgotPassword(email: string): Observable<string> {
    return this.api.post<null>('auth/forgot-password', { email }, silentNoRedirect()).pipe(map((r) => r.message));
  }

  resetPassword(input: { token: string; email: string; password: string; password_confirmation: string }): Observable<string> {
    return this.api.post<null>('auth/reset-password', input, silentNoRedirect()).pipe(map((r) => r.message));
  }

  /** Re-validates a stored token on app start. Invalid tokens are cleared silently. */
  restoreSession(): Observable<unknown> {
    if (!this.store.token()) {
      return of(null);
    }
    const path = this.store.isStaff() ? 'admin/auth/me' : 'me';
    return this.api.get<User>(path, undefined, silentNoRedirect()).pipe(
      tap((user) => {
        this.store.setUser(user);
        if (!user.is_staff || !this.router.url.startsWith('/admin')) {
          this.wishlist.load();
        }
      }),
      catchError(() => {
        this.store.clear();
        return of(null);
      }),
    );
  }

  refreshUser(): Observable<User> {
    return this.api.get<User>(this.store.isStaff() ? 'admin/auth/me' : 'me').pipe(tap((u) => this.store.setUser(u)));
  }

  private onAuthenticated(payload: AuthPayload, syncShop = true): User {
    this.store.setSession(payload.token, payload.user);
    if (syncShop) {
      // The server merged any guest cart into the account; forget the guest token and reload.
      this.cart.clearGuestToken();
      this.cart.load();
      this.wishlist.load();
    }
    return payload.user;
  }
}
