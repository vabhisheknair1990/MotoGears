import { Injectable, inject } from '@angular/core';
import { Observable, map, shareReplay } from 'rxjs';
import { ApiService } from '../api/api.service';
import { silent } from '../api/http-context';
import { Banner, BlogPost, CmsPage, Faq, Homepage, Page, StoreSettings } from '../models/api.models';

@Injectable({ providedIn: 'root' })
export class CmsService {
  private readonly api = inject(ApiService);
  private settings$?: Observable<StoreSettings>;

  getHomepage(vehicleVariant?: number | null): Observable<Homepage> {
    return this.api.get<Homepage>('homepage', { vehicle_variant: vehicleVariant ?? undefined });
  }

  getBanners(placement?: 'hero' | 'promo' | 'offer'): Observable<Banner[]> {
    return this.api.get<Banner[]>('banners', { placement });
  }

  getSettings(): Observable<StoreSettings> {
    return (this.settings$ ??= this.api.get<StoreSettings>('settings', undefined, silent()).pipe(shareReplay({ bufferSize: 1, refCount: false })));
  }

  getPage(slug: string): Observable<CmsPage> {
    return this.api.get<CmsPage>(`pages/${encodeURIComponent(slug)}`, undefined, silent());
  }

  getFaqs(): Observable<{ category: string; items: Faq[] }[]> {
    return this.api.get<{ category: string; items: Faq[] }[]>('faqs');
  }

  getBlog(query: { page?: number; category?: string | null; tag?: string | null; search?: string | null } = {}): Observable<Page<BlogPost, { categories: { id: number; name: string; slug: string; posts_count: number }[] }>> {
    return this.api.page('blog', query);
  }

  getBlogPost(slug: string): Observable<BlogPost> {
    return this.api.get<BlogPost>(`blog/${encodeURIComponent(slug)}`, undefined, silent());
  }

  submitContact(input: { name: string; email: string; phone?: string | null; subject: string; message: string }): Observable<string> {
    return this.api.post<null>('contact', input, silent()).pipe(map((r) => r.message));
  }

  subscribe(email: string): Observable<string> {
    return this.api.post<null>('newsletter', { email }, silent()).pipe(map((r) => r.message));
  }
}
