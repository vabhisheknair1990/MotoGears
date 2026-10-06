import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { SeoService } from '../../../core/services/seo.service';
import { compactInr, formatInr, formatNumber } from '../../../core/utils/format';
import { errorMessage } from '../../../core/utils/http-errors';
import { AppDatePipe, InrPipe } from '../../../shared/pipes/inr.pipe';
import { IconComponent } from '../../../shared/components/icon.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge.component';
import { AdminApiService } from '../data/admin-api.service';
import { Dashboard } from '../data/admin.models';
import { AreaChartComponent, ChartPoint } from '../shared/area-chart.component';
import { BarItem, BarListComponent } from '../shared/bar-list.component';
import { PageHeaderComponent } from '../shared/page-header.component';
import { StatCardComponent } from '../shared/stat-card.component';

@Component({
  selector: 'adm-dashboard-page',
  imports: [RouterLink, AppDatePipe, InrPipe, IconComponent, StatusBadgeComponent, AreaChartComponent, BarListComponent, PageHeaderComponent, StatCardComponent],
  templateUrl: './dashboard.page.html',
  styleUrl: './dashboard.page.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DashboardPage {
  private readonly api = inject(AdminApiService);
  protected readonly data = signal<Dashboard | null>(null);
  protected readonly error = signal<string | null>(null);
  protected readonly range = signal<'30d' | '12m'>('30d');
  protected readonly metric = signal<'revenue' | 'orders'>('revenue');
  protected readonly inr = formatInr;
  protected readonly compact = compactInr;
  protected readonly num = (v: number) => formatNumber(v);

  protected readonly chart = computed<ChartPoint[]>(() => {
    const d = this.data();
    if (!d) return [];
    const rows = this.range() === '30d' ? d.sales_chart : d.monthly_chart;
    const m = this.metric();
    return rows.map((r) => ({ label: r.label, value: r[m], sub: m === 'revenue' ? `${r.orders} orders` : formatInr(r.revenue, true) }));
  });
  protected readonly chartTotal = computed(() => this.chart().reduce((s, p) => s + p.value, 0));

  protected readonly topCategories = computed<BarItem[]>(() => (this.data()?.top_categories ?? []).map((c) => ({ label: c.name, value: c.revenue, display: compactInr(c.revenue), sub: `${c.units} units` })));
  protected readonly topBrands = computed<BarItem[]>(() => (this.data()?.top_brands ?? []).map((c) => ({ label: c.name, value: c.revenue, display: compactInr(c.revenue), sub: `${c.units} units` })));
  protected readonly statusBars = computed<BarItem[]>(() => (this.data()?.orders.by_status ?? []).filter((s) => s.count > 0).map((s) => ({ label: s.label, value: s.count })));
  protected readonly paymentBars = computed<BarItem[]>(() => (this.data()?.payment_methods ?? []).map((p) => ({ label: p.label, value: p.revenue, display: compactInr(p.revenue), sub: `${p.orders} orders` })));

  constructor() {
    inject(SeoService).set({ title: 'Admin dashboard' });
    this.load();
  }

  protected load(): void {
    this.error.set(null);
    this.api.get<Dashboard>('dashboard').subscribe({
      next: (d) => this.data.set(d),
      error: (err) => this.error.set(errorMessage(err, 'Could not load the dashboard.')),
    });
  }
}
