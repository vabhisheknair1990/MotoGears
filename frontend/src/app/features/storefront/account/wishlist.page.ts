import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ProductCard } from '../../../core/models/api.models';
import { ToastService } from '../../../core/services/toast.service';
import { WishlistService } from '../../../core/services/wishlist.service';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { PriceComponent } from '../../../shared/components/price.component';
import { AppDatePipe } from '../../../shared/pipes/inr.pipe';

@Component({
  selector: 'app-wishlist-page',
  imports: [RouterLink, EmptyStateComponent, IconComponent, PriceComponent, AppDatePipe],
  template: `
    <h1 class="h1">Wishlist <span class="text-muted count">({{ wishlist.count() }})</span></h1>
    @if (loading()) {
      <div class="skeleton" style="height: 200px"></div>
    } @else {
      <div class="card">
        @for (i of wishlist.wishlist()?.items ?? []; track i.id) {
          <div class="row-w">
            <a [routerLink]="['/products', i.product.slug]"><img [src]="i.product.image" [alt]="i.product.name" class="img" /></a>
            <div class="grow">
              <span class="text-xs text-muted fw-600">{{ i.product.brand?.name }}</span>
              <a [routerLink]="['/products', i.product.slug]" class="fw-600">{{ i.product.name }}</a>
              <app-price [price]="i.product.price" [mrp]="i.product.mrp" />
              <span class="text-xs" [class.text-danger]="i.product.stock_status === 'out_of_stock'" [class.text-success]="i.product.stock_status !== 'out_of_stock'">
                {{ i.product.stock_status === 'out_of_stock' ? 'Out of stock' : 'In stock' }} · added {{ i.added_at | appDate }}
              </span>
            </div>
            <div class="acts">
              <button class="btn btn-primary btn-sm" [disabled]="busy() === i.product.id || i.product.stock_status === 'out_of_stock'" (click)="moveToCart(i.product)"><app-icon name="cart" [size]="15" /> Move to cart</button>
              <button class="btn btn-sm btn-ghost" [disabled]="busy() === i.product.id" (click)="remove(i.product)"><app-icon name="trash" [size]="15" /> Remove</button>
            </div>
          </div>
        } @empty {
          <app-empty-state icon="heart" title="Your wishlist is empty" message="Tap the heart on any product to save it for later."><a class="btn btn-primary" routerLink="/shop">Browse products</a></app-empty-state>
        }
      </div>
    }
  `,
  styles: `
    .h1 { font-size: 28px; margin-bottom: 16px; } .count { font-size: .6em; font-family: var(--font); }
    .row-w { display: flex; gap: 14px; align-items: center; padding: 14px 18px; border-bottom: 1px solid var(--line-2); flex-wrap: wrap; }
    .row-w .grow { display: flex; flex-direction: column; gap: 3px; min-width: 200px; }
    .img { width: 84px; height: 84px; border-radius: var(--radius-sm); object-fit: cover; }
    .acts { display: flex; flex-direction: column; gap: 6px; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class WishlistPage {
  protected readonly wishlist = inject(WishlistService);
  private readonly toast = inject(ToastService);
  protected readonly loading = signal(true);
  protected readonly busy = signal<number | null>(null);

  constructor() {
    this.wishlist.fetch().subscribe({ next: () => this.loading.set(false), error: () => this.loading.set(false) });
  }

  moveToCart(p: ProductCard): void {
    this.busy.set(p.id);
    this.wishlist.moveToCart(p.id).subscribe({
      next: () => { this.busy.set(null); this.toast.success('Moved to cart', { label: 'View cart', url: '/cart' }); },
      error: () => this.busy.set(null),
    });
  }

  remove(p: ProductCard): void {
    this.busy.set(p.id);
    this.wishlist.remove(p.id).subscribe({
      next: () => { this.busy.set(null); this.toast.info('Product removed from wishlist'); },
      error: () => this.busy.set(null),
    });
  }
}
