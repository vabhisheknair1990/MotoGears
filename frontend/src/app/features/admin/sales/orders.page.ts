import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { catchError, of } from 'rxjs';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { errorMessage } from '../../../core/utils/http-errors';
import { AppDatePipe, InrPipe } from '../../../shared/pipes/inr.pipe';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { PaginationComponent } from '../../../shared/components/pagination.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge.component';
import { AdminApiService, downloadCsv } from '../data/admin-api.service';
import { AdminOrder, OrderListMeta } from '../data/admin.models';
import { ListState } from '../data/list-state';
import { PageHeaderComponent } from '../shared/page-header.component';
import { SearchInputComponent } from '../shared/search-input.component';

@Component({
  selector: 'adm-orders-page',
  imports: [RouterLink, AppDatePipe, InrPipe, EmptyStateComponent, IconComponent, PaginationComponent, StatusBadgeComponent, PageHeaderComponent, SearchInputComponent],
  template: `
    <adm-page-header title="Orders" subtitle="Search, filter and fulfil customer orders.">
      <button type="button" class="btn" (click)="exportCsv()" [disabled]="!list.items().length"><app-icon name="download" [size]="16" /> Export page</button>
    </adm-page-header>

    <div class="tabs status-tabs" role="tablist" aria-label="Order status">
      <button type="button" role="tab" [class.active]="!list.value('status')" [attr.aria-selected]="!list.value('status')" (click)="setStatus('')">All</button>
      @for (s of statuses(); track s.value) {
        <button type="button" role="tab" [class.active]="list.value('status') === s.value" [attr.aria-selected]="list.value('status') === s.value" (click)="setStatus(s.value)">
          {{ s.label }} @if (counts()[s.value]) { <span class="tab-count">{{ counts()[s.value] }}</span> }
        </button>
      }
    </div>

    <section class="card">
      <div class="toolbar">
        <adm-search placeholder="Order #, customer name, email or phone…" [value]="list.value('search')" (search)="list.set({ search: $event || null })" />
        <select class="select input-sm" aria-label="Payment status" [value]="list.value('payment_status')" (change)="list.set({ payment_status: $any($event.target).value || null })">
          <option value="">Any payment</option><option value="paid">Paid</option><option value="pending">Pending</option><option value="failed">Failed</option><option value="refunded">Refunded</option>
        </select>
        <select class="select input-sm" aria-label="Payment method" [value]="list.value('payment_method')" (change)="list.set({ payment_method: $any($event.target).value || null })">
          <option value="">Any method</option>
          @for (m of lookups()?.payment_methods ?? []; track m.value) { <option [value]="m.value">{{ m.label }}</option> }
        </select>
        <label class="date"><span>From</span><input class="input input-sm" type="date" [value]="list.value('from')" (change)="list.set({ from: $any($event.target).value || null })" /></label>
        <label class="date"><span>To</span><input class="input input-sm" type="date" [value]="list.value('to')" (change)="list.set({ to: $any($event.target).value || null })" /></label>
        <select class="select input-sm" aria-label="Sort" [value]="list.value('sort')" (change)="list.set({ sort: $any($event.target).value })">
          <option value="newest">Newest first</option><option value="oldest">Oldest first</option><option value="total_high">Highest total</option><option value="total_low">Lowest total</option>
        </select>
      </div>
      @if (list.error(); as err) { <div class="alert alert-danger m-16">{{ err }} <button class="btn btn-sm" (click)="list.load()">Retry</button></div> }
      <div class="table-wrap flat" [class.busy]="list.loading()">
        <table class="table clickable">
          <thead><tr><th>Order</th><th>Customer</th><th class="hide-mobile">Items</th><th>Payment</th><th>Status</th><th class="num">Total</th></tr></thead>
          <tbody>
            @for (o of list.items(); track o.id) {
              <tr [routerLink]="['/admin/orders', o.id]" tabindex="0" (keydown.enter)="open(o.id)">
                <td><div class="fw-600 mono">{{ o.order_number }}</div><div class="text-xs text-muted">{{ o.placed_at | appDate: true }}</div></td>
                <td><div>{{ o.customer?.name ?? o.shipping_address.name }}</div><div class="text-xs text-muted">{{ o.shipping_address.city }}, {{ o.shipping_address.state }}</div></td>
                <td class="hide-mobile">{{ o.total_quantity ?? o.items_count }} × <span class="text-muted text-sm">{{ o.items?.[0]?.product_name }}@if ((o.items?.length ?? 0) > 1) { +{{ (o.items?.length ?? 1) - 1 }} }</span></td>
                <td><app-status-badge [status]="o.payment_status" /><div class="text-xs text-muted mt-2">{{ o.payment_method_label }}</div></td>
                <td><app-status-badge [status]="o.status" [label]="o.status_label" /></td>
                <td class="num fw-600">{{ o.grand_total | inr }}</td>
              </tr>
            } @empty {
              @if (!list.loading()) { <tr><td colspan="6"><app-empty-state icon="package" title="No orders match" message="Try clearing filters or searching by order number." /></td></tr> }
              @else { @for (i of [1, 2, 3, 4, 5]; track i) { <tr><td colspan="6"><div class="skeleton" style="height:18px"></div></td></tr> } }
            }
          </tbody>
        </table>
      </div>
      <div class="pg"><app-pagination [meta]="list.meta()" (pageChange)="list.goTo($event)" /></div>
    </section>
  `,
  styles: `
    .status-tabs { overflow-x: auto; margin-bottom: 14px; white-space: nowrap; }
    .tab-count { background: var(--line-2); border-radius: 999px; padding: 0 7px; font-size: 11px; margin-left: 4px; }
    .toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 14px 16px; border-bottom: 1px solid var(--line); }
    .toolbar .select { width: auto; }
    .date { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--muted); }
    .date .input { width: 150px; }
    .flat { border: 0; border-radius: 0; }
    .busy { opacity: .6; }
    .pg { padding: 0 16px; }
    .m-16 { margin: 16px; }
    .mt-2 { margin-top: 3px; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class OrdersPage {
  private readonly api = inject(AdminApiService);
  private readonly router = inject(Router);
  private readonly toast = inject(ToastService);
  protected readonly lookups = toSignal(this.api.lookups().pipe(catchError(() => of(null))), { initialValue: null });
  protected readonly list = new ListState<AdminOrder, OrderListMeta>((q) => this.api.page<AdminOrder, OrderListMeta>('orders', q));
  protected readonly statuses = computed(() => this.list.meta()?.statuses ?? this.lookups()?.order_statuses ?? []);
  protected readonly counts = computed(() => this.list.meta()?.status_counts ?? {});

  constructor() {
    inject(SeoService).set({ title: 'Orders' });
    const qp = inject(ActivatedRoute).snapshot.queryParamMap;
    this.list.query.set({ page: 1, per_page: 20, sort: 'newest', status: qp.get('status'), customer_id: qp.get('customer_id'), search: qp.get('search') });
    this.list.load();
  }

  protected setStatus(status: string): void {
    this.list.set({ status: status || null });
  }

  protected open(id: number): void {
    void this.router.navigate(['/admin/orders', id]);
  }

  protected exportCsv(): void {
    try {
      downloadCsv(
        `orders-page-${this.list.meta()?.current_page ?? 1}.csv`,
        ['Order', 'Placed', 'Customer', 'Email', 'City', 'Status', 'Payment', 'Method', 'Subtotal', 'Discount', 'Shipping', 'Tax', 'Total'],
        this.list.items().map((o) => [o.order_number, o.placed_at, o.customer?.name ?? o.shipping_address.name, o.customer?.email, o.shipping_address.city, o.status_label, o.payment_status, o.payment_method_label, o.subtotal, o.discount, o.shipping, o.tax, o.grand_total]),
      );
    } catch (e) {
      this.toast.error(errorMessage(e, 'Export failed.'));
    }
  }
}
