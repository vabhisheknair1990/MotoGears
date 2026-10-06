import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { applyServerErrors, errorMessage } from '../../../core/utils/http-errors';
import { TimeAgoPipe } from '../../../shared/pipes/inr.pipe';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { ModalComponent } from '../../../shared/components/modal.component';
import { PaginationComponent } from '../../../shared/components/pagination.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge.component';
import { AdminApiService } from '../data/admin-api.service';
import { InventoryRecord } from '../data/admin.models';
import { ListState } from '../data/list-state';
import { PageHeaderComponent } from '../shared/page-header.component';
import { SearchInputComponent } from '../shared/search-input.component';

export const ADJUST_TYPES = [
  { value: 'stock_received', label: 'Stock received (purchase / GRN)' },
  { value: 'manual_adjustment', label: 'Manual adjustment (count correction, damage)' },
  { value: 'return', label: 'Customer return restock' },
];

@Component({
  selector: 'adm-inventory-page',
  imports: [ReactiveFormsModule, RouterLink, TimeAgoPipe, EmptyStateComponent, IconComponent, ModalComponent, PaginationComponent, StatusBadgeComponent, PageHeaderComponent, SearchInputComponent],
  template: `
    <adm-page-header title="Inventory" subtitle="On-hand, reserved (in open orders) and available stock per SKU. Every change is logged.">
      <a routerLink="/admin/inventory/transactions" class="btn"><app-icon name="history" [size]="16" /> Stock movements</a>
    </adm-page-header>
    <div class="tabs" role="tablist">
      <button type="button" role="tab" [class.active]="!list.value('status')" (click)="list.set({ status: null })">All <span class="tab-count">{{ counts()?.all ?? '' }}</span></button>
      <button type="button" role="tab" [class.active]="list.value('status') === 'low_stock'" (click)="list.set({ status: 'low_stock' })">Low stock <span class="tab-count warn">{{ counts()?.low_stock ?? '' }}</span></button>
      <button type="button" role="tab" [class.active]="list.value('status') === 'out_of_stock'" (click)="list.set({ status: 'out_of_stock' })">Out of stock <span class="tab-count bad">{{ counts()?.out_of_stock ?? '' }}</span></button>
    </div>
    <section class="card">
      <div class="toolbar">
        <adm-search placeholder="SKU or product name…" [value]="list.value('search')" (search)="list.set({ search: $event || null })" />
        <select class="select input-sm" aria-label="Sort" [value]="list.value('sort')" (change)="list.set({ sort: $any($event.target).value })">
          <option value="available_asc">Lowest available first</option><option value="available_desc">Highest available first</option><option value="sku">SKU</option><option value="updated">Recently updated</option>
        </select>
      </div>
      <div class="table-wrap flat" [class.busy]="list.loading()">
        <table class="table">
          <thead><tr><th>Product</th><th class="num">On hand</th><th class="num">Reserved</th><th class="num">Available</th><th class="num hide-mobile">Threshold</th><th>Status</th><th class="hide-mobile">Updated</th><th><span class="sr-only">Actions</span></th></tr></thead>
          <tbody>
            @for (i of list.items(); track i.id) {
              <tr>
                <td>
                  <div class="prod">
                    @if (i.product?.image; as img) { <img class="thumb" [src]="img" alt="" loading="lazy" /> }
                    <div><a class="fw-600 plain" [routerLink]="['/admin/inventory', i.id]">{{ i.product?.name }}</a><div class="text-xs text-muted"><span class="mono">{{ i.sku }}</span> · {{ i.product?.brand }}@if (i.location) { · {{ i.location }} }</div></div>
                  </div>
                </td>
                <td class="num">{{ i.quantity }}</td>
                <td class="num">{{ i.reserved }}</td>
                <td class="num fw-700" [class.text-danger]="i.available <= 0" [class.text-warning]="i.available > 0 && i.available <= i.low_stock_threshold">{{ i.available }}</td>
                <td class="num hide-mobile">{{ i.low_stock_threshold }}</td>
                <td><app-status-badge [status]="i.status" /></td>
                <td class="hide-mobile text-sm text-muted">{{ i.updated_at | timeAgo }}</td>
                <td class="act"><button type="button" class="btn btn-sm" (click)="openAdjust(i)"><app-icon name="plus" [size]="14" /> Adjust</button></td>
              </tr>
            } @empty {
              @if (!list.loading()) { <tr><td colspan="8"><app-empty-state icon="layers" title="Nothing here" message="No SKUs match this filter." /></td></tr> }
              @else { @for (k of [1, 2, 3, 4, 5]; track k) { <tr><td colspan="8"><div class="skeleton" style="height:18px"></div></td></tr> } }
            }
          </tbody>
        </table>
      </div>
      <div class="pg"><app-pagination [meta]="list.meta()" (pageChange)="list.goTo($event)" /></div>
    </section>

    <app-modal [open]="!!adjusting()" [title]="'Adjust stock — ' + (adjusting()?.sku ?? '')" size="md" (close)="adjusting.set(null)">
      @if (adjusting(); as a) {
        <p class="text-sm text-muted mt-0">{{ a.product?.name }} — on hand <strong>{{ a.quantity }}</strong>, reserved <strong>{{ a.reserved }}</strong>, available <strong>{{ a.available }}</strong>.</p>
        <form id="adj-form" [formGroup]="form" (ngSubmit)="saveAdjust()" novalidate>
          <div class="field">
            <label class="label" for="adj-type">Reason <span class="req">*</span></label>
            <select id="adj-type" class="select" formControlName="type">@for (t of types; track t.value) { <option [value]="t.value">{{ t.label }}</option> }</select>
          </div>
          <div class="form-grid cols-2">
            <div class="field">
              <label class="label" for="adj-qty">Quantity change <span class="req">*</span></label>
              <input id="adj-qty" class="input" type="number" formControlName="quantity" step="1" />
              @if (form.controls.quantity.invalid && form.controls.quantity.touched) { <span class="error-text">{{ form.controls.quantity.errors?.['server'] ?? 'Enter a non-zero whole number (negative to remove stock).' }}</span> }
              @else { <span class="hint">Use a negative number to remove stock. New on-hand: <strong>{{ a.quantity + (+form.controls.quantity.value || 0) }}</strong></span> }
            </div>
            <div class="field"><label class="label" for="adj-ref">Reference</label><input id="adj-ref" class="input" formControlName="reference" placeholder="PO-1042" /></div>
          </div>
          <div class="field"><label class="label" for="adj-note">Note</label><input id="adj-note" class="input" formControlName="note" /></div>
        </form>
      }
      <div modal-footer>
        <button type="button" class="btn btn-ghost" (click)="adjusting.set(null)">Cancel</button>
        <button type="submit" form="adj-form" class="btn btn-primary" [disabled]="saving()">@if (saving()) { <span class="spinner"></span> } Save adjustment</button>
      </div>
    </app-modal>
  `,
  styles: `
    .tabs { margin-bottom: 14px; }
    .tab-count { background: var(--line-2); border-radius: 999px; padding: 0 7px; font-size: 11px; margin-left: 4px; }
    .tab-count.warn { background: #fef3c7; color: #92400e; } .tab-count.bad { background: #fee2e2; color: #991b1b; }
    .toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 14px 16px; border-bottom: 1px solid var(--line); }
    .toolbar .select { width: auto; }
    .flat { border: 0; border-radius: 0; } .busy { opacity: .6; } .pg { padding: 0 16px; }
    .prod { display: flex; gap: 10px; align-items: center; }
    .plain { color: var(--ink); } .plain:hover { color: var(--brand); }
    .act { text-align: right; white-space: nowrap; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class InventoryPage {
  private readonly api = inject(AdminApiService);
  private readonly toast = inject(ToastService);
  protected readonly list = new ListState<InventoryRecord, { counts: { all: number; low_stock: number; out_of_stock: number } }>((q) => this.api.page('inventory', q));
  protected readonly counts = computed(() => this.list.meta()?.counts ?? null);
  protected readonly adjusting = signal<InventoryRecord | null>(null);
  protected readonly saving = signal(false);
  protected readonly types = ADJUST_TYPES;
  protected readonly form = inject(FormBuilder).nonNullable.group({
    type: ['stock_received', Validators.required],
    quantity: [0, [Validators.required, (c: { value: unknown }) => (Number(c.value) === 0 || !Number.isInteger(Number(c.value)) ? { nonzero: true } : null)]],
    reference: ['', Validators.maxLength(100)],
    note: ['', Validators.maxLength(255)],
  });

  constructor() {
    inject(SeoService).set({ title: 'Inventory' });
    const qp = inject(ActivatedRoute).snapshot.queryParamMap;
    this.list.query.set({ page: 1, per_page: 25, sort: 'available_asc', status: qp.get('status') });
    this.list.load();
  }

  protected openAdjust(i: InventoryRecord): void {
    this.form.reset({ type: 'stock_received', quantity: 0, reference: '', note: '' });
    this.adjusting.set(i);
  }

  protected saveAdjust(): void {
    const i = this.adjusting();
    this.form.markAllAsTouched();
    if (!i || this.form.invalid || this.saving()) return;
    const v = this.form.getRawValue();
    this.saving.set(true);
    this.api.post<InventoryRecord>(`inventory/${i.id}/adjust`, { ...v, quantity: Number(v.quantity), reference: v.reference || null, note: v.note || null }).subscribe({
      next: (res) => {
        this.saving.set(false);
        this.adjusting.set(null);
        this.toast.success(res.message || 'Stock adjusted');
        this.list.load();
      },
      error: (err) => {
        this.saving.set(false);
        applyServerErrors(this.form, err);
        this.toast.error(errorMessage(err));
      },
    });
  }
}
