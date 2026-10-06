import { ChangeDetectionStrategy, Component, computed, inject, input, signal } from '@angular/core';
import { takeUntilDestroyed, toObservable } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { catchError, combineLatest, of, switchMap, tap } from 'rxjs';
import { HttpErrorResponse } from '@angular/common/http';
import { Product, ProductCard } from '../../../core/models/api.models';
import { CartService } from '../../../core/services/cart.service';
import { ProductService } from '../../../core/services/product.service';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { UiStore } from '../../../core/state/ui.store';
import { VehicleStore } from '../../../core/state/vehicle.store';
import { AddToCartButtonComponent } from '../../../shared/components/add-to-cart-button.component';
import { BreadcrumbComponent, Crumb } from '../../../shared/components/breadcrumb.component';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { PriceComponent } from '../../../shared/components/price.component';
import { ProductGalleryComponent } from '../../../shared/components/product-gallery.component';
import { ProductGridComponent } from '../../../shared/components/product-grid.component';
import { QuantityStepperComponent } from '../../../shared/components/quantity-stepper.component';
import { RatingComponent } from '../../../shared/components/rating.component';
import { ReviewListComponent } from '../../../shared/components/review-list.component';
import { WishlistButtonComponent } from '../../../shared/components/wishlist-button.component';
import { InrPipe } from '../../../shared/pipes/inr.pipe';

type Tab = 'description' | 'specs' | 'compatibility' | 'installation' | 'reviews' | 'faq';

@Component({
  selector: 'app-product-detail-page',
  imports: [RouterLink, BreadcrumbComponent, ProductGalleryComponent, PriceComponent, RatingComponent, QuantityStepperComponent,
    AddToCartButtonComponent, WishlistButtonComponent, IconComponent, ReviewListComponent, ProductGridComponent, EmptyStateComponent, InrPipe],
  templateUrl: './product-detail.page.html',
  styleUrl: './product-detail.page.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductDetailPage {
  private readonly products = inject(ProductService);
  private readonly cart = inject(CartService);
  private readonly seo = inject(SeoService);
  private readonly toast = inject(ToastService);
  private readonly router = inject(Router);
  protected readonly vehicle = inject(VehicleStore);
  protected readonly ui = inject(UiStore);

  /** Route param (component input binding). */
  readonly slug = input.required<string>();

  protected readonly product = signal<Product | null>(null);
  protected readonly related = signal<ProductCard[]>([]);
  protected readonly loading = signal(true);
  protected readonly notFound = signal(false);
  protected readonly qty = signal(1);
  protected readonly tab = signal<Tab>('description');
  protected readonly buying = signal(false);

  protected readonly crumbs = computed<Crumb[]>(() => {
    const p = this.product();
    const out: Crumb[] = [{ label: 'Home', link: '/' }, { label: 'Shop', link: '/shop' }];
    if (p?.category.parent) out.push({ label: p.category.parent.name, link: ['/category', p.category.parent.slug] });
    if (p) out.push({ label: p.category.name, link: ['/category', p.category.slug] }, { label: p.name });
    return out;
  });
  protected readonly maxQty = computed(() => {
    const p = this.product();
    if (!p) return 1;
    return p.inventory.allow_backorder ? 10 : Math.max(1, Math.min(10, p.inventory.available));
  });
  protected readonly fit = computed(() => this.product()?.fits_selected_vehicle ?? null);
  protected readonly groupedCompat = computed(() => {
    const map = new Map<string, { make: string; rows: Product['compatibility'] }>();
    for (const c of this.product()?.compatibility ?? []) {
      const key = c.manufacturer?.name ?? 'Other';
      if (!map.has(key)) map.set(key, { make: key, rows: [] });
      map.get(key)!.rows.push(c);
    }
    return [...map.values()];
  });

  constructor() {
    combineLatest([toObservable(this.slug), toObservable(this.vehicle.variantId)]).pipe(
      tap(() => this.loading.set(true)),
      switchMap(([slug, variant]) => this.products.getProduct(slug, variant).pipe(
        catchError((e: HttpErrorResponse) => {
          this.notFound.set(e.status === 404);
          return of(null);
        }),
      )),
      tap((p) => {
        this.loading.set(false);
        this.product.set(p);
        if (p) {
          this.notFound.set(false);
          this.qty.set(1);
          this.seo.set({ title: p.meta_title, description: p.meta_description, image: p.images[0]?.url, type: 'product' });
        }
      }),
      switchMap((p) => (p ? this.products.getRelated(p.id).pipe(catchError(() => of([]))) : of([]))),
      takeUntilDestroyed(),
    ).subscribe((r) => this.related.set(r));
  }

  buyNow(): void {
    const p = this.product();
    if (!p) return;
    this.buying.set(true);
    this.cart.add(p.id, this.qty()).subscribe({
      next: () => void this.router.navigate(['/checkout']),
      error: () => this.buying.set(false),
    });
  }

  share(): void {
    const p = this.product();
    if (!p) return;
    const url = globalThis.location?.href ?? '';
    const nav = globalThis.navigator as Navigator & { share?: (d: ShareData) => Promise<void> };
    if (nav.share) {
      void nav.share({ title: p.name, text: `${p.name} on MotoGears`, url }).catch(() => undefined);
    } else {
      void nav.clipboard?.writeText(url).then(() => this.toast.success('Product link copied to clipboard'));
    }
  }

  showTab(t: Tab): void {
    this.tab.set(t);
    document.getElementById('details')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}
