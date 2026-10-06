import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { environment } from '../../../environments/environment';
import { RazorpayCheckoutOptions } from '../models/api.models';
import { envelope } from '../../../testing/fixtures';
import { RazorpayService } from './razorpay.service';

const options: RazorpayCheckoutOptions = {
  key: 'rzp_test_KEY', order_id: 'order_ABC', amount: 354000, currency: 'INR', name: 'MotoGears', description: 'Order MG1',
  prefill: { name: 'Aarav', email: 'a@example.com', contact: '9999999999' }, notes: {}, theme: { color: '#d7263d' }, test_mode: true,
};

/** Stand-in for window.Razorpay that records options and lets the test drive the popup. */
class FakeRazorpay {
  static last: FakeRazorpay;
  failedCb?: (r: unknown) => void;
  opened = false;
  constructor(public opts: { handler: (r: unknown) => void; modal: { ondismiss: () => void } } & Record<string, unknown>) {
    FakeRazorpay.last = this;
  }
  open(): void { this.opened = true; }
  on(_: string, cb: (r: unknown) => void): void { this.failedCb = cb; }
}

describe('RazorpayService', () => {
  let service: RazorpayService;
  let http: HttpTestingController;
  const url = (p: string) => `${environment.apiUrl}/${p}`;

  beforeEach(() => {
    (window as unknown as { Razorpay?: unknown }).Razorpay = FakeRazorpay;
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    service = TestBed.inject(RazorpayService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    delete (window as unknown as { Razorpay?: unknown }).Razorpay;
    http.verify();
  });

  it('opens checkout with the server-issued order and only the public key', async () => {
    const pending = service.checkout(7, options);
    await Promise.resolve();
    await Promise.resolve();
    const rzp = FakeRazorpay.last;
    expect(rzp.opened).toBe(true);
    expect(rzp.opts['order_id']).toBe('order_ABC');
    expect(rzp.opts['key']).toBe('rzp_test_KEY');
    expect(JSON.stringify(rzp.opts)).not.toContain('secret');
    rzp.opts.modal.ondismiss();
    expect(await pending).toEqual({ status: 'dismissed' });
  });

  it('sends the signed response to Laravel for verification before reporting success', async () => {
    const pending = service.checkout(7, options);
    await Promise.resolve();
    await Promise.resolve();
    FakeRazorpay.last.opts.handler({ razorpay_payment_id: 'pay_1', razorpay_order_id: 'order_ABC', razorpay_signature: 'sig' });
    const req = http.expectOne(url('orders/7/razorpay/verify'));
    expect(req.request.body).toEqual({ razorpay_payment_id: 'pay_1', razorpay_order_id: 'order_ABC', razorpay_signature: 'sig' });
    req.flush(envelope({ order: { id: 7, status: 'confirmed' }, payment: { status: 'success' }, payment_successful: true }));
    const outcome = await pending;
    expect(outcome.status).toBe('paid');
  });

  it('records failed attempts without closing the flow', async () => {
    service.checkout(7, options);
    await Promise.resolve();
    await Promise.resolve();
    FakeRazorpay.last.failedCb?.({ error: { description: 'Card declined' } });
    const req = http.expectOne(url('orders/7/razorpay/failed'));
    expect(req.request.body).toEqual({ razorpay_order_id: 'order_ABC', reason: 'Card declined' });
    req.flush(envelope(null));
  });
});
