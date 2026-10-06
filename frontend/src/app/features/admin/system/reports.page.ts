import { ChangeDetectionStrategy, Component, computed, effect, inject, signal, untracked } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Observable } from 'rxjs';
import { SeoService } from '../../../core/services/seo.service';
import { compactInr, formatInr, formatNumber } from '../../../core/utils/format';
import { errorMessage } from '../../../core/utils/http-errors';
import { InrPipe } from '../../../shared/pipes/inr.pipe';
import { IconComponent } from '../../../shared/components/icon.component';
import { AdminApiService, downloadCsv } from '../data/admin-api.service';
import { CustomersReport, InventoryReport, ProductsReport, ReportMeta, ReportPeriod, SalesReport } from '../data/admin.models';
import { AreaChartComponent, ChartPoint } from '../shared/area-chart.component';
import { BarItem, BarListComponent } from '../shared/bar-list.component';
import { PageHeaderComponent } from '../shared/page-header.component';
import { StatCardComponent } from '../shared/stat-card.component';

type Tab = 'sales' | 'products' | 'customers' | 'inventory';

const PERIODS: { value: ReportPeriod; label: string }[] = [
  { value: 'today', label: 'Today' },
  { value: 'yesterday', label: 'Yesterday' },
  { value: 'last_7_days', label: 'Last 7 days' },
  { value: 'last_30_days', label: 'Last 30 days' },
  { value: 'this_month', label: 'This month' },
  { value: 'last_month', label: 'Last month' },
  { value: 'this_year', label: 'This year' },
  { value: 'custom', label: 'Custom range' },
];

@Component({
  selector: 'adm-reports-page',
  imports: [RouterLink, InrPipe, IconComponent, AreaChartComponent, BarListComponent, PageHeaderComponent, StatCardComponent],
  templateUrl: './reports.page.html',
  styles: `
    .bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; margin-bottom: 16px; }
    .ctl { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .ctl .select, .ctl .input { width: auto; }
    .kpis { display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); margin-bottom: 18px; }
    .grid-2 { display: grid; gap: 18px; margin-bottom: 18px; }
    @media (min-width: 1000px) { .grid-2 { grid-template-columns: 1fr 1fr; } }
    .card { margin-bottom: 18px; min-width: 0; }
    .grid-2 .card { margin-bottom: 0; }
    .card-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
    .card-head h2 { font-size: 16px; margin: 0; }
    .flat { border: 0; border-radius: 0; }
    .range { color: var(--muted); font-size: 13px; }
    .loading { opacity: .6; pointer-events: none; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ReportsPage {
  private readonly api = inject(AdminApiService);
  protected readonly periods = PERIODS;
  protected readonly tab = signal<Tab>('sales');
  protected readonly period = signal<ReportPeriod>('last_30_days');
  protected readonly from = signal('');
  protected readonly to = signal('');
  protected readonly loading = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly meta = signal<ReportMeta | null>(null);
  protected readonly sales = signal<SalesReport | null>(null);
  protected readonly products = signal<ProductsReport | null>(null);
  protected readonly customers = signal<CustomersReport | null>(null);
  protected readonly inventory = signal<InventoryReport | null>(null);
  protected readonly inr = formatInr;
  protected readonly compact = compactInr;
  protected readonly num = (v: number) => formatNumber(v);

  protected readonly salesSeries = computed<ChartPoint[]>(() => (this.sales()?.series ?? []).map((s) => ({ label: s.label, value: s.revenue, sub: `${s.orders} orders` })));
  protected readonly signupSeries = computed<ChartPoint[]>(() => (this.customers()?.signups ?? []).map((s) => ({ label: s.label, value: s.count })));
  protected readonly paymentBars = computed<BarItem[]>(() => (this.sales()?.by_payment_method ?? []).map((p) => ({ label: p.label, value: p.revenue, display: compactInr(p.revenue), sub: `${p.orders} orders` })));
  protected readonly statusBars = computed<BarItem[]>(() => (this.sales()?.by_status ?? []).map((s) => ({ label: s.label, value: s.count, display: `${s.count} · ${compactInr(s.value)}` })));
  protected readonly categoryBars = computed<BarItem[]>(() => (this.products()?.by_category ?? []).map((c) => ({ label: c.name, value: c.revenue, display: compactInr(c.revenue), sub: `${c.units} units` })));
  protected readonly brandBars = computed<BarItem[]>(() => (this.products()?.by_brand ?? []).map((c) => ({ label: c.name, value: c.revenue, display: compactInr(c.revenue), sub: `${c.units} units` })));
  protected readonly cityBars = computed<BarItem[]>(() => (this.customers()?.top_cities ?? []).map((c) => ({ label: c.city, value: c.orders, display: `${c.orders} orders` })));
  protected readonly movementBars = computed<BarItem[]>(() => (this.inventory()?.movements ?? []).map((m) => ({ label: m.label, value: Math.abs(m.units), display: `${m.units > 0 ? '+' : ''}${m.units} units`, sub: `${m.entries} entries` })));

  constructor() {
    inject(SeoService).set({ title: 'Reports' });
    effect(() => {
      const tab = this.tab();
      const period = this.period();
      untracked(() => {
        if (period === 'custom' && (!this.from() || !this.to())) return;
        this.load(tab, period);
      });
    });
  }

  protected applyCustom(): void {
    if (this.from() && this.to()) this.load(this.tab(), 'custom');
  }

  private load(tab: Tab, period: ReportPeriod): void {
    const q = { period, from: period === 'custom' ? this.from() : null, to: period === 'custom' ? this.to() : null };
    this.loading.set(true);
    this.error.set(null);
    const done = <T>(obs: Observable<{ data: T; meta?: unknown }>, set: (v: T) => void) =>
      obs.subscribe({
        next: (r) => {
          set(r.data);
          this.meta.set((r.meta as ReportMeta) ?? null);
          this.loading.set(false);
        },
        error: (err) => {
          this.error.set(errorMessage(err, 'Could not load the report.'));
          this.loading.set(false);
        },
      });
    switch (tab) {
      case 'sales': done(this.api.envelope<SalesReport>('reports/sales', q), (v) => this.sales.set(v)); break;
      case 'products': done(this.api.envelope<ProductsReport>('reports/products', q), (v) => this.products.set(v)); break;
      case 'customers': done(this.api.envelope<CustomersReport>('reports/customers', q), (v) => this.customers.set(v)); break;
      case 'inventory': done(this.api.envelope<InventoryReport>('reports/inventory', q), (v) => this.inventory.set(v)); break;
    }
  }

  protected exportCsv(): void {
    const m = this.meta();
    const suffix = m ? `${m.from}_${m.to}` : 'report';
    switch (this.tab()) {
      case 'sales': {
        const s = this.sales();
        if (s) downloadCsv(`sales_${suffix}.csv`, ['Date', 'Orders', 'Revenue'], s.series.map((r) => [r.date, r.orders, r.revenue]));
        break;
      }
      case 'products': {
        const p = this.products();
        if (p) downloadCsv(`top-products_${suffix}.csv`, ['Product', 'SKU', 'Units', 'Orders', 'Revenue'], p.top_products.map((r) => [r.name, r.sku, r.units, r.orders, r.revenue]));
        break;
      }
      case 'customers': {
        const c = this.customers();
        if (c) downloadCsv(`top-customers_${suffix}.csv`, ['Customer', 'Email', 'Orders', 'Spent'], c.top_customers.map((r) => [r.name, r.email, r.orders, r.spent]));
        break;
      }
      case 'inventory': {
        const i = this.inventory();
        if (i) downloadCsv(`stock-alerts_${suffix}.csv`, ['SKU', 'Product', 'On hand', 'Reserved', 'Available', 'Threshold'], [...i.out_of_stock, ...i.low_stock].map((r) => [r.sku, r.product, r.quantity, r.reserved, r.available, r.threshold]));
        break;
      }
    }
  }
}
