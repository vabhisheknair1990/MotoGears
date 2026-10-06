import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { environment } from '../../../environments/environment';
import { cart, envelope } from '../../../testing/fixtures';
import { CartStore } from '../state/cart.store';
import { CART_TOKEN_KEY, CartService } from './cart.service';

describe('CartService', () => {
  let service: CartService;
  let store: CartStore;
  let http: HttpTestingController;
  const url = (p: string) => `${environment.apiUrl}/${p}`;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    service = TestBed.inject(CartService);
    store = TestBed.inject(CartStore);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('sends only product id and quantity — never prices', () => {
    service.add(1, 2).subscribe();
    const req = http.expectOne(url('cart/items'));
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ product_id: 1, quantity: 2 });
    expect(store.pending().has(1)).toBe(true);
    req.flush(envelope(cart()));
    expect(store.pending().has(1)).toBe(false);
  });

  it('replaces local state with the authoritative server cart and remembers the guest token', () => {
    service.update(10, 3).subscribe();
    http.expectOne(url('cart/items/10')).flush(envelope(cart({ total_quantity: 3, grand_total: 5664, token: 'tok-1' })));
    expect(store.count()).toBe(3);
    expect(store.total()).toBe(5664);
    expect(localStorage.getItem(CART_TOKEN_KEY)).toBe('tok-1');
  });

  it('applies and removes coupons through the API', () => {
    service.applyCoupon('WELCOME10').subscribe();
    const req = http.expectOne(url('cart/apply-coupon'));
    expect(req.request.body).toEqual({ code: 'WELCOME10' });
    req.flush(envelope(cart({ discount: 320 })));
    expect(store.discount()).toBe(320);

    service.removeCoupon().subscribe();
    http.expectOne(url('cart/coupon')).flush(envelope(cart({ discount: 0 })));
    expect(store.discount()).toBe(0);
  });

  it('keeps state unchanged when the API rejects an update', () => {
    store.cart.set(cart({ total_quantity: 2 }));
    service.update(10, 99).subscribe({ error: () => undefined });
    http.expectOne(url('cart/items/10')).flush({ success: false, message: 'Only 5 in stock' }, { status: 422, statusText: 'Unprocessable' });
    expect(store.count()).toBe(2);
  });

  it('reset() clears the cart after checkout', () => {
    store.cart.set(cart());
    service.reset();
    expect(store.cart()).toBeNull();
  });
});
