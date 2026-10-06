import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ApiService } from '../api/api.service';
import { silent } from '../api/http-context';
import { Page, Review, ReviewSummary } from '../models/api.models';

@Injectable({ providedIn: 'root' })
export class ReviewService {
  private readonly api = inject(ApiService);

  getProductReviews(productId: number, query: { page?: number; sort?: string | null; rating?: number | null } = {}): Observable<Page<Review, { summary: ReviewSummary }>> {
    return this.api.page<Review, { summary: ReviewSummary }>(`products/${productId}/reviews`, query);
  }

  submit(productId: number, input: { rating: number; title?: string | null; comment: string }, images: File[] = []): Observable<{ message: string; review: Review }> {
    const form = new FormData();
    form.append('rating', String(input.rating));
    if (input.title) form.append('title', input.title);
    form.append('comment', input.comment);
    images.forEach((f) => form.append('images[]', f));
    return this.api.post<Review>(`products/${productId}/reviews`, form, silent()).pipe(map((r) => ({ message: r.message, review: r.data })));
  }

  getMine(page = 1): Observable<Page<Review>> {
    return this.api.page<Review>('me/reviews', { page });
  }

  deleteMine(id: number): Observable<unknown> {
    return this.api.delete(`me/reviews/${id}`);
  }
}
