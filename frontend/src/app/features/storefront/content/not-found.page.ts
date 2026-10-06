import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { SeoService } from '../../../core/services/seo.service';
import { IconComponent } from '../../../shared/components/icon.component';

@Component({
  selector: 'app-not-found-page',
  imports: [RouterLink, IconComponent],
  template: `
    <div class="container nf">
      <div class="code" aria-hidden="true">404</div>
      <h1>This road doesn't go anywhere</h1>
      <p class="text-muted">The page you're looking for was moved, removed or never existed.</p>
      <div class="row center wrap">
        <a routerLink="/" class="btn btn-primary"><app-icon name="home" [size]="18" /> Back to home</a>
        <a routerLink="/shop" class="btn btn-ghost">Browse all parts</a>
        <a routerLink="/contact" class="btn btn-ghost">Contact support</a>
      </div>
    </div>
  `,
  styles: `
    .nf { text-align: center; padding: 80px 16px 96px; }
    .code { font-family: 'Barlow Condensed', sans-serif; font-weight: 800; font-size: clamp(96px, 20vw, 180px); line-height: 1; color: var(--brand); letter-spacing: 2px; }
    h1 { margin: 8px 0; }
    p { margin-bottom: 24px; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NotFoundPage {
  constructor() {
    inject(SeoService).set({ title: 'Page not found' });
  }
}
