import { DOCUMENT, Injectable, inject } from '@angular/core';
import { Meta, Title } from '@angular/platform-browser';
import { environment } from '../../../environments/environment';

/** Title, description, canonical URL and Open Graph tags per page. */
@Injectable({ providedIn: 'root' })
export class SeoService {
  private readonly title = inject(Title);
  private readonly meta = inject(Meta);
  private readonly doc = inject(DOCUMENT);

  set(opts: { title?: string | null; description?: string | null; image?: string | null; type?: string }): void {
    const title = opts.title ? `${opts.title} | ${environment.storeName}` : `${environment.storeName} — Genuine Car & Bike Parts Online`;
    this.title.setTitle(title);
    const description = (opts.description ?? 'Shop genuine car and motorcycle parts and accessories with guaranteed vehicle fitment.').replace(/<[^>]+>/g, '').slice(0, 160);
    this.meta.updateTag({ name: 'description', content: description });
    this.meta.updateTag({ property: 'og:title', content: title });
    this.meta.updateTag({ property: 'og:description', content: description });
    this.meta.updateTag({ property: 'og:type', content: opts.type ?? 'website' });
    if (opts.image) {
      this.meta.updateTag({ property: 'og:image', content: opts.image });
    }
    this.setCanonical();
  }

  private setCanonical(): void {
    const href = this.doc.location?.href?.split('?')[0];
    if (!href) return;
    let link = this.doc.head.querySelector<HTMLLinkElement>('link[rel="canonical"]');
    if (!link) {
      link = this.doc.createElement('link');
      link.rel = 'canonical';
      this.doc.head.appendChild(link);
    }
    link.href = href;
  }
}
