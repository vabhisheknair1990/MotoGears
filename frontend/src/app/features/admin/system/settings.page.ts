import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, ValidatorFn, Validators } from '@angular/forms';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { applyServerErrors, errorMessage } from '../../../core/utils/http-errors';
import { IconComponent } from '../../../shared/components/icon.component';
import { AdminApiService } from '../data/admin-api.service';
import { SettingEntry } from '../data/admin.models';
import { PageHeaderComponent } from '../shared/page-header.component';

const META: Record<string, { label: string; hint?: string; prefix?: string; suffix?: string; readonly?: boolean; email?: boolean; min?: number; max?: number }> = {
  store_name: { label: 'Store name' },
  store_tagline: { label: 'Tagline' },
  support_phone: { label: 'Support phone' },
  support_email: { label: 'Support email', email: true },
  store_address: { label: 'Registered address', hint: 'Printed on invoices.' },
  gstin: { label: 'GSTIN', hint: 'Printed on invoices.' },
  currency: { label: 'Currency', readonly: true, hint: 'The store trades in Indian rupees only.' },
  free_shipping_threshold: { label: 'Free standard shipping from', prefix: '₹', min: 0 },
  standard_shipping_cost: { label: 'Standard shipping cost', prefix: '₹', min: 0 },
  express_shipping_cost: { label: 'Express shipping cost', prefix: '₹', min: 0 },
  shipping_tax_rate: { label: 'GST on shipping', suffix: '%', min: 0, max: 28 },
  cod_enabled: { label: 'Offer Cash on Delivery' },
  cod_max_order_value: { label: 'COD limit (max order value)', prefix: '₹', min: 0 },
  unpaid_order_timeout_minutes: { label: 'Cancel unpaid online orders after', suffix: 'min', min: 5, max: 10080, hint: 'Reserved stock is released when an unpaid order is auto-cancelled.' },
  max_quantity_per_item: { label: 'Max quantity per cart line', min: 1, max: 100 },
};

const GROUP_TITLES: Record<string, { title: string; icon: string }> = {
  store: { title: 'Store details', icon: 'store' },
  shipping: { title: 'Shipping', icon: 'truck' },
  payments: { title: 'Payments', icon: 'card' },
  checkout: { title: 'Checkout', icon: 'cart' },
  general: { title: 'General', icon: 'settings' },
};

@Component({
  selector: 'adm-settings-page',
  imports: [ReactiveFormsModule, IconComponent, PageHeaderComponent],
  template: `
    <adm-page-header title="Settings" subtitle="Store-wide configuration. Changes apply immediately and are recorded in the audit log." />
    @if (error()) { <div class="alert alert-danger"><app-icon name="alert" [size]="18" /> {{ error() }}</div> }
    @if (groups().length) {
      <form [formGroup]="form" (ngSubmit)="save()" novalidate>
        @for (g of groups(); track g.key) {
          <section class="card">
            <div class="card-head"><h2><app-icon [name]="g.icon" [size]="18" /> {{ g.title }}</h2></div>
            <div class="card-body form-grid cols-2">
              @for (s of g.items; track s.key) {
                @let m = meta(s.key);
                @if (s.type === 'boolean') {
                  <div class="field full"><label class="check"><input type="checkbox" [formControlName]="s.key" /> {{ m.label }}</label>@if (m.hint) { <span class="hint">{{ m.hint }}</span> }</div>
                } @else {
                  <div class="field">
                    <label class="label" [attr.for]="'s-' + s.key">{{ m.label }}</label>
                    <div class="affix" [class.has-prefix]="m.prefix" [class.has-suffix]="m.suffix">
                      @if (m.prefix) { <span class="pre">{{ m.prefix }}</span> }
                      <input class="input" [id]="'s-' + s.key" [type]="s.type === 'number' ? 'number' : m.email ? 'email' : 'text'" [formControlName]="s.key" [attr.min]="m.min ?? null" [attr.max]="m.max ?? null" [readonly]="m.readonly" />
                      @if (m.suffix) { <span class="suf">{{ m.suffix }}</span> }
                    </div>
                    @if (invalid(s.key)) { <span class="error-text">{{ form.get(s.key)?.errors?.['server'] ?? 'Please enter a valid value.' }}</span> } @else if (m.hint) { <span class="hint">{{ m.hint }}</span> }
                  </div>
                }
              }
            </div>
          </section>
        }
        <div class="actions">
          <button type="button" class="btn btn-ghost" (click)="reset()" [disabled]="form.pristine || saving()">Discard changes</button>
          <button type="submit" class="btn btn-primary" [disabled]="form.pristine || saving()">@if (saving()) { <span class="spinner"></span> } Save settings</button>
        </div>
      </form>
    } @else if (!error()) {
      <div class="card"><div class="skeleton" style="height:300px;margin:16px"></div></div>
    }
  `,
  styles: `
    section { margin-bottom: 18px; }
    .card-head h2 { font-size: 16px; margin: 0; display: flex; gap: 8px; align-items: center; }
    .field.full { grid-column: 1 / -1; }
    .affix { position: relative; display: flex; }
    .affix .input { flex: 1; }
    .pre, .suf { position: absolute; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 14px; pointer-events: none; }
    .pre { left: 12px; } .suf { right: 12px; }
    .has-prefix .input { padding-left: 28px; } .has-suffix .input { padding-right: 44px; }
    .actions { display: flex; justify-content: flex-end; gap: 10px; position: sticky; bottom: 0; padding: 12px 0; background: linear-gradient(transparent, #f4f6f9 30%); }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SettingsPage {
  private readonly api = inject(AdminApiService);
  private readonly toast = inject(ToastService);
  protected readonly data = signal<Record<string, SettingEntry[]> | null>(null);
  protected readonly error = signal<string | null>(null);
  protected readonly saving = signal(false);
  protected form = new FormGroup<Record<string, FormControl>>({});

  protected readonly groups = computed(() => {
    const d = this.data();
    if (!d) return [];
    return Object.entries(d).map(([key, items]) => ({ key, items, ...(GROUP_TITLES[key] ?? { title: key, icon: 'settings' }) }));
  });

  constructor() {
    inject(SeoService).set({ title: 'Settings' });
    this.load();
  }

  protected meta(key: string) {
    return META[key] ?? { label: key.replace(/_/g, ' ') };
  }

  private load(): void {
    this.api.get<Record<string, SettingEntry[]>>('settings').subscribe({
      next: (d) => {
        const controls: Record<string, FormControl> = {};
        for (const items of Object.values(d)) {
          for (const s of items) {
            const m = this.meta(s.key);
            const v: ValidatorFn[] = [];
            if (s.type !== 'boolean' && s.key !== 'store_tagline') v.push(Validators.required);
            if (m.email) v.push(Validators.email);
            if (m.min !== undefined) v.push(Validators.min(m.min));
            if (m.max !== undefined) v.push(Validators.max(m.max));
            controls[s.key] = new FormControl(s.value, v);
          }
        }
        this.form = new FormGroup(controls);
        this.data.set(d);
      },
      error: (err) => this.error.set(errorMessage(err, 'Could not load settings.')),
    });
  }

  protected invalid(key: string): boolean {
    const c = this.form.get(key);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  protected reset(): void {
    this.load();
  }

  protected save(): void {
    this.form.markAllAsTouched();
    if (this.form.invalid || this.saving()) return;
    const settings: Record<string, unknown> = {};
    for (const [key, c] of Object.entries(this.form.controls)) {
      if (c.dirty && key !== 'currency') settings[key] = c.value;
    }
    this.saving.set(true);
    this.api.put('settings', { settings }).subscribe({
      next: (res) => {
        this.saving.set(false);
        this.form.markAsPristine();
        this.toast.success(res.message || 'Settings saved');
      },
      error: (err) => {
        this.saving.set(false);
        // Errors come back as "settings.key"; map them onto the matching control.
        applyServerErrors(this.form, err);
        this.toast.error(errorMessage(err));
      },
    });
  }
}
