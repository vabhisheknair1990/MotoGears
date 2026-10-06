import { ChangeDetectionStrategy, Component, computed, inject, input, output } from '@angular/core';
import { CartService } from '../../core/services/cart.service';
import { ToastService } from '../../core/services/toast.service';
import { CartStore } from '../../core/state/cart.store';
import { UiStore } from '../../core/state/ui.store';
import { StockStatus } from '../../core/models/api.models';
import { IconComponent } from './icon.component';

@Component({
  selector: 'app-add-to-cart-button',
  imports: [IconComponent],
  template: `
    <button type="button" class="btn" [class]="classes()" [disabled]="busy() || soldOut()" (click)="add($event)">
      @if (busy()) {
        <span class="spinner"></span>
      } @else {
        <app-icon [name]="soldOut() ? 'x' : 'cart'" [size]="18" />
      }
      <span>{{ soldOut() ? 'Out of stock' : label() }}</span>
    </button>
  `,
  styles: `:host { display: contents; }`,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AddToCartButtonComponent {
  private readonly cart = inject(CartService);
  private readonly store = inject(CartStore);
  private readonly ui = inject(UiStore);
  private readonly toast = inject(ToastService);

  readonly productId = input.required<number>();
  readonly quantity = input(1);
  readonly stockStatus = input<StockStatus>('in_stock');
  readonly label = input('Add to cart');
  readonly size = input<'sm' | 'md' | 'lg'>('md');
  readonly block = input(false);
  readonly openDrawer = input(true);
  readonly added = output<void>();

  protected readonly busy = computed(() => this.store.pending().has(this.productId()));
  protected readonly soldOut = computed(() => this.stockStatus() === 'out_of_stock');
  protected readonly classes = computed(() =>
    ['btn', this.soldOut() ? '' : 'btn-primary', this.size() === 'sm' ? 'btn-sm' : this.size() === 'lg' ? 'btn-lg' : '', this.block() ? 'btn-block' : ''].join(' '),
  );

  add(event: Event): void {
    event.preventDefault();
    event.stopPropagation();
    this.cart.add(this.productId(), this.quantity()).subscribe({
      next: () => {
        this.toast.success('Product added to cart', { label: 'View cart', url: '/cart' });
        this.added.emit();
        if (this.openDrawer()) this.ui.cartDrawerOpen.set(true);
      },
    });
  }
}
