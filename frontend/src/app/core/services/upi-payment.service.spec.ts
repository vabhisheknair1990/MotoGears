import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { environment } from '../../../environments/environment';
import { authInterceptor } from '../interceptors/auth.interceptor';
import { UpiPaymentService } from './upi-payment.service';

describe('UpiPaymentService', () => {
  let service: UpiPaymentService;
  let http: HttpTestingController;
  const url = (p: string) => `${environment.apiUrl}/${p}`;
  const session = { status: 'pending', flow: 'intent', amount: 1180, payee: 'MotoGears', demo: true };

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(withInterceptors([authInterceptor])), provideHttpClientTesting()] });
    service = TestBed.inject(UpiPaymentService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('uses the customer order endpoints', () => {
    let qr: unknown;
    service.qr({ id: 7, number: 'MG1' }).subscribe((s) => (qr = s));
    const req = http.expectOne(url('orders/7/upi/qr'));
    expect(req.request.method).toBe('POST');
    req.flush({ success: true, message: 'ok', data: { ...session, qr: 'data:image/svg+xml;base64,AAA' } });
    expect((qr as { qr: string }).qr).toBe('data:image/svg+xml;base64,AAA');

    service.collect({ id: 7, number: 'MG1' }, 'asha@okaxis').subscribe();
    const collect = http.expectOne(url('orders/7/upi/collect'));
    expect(collect.request.body).toEqual({ vpa: 'asha@okaxis' });
    collect.flush({ success: true, message: 'ok', data: { ...session, flow: 'collect' } });

    service.status({ id: 7, number: 'MG1' }).subscribe();
    const status = http.expectOne(url('orders/7/upi/status'));
    expect(status.request.method).toBe('GET');
    status.flush({ success: true, message: 'ok', data: session });
  });

  it('uses the guest endpoints with the order token header', () => {
    service.status({ id: 7, number: 'MG261011-1', token: 'tok' }).subscribe();
    const req = http.expectOne(url('guest/orders/MG261011-1/upi/status'));
    expect(req.request.headers.get('X-Order-Token')).toBe('tok');
    req.flush({ success: true, message: 'ok', data: session });

    service.simulate({ id: 7, number: 'MG261011-1', token: 'tok' }, 'success').subscribe();
    const sim = http.expectOne(url('guest/orders/MG261011-1/upi/simulate'));
    expect(sim.request.body).toEqual({ outcome: 'success' });
    sim.flush({ success: true, message: 'ok', data: { ...session, status: 'paid' } });
  });
});
