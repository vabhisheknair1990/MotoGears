import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { SeoService } from '../../../core/services/seo.service';
import { AppDatePipe } from '../../../shared/pipes/inr.pipe';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { PaginationComponent } from '../../../shared/components/pagination.component';
import { AdminApiService } from '../data/admin-api.service';
import { InventoryTransaction } from '../data/admin.models';
import { ListState } from '../data/list-state';
import { PageHeaderComponent } from '../shared/page-header.component';

@Component({
  selector: 'adm-stock-movements-page',
  imports: [RouterLink, AppDatePipe, EmptyStateComponent, PaginationComponent, PageHeaderComponent],
  template: `
    <adm-page-header title="Stock movements" subtitle="Every stock change across all SKUs: receipts, sales reservations, shipments, cancellations and manual adjustments." back="/admin/inventory" backLabel="Inventory" />
    <section class="card">
      <div class="toolbar">
        <select class="select input-sm" aria-label="Movement type" [value]="list.value('type')" (change)="list.set({ type: $any($event.target).value || null })">
          <option value="">All movement types</option>
          @for (t of types; track t.value) { <option [value]="t.value">{{ t.label }}</option> }
        </select>
        <label class="date">From <input class="input input-sm" type="date" (change)="list.set({ from: $any($event.target).value || null })" /></label>
        <label class="date">To <input class="input input-sm" type="date" (change)="list.set({ to: $any($event.target).value || null })" /></label>
      </div>
      <div class="table-wrap flat" [class.busy]="list.loading()">
        <table class="table">
          <thead><tr><th>When</th><th>Product</th><th>Type</th><th class="num">Change</th><th class="num">On hand after</th><th class="hide-mobile">Reference</th><th class="hide-mobile">By</th></tr></thead>
          <tbody>
            @for (t of list.items(); track t.id) {
              <tr>
                <td class="text-sm">{{ t.created_at | appDate: true }}</td>
                <td>@if (t.product) { <span class="fw-600">{{ t.product.name }}</span><div class="text-xs text-muted mono">{{ t.product.sku }}</div> }</td>
                <td>{{ t.type_label }}@if (t.note) { <div class="text-xs text-muted">{{ t.note }}</div> }</td>
                <td class="num fw-700" [class.text-success]="t.quantity > 0" [class.text-danger]="t.quantity < 0">{{ t.quantity > 0 ? '+' : '' }}{{ t.quantity }}</td>
                <td class="num">{{ t.quantity_after }}</td>
                <td class="hide-mobile mono text-sm">@if (t.reference_type?.endsWith('Order') && t.reference_id) { <a class="link" [routerLink]="['/admin/orders', t.reference_id]">{{ t.reference }}</a> } @else { {{ t.reference ?? '—' }} }</td>
                <td class="hide-mobile text-sm">{{ t.user ?? 'System' }}</td>
              </tr>
            } @empty {
              @if (!list.loading()) { <tr><td colspan="7"><app-empty-state icon="history" title="No stock movements" /></td></tr> }
            }
          </tbody>
        </table>
      </div>
      <div class="pg"><app-pagination [meta]="list.meta()" (pageChange)="list.goTo($event)" /></div>
    </section>
  `,
  styles: `
    .toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 14px 16px; border-bottom: 1px solid var(--line); }
    .toolbar .select { width: auto; }
    .date { display: inline-flex; gap: 6px; align-items: center; font-size: 12.5px; color: var(--muted); }
    .date .input { width: 150px; }
    .flat { border: 0; border-radius: 0; } .busy { opacity: .6; } .pg { padding: 0 16px; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class StockMovementsPage {
  private readonly api = inject(AdminApiService);
  protected readonly list = new ListState<InventoryTransaction>((q) => this.api.page<InventoryTransaction>('inventory/transactions', q), { per_page: 30 });
  protected readonly types = [
    { value: 'stock_received', label: 'Stock received' },
    { value: 'order_reserved', label: 'Order reserved' },
    { value: 'order_cancelled', label: 'Order cancelled (released)' },
    { value: 'order_shipped', label: 'Order shipped' },
    { value: 'manual_adjustment', label: 'Manual adjustment' },
    { value: 'return', label: 'Return' },
  ];

  constructor() {
    inject(SeoService).set({ title: 'Stock movements' });
    this.list.load();
  }
}
