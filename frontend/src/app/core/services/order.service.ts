import { HttpErrorResponse } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, catchError, map, of, throwError } from 'rxjs';
import { ApiService } from '../api/api.service';
import { silent } from '../api/http-context';
import { Invoice, OnlinePaymentCode, Order, Page, PlaceOrderResult } from '../models/api.models';

@Injectable({ providedIn: 'root' })
export class OrderService {
  private readonly api = inject(ApiService);

  getOrders(query: { page?: number; per_page?: number; status?: string | null; search?: string | null } = {}): Observable<Page<Order>> {
    return this.api.page<Order>('orders', query);
  }

  /** Accepts the numeric ID or the order number. */
  getOrder(idOrNumber: string | number): Observable<Order> {
    return this.api.get<Order>(`orders/${encodeURIComponent(String(idOrNumber))}`, undefined, silent());
  }

  cancel(id: number, reason?: string): Observable<Order> {
    return this.api.post<Order>(`orders/${id}/cancel`, { reason }).pipe(map((r) => r.data));
  }

  /** Retry payment. A declined payment comes back as HTTP 402 with the updated order. */
  pay(id: number, method: OnlinePaymentCode, details: Record<string, string> | null = null): Observable<{ ok: boolean; message: string; result: PlaceOrderResult }> {
    return this.api.post<PlaceOrderResult>(`orders/${id}/pay`, { payment_method: method, payment_details: details }, silent()).pipe(
      map((r) => ({ ok: true, message: r.message, result: r.data })),
      catchError((err: HttpErrorResponse) => {
        const body = err.error as { message?: string; data?: PlaceOrderResult } | null;
        return err.status === 402 && body?.data ? of({ ok: false, message: body.message ?? 'Payment failed', result: body.data }) : throwError(() => err);
      }),
    );
  }

  getInvoice(id: number): Observable<Invoice> {
    return this.api.get<Invoice>(`orders/${id}/invoice`);
  }

  downloadInvoice(id: number): Observable<Blob> {
    return this.api.blob(`orders/${id}/invoice/download`);
  }
}
