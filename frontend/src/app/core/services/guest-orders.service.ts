import { HttpErrorResponse } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { Observable, catchError, map, of, throwError } from 'rxjs';
import { ApiService } from '../api/api.service';
import { silentNoRedirect, withOrderToken } from '../api/http-context';
import { AuthPayload, Invoice, OnlinePaymentCode, Order, PlaceOrderResult } from '../models/api.models';
import { storage } from '../utils/storage';

/** A guest order this browser can open: its number and secret token. */
export interface GuestOrderRef { number: string; token: string; email?: string | null; placed_at?: string | null; total?: number | null }

export const GUEST_ORDERS_KEY = 'mg_guest_orders';
const MAX_REMEMBERED = 20;

/**
 * Orders placed without an account. Every call proves access with the order's secret token
 * (sent as X-Order-Token). Tokens of orders placed on this device are remembered so the
 * customer can reopen them from "Track order" without the email.
 */
@Injectable({ providedIn: 'root' })
export class GuestOrdersService {
  private readonly api = inject(ApiService);
  readonly remembered = signal<GuestOrderRef[]>(storage.getJson<GuestOrderRef[]>(GUEST_ORDERS_KEY) ?? []);

  remember(ref: GuestOrderRef): void {
    const list = [ref, ...this.remembered().filter((r) => r.number !== ref.number)].slice(0, MAX_REMEMBERED);
    this.remembered.set(list);
    storage.set(GUEST_ORDERS_KEY, JSON.stringify(list));
  }

  forget(number: string): void {
    const list = this.remembered().filter((r) => r.number !== number);
    this.remembered.set(list);
    storage.set(GUEST_ORDERS_KEY, list.length ? JSON.stringify(list) : null);
  }

  tokenFor(number: string): string | null {
    return this.remembered().find((r) => r.number === number)?.token ?? null;
  }

  private path(number: string, suffix = ''): string {
    return `guest/orders/${encodeURIComponent(number)}${suffix}`;
  }

  get(number: string, token: string): Observable<Order> {
    return this.api.get<Order>(this.path(number), undefined, withOrderToken(token));
  }

  cancel(number: string, token: string, reason?: string): Observable<Order> {
    return this.api.post<Order>(this.path(number, '/cancel'), { reason }, withOrderToken(token)).pipe(map((r) => r.data));
  }

  /** Retry payment. A declined payment comes back as HTTP 402 with the updated order. */
  pay(number: string, token: string, method: OnlinePaymentCode, details: Record<string, string> | null = null): Observable<{ ok: boolean; message: string; result: PlaceOrderResult }> {
    return this.api.post<PlaceOrderResult>(this.path(number, '/pay'), { payment_method: method, payment_details: details }, withOrderToken(token)).pipe(
      map((r) => ({ ok: true, message: r.message, result: r.data })),
      catchError((err: HttpErrorResponse) => {
        const body = err.error as { message?: string; data?: PlaceOrderResult } | null;
        return err.status === 402 && body?.data ? of({ ok: false, message: body.message ?? 'Payment failed', result: body.data }) : throwError(() => err);
      }),
    );
  }

  getInvoice(number: string, token: string): Observable<Invoice> {
    return this.api.get<Invoice>(this.path(number, '/invoice'), undefined, withOrderToken(token));
  }

  downloadInvoice(number: string, token: string): Observable<Blob> {
    return this.api.blob(this.path(number, '/invoice/download'), undefined, withOrderToken(token));
  }

  /** Order number + checkout email → the order's token. */
  track(orderNumber: string, email: string): Observable<{ order_number: string; token: string }> {
    return this.api.post<{ order_number: string; token: string }>('guest/orders/track', { order_number: orderNumber.trim(), email: email.trim() }, silentNoRedirect()).pipe(map((r) => r.data));
  }

  createAccount(number: string, token: string, input: { password: string; password_confirmation: string; marketing_opt_in?: boolean }): Observable<AuthPayload & { order_id: number }> {
    return this.api.post<AuthPayload & { order_id: number }>(this.path(number, '/create-account'), input, withOrderToken(token)).pipe(map((r) => r.data));
  }

  /** Logged-in customer: move this guest order into their account (emails must match). */
  attach(number: string, token: string): Observable<Order> {
    return this.api.post<Order>(this.path(number, '/attach'), {}, withOrderToken(token)).pipe(map((r) => r.data));
  }
}
