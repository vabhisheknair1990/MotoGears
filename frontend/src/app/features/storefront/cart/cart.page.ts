import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { CartItem } from '../../../core/models/api.models';
import { CartService } from '../../../core/services/cart.service';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { WishlistService } from '../../../core/services/wishlist.service';
import { AuthStore } from '../../../core/state/auth.store';
import { CartStore } from '../../../core/state/cart.store';
import { errorMessage } from '../../../core/utils/http-errors';
import { BreadcrumbComponent } from '../../../shared/components/breadcrumb.component';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { QuantityStepperComponent } from '../../../shared/components/quantity-stepper.component';
import { InrPipe } from '../../../shared/pipes/inr.pipe';

@Component({
  selector: 'app-cart-page',
  imports: [RouterLink, FormsModule, BreadcrumbComponent, EmptyStateComponent, IconComponent, QuantityStepperComponent, InrPipe],
  templateUrl: './cart.page.html',
  styleUrl: './cart.page.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CartPage {
  protected readonly store = inject(CartStore);
  protected readonly auth = inject(AuthStore);
  private readonly cart = inject(CartService);
  private readonly wishlist = inject(WishlistService);
  private readonly toast = inject(ToastService);

  protected code = '';
  protected readonly couponError = signal<string | null>(null);
  protected readonly applying = signal(false);
  protected readonly busyItem = signal<number | null>(null);

  constructor() {
    inject(SeoService).set({ title: 'Your cart' });
    this.cart.load();
  }

  update(item: CartItem, qty: number): void {
    this.busyItem.set(item.id);
    this.cart.update(item.id, qty).subscribe({ next: () => this.busyItem.set(null), error: () => this.busyItem.set(null) });
  }

  remove(item: CartItem): void {
    this.busyItem.set(item.id);
    this.cart.remove(item.id).subscribe({
      next: () => { this.busyItem.set(null); this.toast.info(`${item.product.name} removed from cart`); },
      error: () => this.busyItem.set(null),
    });
  }

  saveForLater(item: CartItem): void {
    if (!this.auth.isLoggedIn()) {
      this.toast.info('Log in to save items to your wishlist.');
      return;
    }
    this.wishlist.add(item.product.id).subscribe(() => {
      this.cart.remove(item.id).subscribe();
      this.toast.success('Moved to your wishlist');
    });
  }

  applyCoupon(): void {
    const code = this.code.trim();
    if (!code) return;
    this.applying.set(true);
    this.couponError.set(null);
    this.cart.applyCoupon(code).subscribe({
      next: (r) => { this.applying.set(false); this.code = ''; this.toast.success(r.message.replace(/^Coupon (\S+) applied$/, 'Coupon $1 applied')); },
      error: (e) => { this.applying.set(false); this.couponError.set(errorMessage(e)); },
    });
  }

  removeCoupon(): void {
    this.cart.removeCoupon().subscribe(() => this.toast.info('Coupon removed'));
  }

  setShipping(method: 'standard' | 'express'): void {
    this.cart.setShipping(method).subscribe();
  }

  clear(): void {
    this.cart.clear().subscribe(() => this.toast.info('Cart cleared'));
  }
}
