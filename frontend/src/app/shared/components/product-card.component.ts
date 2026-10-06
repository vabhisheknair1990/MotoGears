import { ChangeDetectionStrategy, Component, computed, inject, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ProductCard } from '../../core/models/api.models';
import { VehicleStore } from '../../core/state/vehicle.store';
import { AddToCartButtonComponent } from './add-to-cart-button.component';
import { IconComponent } from './icon.component';
import { PriceComponent } from './price.component';
import { RatingComponent } from './rating.component';
import { WishlistButtonComponent } from './wishlist-button.component';

@Component({
  selector: 'app-product-card',
  imports: [RouterLink, PriceComponent, RatingComponent, WishlistButtonComponent, AddToCartButtonComponent, IconComponent],
  template: `
    @let p = product();
    <article class="card-p" [class.list]="layout() === 'list'">
      <a class="media" [routerLink]="['/products', p.slug]" [attr.aria-label]="p.name">
        @if (p.image) {
          <img [src]="p.image" [alt]="p.name" loading="lazy" decoding="async" width="400" height="400" />
        } @else {
          <div class="noimg"><app-icon name="image" [size]="32" /></div>
        }
        <div class="flags">
          @if (p.discount_percent >= 5) { <span class="badge badge-brand">{{ p.discount_percent }}% OFF</span> }
          @if (p.is_featured) { <span class="badge badge-dark">Featured</span> }
        </div>
      </a>
      <div class="wl"><app-wishlist-button [productId]="p.id" /></div>
      <div class="body">
        <div class="brand">{{ p.brand?.name }}</div>
        <h3 class="name"><a [routerLink]="['/products', p.slug]">{{ p.name }}</a></h3>
        @if (p.review_count > 0) {
          <app-rating [value]="p.rating" [count]="p.review_count" />
        } @else {
          <span class="text-xs text-subtle">No reviews yet</span>
        }
        <div class="fit">
          @if (fit() === 'fits') {
            <span class="fit-yes"><app-icon name="check-circle" [size]="14" /> Fits your {{ vehicleName() }}</span>
          } @else if (p.is_universal) {
            <span class="fit-uni"><app-icon name="globe" [size]="14" /> Universal fit</span>
          } @else if (fit() === 'no') {
            <span class="fit-no"><app-icon name="alert" [size]="14" /> Check fitment</span>
          } @else {
            <span class="fit-uni"><app-icon [name]="p.vehicle_type === 'motorcycle' ? 'bike' : 'car'" [size]="14" /> Vehicle-specific</span>
          }
        </div>
        <app-price [price]="p.price" [mrp]="p.mrp" />
        <div class="stock" [class]="'stock ' + p.stock_status">
          @switch (p.stock_status) {
            @case ('in_stock') { In stock }
            @case ('low_stock') { Only {{ p.available_stock }} left }
            @case ('backorder') { Available on backorder }
            @default { Out of stock }
          }
        </div>
        <div class="actions">
          <app-add-to-cart-button [productId]="p.id" [stockStatus]="p.stock_status" size="sm" [block]="true" />
        </div>
      </div>
    </article>
  `,
  styleUrl: './product-card.component.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductCardComponent {
  private readonly vehicle = inject(VehicleStore);
  readonly product = input.required<ProductCard>();
  readonly layout = input<'grid' | 'list'>('grid');
  /** true = the listing was filtered by the selected vehicle, so every result fits it. */
  readonly fitsSelected = input<boolean | null>(null);

  protected readonly vehicleName = computed(() => {
    const v = this.vehicle.selected();
    return v ? v.modelName : '';
  });
  protected readonly fit = computed<'fits' | 'no' | 'unknown'>(() => {
    const p = this.product();
    if (p.fits_vehicle === true || (this.fitsSelected() && !p.is_universal)) return 'fits';
    if (p.fits_vehicle === false) return 'no';
    return 'unknown';
  });
}
