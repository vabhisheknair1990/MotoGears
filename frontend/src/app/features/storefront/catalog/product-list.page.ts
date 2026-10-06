import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed, toObservable } from '@angular/core/rxjs-interop';
import { ActivatedRoute, ParamMap, Router, RouterLink } from '@angular/router';
import { catchError, combineLatest, map, of, switchMap, tap } from 'rxjs';
import { Brand, Category, Facets, PageMeta, ProductCard } from '../../../core/models/api.models';
import { BrandService } from '../../../core/services/brand.service';
import { CategoryService } from '../../../core/services/category.service';
import { ProductFilters, ProductService } from '../../../core/services/product.service';
import { SearchService } from '../../../core/services/search.service';
import { SeoService } from '../../../core/services/seo.service';
import { UiStore } from '../../../core/state/ui.store';
import { VehicleStore } from '../../../core/state/vehicle.store';
import { BreadcrumbComponent, Crumb } from '../../../shared/components/breadcrumb.component';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { ActiveFilters, FilterPanelComponent } from '../../../shared/components/filter-panel.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { PaginationComponent } from '../../../shared/components/pagination.component';
import { ProductGridComponent } from '../../../shared/components/product-grid.component';

type Mode = 'shop' | 'category' | 'brand' | 'search';

/** One listing page for /shop, /category/:slug, /brand/:slug and /search. All state lives in the URL. */
@Component({
  selector: 'app-product-list-page',
  imports: [RouterLink, IconComponent, ProductGridComponent, FilterPanelComponent, PaginationComponent, BreadcrumbComponent, EmptyStateComponent],
  templateUrl: './product-list.page.html',
  styleUrl: './product-list.page.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductListPage {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly products = inject(ProductService);
  private readonly categories = inject(CategoryService);
  private readonly brands = inject(BrandService);
  private readonly search = inject(SearchService);
  private readonly seo = inject(SeoService);
  protected readonly vehicle = inject(VehicleStore);
  protected readonly ui = inject(UiStore);

  protected readonly mode = signal<Mode>('shop');
  protected readonly slug = signal<string | null>(null);
  protected readonly items = signal<ProductCard[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);
  protected readonly facets = signal<Facets | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);
  protected readonly category = signal<Category | null>(null);
  protected readonly brand = signal<Brand | null>(null);
  protected readonly query = signal('');
  protected readonly sort = signal('popular');
  protected readonly view = signal<'grid' | 'list'>('grid');
  protected readonly filtersOpen = signal(false);
  protected readonly vehicleFilterOn = signal(true);
  protected readonly active = signal<ActiveFilters>(emptyFilters());

  protected readonly title = computed(() => {
    switch (this.mode()) {
      case 'category': return this.category()?.name ?? '';
      case 'brand': return this.brand()?.name ?? '';
      case 'search': return this.query() ? `Results for “${this.query()}”` : 'Search';
      default: return this.route.snapshot.queryParamMap.get('sort') === 'newest' ? 'New arrivals' : this.active().discount ? 'Offers & deals' : 'All products';
    }
  });
  protected readonly crumbs = computed<Crumb[]>(() => {
    const out: Crumb[] = [{ label: 'Home', link: '/' }, { label: 'Shop', link: '/shop' }];
    if (this.mode() === 'category') {
      (this.category()?.breadcrumbs ?? []).forEach((b) => out.push({ label: b.name, link: ['/category', b.slug] }));
    } else if (this.mode() === 'brand') {
      out.push({ label: 'Brands', link: '/brands' }, { label: this.brand()?.name ?? '' });
    } else if (this.mode() === 'search') {
      out.push({ label: 'Search' });
    }
    return out;
  });
  protected readonly filteringByVehicle = computed(() => !!this.vehicle.selected() && this.vehicleFilterOn());
  protected readonly chips = computed(() => {
    const a = this.active();
    const f = this.facets();
    const chips: { label: string; patch: Partial<ActiveFilters> }[] = [];
    a.brand.forEach((b) => chips.push({ label: f?.brands.find((x) => x.slug === b)?.name ?? b, patch: { brand: a.brand.filter((x) => x !== b) } }));
    if (a.category && this.mode() !== 'category') chips.push({ label: f?.categories.find((c) => c.slug === a.category)?.name ?? a.category, patch: { category: null } });
    if (a.min_price !== null || a.max_price !== null) chips.push({ label: `₹${a.min_price ?? 0} – ${a.max_price !== null ? '₹' + a.max_price : 'max'}`, patch: { min_price: null, max_price: null } });
    if (a.rating) chips.push({ label: `${a.rating}★ & up`, patch: { rating: null } });
    if (a.discount) chips.push({ label: `${a.discount}%+ off`, patch: { discount: null } });
    if (a.in_stock) chips.push({ label: 'In stock', patch: { in_stock: false } });
    Object.entries(a.attributes).forEach(([k, vals]) => vals.forEach((v) => {
      const next = { ...a.attributes, [k]: vals.filter((x) => x !== v) };
      chips.push({ label: v.replace(/-/g, ' '), patch: { attributes: next } });
    }));
    return chips;
  });

  constructor() {
    combineLatest([this.route.paramMap, this.route.queryParamMap, this.route.data, toObservable(this.vehicle.variantId)]).pipe(
      map(([params, query, data]) => this.readState(params, query, (data['mode'] as Mode) ?? 'shop')),
      tap(() => { this.loading.set(true); this.error.set(false); }),
      switchMap((filters) => {
        const header$ = this.mode() === 'category' && this.slug()
          ? this.categories.getCategory(this.slug()!).pipe(tap((c) => this.category.set(c)), catchError(() => { this.category.set(null); return of(null); }))
          : this.mode() === 'brand' && this.slug()
            ? this.brands.getBrand(this.slug()!).pipe(tap((b) => this.brand.set(b)), catchError(() => { this.brand.set(null); return of(null); }))
            : of(null);
        const list$ = this.mode() === 'search'
          ? this.products.search({ ...filters, q: this.query() })
          : this.products.getProducts(filters);
        return combineLatest([header$, list$.pipe(catchError(() => { this.error.set(true); return of(null); }))]);
      }),
      takeUntilDestroyed(),
    ).subscribe(([, page]) => {
      this.loading.set(false);
      if (!page) return;
      this.items.set(page.items);
      this.meta.set(page.meta);
      if (page.meta.facets) this.facets.set(page.meta.facets);
      this.updateSeo();
      if (this.mode() === 'search' && this.query()) this.search.remember(this.query());
    });
  }

  private readState(params: ParamMap, q: ParamMap, mode: Mode): ProductFilters {
    this.mode.set(mode);
    this.slug.set(params.get('slug'));
    this.query.set(q.get('q') ?? '');
    this.sort.set(q.get('sort') ?? (mode === 'search' ? 'relevance' : 'popular'));
    this.view.set(q.get('view') === 'list' ? 'list' : 'grid');
    this.vehicleFilterOn.set(q.get('fit') !== '0');
    const attributes: Record<string, string[]> = {};
    q.keys.filter((k) => k.startsWith('attr_')).forEach((k) => (attributes[k.slice(5)] = (q.get(k) ?? '').split(',').filter(Boolean)));
    const num = (k: string) => (q.get(k) !== null && q.get(k) !== '' ? Number(q.get(k)) : null);
    const active: ActiveFilters = {
      brand: (q.get('brand') ?? '').split(',').filter(Boolean),
      category: q.get('category'),
      min_price: num('min_price'),
      max_price: num('max_price'),
      rating: num('rating'),
      discount: num('discount'),
      in_stock: q.get('in_stock') === '1',
      attributes,
    };
    this.active.set(active);
    if (mode === 'category') this.brand.set(null);
    if (mode === 'brand') this.category.set(null);

    return {
      page: num('page') ?? 1,
      per_page: 24,
      sort: this.sort(),
      category: mode === 'category' ? params.get('slug') : active.category,
      brand: mode === 'brand' ? params.get('slug') : active.brand,
      min_price: active.min_price,
      max_price: active.max_price,
      rating: active.rating,
      discount: active.discount,
      in_stock: active.in_stock || null,
      featured: q.get('featured') === '1' || null,
      attributes: active.attributes,
      vehicle_variant: this.filteringByVehicle() ? this.vehicle.variantId() : null,
      vehicle_year: this.filteringByVehicle() ? this.vehicle.selected()?.year ?? null : null,
      with_facets: true,
    };
  }

  applyFilters(patch: Partial<ActiveFilters>): void {
    const next = { ...this.active(), ...patch };
    const qp: Record<string, string | null> = {
      brand: next.brand.length ? next.brand.join(',') : null,
      category: next.category,
      min_price: next.min_price !== null ? String(next.min_price) : null,
      max_price: next.max_price !== null ? String(next.max_price) : null,
      rating: next.rating ? String(next.rating) : null,
      discount: next.discount ? String(next.discount) : null,
      in_stock: next.in_stock ? '1' : null,
      page: null,
    };
    Object.keys(this.route.snapshot.queryParams).filter((k) => k.startsWith('attr_')).forEach((k) => (qp[k] = null));
    Object.entries(next.attributes).forEach(([k, v]) => (qp['attr_' + k] = v.length ? v.join(',') : null));
    this.navigate(qp);
  }

  clearFilters(): void {
    const keep = ['q', 'sort', 'view', 'fit'];
    const qp: Record<string, string | null> = {};
    Object.keys(this.route.snapshot.queryParams).filter((k) => !keep.includes(k)).forEach((k) => (qp[k] = null));
    this.navigate(qp);
  }

  setSort(sort: string): void {
    this.navigate({ sort, page: null });
  }

  setView(view: 'grid' | 'list'): void {
    this.navigate({ view: view === 'list' ? 'list' : null });
  }

  setPage(page: number): void {
    this.navigate({ page: String(page) });
    globalThis.scrollTo?.({ top: 0, behavior: 'smooth' });
  }

  toggleVehicleFilter(): void {
    this.navigate({ fit: this.vehicleFilterOn() ? '0' : null, page: null });
  }

  private navigate(queryParams: Record<string, string | null>): void {
    void this.router.navigate([], { relativeTo: this.route, queryParams, queryParamsHandling: 'merge' });
  }

  private updateSeo(): void {
    const c = this.category();
    const b = this.brand();
    if (this.mode() === 'category' && c) this.seo.set({ title: c.seo_title, description: c.seo_description, image: c.image });
    else if (this.mode() === 'brand' && b) this.seo.set({ title: b.seo_title, description: b.seo_description, image: b.logo });
    else this.seo.set({ title: this.title() });
  }
}

function emptyFilters(): ActiveFilters {
  return { brand: [], category: null, min_price: null, max_price: null, rating: null, discount: null, in_stock: false, attributes: {} };
}
