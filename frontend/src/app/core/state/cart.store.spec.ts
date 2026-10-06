import { TestBed } from '@angular/core/testing';
import { cart, cartItem, productCard } from '../../../testing/fixtures';
import { CartStore } from './cart.store';

describe('CartStore', () => {
  let store: CartStore;

  beforeEach(() => {
    store = TestBed.inject(CartStore);
  });

  it('starts empty', () => {
    expect(store.isEmpty()).toBe(true);
    expect(store.count()).toBe(0);
    expect(store.total()).toBe(0);
  });

  it('exposes server totals without recalculating them', () => {
    store.cart.set(cart({ subtotal: 5000, discount: 500, shipping: 100, tax: 828, grand_total: 5428, total_quantity: 3 }));
    expect(store.subtotal()).toBe(5000);
    expect(store.discount()).toBe(500);
    expect(store.shipping()).toBe(100);
    expect(store.tax()).toBe(828);
    expect(store.total()).toBe(5428);
    expect(store.count()).toBe(3);
    expect(store.isEmpty()).toBe(false);
  });

  it('looks up the quantity of a product in the cart', () => {
    store.cart.set(cart({ items: [cartItem({ quantity: 4, product: productCard({ id: 42 }) })] }));
    expect(store.quantityOf(42)).toBe(4);
    expect(store.quantityOf(99)).toBe(0);
  });

  it('tracks pending add-to-cart requests per product', () => {
    store.setPending(7, true);
    store.setPending(8, true);
    store.setPending(7, false);
    expect([...store.pending()]).toEqual([8]);
  });
});
