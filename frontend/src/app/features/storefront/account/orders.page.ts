import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Order, PageMeta } from '../../../core/models/api.models';
import { OrderService } from '../../../core/services/order.service';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { PaginationComponent } from '../../../shared/components/pagination.component';
import { SkeletonComponent } from '../../../shared/components/skeleton.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge.component';
import { AppDatePipe, InrPipe } from '../../../shared/pipes/inr.pipe';

@Component({
  selector: 'app-orders-page',
  imports: [RouterLink, StatusBadgeComponent, PaginationComponent, EmptyStateComponent, SkeletonComponent, IconComponent, InrPipe, AppDatePipe],
  template: `
    <div class="row between wrap mb-16">
      <h1 class="h1">My orders</h1>
      <div class="row gap-8">
        <select class="select input-sm" style="width: auto" (change)="status.set($any($event.target).value); load(1)" aria-label="Filter by status">
          <option value="">All orders</option>
          @for (s of statuses; track s.value) { <option [value]="s.value">{{ s.label }}</option> }
        </select>
        <input class="input input-sm" type="search" placeholder="Order number" aria-label="Search by order number" (keyup.enter)="search.set($any($event.target).value); load(1)" />
      </div>
    </div>
    @if (loading()) {
      <div class="card card-body"><app-skeleton variant="row" [count]="4" /></div>
    } @else {
      @for (o of orders(); track o.id) {
        <a class="order card" [routerLink]="['/account/orders', o.id]">
          <div class="top">
            <div><span class="text-xs text-muted">Order</span><strong class="mono">{{ o.order_number }}</strong></div>
            <div><span class="text-xs text-muted">Placed</span><strong>{{ o.placed_at | appDate }}</strong></div>
            <div><span class="text-xs text-muted">Total</span><strong>{{ o.grand_total | inr }}</strong></div>
            <div><span class="text-xs text-muted">Payment</span><strong>{{ o.payment_method_label }}</strong></div>
            <app-status-badge [status]="o.status" [label]="o.status_label" />
          </div>
          <div class="items">
            @for (i of o.preview_items ?? []; track $index) { <img [src]="i.image" [alt]="i.name" class="thumb" [title]="i.name" /> }
            <span class="text-sm text-muted grow">{{ o.preview_items?.[0]?.name }}@if ((o.items_count ?? 0) > 1) { and {{ (o.items_count ?? 1) - 1 }} more }</span>
            @if (o.can_pay) { <span class="badge badge-warning">Payment pending</span> }
            <span class="link text-sm">View details <app-icon name="chevron-right" [size]="14" /></span>
          </div>
        </a>
      } @empty {
        <div class="card"><app-empty-state icon="package" title="No orders found." message="When you place an order it will appear here."><a class="btn btn-primary" routerLink="/shop">Start shopping</a></app-empty-state></div>
      }
      <app-pagination [meta]="meta()" (pageChange)="load($event)" />
    }
  `,
  styles: `
    .h1 { font-size: 28px; margin: 0; }
    .order { display: block; margin-bottom: 12px; transition: box-shadow .15s; }
    .order:hover { box-shadow: var(--shadow); color: var(--ink); }
    .top { display: flex; flex-wrap: wrap; gap: 12px 28px; align-items: center; padding: 14px 18px; border-bottom: 1px solid var(--line-2); background: var(--surface-2); border-radius: var(--radius) var(--radius) 0 0; }
    .top div { display: flex; flex-direction: column; }
    .top app-status-badge { margin-left: auto; }
    .items { display: flex; align-items: center; gap: 10px; padding: 12px 18px; }
    .link { display: inline-flex; align-items: center; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class OrdersPage {
  private readonly api = inject(OrderService);
  protected readonly orders = signal<Order[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);
  protected readonly loading = signal(true);
  protected readonly status = signal('');
  protected readonly search = signal('');
  protected readonly statuses = [
    { value: 'pending', label: 'Pending' }, { value: 'confirmed', label: 'Confirmed' }, { value: 'processing', label: 'Processing' },
    { value: 'shipped', label: 'Shipped' }, { value: 'delivered', label: 'Delivered' }, { value: 'cancelled', label: 'Cancelled' }, { value: 'returned', label: 'Returned' },
  ];

  constructor() {
    this.load(1);
  }

  load(page: number): void {
    this.loading.set(true);
    this.api.getOrders({ page, per_page: 10, status: this.status() || null, search: this.search() || null }).subscribe({
      next: (p) => { this.orders.set(p.items); this.meta.set(p.meta); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }
}
