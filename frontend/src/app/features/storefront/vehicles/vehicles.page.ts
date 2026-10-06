import { ChangeDetectionStrategy, Component, computed, inject, input, signal } from '@angular/core';
import { takeUntilDestroyed, toObservable } from '@angular/core/rxjs-interop';
import { Router } from '@angular/router';
import { catchError, combineLatest, of, switchMap, tap } from 'rxjs';
import { CustomerVehicle, PageMeta, ProductCard, SelectedVehicle, VehicleVariant } from '../../../core/models/api.models';
import { CustomerService } from '../../../core/services/customer.service';
import { ProductService } from '../../../core/services/product.service';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { VehicleService } from '../../../core/services/vehicle.service';
import { AuthStore } from '../../../core/state/auth.store';
import { VehicleStore } from '../../../core/state/vehicle.store';
import { BreadcrumbComponent } from '../../../shared/components/breadcrumb.component';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { PaginationComponent } from '../../../shared/components/pagination.component';
import { ProductGridComponent } from '../../../shared/components/product-grid.component';
import { VehicleSelectorComponent } from '../../../shared/components/vehicle-selector.component';
import { errorMessage } from '../../../core/utils/http-errors';

/** Shop-by-vehicle landing: select a vehicle, see its specs and every compatible part by category. */
@Component({
  selector: 'app-vehicles-page',
  imports: [BreadcrumbComponent, VehicleSelectorComponent, ProductGridComponent, PaginationComponent, EmptyStateComponent, IconComponent],
  template: `
    <section class="vhero">
      <div class="container">
        <app-breadcrumb [items]="[{ label: 'Home', link: '/' }, { label: 'Shop by vehicle' }]" />
        <h1>Shop by vehicle</h1>
        <p class="lead">Choose your make, model, year and variant — we'll show only the parts that fit.</p>
        <app-vehicle-selector layout="inline" buttonLabel="SHOW PARTS" (selected)="onSelected($event)" />
        @if (garage().length) {
          <div class="garage">
            <span class="text-sm">My garage:</span>
            @for (g of garage(); track g.id) {
              <button type="button" class="chip" [class.active]="g.variant.id === store.variantId()" (click)="useGarage(g)">
                <app-icon [name]="g.variant.model?.vehicle_type === 'motorcycle' ? 'bike' : 'car'" [size]="14" /> {{ g.nickname || g.variant.full_name }}
              </button>
            }
          </div>
        }
      </div>
    </section>

    <div class="container">
      @if (variant(); as v) {
        <div class="vcard card">
          <div class="vicon"><app-icon [name]="v.model?.vehicle_type === 'motorcycle' ? 'bike' : 'car'" [size]="36" /></div>
          <div class="grow">
            <span class="eyebrow">Your vehicle</span>
            <h2>{{ v.full_name }}</h2>
            <div class="specs">
              <span><app-icon name="calendar" [size]="14" /> {{ store.selected()?.year ?? v.year_range }}</span>
              @if (v.engine) { <span><app-icon name="gauge" [size]="14" /> {{ v.engine }}</span> }
              @if (v.fuel_type) { <span><app-icon name="flame" [size]="14" /> {{ v.fuel_type }}</span> }
              @if (v.transmission) { <span><app-icon name="settings" [size]="14" /> {{ v.transmission }}</span> }
              @if (v.displacement_cc) { <span>{{ v.displacement_cc }} cc</span> }
            </div>
          </div>
          <div class="row wrap gap-8">
            @if (auth.isLoggedIn() && !inGarage()) {
              <button type="button" class="btn" (click)="saveToGarage()" [disabled]="saving()"><app-icon name="plus" [size]="16" /> Save to garage</button>
            }
            <button type="button" class="btn btn-ghost" (click)="clear()">Clear</button>
          </div>
        </div>

        <div class="row wrap gap-8 cats">
          <button type="button" class="chip" [class.active]="!category()" (click)="setCategory(null)">All parts</button>
          @for (c of categoryChips(); track c.slug) {
            <button type="button" class="chip" [class.active]="category() === c.slug" (click)="setCategory(c.slug)">{{ c.name }} <span class="text-subtle">{{ c.count }}</span></button>
          }
        </div>

        <p class="text-muted text-sm">{{ meta()?.total ?? 0 }} compatible products</p>
        @if (!loading() && !items().length) {
          <app-empty-state icon="search" title="No compatible parts yet" message="We don't have parts mapped to this variant in this category yet." />
        } @else {
          <app-product-grid [products]="items()" [loading]="loading()" [fitsSelected]="true" />
          <app-pagination [meta]="meta()" (pageChange)="page.set($event)" />
        }
      } @else {
        <app-empty-state icon="car" title="Select a vehicle to get started" message="Use the selector above. Your choice is remembered while you browse the store." />
      }
    </div>
  `,
  styles: `
    .vhero { background: var(--dark); color: #fff; padding: 20px 0 32px; margin-bottom: 24px; }
    .vhero h1 { color: #fff; margin-top: 14px; }
    .vhero app-breadcrumb { opacity: .8; }
    .lead { color: #aab2bf; }
    .garage { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 16px; }
    .garage .chip { background: var(--dark-2); border-color: var(--dark-line); color: #fff; cursor: pointer; }
    .garage .chip.active { background: var(--brand); border-color: var(--brand); }
    .vcard { display: flex; flex-wrap: wrap; align-items: center; gap: 18px; padding: 20px; margin-bottom: 18px; }
    .vicon { width: 70px; height: 70px; border-radius: 16px; background: var(--brand-50); color: var(--brand); display: grid; place-items: center; }
    .vcard h2 { margin: 0 0 6px; }
    .specs { display: flex; flex-wrap: wrap; gap: 14px; font-size: 13px; color: var(--muted); }
    .specs span { display: inline-flex; gap: 5px; align-items: center; }
    .cats { margin-bottom: 12px; }
    .cats .chip { cursor: pointer; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class VehiclesPage {
  private readonly products = inject(ProductService);
  private readonly vehicles = inject(VehicleService);
  private readonly customer = inject(CustomerService);
  private readonly router = inject(Router);
  private readonly toast = inject(ToastService);
  protected readonly store = inject(VehicleStore);
  protected readonly auth = inject(AuthStore);

  /** ?variant=ID from the URL (e.g. shared links or the homepage finder). */
  readonly variantParam = input<string | undefined>(undefined, { alias: 'variant' });

  protected readonly variant = signal<VehicleVariant | null>(null);
  protected readonly items = signal<ProductCard[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);
  protected readonly loading = signal(false);
  protected readonly page = signal(1);
  protected readonly category = signal<string | null>(null);
  protected readonly categoryChips = signal<{ name: string; slug: string; count: number }[]>([]);
  protected readonly garage = signal<CustomerVehicle[]>([]);
  protected readonly saving = signal(false);
  protected readonly inGarage = computed(() => this.garage().some((g) => g.variant.id === this.store.variantId()));

  constructor() {
    inject(SeoService).set({ title: 'Shop by vehicle', description: 'Find parts that fit your car or motorcycle — select make, model, year and variant.' });
    if (this.auth.isLoggedIn()) {
      this.customer.getVehicles().pipe(catchError(() => of([])), takeUntilDestroyed()).subscribe((g) => this.garage.set(g));
    }
    // Resolve the vehicle from ?variant= or the stored selection.
    toObservable(this.variantParam).pipe(
      switchMap((param) => {
        const id = Number(param) || this.store.variantId();
        return id ? this.vehicles.getVariant(id).pipe(catchError(() => of(null))) : of(null);
      }),
      takeUntilDestroyed(),
    ).subscribe((v) => {
      this.variant.set(v);
      if (v && v.model?.manufacturer && this.store.variantId() !== v.id) {
        this.store.select({
          manufacturerId: v.model.manufacturer.id, manufacturerName: v.model.manufacturer.name, modelId: v.model.id, modelName: v.model.name,
          year: this.store.selected()?.variantId === v.id ? this.store.selected()!.year : null, variantId: v.id, variantName: v.name, vehicleType: v.model.vehicle_type,
        });
      }
    });
    combineLatest([toObservable(this.store.variantId), toObservable(this.page), toObservable(this.category)]).pipe(
      tap(([id]) => this.loading.set(!!id)),
      switchMap(([id, page, category]) => (id
        ? this.products.getCompatibleProducts(id, { page, per_page: 20, category, vehicle_year: this.store.selected()?.year ?? null, with_facets: true, sort: 'popular' }).pipe(catchError(() => of(null)))
        : of(null))),
      takeUntilDestroyed(),
    ).subscribe((p) => {
      this.loading.set(false);
      this.items.set(p?.items ?? []);
      this.meta.set(p?.meta ?? null);
      if (p?.meta.facets && !this.category()) this.categoryChips.set(p.meta.facets.categories.map((c) => ({ name: c.name, slug: c.slug, count: c.count })));
    });
  }

  onSelected(v: SelectedVehicle): void {
    this.page.set(1);
    this.category.set(null);
    void this.router.navigate([], { queryParams: { variant: v.variantId } });
  }

  useGarage(g: CustomerVehicle): void {
    void this.router.navigate([], { queryParams: { variant: g.variant.id } });
  }

  setCategory(slug: string | null): void {
    this.category.set(slug);
    this.page.set(1);
  }

  saveToGarage(): void {
    const v = this.store.selected();
    if (!v) return;
    this.saving.set(true);
    this.customer.saveVehicle({ vehicle_variant_id: v.variantId, year: v.year }).subscribe({
      next: (saved) => { this.garage.update((g) => [saved, ...g]); this.saving.set(false); this.toast.success('Vehicle saved to your garage'); },
      error: (e) => { this.saving.set(false); this.toast.error(errorMessage(e)); },
    });
  }

  clear(): void {
    this.store.clear();
    this.variant.set(null);
    void this.router.navigate([], { queryParams: {} });
  }
}
