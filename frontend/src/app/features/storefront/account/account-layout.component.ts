import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { WishlistService } from '../../../core/services/wishlist.service';
import { AuthStore } from '../../../core/state/auth.store';
import { IconComponent } from '../../../shared/components/icon.component';

@Component({
  selector: 'app-account-layout',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, IconComponent],
  template: `
    <div class="container acc">
      <aside class="nav card">
        <div class="who">
          <span class="avatar">{{ auth.firstName().charAt(0) }}</span>
          <div><strong>{{ auth.user()?.name }}</strong><span class="text-xs text-muted truncate">{{ auth.user()?.email }}</span></div>
        </div>
        <nav aria-label="Account">
          @for (l of links; track l.path) {
            <a [routerLink]="l.path" routerLinkActive="active" [routerLinkActiveOptions]="{ exact: l.path === '/account' }">
              <app-icon [name]="l.icon" [size]="18" /> {{ l.label }}
              @if (l.path === '/account/wishlist' && wishlist.count()) { <span class="badge">{{ wishlist.count() }}</span> }
            </a>
          }
          <button type="button" (click)="logout()"><app-icon name="logout" [size]="18" /> Logout</button>
        </nav>
      </aside>
      <section class="content"><router-outlet /></section>
    </div>
  `,
  styles: `
    .acc { display: grid; gap: 20px; padding-top: 24px; }
    @media (min-width: 900px) { .acc { grid-template-columns: 260px minmax(0, 1fr); align-items: start; } .nav { position: sticky; top: 150px; } }
    .who { display: flex; gap: 12px; align-items: center; padding: 16px; border-bottom: 1px solid var(--line); }
    .who div { display: flex; flex-direction: column; min-width: 0; }
    .avatar { width: 44px; height: 44px; border-radius: 50%; background: var(--brand); color: #fff; display: grid; place-items: center; font-weight: 800; font-size: 18px; text-transform: uppercase; flex: none; }
    nav { display: flex; flex-direction: column; padding: 8px; }
    @media (max-width: 899px) { nav { flex-direction: row; overflow-x: auto; } nav a, nav button { white-space: nowrap; } }
    nav a, nav button { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: var(--radius-sm); font-size: 14px; font-weight: 500; color: var(--ink-2); border: 0; background: none; text-align: left; }
    nav a:hover, nav button:hover { background: var(--surface-2); color: var(--ink); }
    nav a.active { background: var(--brand-50); color: var(--brand); font-weight: 700; }
    nav .badge { margin-left: auto; }
    .content { min-width: 0; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountLayoutComponent {
  protected readonly auth = inject(AuthStore);
  protected readonly wishlist = inject(WishlistService);
  private readonly authService = inject(AuthService);
  protected readonly links = [
    { path: '/account', label: 'Dashboard', icon: 'gauge' },
    { path: '/account/orders', label: 'My orders', icon: 'package' },
    { path: '/account/wishlist', label: 'Wishlist', icon: 'heart' },
    { path: '/account/addresses', label: 'Addresses', icon: 'pin' },
    { path: '/account/vehicles', label: 'Saved vehicles', icon: 'car' },
    { path: '/account/reviews', label: 'Reviews', icon: 'star' },
    { path: '/account/profile', label: 'Profile', icon: 'user' },
    { path: '/account/password', label: 'Password', icon: 'lock' },
    { path: '/account/notifications', label: 'Notifications', icon: 'bell' },
  ];

  logout(): void {
    this.authService.logout('/');
  }
}
