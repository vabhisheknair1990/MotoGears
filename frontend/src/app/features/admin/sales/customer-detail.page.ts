import { ChangeDetectionStrategy, Component, computed, effect, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ConfirmService } from '../../../core/services/confirm.service';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { AuthStore } from '../../../core/state/auth.store';
import { errorMessage } from '../../../core/utils/http-errors';
import { AppDatePipe, InrPipe } from '../../../shared/pipes/inr.pipe';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge.component';
import { AdminApiService } from '../data/admin-api.service';
import { AdminCustomerDetail } from '../data/admin.models';
import { PageHeaderComponent } from '../shared/page-header.component';
import { StatCardComponent } from '../shared/stat-card.component';

@Component({
  selector: 'adm-customer-detail-page',
  imports: [RouterLink, AppDatePipe, InrPipe, EmptyStateComponent, IconComponent, StatusBadgeComponent, PageHeaderComponent, StatCardComponent],
  template: `
    @if (state() === 'missing') {
      <div class="card"><app-empty-state icon="users" title="Customer not found"><a routerLink="/admin/customers" class="btn btn-primary">Back to customers</a></app-empty-state></div>
    } @else if (c(); as c) {
      <adm-page-header [title]="c.name" [subtitle]="c.email + (c.phone ? ' · ' + c.phone : '') + ' · joined ' + (c.created_at | appDate)" back="/admin/customers" backLabel="Customers">
        <a class="btn" [routerLink]="['/admin/orders']" [queryParams]="{ customer_id: c.id }"><app-icon name="package" [size]="16" /> All orders</a>
        @if (canManage()) {
          <button type="button" class="btn" [class.btn-danger]="c.is_active" [disabled]="busy()" (click)="toggleActive()">
            <app-icon [name]="c.is_active ? 'lock' : 'check'" [size]="16" /> {{ c.is_active ? 'Disable account' : 'Enable account' }}
          </button>
        }
      </adm-page-header>
      @if (!c.is_active) { <div class="alert alert-warning"><app-icon name="lock" [size]="18" /> This account is disabled — the customer cannot sign in or place orders.</div> }

      <div class="kpis">
        <adm-stat label="Orders" [value]="c.orders_count" icon="package" />
        <adm-stat label="Lifetime spend" [value]="(c.total_spent | inr: 'whole')" icon="rupee" />
        <adm-stat label="Average order" [value]="(c.average_order_value | inr: 'whole')" icon="wallet" />
        <adm-stat label="Last login" [value]="c.last_login_at ? (c.last_login_at | appDate) : 'Never'" icon="clock" [hint]="c.marketing_opt_in ? 'Opted in to marketing' : 'No marketing emails'" />
      </div>

      <div class="layout">
        <section class="card">
          <div class="card-head"><h2>Recent orders</h2></div>
          <div class="table-wrap flat">
            <table class="table clickable">
              <thead><tr><th>Order</th><th>Date</th><th>Status</th><th class="num">Total</th></tr></thead>
              <tbody>
                @for (o of c.orders; track o.id) {
                  <tr [routerLink]="['/admin/orders', o.id]"><td class="mono fw-600">{{ o.order_number }}</td><td>{{ o.placed_at | appDate }}</td><td><app-status-badge [status]="o.status" [label]="o.status_label" /></td><td class="num fw-600">{{ o.grand_total | inr }}</td></tr>
                } @empty { <tr><td colspan="4" class="text-muted">No orders yet.</td></tr> }
              </tbody>
            </table>
          </div>
        </section>
        <div class="side">
          <section class="card">
            <div class="card-head"><h2>Addresses</h2></div>
            <div class="card-body list">
              @for (a of c.addresses; track a.id) {
                <address><strong>{{ a.label }}</strong>@if (a.is_default) { <span class="badge badge-info">Default</span> }<br />{{ a.name }} · {{ a.phone }}<br />{{ a.line1 }}@if (a.line2) {, {{ a.line2 }}}<br />{{ a.city }}, {{ a.state }} {{ a.postal_code }}</address>
              } @empty { <span class="text-muted text-sm">No saved addresses.</span> }
            </div>
          </section>
          <section class="card">
            <div class="card-head"><h2>Garage</h2></div>
            <div class="card-body list">
              @for (v of c.vehicles; track v.id) {
                <div class="veh"><app-icon [name]="v.variant.model?.vehicle_type === 'motorcycle' ? 'bike' : 'car'" [size]="18" /><div><strong>{{ v.nickname || v.variant.full_name }}</strong>@if (v.nickname) { <div class="text-xs text-muted">{{ v.variant.full_name }}</div> }</div></div>
              } @empty { <span class="text-muted text-sm">No saved vehicles.</span> }
            </div>
          </section>
        </div>
      </div>

      <section class="card mt">
        <div class="card-head"><h2>Reviews</h2></div>
        <div class="table-wrap flat">
          <table class="table">
            <thead><tr><th>Product</th><th>Rating</th><th>Review</th><th>Status</th></tr></thead>
            <tbody>
              @for (r of c.reviews; track r.id) {
                <tr><td>{{ r.product?.name }}</td><td class="rating">★ {{ r.rating }}</td><td><strong>{{ r.title }}</strong><div class="text-sm text-muted clamp-2">{{ r.comment }}</div></td><td><app-status-badge [status]="r.status" /></td></tr>
              } @empty { <tr><td colspan="4" class="text-muted">No reviews written.</td></tr> }
            </tbody>
          </table>
        </div>
      </section>
    } @else {
      <div class="card"><div class="skeleton" style="height:300px;margin:16px"></div></div>
    }
  `,
  styles: `
    .kpis { display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); margin-bottom: 18px; }
    .layout { display: grid; gap: 18px; }
    @media (min-width: 1100px) { .layout { grid-template-columns: minmax(0, 1fr) 340px; align-items: start; } }
    .side { display: flex; flex-direction: column; gap: 18px; }
    .card-head h2 { font-size: 16px; margin: 0; }
    .flat { border: 0; border-radius: 0; }
    .list { display: flex; flex-direction: column; gap: 14px; }
    address { font-style: normal; font-size: 13.5px; line-height: 1.55; }
    address .badge { margin-left: 6px; }
    .veh { display: flex; gap: 10px; align-items: flex-start; font-size: 14px; }
    .rating { color: #b45309; font-weight: 600; white-space: nowrap; }
    .mt { margin-top: 18px; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CustomerDetailPage {
  readonly id = input.required<string>();
  private readonly api = inject(AdminApiService);
  private readonly toast = inject(ToastService);
  private readonly confirm = inject(ConfirmService);
  private readonly seo = inject(SeoService);
  private readonly auth = inject(AuthStore);
  protected readonly canManage = computed(() => this.auth.hasPermission('customers.manage'));
  protected readonly c = signal<AdminCustomerDetail | null>(null);
  protected readonly state = signal<'loading' | 'ready' | 'missing'>('loading');
  protected readonly busy = signal(false);

  constructor() {
    effect(() => {
      const id = this.id();
      this.state.set('loading');
      this.api.get<AdminCustomerDetail>(`customers/${id}`).subscribe({
        next: (c) => {
          this.c.set(c);
          this.state.set('ready');
          this.seo.set({ title: c.name });
        },
        error: () => this.state.set('missing'),
      });
    });
  }

  protected async toggleActive(): Promise<void> {
    const c = this.c();
    if (!c) return;
    if (c.is_active) {
      const ok = await this.confirm.ask({ title: 'Disable this account?', message: `${c.name} will be signed out everywhere and won't be able to log in or order until re-enabled.`, confirmLabel: 'Disable', danger: true });
      if (!ok) return;
    }
    this.busy.set(true);
    this.api.patch<{ id: number; is_active: boolean }>(`customers/${c.id}`, { is_active: !c.is_active }).subscribe({
      next: (res) => {
        this.busy.set(false);
        this.c.set({ ...c, is_active: res.data.is_active });
        this.toast.success(res.message);
      },
      error: (err) => {
        this.busy.set(false);
        this.toast.error(errorMessage(err));
      },
    });
  }
}
