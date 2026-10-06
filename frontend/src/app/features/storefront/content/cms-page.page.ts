import { ChangeDetectionStrategy, Component, effect, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { CmsPage } from '../../../core/models/api.models';
import { CmsService } from '../../../core/services/cms.service';
import { SeoService } from '../../../core/services/seo.service';
import { BreadcrumbComponent } from '../../../shared/components/breadcrumb.component';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { SkeletonComponent } from '../../../shared/components/skeleton.component';

/** Renders an admin-managed CMS page (about, terms, privacy, shipping, returns…). */
@Component({
  selector: 'app-cms-page',
  imports: [RouterLink, BreadcrumbComponent, EmptyStateComponent, SkeletonComponent],
  template: `
    <div class="container narrow">
      @if (state() === 'loading') {
        <div class="page-title"><div class="skeleton" style="height:36px;width:50%"></div></div>
        <app-skeleton [count]="8" />
      } @else if (page(); as p) {
        <div class="page-title">
          <app-breadcrumb [items]="[{ label: 'Home', link: '/' }, { label: p.title }]" />
          <h1>{{ p.title }}</h1>
        </div>
        <!-- Content is admin-authored HTML; Angular's sanitizer strips scripts and unsafe attributes. -->
        <article class="prose card card-body" [innerHTML]="p.content"></article>
      } @else {
        <app-empty-state icon="file" title="Page not found" message="The page you are looking for doesn't exist or has been unpublished.">
          <a routerLink="/" class="btn btn-primary">Back to home</a>
        </app-empty-state>
      }
    </div>
  `,
  styles: `
    .narrow { max-width: 860px; padding-bottom: 48px; }
    article { padding: 28px; line-height: 1.7; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CmsPagePage {
  readonly slug = input.required<string>();
  private readonly cms = inject(CmsService);
  private readonly seo = inject(SeoService);
  protected readonly page = signal<CmsPage | null>(null);
  protected readonly state = signal<'loading' | 'ready' | 'missing'>('loading');

  constructor() {
    effect((onCleanup) => {
      const slug = this.slug();
      this.state.set('loading');
      const sub = this.cms.getPage(slug).subscribe({
        next: (p) => {
          this.page.set(p);
          this.state.set('ready');
          this.seo.set({ title: p.meta_title || p.title, description: p.meta_description ?? p.content });
        },
        error: () => {
          this.page.set(null);
          this.state.set('missing');
          this.seo.set({ title: 'Page not found' });
        },
      });
      onCleanup(() => sub.unsubscribe());
    });
  }
}
