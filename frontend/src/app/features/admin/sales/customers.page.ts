import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { SeoService } from '../../../core/services/seo.service';
import { AppDatePipe, InrPipe, TimeAgoPipe } from '../../../shared/pipes/inr.pipe';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { PaginationComponent } from '../../../shared/components/pagination.component';
import { AdminApiService } from '../data/admin-api.service';
import { AdminCustomer } from '../data/admin.models';
import { ListState } from '../data/list-state';
import { PageHeaderComponent } from '../shared/page-header.component';
import { SearchInputComponent } from '../shared/search-input.component';

@Component({
  selector: 'adm-customers-page',
  imports: [RouterLink, AppDatePipe, InrPipe, TimeAgoPipe, EmptyStateComponent, PaginationComponent, PageHeaderComponent, SearchInputComponent],
  template: `
    <adm-page-header title="Customers" subtitle="Shopper accounts, lifetime value and order history." />
    <section class="card">
      <div class="toolbar">
        <adm-search placeholder="Name, email or phone…" [value]="list.value('search')" (search)="list.set({ search: $event || null })" />
        <select class="select input-sm" aria-label="Status" [value]="list.value('status')" (change)="list.set({ status: $any($event.target).value || null })">
          <option value="">All statuses</option><option value="active">Active</option><option value="inactive">Disabled</option>
        </select>
        <select class="select input-sm" aria-label="Sort" [value]="list.value('sort')" (change)="list.set({ sort: $any($event.target).value })">
          <option value="newest">Newest</option><option value="spent">Highest spend</option><option value="orders">Most orders</option><option value="name">Name A–Z</option>
        </select>
        <span class="grow"></span><span class="text-sm text-muted">{{ list.meta()?.total ?? 0 }} customers</span>
      </div>
      <div class="table-wrap flat" [class.busy]="list.loading()">
        <table class="table clickable">
          <thead><tr><th>Customer</th><th class="hide-mobile">Phone</th><th class="num">Orders</th><th class="num">Spent</th><th class="hide-mobile">Last order</th><th class="hide-mobile">Joined</th><th>Status</th></tr></thead>
          <tbody>
            @for (c of list.items(); track c.id) {
              <tr [routerLink]="['/admin/customers', c.id]" tabindex="0" (keydown.enter)="open(c.id)">
                <td><div class="fw-600">{{ c.name }}</div><div class="text-xs text-muted">{{ c.email }}</div></td>
                <td class="hide-mobile">{{ c.phone ?? '—' }}</td>
                <td class="num">{{ c.orders_count }}</td>
                <td class="num fw-600">{{ c.total_spent | inr: 'whole' }}</td>
                <td class="hide-mobile">{{ c.last_order_at ? (c.last_order_at | timeAgo) : '—' }}</td>
                <td class="hide-mobile">{{ c.created_at | appDate }}</td>
                <td>@if (c.is_active) { <span class="badge badge-success">Active</span> } @else { <span class="badge badge-danger">Disabled</span> }</td>
              </tr>
            } @empty {
              @if (!list.loading()) { <tr><td colspan="7"><app-empty-state icon="users" title="No customers found" /></td></tr> }
              @else { @for (i of [1, 2, 3, 4, 5]; track i) { <tr><td colspan="7"><div class="skeleton" style="height:18px"></div></td></tr> } }
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
    .flat { border: 0; border-radius: 0; } .busy { opacity: .6; } .pg { padding: 0 16px; } .grow { flex: 1; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CustomersPage {
  private readonly api = inject(AdminApiService);
  private readonly router = inject(Router);
  protected readonly list = new ListState<AdminCustomer>((q) => this.api.page<AdminCustomer>('customers', q), { per_page: 20, sort: 'newest' });

  constructor() {
    inject(SeoService).set({ title: 'Customers' });
    this.list.load();
  }

  protected open(id: number): void {
    void this.router.navigate(['/admin/customers', id]);
  }
}
