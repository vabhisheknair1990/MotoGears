import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRouteSnapshot, NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { catchError, filter, interval, map, of, startWith, switchMap } from 'rxjs';
import { AuthService } from '../../../core/services/auth.service';
import { AuthStore } from '../../../core/state/auth.store';
import { TimeAgoPipe } from '../../../shared/pipes/inr.pipe';
import { IconComponent } from '../../../shared/components/icon.component';
import { AdminApiService } from '../data/admin-api.service';
import { AdminNotification } from '../data/admin.models';
import { ADMIN_NAV } from './admin-nav';

@Component({
  selector: 'adm-layout',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, IconComponent, TimeAgoPipe],
  templateUrl: './admin-layout.component.html',
  styleUrl: './admin-layout.component.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { '(document:keydown.escape)': 'closeMenus()' },
})
export class AdminLayoutComponent {
  protected readonly auth = inject(AuthStore);
  private readonly authService = inject(AuthService);
  private readonly api = inject(AdminApiService);
  private readonly router = inject(Router);

  protected readonly sidebarOpen = signal(false);
  protected readonly menu = signal<'user' | 'notifications' | null>(null);
  protected readonly notifications = signal<AdminNotification[]>([]);
  protected readonly unread = signal(0);

  protected readonly nav = computed(() =>
    ADMIN_NAV.map((s) => ({ ...s, items: s.items.filter((i) => this.auth.hasPermission(i.permission)) })).filter((s) => s.items.length),
  );
  protected readonly badges = computed(() => this.auth.user()?.badges ?? { open_orders: 0, pending_reviews: 0, new_enquiries: 0, unread_notifications: 0 });
  protected readonly initials = computed(() =>
    (this.auth.user()?.name ?? '?').split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase(),
  );

  /** Page title + breadcrumb from the deepest route's `data.title`. */
  protected readonly crumbs = toSignal(
    this.router.events.pipe(
      filter((e) => e instanceof NavigationEnd),
      startWith(null),
      map(() => {
        const out: { label: string; link: string }[] = [];
        let snap: ActivatedRouteSnapshot | null = this.router.routerState.snapshot.root;
        let url = '';
        while (snap) {
          const seg = snap.url.map((u) => u.path).join('/');
          if (seg) url += '/' + seg;
          const title = snap.data?.['title'] as string | undefined;
          if (title && out[out.length - 1]?.label !== title) out.push({ label: title, link: url || '/admin' });
          snap = snap.firstChild;
        }
        return out;
      }),
    ),
    { initialValue: [] },
  );

  constructor() {
    const destroyRef = inject(DestroyRef);
    // Refresh badge counts (open orders, pending reviews…) every minute.
    interval(60_000)
      .pipe(
        startWith(0),
        switchMap(() => this.authService.refreshUser().pipe(catchError(() => of(null)))),
        takeUntilDestroyed(destroyRef),
      )
      .subscribe((u) => this.unread.set(u?.badges?.unread_notifications ?? this.unread()));
    this.router.events.pipe(filter((e) => e instanceof NavigationEnd), takeUntilDestroyed(destroyRef)).subscribe(() => {
      this.sidebarOpen.set(false);
      this.menu.set(null);
    });
  }

  protected toggleMenu(which: 'user' | 'notifications'): void {
    const next = this.menu() === which ? null : which;
    this.menu.set(next);
    if (next === 'notifications') this.loadNotifications();
  }

  protected closeMenus(): void {
    this.menu.set(null);
    this.sidebarOpen.set(false);
  }

  private loadNotifications(): void {
    this.api.page<AdminNotification, { unread: number }>('notifications', { per_page: 10 }).subscribe({
      next: (p) => {
        this.notifications.set(p.items);
        this.unread.set(p.meta.unread ?? 0);
      },
      error: () => this.notifications.set([]),
    });
  }

  protected markAllRead(): void {
    this.api.post('notifications/read').subscribe(() => {
      this.unread.set(0);
      this.notifications.update((list) => list.map((n) => ({ ...n, read_at: n.read_at ?? new Date().toISOString() })));
    });
  }

  protected open(n: AdminNotification): void {
    if (!n.read_at) {
      this.api.post('notifications/read', { ids: [n.id] }).subscribe();
      this.unread.update((c) => Math.max(0, c - 1));
    }
    this.menu.set(null);
    if (n.url) void this.router.navigateByUrl(n.url);
  }

  protected logout(): void {
    this.authService.logout('/admin/login');
  }
}
