import { DOCUMENT, Injectable, inject } from '@angular/core';
import { Observable, firstValueFrom, map } from 'rxjs';
import { ApiService } from '../api/api.service';
import { silentNoRedirect, withOrderToken } from '../api/http-context';
import { Order, PlaceOrderResult, RazorpayCheckoutOptions } from '../models/api.models';

export const RAZORPAY_SCRIPT = 'https://checkout.razorpay.com/v1/checkout.js';

/** Result of one Razorpay checkout session. */
export type RazorpayOutcome =
  | { status: 'paid'; order: Order }
  | { status: 'failed'; order: Order | null; message: string }
  | { status: 'dismissed' };

interface RazorpaySuccess { razorpay_payment_id: string; razorpay_order_id: string; razorpay_signature: string }
interface RazorpayFailure { error?: { code?: string; description?: string; reason?: string } }
interface RazorpayInstance { open(): void; on(event: 'payment.failed', cb: (r: RazorpayFailure) => void): void }
type RazorpayCtor = new (options: Record<string, unknown>) => RazorpayInstance;

/**
 * Checkout display config that leaves only UPI: Razorpay's own UPI QR on computers, UPI app
 * buttons (Google Pay, PhonePe…) on phones. Hiding the other methods works on both; a custom
 * UPI-only block finds "no appropriate payment method" on desktop.
 */
export const UPI_ONLY = {
  display: {
    hide: ['card', 'netbanking', 'wallet', 'emi', 'cardless_emi', 'paylater'].map((method) => ({ method })),
    preferences: { show_default_blocks: true },
  },
};

/**
 * Opens Razorpay Standard Checkout for an order Laravel already created on Razorpay.
 * The browser only relays Razorpay's signed response; Laravel verifies the HMAC signature,
 * amount and order before marking anything paid. No keys other than the public key_id
 * ever reach the browser.
 */
@Injectable({ providedIn: 'root' })
export class RazorpayService {
  private readonly api = inject(ApiService);
  private readonly doc = inject(DOCUMENT);
  private loader?: Promise<RazorpayCtor>;

  /** Lazily injects checkout.js once per page load. */
  load(): Promise<RazorpayCtor> {
    const w = this.doc.defaultView as (Window & { Razorpay?: RazorpayCtor }) | null;
    if (w?.Razorpay) return Promise.resolve(w.Razorpay);
    this.loader ??= new Promise<RazorpayCtor>((resolve, reject) => {
      const s = this.doc.createElement('script');
      s.src = RAZORPAY_SCRIPT;
      s.async = true;
      s.onload = () => (w?.Razorpay ? resolve(w.Razorpay) : reject(new Error('Razorpay failed to initialise')));
      s.onerror = () => {
        this.loader = undefined;
        reject(new Error('Could not load Razorpay. Check your connection or disable ad blockers and try again.'));
      };
      this.doc.head.appendChild(s);
    });
    return this.loader;
  }

  /**
   * Opens the popup and resolves once the payment is verified, failed for good, or the popup is closed.
   * `guest` (order number + secret token) routes verification through the guest order API.
   * `upiOnly` shows only UPI in the window (UPI apps on phones, QR / UPI ID where Razorpay offers it).
   */
  async checkout(orderId: number, options: RazorpayCheckoutOptions, guest?: { number: string; token: string }, upiOnly = false): Promise<RazorpayOutcome> {
    const Razorpay = await this.load();
    return new Promise<RazorpayOutcome>((resolve) => {
      let settled = false;
      const finish = (o: RazorpayOutcome) => {
        if (!settled) {
          settled = true;
          resolve(o);
        }
      };
      const rzp = new Razorpay({
        key: options.key,
        order_id: options.order_id,
        amount: options.amount,
        currency: options.currency,
        name: options.name,
        description: options.description,
        prefill: options.prefill,
        image: options.image,
        notes: options.notes,
        theme: options.theme,
        ...(upiOnly ? { config: UPI_ONLY } : {}),
        retry: { enabled: true },
        handler: (res: RazorpaySuccess) => {
          firstValueFrom(this.verify(orderId, res, guest))
            .then((r) => finish(r.payment_successful ? { status: 'paid', order: r.order } : { status: 'failed', order: r.order, message: r.payment.failure_reason ?? 'Payment failed' }))
            .catch((e: { error?: { message?: string } }) =>
              finish({ status: 'failed', order: null, message: e?.error?.message ?? 'We could not confirm your payment yet. If money was deducted, the order will update automatically.' }),
            );
        },
        modal: {
          confirm_close: true,
          ondismiss: () => finish({ status: 'dismissed' }),
        },
      });
      // Razorpay keeps the popup open so the customer can try another method; just record the attempt.
      rzp.on('payment.failed', (r) => {
        this.reportFailure(orderId, options.order_id, r.error?.description ?? r.error?.reason, guest).subscribe({ error: () => undefined });
      });
      rzp.open();
    });
  }

  verify(orderId: number, res: RazorpaySuccess, guest?: { number: string; token: string }): Observable<PlaceOrderResult> {
    const [path, ctx] = guest
      ? [`guest/orders/${encodeURIComponent(guest.number)}/razorpay/verify`, withOrderToken(guest.token)]
      : [`orders/${orderId}/razorpay/verify`, silentNoRedirect()];
    return this.api.post<PlaceOrderResult>(path, res, ctx).pipe(map((r) => r.data));
  }

  reportFailure(orderId: number, razorpayOrderId: string, reason?: string, guest?: { number: string; token: string }): Observable<unknown> {
    const [path, ctx] = guest
      ? [`guest/orders/${encodeURIComponent(guest.number)}/razorpay/failed`, withOrderToken(guest.token)]
      : [`orders/${orderId}/razorpay/failed`, silentNoRedirect()];
    return this.api.post(path, { razorpay_order_id: razorpayOrderId, reason: reason ?? null }, ctx);
  }
}
