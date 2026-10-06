import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { catchError, of } from 'rxjs';
import { ConfirmService } from '../../../core/services/confirm.service';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { errorMessage } from '../../../core/utils/http-errors';
import { InrPipe } from '../../../shared/pipes/inr.pipe';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { PaginationComponent } from '../../../shared/components/pagination.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge.component';
import { AdminApiService, downloadCsv } from '../data/admin-api.service';
import { AdminProductRow } from '../data/admin.models';
import { ListState } from '../data/list-state';
import { categoryOptions } from '../crud/crud.configs';
import { PageHeaderComponent } from '../shared/page-header.component';
import { SearchInputComponent } from '../shared/search-input.component';

@Component({
  selector: 'adm-products-page',
  imports: [RouterLink, InrPipe, EmptyStateComponent, IconComponent, PaginationComponent, StatusBadgeComponent, PageHeaderComponent, SearchInputComponent],
  template: `
    <adm-page-header title="Products" subtitle="Your catalogue. Prices exclude GST; stock is managed in Inventory.">
      <button type="button" class="btn" (click)="exportCsv()" [disabled]="!list.items().length"><app-icon name="download" [size]="16" /> Export page</button>
      <a routerLink="/admin/products/import" class="btn"><app-icon name="upload" [size]="16" /> Import from Excel</a>
      <a routerLink="/admin/products/create" class="btn btn-primary"><app-icon name="plus" [size]="16" /> Add product</a>
    </adm-page-header>
    <section class="card">
      <div class="toolbar">
        <adm-search placeholder="Name, SKU or part number…" [value]="list.value('search')" (search)="list.set({ search: $event || null })" />
        <select class="select input-sm" aria-label="Category" [value]="list.value('category')" (change)="list.set({ category: $any($event.target).value || null })">
          <option value="">All categories</option>@for (c of categories(); track c.value) { <option [value]="c.value">{{ c.label }}</option> }
        </select>
        <select class="select input-sm" aria-label="Brand" [value]="list.value('brand')" (change)="list.set({ brand: $any($event.target).value || null })">
          <option value="">All brands</option>@for (b of lookups()?.brands ?? []; track b.id) { <option [value]="b.id">{{ b.name }}</option> }
        </select>
        <select class="select input-sm" aria-label="Status" [value]="list.value('status')" (change)="list.set({ status: $any($event.target).value || null })">
          <option value="">Active &amp; inactive</option><option value="active">Active</option><option value="inactive">Inactive</option><option value="trashed">Deleted</option>
        </select>
        <select class="select input-sm" aria-label="Stock" [value]="list.value('stock')" (change)="list.set({ stock: $any($event.target).value || null })">
          <option value="">Any stock</option><option value="in_stock">In stock</option><option value="low_stock">Low stock</option><option value="out_of_stock">Out of stock</option>
        </select>
        <select class="select input-sm" aria-label="Sort" [value]="list.value('sort')" (change)="list.set({ sort: $any($event.target).value })">
          <option value="newest">Newest</option><option value="name">Name</option><option value="price_low">Price ↑</option><option value="price_high">Price ↓</option><option value="stock_low">Stock ↑</option><option value="best_selling">Best selling</option>
        </select>
        <span class="grow"></span><span class="text-sm text-muted">{{ list.meta()?.total ?? 0 }} products</span>
      </div>
      <div class="table-wrap flat" [class.busy]="list.loading()">
        <table class="table">
          <thead><tr><th>Product</th><th class="hide-mobile">Category</th><th class="num">Price</th><th class="num">Available</th><th class="num hide-mobile">Sold</th><th class="center">Featured</th><th class="center">Active</th><th><span class="sr-only">Actions</span></th></tr></thead>
          <tbody>
            @for (p of list.items(); track p.id) {
              <tr [class.trashed]="p.deleted_at">
                <td>
                  <div class="prod">
                    @if (p.image) { <img class="thumb" [src]="p.image" alt="" loading="lazy" /> } @else { <span class="thumb ph"><app-icon name="image" [size]="16" /></span> }
                    <div class="min0">
                      <a class="fw-600 plain clamp-2" [routerLink]="['/admin/products', p.id, 'edit']">{{ p.name }}</a>
                      <div class="text-xs text-muted"><span class="mono">{{ p.sku }}</span> · {{ p.brand?.name }}@if (p.is_universal) { · Universal }</div>
                    </div>
                  </div>
                </td>
                <td class="hide-mobile text-sm">{{ p.category?.name }}</td>
                <td class="num"><span class="fw-600">{{ p.price | inr }}</span>@if (p.mrp > p.price) { <div class="text-xs text-muted"><s>{{ p.mrp | inr }}</s> −{{ p.discount_percent }}%</div> }</td>
                <td class="num">
                  <span class="fw-600" [class.text-danger]="(p.stock?.available ?? 0) <= 0">{{ p.stock?.available ?? 0 }}</span>
                  <div><app-status-badge [status]="p.stock_status" /></div>
                </td>
                <td class="num hide-mobile">{{ p.sold_count }}</td>
                <td class="center"><button type="button" class="star" [class.on]="p.is_featured" (click)="toggle(p, 'is_featured')" [disabled]="busy() === p.id || !!p.deleted_at" [attr.aria-label]="(p.is_featured ? 'Unfeature ' : 'Feature ') + p.name" [attr.aria-pressed]="p.is_featured"><app-icon [name]="p.is_featured ? 'star-filled' : 'star'" [size]="18" /></button></td>
                <td class="center">
                  <label class="switch"><input type="checkbox" [checked]="p.is_active" [disabled]="busy() === p.id || !!p.deleted_at" (change)="toggle(p, 'is_active')" [attr.aria-label]="'Active: ' + p.name" /><span class="track"></span></label>
                </td>
                <td class="act">
                  @if (p.deleted_at) {
                    <button type="button" class="btn btn-sm" (click)="restore(p)" [disabled]="busy() === p.id"><app-icon name="rotate" [size]="14" /> Restore</button>
                  } @else {
                    <a class="icon-btn sm" [routerLink]="['/products', p.slug]" target="_blank" rel="noopener" title="View in store" [attr.aria-label]="'View ' + p.name + ' in store'"><app-icon name="external" [size]="16" /></a>
                    <a class="icon-btn sm" [routerLink]="['/admin/products', p.id, 'edit']" title="Edit" [attr.aria-label]="'Edit ' + p.name"><app-icon name="edit" [size]="16" /></a>
                    <button type="button" class="icon-btn sm danger" (click)="remove(p)" [disabled]="busy() === p.id" title="Delete" [attr.aria-label]="'Delete ' + p.name"><app-icon name="trash" [size]="16" /></button>
                  }
                </td>
              </tr>
            } @empty {
              @if (!list.loading()) {
                <tr><td colspan="8"><app-empty-state icon="box" title="No products found" message="Adjust your filters or add a new product."><a routerLink="/admin/products/create" class="btn btn-primary btn-sm">Add product</a></app-empty-state></td></tr>
              } @else { @for (k of [1, 2, 3, 4, 5, 6]; track k) { <tr><td colspan="8"><div class="skeleton" style="height:22px"></div></td></tr> } }
            }
          </tbody>
        </table>
      </div>
      <div class="pg"><app-pagination [meta]="list.meta()" (pageChange)="list.goTo($event)" /></div>
    </section>
  `,
  styles: `
    .toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 14px 16px; border-bottom: 1px solid var(--line); }
    .toolbar .select { width: auto; max-width: 200px; } .grow { flex: 1; }
    .flat { border: 0; border-radius: 0; } .busy { opacity: .6; } .pg { padding: 0 16px; }
    .prod { display: flex; gap: 10px; align-items: center; min-width: 220px; }
    .min0 { min-width: 0; }
    .thumb.ph { display: inline-grid; place-items: center; color: var(--muted); background: var(--line-2); flex: none; }
    .plain { color: var(--ink); } .plain:hover { color: var(--brand); }
    .center { text-align: center; }
    .act { white-space: nowrap; text-align: right; }
    .act > * { vertical-align: middle; }
    .star { border: 0; background: none; cursor: pointer; color: #cbd5e1; padding: 4px; border-radius: 6px; }
    .star.on { color: #f59e0b; }
    .star:hover:not(:disabled) { color: #f59e0b; }
    .star:focus-visible { outline: 2px solid var(--brand); }
    .trashed td { opacity: .6; }
    .icon-btn.danger:hover { color: #dc2626; background: #fee2e2; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductsPage {
  private readonly api = inject(AdminApiService);
  private readonly toast = inject(ToastService);
  private readonly confirm = inject(ConfirmService);
  protected readonly lookups = toSignal(this.api.lookups().pipe(catchError(() => of(null))), { initialValue: null });
  protected readonly categories = computed(() => categoryOptions(this.lookups()));
  protected readonly list = new ListState<AdminProductRow>((q) => this.api.page<AdminProductRow>('products', q));
  protected readonly busy = signal<number | null>(null);

  constructor() {
    inject(SeoService).set({ title: 'Products' });
    const qp = inject(ActivatedRoute).snapshot.queryParamMap;
    this.list.query.set({ page: 1, per_page: 20, sort: 'newest', stock: qp.get('stock'), search: qp.get('search') });
    this.list.load();
  }

  protected toggle(p: AdminProductRow, key: 'is_active' | 'is_featured'): void {
    this.busy.set(p.id);
    this.api.patch(`products/${p.id}/toggle`, { [key]: !p[key] }).subscribe({
      next: (res) => {
        this.busy.set(null);
        this.list.patchRow((r) => r.id === p.id, { ...p, [key]: !p[key] });
        this.toast.success(res.message || 'Product updated');
      },
      error: (err) => {
        this.busy.set(null);
        this.toast.error(errorMessage(err));
        this.list.load();
      },
    });
  }

  protected async remove(p: AdminProductRow): Promise<void> {
    const ok = await this.confirm.ask({ title: 'Delete product?', message: `“${p.name}” will be removed from the store. Past orders keep their snapshot, and you can restore it from the Deleted filter.`, confirmLabel: 'Delete', danger: true });
    if (!ok) return;
    this.busy.set(p.id);
    this.api.delete(`products/${p.id}`).subscribe({
      next: (res) => {
        this.busy.set(null);
        this.toast.success(res.message || 'Product deleted');
        this.list.load();
      },
      error: (err) => {
        this.busy.set(null);
        this.toast.error(errorMessage(err));
      },
    });
  }

  protected restore(p: AdminProductRow): void {
    this.busy.set(p.id);
    this.api.post(`products/${p.id}/restore`).subscribe({
      next: (res) => {
        this.busy.set(null);
        this.toast.success(res.message || 'Product restored');
        this.list.load();
      },
      error: (err) => {
        this.busy.set(null);
        this.toast.error(errorMessage(err));
      },
    });
  }

  protected exportCsv(): void {
    downloadCsv(
      'products.csv',
      ['ID', 'SKU', 'Part number', 'Name', 'Brand', 'Category', 'MRP', 'Price', 'Available', 'Sold', 'Active'],
      this.list.items().map((p) => [p.id, p.sku, p.part_number, p.name, p.brand?.name, p.category?.name, p.mrp, p.price, p.stock?.available ?? 0, p.sold_count, p.is_active ? 'yes' : 'no']),
    );
  }
}
