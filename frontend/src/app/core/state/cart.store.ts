import { Injectable, computed, signal } from '@angular/core';
import { Cart } from '../models/api.models';

/**
 * Mirror of the server cart. Every mutation goes through CartService, which replaces the
 * whole state with the authoritative cart returned by Laravel — totals are never computed here.
 */
@Injectable({ providedIn: 'root' })
export class CartStore {
  readonly cart = signal<Cart | null>(null);
  readonly loading = signal(false);
  /** Product IDs with an in-flight add-to-cart request (for button spinners). */
  readonly pending = signal<ReadonlySet<number>>(new Set());

  readonly items = computed(() => this.cart()?.items ?? []);
  readonly count = computed(() => this.cart()?.total_quantity ?? 0);
  readonly subtotal = computed(() => this.cart()?.subtotal ?? 0);
  readonly discount = computed(() => this.cart()?.discount ?? 0);
  readonly shipping = computed(() => this.cart()?.shipping ?? 0);
  readonly tax = computed(() => this.cart()?.tax ?? 0);
  readonly total = computed(() => this.cart()?.grand_total ?? 0);
  readonly isEmpty = computed(() => this.items().length === 0);

  quantityOf(productId: number): number {
    return this.items().find((i) => i.product.id === productId)?.quantity ?? 0;
  }

  setPending(productId: number, on: boolean): void {
    this.pending.update((s) => {
      const next = new Set(s);
      if (on) {
        next.add(productId);
      } else {
        next.delete(productId);
      }
      return next;
    });
  }
}
