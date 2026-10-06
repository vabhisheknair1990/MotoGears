import { Injectable, computed, signal } from '@angular/core';
import { User } from '../models/api.models';
import { storage } from '../utils/storage';

const TOKEN_KEY = 'mg.token';
const USER_KEY = 'mg.user';

/** Signal-based authentication state. The bearer token lives in localStorage. */
@Injectable({ providedIn: 'root' })
export class AuthStore {
  readonly token = signal<string | null>(storage.get(TOKEN_KEY));
  readonly user = signal<User | null>(storage.getJson<User>(USER_KEY));

  readonly isLoggedIn = computed(() => !!this.token() && !!this.user());
  readonly isStaff = computed(() => !!this.user()?.is_staff);
  readonly firstName = computed(() => this.user()?.name.split(' ')[0] ?? '');
  readonly permissions = computed(() => new Set(this.user()?.permissions ?? []));

  hasPermission(permission: string | string[] | undefined): boolean {
    if (!permission) return true;
    const perms = this.permissions();
    if (perms.has('*')) return true;
    return (Array.isArray(permission) ? permission : [permission]).some((p) => perms.has(p));
  }

  setSession(token: string, user: User): void {
    storage.set(TOKEN_KEY, token);
    this.token.set(token);
    this.setUser(user);
  }

  setUser(user: User): void {
    storage.setJson(USER_KEY, user);
    this.user.set(user);
  }

  clear(): void {
    storage.set(TOKEN_KEY, null);
    storage.set(USER_KEY, null);
    this.token.set(null);
    this.user.set(null);
  }
}
