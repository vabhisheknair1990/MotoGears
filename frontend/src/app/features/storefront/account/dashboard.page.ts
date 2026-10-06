import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { AccountDashboard } from '../../../core/models/api.models';
import { CustomerService } from '../../../core/services/customer.service';
import { IconComponent } from '../../../shared/components/icon.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge.component';
import { AppDatePipe, InrPipe } from '../../../shared/pipes/inr.pipe';

@Component({
  selector: 'app-account-dashboard',
  imports: [RouterLink, IconComponent, StatusBadgeComponent, InrPipe, AppDatePipe],
  template: `
    @if (data(); as d) {
      <h1 class="h1">Hello, {{ d.user.name.split(' ')[0] }}</h1>
      <p class="text-muted">Member since {{ d.user.created_at | appDate }}</p>
      <div class="kpis">
        <a routerLink="/account/orders" class="kpi card"><app-icon name="package" /><strong>{{ d.stats.orders }}</strong><span>Orders ({{ d.stats.open_orders }} open)</span></a>
        <div class="kpi card"><app-icon name="rupee" /><strong>{{ d.stats.total_spent | inr: 'whole' }}</strong><span>Total spent</span></div>
        <a routerLink="/account/wishlist" class="kpi card"><app-icon name="heart" /><strong>{{ d.stats.wishlist }}</strong><span>Wishlist items</span></a>
        <a routerLink="/account/vehicles" class="kpi card"><app-icon name="car" /><strong>{{ d.stats.vehicles }}</strong><span>Saved vehicles</span></a>
      </div>
      <div class="grid md-grid-2 gap-16 mt-16">
        <section class="card">
          <div class="card-head"><h3>Recent orders</h3><a routerLink="/account/orders" class="link text-sm">View all</a></div>
          @for (o of d.recent_orders; track o.id) {
            <a class="ord" [routerLink]="['/account/orders', o.id]">
              <div class="grow"><strong class="mono">{{ o.order_number }}</strong><span class="text-xs text-muted">{{ o.placed_at | appDate }} · {{ o.items_count }} items</span></div>
              <app-status-badge [status]="o.status" [label]="o.status_label" />
              <strong>{{ o.grand_total | inr }}</strong>
            </a>
          } @empty { <p class="card-body text-muted mb-0">No orders yet. <a routerLink="/shop" class="link">Start shopping</a></p> }
        </section>
        <section class="card">
          <div class="card-head"><h3>My vehicle</h3><a routerLink="/account/vehicles" class="link text-sm">Manage garage</a></div>
          <div class="card-body">
            @if (d.default_vehicle; as v) {
              <div class="veh"><app-icon [name]="v.variant.model?.vehicle_type === 'motorcycle' ? 'bike' : 'car'" [size]="30" />
                <div><strong>{{ v.nickname || v.variant.full_name }}</strong><span class="text-sm text-muted">{{ v.variant.full_name }} · {{ v.year ?? v.variant.year_range }}</span></div></div>
              <a class="btn btn-primary btn-block mt-16" routerLink="/vehicles" [queryParams]="{ variant: v.variant.id }">Shop parts for this vehicle</a>
            } @else {
              <p class="text-muted">Save your vehicle to get personalised part recommendations.</p>
              <a class="btn btn-primary" routerLink="/account/vehicles">Add a vehicle</a>
            }
          </div>
        </section>
      </div>
      @if (d.stats.unread_notifications) {
        <a routerLink="/account/notifications" class="alert alert-info mt-16"><app-icon name="bell" [size]="18" /> You have {{ d.stats.unread_notifications }} unread notification(s).</a>
      }
    } @else {
      <div class="skeleton" style="height: 120px"></div><div class="skeleton mt-16" style="height: 240px"></div>
    }
  `,
  styles: `
    .h1 { font-size: 28px; margin: 0; }
    .kpis { display: grid; gap: 12px; grid-template-columns: repeat(2, 1fr); margin-top: 16px; }
    @media (min-width: 1000px) { .kpis { grid-template-columns: repeat(4, 1fr); } }
    .kpi { padding: 16px; display: flex; flex-direction: column; gap: 4px; }
    .kpi app-icon { color: var(--brand); }
    .kpi strong { font-size: 24px; font-family: var(--font-display); }
    .kpi span { font-size: 13px; color: var(--muted); }
    .ord { display: flex; align-items: center; gap: 12px; padding: 12px 20px; border-bottom: 1px solid var(--line-2); }
    .ord .grow { display: flex; flex-direction: column; }
    .ord:hover { background: var(--surface-2); color: var(--ink); }
    .veh { display: flex; gap: 14px; align-items: center; color: var(--brand); }
    .veh div { display: flex; flex-direction: column; color: var(--ink); }
    a.alert { display: flex; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountDashboardPage {
  protected readonly data = signal<AccountDashboard | null>(null);
  constructor() {
    inject(CustomerService).getDashboard().subscribe((d) => this.data.set(d));
  }
}
