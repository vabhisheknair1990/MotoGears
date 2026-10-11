import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { environment } from '../../../environments/environment';
import { authInterceptor } from '../interceptors/auth.interceptor';
import { CheckoutService } from './checkout.service';
import { GUEST_ORDERS_KEY, GuestOrdersService } from './guest-orders.service';

describe('GuestOrdersService', () => {
  let service: GuestOrdersService;
  let http: HttpTestingController;
  const url = (p: string) => `${environment.apiUrl}/${p}`;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({ providers: [provideHttpClient(withInterceptors([authInterceptor])), provideHttpClientTesting()] });
    service = TestBed.inject(GuestOrdersService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('sends the order secret as X-Order-Token, never in the URL', () => {
    service.get('MG261011-12345', 'secret-token').subscribe();
    const req = http.expectOne(url('guest/orders/MG261011-12345'));
    expect(req.request.headers.get('X-Order-Token')).toBe('secret-token');
    expect(req.request.urlWithParams).not.toContain('secret-token');
    req.flush({ success: true, message: 'ok', data: { id: 1 } });
  });

  it('remembers orders placed on this device, newest first, without duplicates', () => {
    service.remember({ number: 'A', token: 't1' });
    service.remember({ number: 'B', token: 't2' });
    service.remember({ number: 'A', token: 't3' });
    expect(service.remembered().map((r) => r.number)).toEqual(['A', 'B']);
    expect(service.tokenFor('A')).toBe('t3');
    expect(JSON.parse(localStorage.getItem(GUEST_ORDERS_KEY)!).length).toBe(2);
    service.forget('A');
    expect(service.tokenFor('A')).toBeNull();
  });

  it('looks up an order by number and email', () => {
    service.track(' MG1 ', ' me@example.com ').subscribe((r) => expect(r.token).toBe('tok'));
    const req = http.expectOne(url('guest/orders/track'));
    expect(req.request.body).toEqual({ order_number: 'MG1', email: 'me@example.com' });
    req.flush({ success: true, message: 'ok', data: { order_number: 'MG1', token: 'tok' } });
  });

  it('treats a declined retry (HTTP 402) as a result, not an error', () => {
    let ok: boolean | undefined;
    service.pay('MG1', 'tok', 'demo_upi', { upi_id: 'x@y' }).subscribe((r) => (ok = r.ok));
    http.expectOne(url('guest/orders/MG1/pay')).flush({ success: false, message: 'Payment failed', data: { order: { id: 1 }, payment_successful: false } }, { status: 402, statusText: 'Payment Required' });
    expect(ok).toBe(false);
  });
});

describe('CheckoutService (guest)', () => {
  let service: CheckoutService;
  let http: HttpTestingController;
  const url = (p: string) => `${environment.apiUrl}/${p}`;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    service = TestBed.inject(CheckoutService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('uses the guest endpoints without an account', () => {
    service.getSummary('standard', true).subscribe();
    http.expectOne((r) => r.url === url('guest/checkout')).flush({ success: true, message: 'ok', data: {} });

    const address = { label: 'Home', name: 'Asha', phone: '9876543210', line1: '1 Road', line2: null, landmark: null, city: 'Kochi', state: 'Kerala', postal_code: '682016', country: 'IN', is_default: false };
    service.placeGuestOrder({ email: 'a@b.co', shipping_address: address, shipping_method: 'standard', payment_method: 'cod' }).subscribe();
    const req = http.expectOne(url('guest/orders'));
    expect(req.request.body.email).toBe('a@b.co');
    expect(req.request.body.shipping_address.postal_code).toBe('682016');
    expect('shipping_address_id' in req.request.body).toBe(false);
    req.flush({ success: true, message: 'ok', data: { order: { order_number: 'MG1' }, guest_token: 't' } });
  });
});
