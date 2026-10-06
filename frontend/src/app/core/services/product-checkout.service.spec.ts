import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { environment } from '../../../environments/environment';
import { cart, envelope, productCard } from '../../../testing/fixtures';
import { CartStore } from '../state/cart.store';
import { CheckoutService } from './checkout.service';
import { ProductService } from './product.service';

describe('ProductService (listing)', () => {
  it('passes filters, vehicle and attributes as query parameters', () => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    const http = TestBed.inject(HttpTestingController);
    let total = 0;
    TestBed.inject(ProductService)
      .getProducts({ page: 2, category: 'brake-system', brand: ['bosch', 'brembo'], vehicle_variant: 123, min_price: 500, max_price: 5000, in_stock: true, sort: 'price_low', attributes: { position: ['front'] } })
      .subscribe((p) => (total = p.meta.total));
    const req = http.expectOne((r) => r.url === `${environment.apiUrl}/products`);
    const q = req.request.params;
    expect(q.get('category')).toBe('brake-system');
    expect(q.get('brand')).toBe('bosch,brembo');
    expect(q.get('vehicle_variant')).toBe('123');
    expect(q.get('in_stock')).toBe('1');
    expect(q.get('attributes[position]')).toBe('front');
    req.flush(envelope([productCard()], 'ok', { current_page: 2, last_page: 3, per_page: 20, total: 41, from: 21, to: 40 }));
    expect(total).toBe(41);
    http.verify();
  });
});

describe('CheckoutService', () => {
  it('sends only choices (no prices) and empties the local cart after ordering', () => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    const http = TestBed.inject(HttpTestingController);
    const store = TestBed.inject(CartStore);
    store.cart.set(cart());
    let message = '';
    TestBed.inject(CheckoutService)
      .placeOrder({ shipping_address_id: 4, shipping_method: 'express', payment_method: 'demo_upi', payment_details: { vpa: 'aarav@okaxis' } })
      .subscribe((r) => (message = r.message));
    const req = http.expectOne(`${environment.apiUrl}/orders`);
    expect(Object.keys(req.request.body as object).some((k) => /price|total|amount/.test(k))).toBe(false);
    req.flush(envelope({ order: { order_number: 'MG1' }, payment: {}, payment_successful: true }, 'Order placed successfully'));
    expect(message).toBe('Order placed successfully');
    expect(store.cart()).toBeNull();
    http.verify();
  });
});
