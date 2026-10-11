import { HttpContext } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ApiService } from '../api/api.service';
import { silent, withOrderToken } from '../api/http-context';
import { UpiSession } from '../models/api.models';

/** Identifies the order being paid: customers by id, guests by number + secret token. */
export interface UpiOrderRef { id: number; number: string; token?: string | null }

/**
 * In-page UPI payments. Laravel talks to the payment provider; the browser only asks it to
 * show a QR code / send a request to a UPI ID, and polls the status until it's paid.
 */
@Injectable({ providedIn: 'root' })
export class UpiPaymentService {
  private readonly api = inject(ApiService);

  /** QR code to scan with any UPI app (the same code is returned while it is still valid). */
  qr(ref: UpiOrderRef): Observable<UpiSession> {
    const [path, ctx] = this.target(ref, 'qr');
    return this.api.post<UpiSession>(path, {}, ctx).pipe(map((r) => r.data));
  }

  /** Send a payment request to the customer's UPI ID. */
  collect(ref: UpiOrderRef, vpa: string): Observable<UpiSession> {
    const [path, ctx] = this.target(ref, 'collect');
    return this.api.post<UpiSession>(path, { vpa }, ctx).pipe(map((r) => r.data));
  }

  status(ref: UpiOrderRef): Observable<UpiSession> {
    const [path, ctx] = this.target(ref, 'status');
    return this.api.get<UpiSession>(path, undefined, ctx);
  }

  /** Demo simulator only: approve or decline the pending payment. */
  simulate(ref: UpiOrderRef, outcome: 'success' | 'failure'): Observable<UpiSession> {
    const [path, ctx] = this.target(ref, 'simulate');
    return this.api.post<UpiSession>(path, { outcome }, ctx).pipe(map((r) => r.data));
  }

  private target(ref: UpiOrderRef, action: string): [string, HttpContext] {
    return ref.token
      ? [`guest/orders/${encodeURIComponent(ref.number)}/upi/${action}`, withOrderToken(ref.token)]
      : [`orders/${ref.id}/upi/${action}`, silent()];
  }
}
