import { Injectable, inject } from '@angular/core';
import { Observable, finalize, map, tap } from 'rxjs';
import { ApiService } from '../api/api.service';
import { silent } from '../api/http-context';
import { ApiEnvelope, Cart } from '../models/api.models';
import { CartStore } from '../state/cart.store';
import { storage } from '../utils/storage';

export const CART_TOKEN_KEY = 'mg.cart';

@Injectable({ providedIn: 'root' })
export class CartService {
  private readonly api = inject(ApiService);
  private readonly store = inject(CartStore);

  guestToken(): string | null {
    return storage.get(CART_TOKEN_KEY);
  }

  rememberGuestToken(token: string | null | undefined): void {
    if (token) {
      storage.set(CART_TOKEN_KEY, token);
    }
  }

  clearGuestToken(): void {
    storage.set(CART_TOKEN_KEY, null);
  }

  load(): void {
    this.store.loading.set(true);
    this.api.get<Cart>('cart', undefined, silent()).pipe(finalize(() => this.store.loading.set(false))).subscribe({
      next: (cart) => this.apply(cart),
      error: () => this.store.cart.set(null),
    });
  }

  add(productId: number, quantity = 1): Observable<ApiEnvelope<Cart>> {
    this.store.setPending(productId, true);
    return this.api.post<Cart>('cart/items', { product_id: productId, quantity }).pipe(
      tap((r) => this.apply(r.data)),
      finalize(() => this.store.setPending(productId, false)),
    );
  }

  update(itemId: number, quantity: number): Observable<Cart> {
    return this.api.patch<Cart>(`cart/items/${itemId}`, { quantity }).pipe(map((r) => this.apply(r.data)));
  }

  remove(itemId: number): Observable<Cart> {
    return this.api.delete<Cart>(`cart/items/${itemId}`).pipe(map((r) => this.apply(r.data)));
  }

  clear(): Observable<Cart> {
    return this.api.delete<Cart>('cart').pipe(map((r) => this.apply(r.data)));
  }

  applyCoupon(code: string): Observable<ApiEnvelope<Cart>> {
    return this.api.post<Cart>('cart/apply-coupon', { code }, silent()).pipe(tap((r) => this.apply(r.data)));
  }

  removeCoupon(): Observable<Cart> {
    return this.api.delete<Cart>('cart/coupon').pipe(map((r) => this.apply(r.data)));
  }

  setShipping(method: 'standard' | 'express'): Observable<Cart> {
    return this.api.put<Cart>('cart/shipping', { shipping_method: method }).pipe(map((r) => this.apply(r.data)));
  }

  /** Called after checkout — the server already emptied the cart. */
  reset(): void {
    this.store.cart.set(null);
  }

  private apply(cart: Cart): Cart {
    this.rememberGuestToken(cart.token);
    this.store.cart.set(cart);
    return cart;
  }
}
