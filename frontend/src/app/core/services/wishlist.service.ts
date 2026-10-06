import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, map, tap } from 'rxjs';
import { ApiService } from '../api/api.service';
import { silent } from '../api/http-context';
import { Wishlist } from '../models/api.models';
import { CartService } from './cart.service';

@Injectable({ providedIn: 'root' })
export class WishlistService {
  private readonly api = inject(ApiService);
  private readonly cart = inject(CartService);

  readonly wishlist = signal<Wishlist | null>(null);
  readonly ids = computed(() => new Set(this.wishlist()?.product_ids ?? []));
  readonly count = computed(() => this.wishlist()?.count ?? 0);

  has(productId: number): boolean {
    return this.ids().has(productId);
  }

  load(): void {
    this.api.get<Wishlist>('wishlist', undefined, silent()).subscribe({ next: (w) => this.wishlist.set(w), error: () => this.wishlist.set(null) });
  }

  fetch(): Observable<Wishlist> {
    return this.api.get<Wishlist>('wishlist').pipe(tap((w) => this.wishlist.set(w)));
  }

  add(productId: number): Observable<Wishlist> {
    return this.api.post<Wishlist>('wishlist/items', { product_id: productId }).pipe(map((r) => this.set(r.data)));
  }

  remove(productId: number): Observable<Wishlist> {
    return this.api.delete<Wishlist>(`wishlist/items/${productId}`).pipe(map((r) => this.set(r.data)));
  }

  moveToCart(productId: number): Observable<Wishlist> {
    return this.api.post<Wishlist>(`wishlist/items/${productId}/move-to-cart`).pipe(
      map((r) => this.set(r.data)),
      tap(() => this.cart.load()),
    );
  }

  reset(): void {
    this.wishlist.set(null);
  }

  private set(w: Wishlist): Wishlist {
    this.wishlist.set(w);
    return w;
  }
}
