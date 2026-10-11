import { Injectable, inject } from '@angular/core';
import { Observable, map, tap } from 'rxjs';
import { ApiService } from '../api/api.service';
import { silent } from '../api/http-context';
import { AddressInput, CheckoutSummary, PaymentCode, PlaceOrderResult } from '../models/api.models';
import { CartService } from './cart.service';

export interface PlaceOrderInput {
  shipping_address_id?: number | null;
  shipping_address?: AddressInput | null;
  save_address?: boolean;
  billing_same_as_shipping?: boolean;
  shipping_method: 'standard' | 'express';
  payment_method: PaymentCode;
  payment_details?: Record<string, string> | null;
  notes?: string | null;
}

/** Guest checkout adds contact details; the address is always typed in. */
export interface GuestOrderInput extends Omit<PlaceOrderInput, 'shipping_address_id' | 'save_address'> {
  email: string;
  shipping_address: AddressInput;
}

@Injectable({ providedIn: 'root' })
export class CheckoutService {
  private readonly api = inject(ApiService);
  private readonly cart = inject(CartService);

  getSummary(shippingMethod?: 'standard' | 'express', guest = false): Observable<CheckoutSummary> {
    return this.api.get<CheckoutSummary>(guest ? 'guest/checkout' : 'checkout', { shipping_method: shippingMethod }, guest ? silent() : undefined);
  }

  /** Prices, stock and coupon are re-validated by Laravel; only choices are sent. */
  placeOrder(input: PlaceOrderInput): Observable<{ message: string; result: PlaceOrderResult }> {
    return this.api.post<PlaceOrderResult>('orders', input, silent()).pipe(
      tap(() => this.cart.reset()),
      map((r) => ({ message: r.message, result: r.data })),
    );
  }

  /** Guest checkout — the cart comes from the guest cart token. */
  placeGuestOrder(input: GuestOrderInput): Observable<{ message: string; result: PlaceOrderResult }> {
    return this.api.post<PlaceOrderResult>('guest/orders', input, silent()).pipe(
      tap(() => this.cart.reset()),
      map((r) => ({ message: r.message, result: r.data })),
    );
  }
}
