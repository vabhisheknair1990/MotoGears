import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { AppNotification, PageMeta } from '../../../core/models/api.models';
import { CustomerService } from '../../../core/services/customer.service';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { PaginationComponent } from '../../../shared/components/pagination.component';
import { TimeAgoPipe } from '../../../shared/pipes/inr.pipe';

@Component({
  selector: 'app-notifications-page',
  imports: [RouterLink, IconComponent, EmptyStateComponent, PaginationComponent, TimeAgoPipe],
  template: `
    <div class="row between mb-16"><h1 class="h1">Notifications</h1>
      @if (unread()) { <button class="btn btn-sm" (click)="markAll()"><app-icon name="check" [size]="15" /> Mark all as read</button> }
    </div>
    <div class="card">
      @for (n of items(); track n.id) {
        <a class="n" [class.unread]="!n.read_at" [routerLink]="n.url ?? '/account'">
          <span class="ic"><app-icon [name]="n.type === 'OrderPlacedNotification' ? 'package' : 'truck'" [size]="18" /></span>
          <div class="grow"><strong>{{ n.title }}</strong><span class="text-sm">{{ n.message }}</span></div>
          <span class="text-xs text-muted">{{ n.created_at | timeAgo }}</span>
        </a>
      } @empty {
        <app-empty-state icon="bell" title="No notifications" message="Order updates will appear here." />
      }
    </div>
    <app-pagination [meta]="meta()" (pageChange)="load($event)" />
  `,
  styles: `
    .h1 { font-size: 28px; margin: 0; }
    .n { display: flex; gap: 12px; align-items: center; padding: 14px 18px; border-bottom: 1px solid var(--line-2); }
    .n .grow { display: flex; flex-direction: column; }
    .n.unread { background: var(--info-50); }
    .n:hover { color: var(--ink); background: var(--surface-2); }
    .ic { width: 36px; height: 36px; border-radius: 50%; background: var(--line-2); display: grid; place-items: center; flex: none; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NotificationsPage {
  private readonly customer = inject(CustomerService);
  protected readonly items = signal<AppNotification[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);
  protected readonly unread = signal(0);

  constructor() {
    this.load(1);
  }

  load(page: number): void {
    this.customer.getNotifications(page).subscribe((p) => { this.items.set(p.items); this.meta.set(p.meta); this.unread.set(p.meta.unread); });
  }

  markAll(): void {
    this.customer.markNotificationsRead().subscribe(() => this.load(this.meta()?.current_page ?? 1));
  }
}
