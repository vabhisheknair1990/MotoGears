import { A11yModule } from '@angular/cdk/a11y';
import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { CartService } from '../../core/services/cart.service';
import { CartStore } from '../../core/state/cart.store';
import { UiStore } from '../../core/state/ui.store';
import { CartItem } from '../../core/models/api.models';
import { InrPipe } from '../pipes/inr.pipe';
import { EmptyStateComponent } from './empty-state.component';
import { IconComponent } from './icon.component';
import { QuantityStepperComponent } from './quantity-stepper.component';

@Component({
  selector: 'app-cart-drawer',
  imports: [A11yModule, RouterLink, IconComponent, InrPipe, QuantityStepperComponent, EmptyStateComponent],
  template: `
    @if (ui.cartDrawerOpen()) {
      <div class="backdrop" (click)="close()"></div>
      <aside class="drawer" role="dialog" aria-modal="true" aria-label="Shopping cart" cdkTrapFocus [cdkTrapFocusAutoCapture]="true" (keydown.escape)="close()">
        <header>
          <h2>Your cart <span class="text-muted">({{ store.count() }})</span></h2>
          <button class="icon-btn sm" (click)="close()" aria-label="Close cart"><app-icon name="x" /></button>
        </header>
        @if (store.isEmpty()) {
          <app-empty-state icon="cart" title="Your cart is empty." message="Find the right parts for your vehicle and add them here.">
            <a class="btn btn-primary" routerLink="/shop" (click)="close()">Start shopping</a>
          </app-empty-state>
        } @else {
          @if (store.cart(); as c) {
            @if (c.amount_to_free_shipping > 0) {
              <div class="ship-bar">
                <span>Add <strong>{{ c.amount_to_free_shipping | inr }}</strong> more for free delivery</span>
                <div class="bar"><i [style.width.%]="100 - (c.amount_to_free_shipping / c.free_shipping_threshold) * 100"></i></div>
              </div>
            } @else {
              <div class="ship-bar ok"><app-icon name="truck" [size]="16" /> You've unlocked free standard delivery</div>
            }
          }
          <ul class="items list-reset">
            @for (item of store.items(); track item.id) {
              <li>
                <a [routerLink]="['/products', item.product.slug]" (click)="close()">
                  <img [src]="item.product.image" [alt]="item.product.name" class="thumb" />
                </a>
                <div class="info">
                  <a class="name clamp-2" [routerLink]="['/products', item.product.slug]" (click)="close()">{{ item.product.name }}</a>
                  <div class="row between">
                    <app-qty [value]="item.quantity" [max]="item.max_quantity || 1" (valueChange)="update(item, $event)" />
                    <strong>{{ item.line_subtotal | inr }}</strong>
                  </div>
                  @if (!item.in_stock) { <span class="error-text">Only {{ item.available_stock }} available</span> }
                </div>
                <button class="icon-btn sm rm" (click)="remove(item)" aria-label="Remove item"><app-icon name="trash" [size]="16" /></button>
              </li>
            }
          </ul>
          <footer>
            <div class="row between"><span>Subtotal</span><strong>{{ store.subtotal() | inr }}</strong></div>
            @if (store.discount() > 0) { <div class="row between text-success"><span>Discount</span><strong>−{{ store.discount() | inr }}</strong></div> }
            <p class="text-xs text-muted">Shipping and GST are calculated at checkout.</p>
            <a class="btn btn-primary btn-lg btn-block" routerLink="/checkout" (click)="close()">Checkout · {{ store.total() | inr }}</a>
            <a class="btn btn-block mt-8" routerLink="/cart" (click)="close()">View cart</a>
          </footer>
        }
      </aside>
    }
  `,
  styles: `
    .drawer { position: fixed; z-index: 100; top: 0; right: 0; bottom: 0; width: min(420px, 100%); background: var(--surface); display: flex; flex-direction: column; box-shadow: var(--shadow-lg); animation: slide-in-right .22s ease; }
    header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid var(--line); }
    h2 { margin: 0; font-size: 20px; }
    .ship-bar { padding: 12px 20px; background: var(--surface-2); font-size: 13px; display: flex; flex-direction: column; gap: 8px; }
    .ship-bar.ok { flex-direction: row; align-items: center; color: var(--success); font-weight: 600; }
    .bar { height: 6px; background: var(--line); border-radius: 3px; overflow: hidden; }
    .bar i { display: block; height: 100%; background: var(--success); border-radius: 3px; }
    .items { flex: 1; overflow-y: auto; padding: 8px 20px; }
    li { display: flex; gap: 12px; padding: 14px 0; border-bottom: 1px solid var(--line-2); position: relative; }
    .thumb { width: 72px; height: 72px; }
    .info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 8px; }
    .name { font-weight: 600; font-size: 14px; padding-right: 28px; }
    .rm { position: absolute; top: 10px; right: -6px; color: var(--muted); }
    footer { padding: 16px 20px; border-top: 1px solid var(--line); display: flex; flex-direction: column; gap: 6px; }
    footer p { margin: 4px 0 8px; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CartDrawerComponent {
  protected readonly ui = inject(UiStore);
  protected readonly store = inject(CartStore);
  private readonly cart = inject(CartService);

  close(): void {
    this.ui.cartDrawerOpen.set(false);
  }

  update(item: CartItem, qty: number): void {
    this.cart.update(item.id, qty).subscribe();
  }

  remove(item: CartItem): void {
    this.cart.remove(item.id).subscribe();
  }
}
