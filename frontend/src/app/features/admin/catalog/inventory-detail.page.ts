import { ChangeDetectionStrategy, Component, effect, inject, input, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { applyServerErrors, errorMessage } from '../../../core/utils/http-errors';
import { AppDatePipe } from '../../../shared/pipes/inr.pipe';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { PaginationComponent } from '../../../shared/components/pagination.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge.component';
import { AdminApiService } from '../data/admin-api.service';
import { InventoryDetail } from '../data/admin.models';
import { PageHeaderComponent } from '../shared/page-header.component';
import { StatCardComponent } from '../shared/stat-card.component';
import { ADJUST_TYPES } from './inventory.page';

@Component({
  selector: 'adm-inventory-detail-page',
  imports: [ReactiveFormsModule, RouterLink, AppDatePipe, EmptyStateComponent, IconComponent, PaginationComponent, StatusBadgeComponent, PageHeaderComponent, StatCardComponent],
  template: `
    @if (missing()) {
      <div class="card"><app-empty-state icon="layers" title="Inventory record not found"><a routerLink="/admin/inventory" class="btn btn-primary">Back to inventory</a></app-empty-state></div>
    } @else if (d(); as d) {
      @let i = d.inventory;
      <adm-page-header [title]="i.product?.name ?? i.sku" [subtitle]="'SKU ' + i.sku + ' · ' + (i.product?.brand ?? '')" back="/admin/inventory" backLabel="Inventory">
        @if (i.product) { <a class="btn" [routerLink]="['/admin/products', i.product.id, 'edit']"><app-icon name="edit" [size]="16" /> Edit product</a> }
      </adm-page-header>
      <div class="kpis">
        <adm-stat label="On hand" [value]="i.quantity" icon="layers" />
        <adm-stat label="Reserved" [value]="i.reserved" icon="clock" hint="In unpaid / unshipped orders" />
        <adm-stat label="Available to sell" [value]="i.available" icon="check-circle" [hint]="'Low-stock alert at ' + i.low_stock_threshold" />
        <div class="card st"><span class="text-muted text-sm">Status</span><app-status-badge [status]="i.status" /></div>
      </div>
      <div class="layout">
        <section class="card">
          <div class="card-head"><h2>Movement history</h2><span class="text-muted text-sm">{{ d.history_meta.total }} entries</span></div>
          <div class="table-wrap flat">
            <table class="table">
              <thead><tr><th>When</th><th>Type</th><th class="num">Change</th><th class="num">On hand after</th><th class="hide-mobile">Reference</th><th class="hide-mobile">By</th></tr></thead>
              <tbody>
                @for (t of d.history; track t.id) {
                  <tr>
                    <td class="text-sm">{{ t.created_at | appDate: true }}</td>
                    <td>{{ t.type_label }}@if (t.note) { <div class="text-xs text-muted">{{ t.note }}</div> }</td>
                    <td class="num fw-700" [class.text-success]="t.quantity > 0" [class.text-danger]="t.quantity < 0">{{ t.quantity > 0 ? '+' : '' }}{{ t.quantity }}</td>
                    <td class="num">{{ t.quantity_after }}</td>
                    <td class="hide-mobile mono text-sm">{{ t.reference ?? '—' }}</td>
                    <td class="hide-mobile text-sm">{{ t.user ?? 'System' }}</td>
                  </tr>
                } @empty { <tr><td colspan="6" class="text-muted">No movements yet.</td></tr> }
              </tbody>
            </table>
          </div>
          <div class="pg"><app-pagination [meta]="{ current_page: d.history_meta.current_page, last_page: d.history_meta.last_page, per_page: d.history_meta.per_page, total: d.history_meta.total, from: null, to: null }" [showSummary]="false" (pageChange)="load($event)" /></div>
        </section>
        <aside class="side">
          <section class="card">
            <div class="card-head"><h2>Adjust stock</h2></div>
            <form class="card-body" [formGroup]="adjust" (ngSubmit)="saveAdjust()" novalidate>
              <div class="field"><label class="label" for="t">Reason</label><select id="t" class="select" formControlName="type">@for (t of types; track t.value) { <option [value]="t.value">{{ t.label }}</option> }</select></div>
              <div class="field">
                <label class="label" for="q">Quantity change</label>
                <input id="q" class="input" type="number" step="1" formControlName="quantity" />
                @if (adjust.controls.quantity.invalid && adjust.controls.quantity.touched) { <span class="error-text">{{ adjust.controls.quantity.errors?.['server'] ?? 'Enter a non-zero whole number.' }}</span> }
                @else { <span class="hint">Negative removes stock. New on-hand: {{ i.quantity + (+adjust.controls.quantity.value || 0) }}</span> }
              </div>
              <div class="field"><label class="label" for="r">Reference</label><input id="r" class="input" formControlName="reference" placeholder="PO / GRN number" /></div>
              <div class="field"><label class="label" for="n">Note</label><input id="n" class="input" formControlName="note" /></div>
              <button class="btn btn-primary btn-block" [disabled]="busy()">@if (busy()) { <span class="spinner"></span> } Apply adjustment</button>
            </form>
          </section>
          <section class="card">
            <div class="card-head"><h2>Settings</h2></div>
            <form class="card-body" [formGroup]="settings" (ngSubmit)="saveSettings()" novalidate>
              <div class="field"><label class="label" for="th">Low-stock threshold</label><input id="th" class="input" type="number" min="0" formControlName="low_stock_threshold" /></div>
              <div class="field"><label class="label" for="loc">Bin / location</label><input id="loc" class="input" formControlName="location" placeholder="WH-A / R3 / S2" /></div>
              <label class="check"><input type="checkbox" formControlName="allow_backorder" /> Allow backorders when out of stock</label>
              <button class="btn btn-block" [disabled]="busy() || settings.pristine">Save settings</button>
            </form>
          </section>
        </aside>
      </div>
    } @else {
      <div class="card"><div class="skeleton" style="height:300px;margin:16px"></div></div>
    }
  `,
  styles: `
    .kpis { display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); margin-bottom: 18px; }
    .st { padding: 16px 18px; display: flex; flex-direction: column; gap: 10px; align-items: flex-start; }
    .layout { display: grid; gap: 18px; }
    @media (min-width: 1100px) { .layout { grid-template-columns: minmax(0, 1fr) 340px; align-items: start; } }
    .side { display: flex; flex-direction: column; gap: 18px; }
    .card-head { display: flex; justify-content: space-between; align-items: center; }
    .card-head h2 { font-size: 16px; margin: 0; }
    .flat { border: 0; border-radius: 0; } .pg { padding: 0 16px; }
    form .btn-block { margin-top: 8px; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class InventoryDetailPage {
  readonly id = input.required<string>();
  private readonly api = inject(AdminApiService);
  private readonly toast = inject(ToastService);
  private readonly seo = inject(SeoService);
  private readonly fb = inject(FormBuilder);
  protected readonly d = signal<InventoryDetail | null>(null);
  protected readonly missing = signal(false);
  protected readonly busy = signal(false);
  protected readonly types = ADJUST_TYPES;
  protected readonly adjust = this.fb.nonNullable.group({
    type: ['stock_received', Validators.required],
    quantity: [0, [Validators.required, (c: { value: unknown }) => (Number(c.value) === 0 || !Number.isInteger(Number(c.value)) ? { nonzero: true } : null)]],
    reference: ['', Validators.maxLength(100)],
    note: ['', Validators.maxLength(255)],
  });
  protected readonly settings = this.fb.nonNullable.group({
    low_stock_threshold: [0, [Validators.required, Validators.min(0), Validators.max(100000)]],
    location: ['', Validators.maxLength(60)],
    allow_backorder: [false],
  });

  constructor() {
    effect(() => {
      this.id();
      this.load(1);
    });
  }

  protected load(page: number): void {
    this.api.get<InventoryDetail>(`inventory/${this.id()}`, { page }).subscribe({
      next: (d) => {
        this.d.set(d);
        this.seo.set({ title: `Stock · ${d.inventory.sku}` });
        this.settings.reset({ low_stock_threshold: d.inventory.low_stock_threshold, location: d.inventory.location ?? '', allow_backorder: d.inventory.allow_backorder });
      },
      error: () => this.missing.set(true),
    });
  }

  protected saveAdjust(): void {
    this.adjust.markAllAsTouched();
    if (this.adjust.invalid || this.busy()) return;
    const v = this.adjust.getRawValue();
    this.busy.set(true);
    this.api.post(`inventory/${this.id()}/adjust`, { ...v, quantity: Number(v.quantity), reference: v.reference || null, note: v.note || null }).subscribe({
      next: (res) => {
        this.busy.set(false);
        this.toast.success(res.message || 'Stock updated');
        this.adjust.reset({ type: v.type, quantity: 0, reference: '', note: '' });
        this.load(1);
      },
      error: (err) => {
        this.busy.set(false);
        applyServerErrors(this.adjust, err);
        this.toast.error(errorMessage(err));
      },
    });
  }

  protected saveSettings(): void {
    if (this.settings.invalid || this.busy()) return;
    const v = this.settings.getRawValue();
    this.busy.set(true);
    this.api.patch(`inventory/${this.id()}`, { ...v, location: v.location || null }).subscribe({
      next: () => {
        this.busy.set(false);
        this.toast.success('Inventory settings saved');
        this.load(1);
      },
      error: (err) => {
        this.busy.set(false);
        applyServerErrors(this.settings, err);
      },
    });
  }
}
