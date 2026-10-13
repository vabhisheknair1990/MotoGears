import { TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import { HttpErrorResponse } from '@angular/common/http';
import { Order, UpiSession } from '../../core/models/api.models';
import { UpiPaymentService } from '../../core/services/upi-payment.service';
import { UPI_ID_PATTERN, UpiPayComponent } from './upi-pay.component';

const order = { id: 5, order_number: 'MG261011-00005', grand_total: 1180, status: 'pending', payment_status: 'pending' } as unknown as Order;
const base: UpiSession = {
  status: 'pending', message: null, flow: 'intent', vpa: null, payer_name: null, expires_at: new Date(Date.now() + 600_000).toISOString(),
  expires_in: 600, amount: 1180, payee: 'MotoGears', demo: true, transaction_id: null,
};

function setup(overrides: Partial<Record<keyof UpiPaymentService, unknown>> = {}) {
  const api = {
    status: vi.fn(() => of({ ...base, status: 'idle', flow: null })),
    qr: vi.fn(() => of({ ...base, qr: 'data:image/svg+xml;base64,PHN2Zy8+', link: 'upi://pay?pa=motogears@demo&am=1180.00' })),
    collect: vi.fn(() => of({ ...base, flow: 'collect', vpa: 'as**@okaxis', payer_name: 'Asha' })),
    simulate: vi.fn(() => of(base)),
    ...overrides,
  };
  TestBed.configureTestingModule({ providers: [{ provide: UpiPaymentService, useValue: api }] });
  const fixture = TestBed.createComponent(UpiPayComponent);
  fixture.componentRef.setInput('order', order);
  fixture.detectChanges();
  return { fixture, api, el: fixture.nativeElement as HTMLElement };
}

describe('UpiPayComponent', () => {
  it('accepts common UPI IDs and rejects malformed ones', () => {
    for (const ok of ['asha@okaxis', '9876543210@ybl', 'a.b-c_d@oksbi', 'name@okhdfcbank']) expect(UPI_ID_PATTERN.test(ok)).toBe(true);
    for (const bad of ['asha', '@ybl', 'a@', 'as ha@ybl', 'a@1bank']) expect(UPI_ID_PATTERN.test(bad)).toBe(false);
  });

  it('shows a QR code straight away and waits for the payment', () => {
    const { el, api } = setup();
    expect(api.qr).toHaveBeenCalledTimes(1);
    expect(el.querySelector('img')?.getAttribute('src')).toContain('data:image/svg+xml');
    expect(el.textContent).toContain('Waiting for payment');
    expect(el.textContent).toContain('Simulate payment'); // demo mode
  });

  it('sends a request to a UPI ID and validates it first', () => {
    const { fixture, el, api } = setup();
    (el.querySelectorAll('[role=tab]')[1] as HTMLButtonElement).click();
    fixture.detectChanges();
    const input = el.querySelector('#upi-vpa') as HTMLInputElement;
    input.value = 'not-an-id';
    input.dispatchEvent(new Event('input'));
    (el.querySelector('form') as HTMLFormElement).dispatchEvent(new Event('submit'));
    fixture.detectChanges();
    expect(api.collect).toHaveBeenCalledTimes(0);
    expect(el.textContent).toContain('Enter a valid UPI ID');

    input.value = ' asha@okaxis ';
    input.dispatchEvent(new Event('input'));
    (el.querySelector('form') as HTMLFormElement).dispatchEvent(new Event('submit'));
    fixture.detectChanges();
    expect(api.collect).toHaveBeenCalledWith({ id: 5, number: 'MG261011-00005', token: null }, 'asha@okaxis');
    expect(el.textContent).toContain('Request sent to as**@okaxis');
  });

  it('offers the Razorpay window when in-page UPI is unavailable', () => {
    const err = new HttpErrorResponse({ status: 503, error: { message: 'UPI payments are temporarily unavailable.', fallback: 'razorpay' } });
    const { fixture, el } = setup({ qr: vi.fn(() => throwError(() => err)) });
    let upiOnly: boolean | null = null;
    fixture.componentInstance.useRazorpay.subscribe((v) => (upiOnly = v));
    fixture.detectChanges();
    expect(el.textContent).toContain('UPI payments are temporarily unavailable.');
    const btn = [...el.querySelectorAll('button')].find((b) => b.textContent?.includes('Pay by UPI with Razorpay')) as HTMLButtonElement;
    btn.click();
    expect(upiOnly).toBe(true);
  });

  it('with Razorpay, "UPI ID / app" opens the Razorpay window with only UPI', () => {
    const live = { ...base, demo: false, collect: false };
    const { fixture, el, api } = setup({
      status: vi.fn(() => of({ ...live, status: 'idle', flow: null })),
      qr: vi.fn(() => of({ ...live, qr: 'data:image/svg+xml;base64,PHN2Zy8+', link: null })),
    });
    let upiOnly: boolean | null = null;
    fixture.componentInstance.useRazorpay.subscribe((v) => (upiOnly = v));
    expect(el.textContent).not.toContain('Simulate payment');
    expect(el.textContent).not.toContain('Open UPI app to pay'); // QR-code links aren't opened as app intents
    expect(el.textContent).toContain('Pay with a UPI app'); // phones: Razorpay's UPI app buttons

    const tab = el.querySelectorAll('[role=tab]')[1] as HTMLButtonElement;
    expect(tab.textContent).toContain('UPI ID / app');
    tab.click();
    fixture.detectChanges();
    expect(el.querySelector('#upi-vpa')).toBeNull();
    const btn = [...el.querySelectorAll('button')].find((b) => b.textContent?.includes('Continue to pay')) as HTMLButtonElement;
    btn.click();
    expect(upiOnly).toBe(true);
    expect(api.collect).not.toHaveBeenCalled();
  });

  it('announces a paid order', async () => {
    const paidOrder = { ...order, status: 'confirmed', payment_status: 'paid' } as Order;
    const { fixture, el } = setup({ status: vi.fn(() => of({ ...base, status: 'paid', order: paidOrder })) });
    let paid: Order | null = null;
    fixture.componentInstance.paid.subscribe((o) => (paid = o));
    fixture.detectChanges();
    expect(el.textContent).toContain('Payment received');
    await new Promise((r) => setTimeout(r, 1000));
    expect(paid).toEqual(paidOrder);
  });
});
