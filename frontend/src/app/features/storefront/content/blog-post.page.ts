import { ChangeDetectionStrategy, Component, effect, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { BlogPost } from '../../../core/models/api.models';
import { CmsService } from '../../../core/services/cms.service';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { AppDatePipe } from '../../../shared/pipes/inr.pipe';
import { BreadcrumbComponent } from '../../../shared/components/breadcrumb.component';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { SkeletonComponent } from '../../../shared/components/skeleton.component';

@Component({
  selector: 'app-blog-post-page',
  imports: [RouterLink, AppDatePipe, BreadcrumbComponent, EmptyStateComponent, IconComponent, SkeletonComponent],
  template: `
    <div class="container narrow">
      @if (state() === 'loading') {
        <div class="page-title"><div class="skeleton" style="height:40px;width:70%"></div></div>
        <div class="skeleton" style="aspect-ratio:16/9;margin-bottom:24px"></div>
        <app-skeleton [count]="10" />
      } @else if (post(); as p) {
        <div class="page-title">
          <app-breadcrumb [items]="[{ label: 'Home', link: '/' }, { label: 'Blog', link: '/blog' }, { label: p.title }]" />
        </div>
        <article>
          <header>
            @if (p.category) { <a class="badge badge-brand" [routerLink]="['/blog']" [queryParams]="{ category: p.category.slug }">{{ p.category.name }}</a> }
            <h1>{{ p.title }}</h1>
            <div class="meta">
              @if (p.author) { <span><app-icon name="user" [size]="15" /> {{ p.author }}</span> }
              <span><app-icon name="calendar" [size]="15" /> {{ p.published_at | appDate }}</span>
              <span><app-icon name="clock" [size]="15" /> {{ p.reading_minutes }} min read</span>
              <button class="btn btn-sm btn-ghost share" type="button" (click)="share(p)"><app-icon name="share" [size]="15" /> Share</button>
            </div>
          </header>
          @if (p.cover_image) { <img class="cover" [src]="p.cover_image" [alt]="p.title" /> }
          <!-- Admin-authored HTML, sanitized by Angular. -->
          <div class="prose" [innerHTML]="p.content"></div>
          @if (p.tags?.length) {
            <div class="tags">
              @for (t of p.tags; track t.id) { <a class="chip" [routerLink]="['/blog']" [queryParams]="{ tag: t.slug }">#{{ t.name }}</a> }
            </div>
          }
        </article>
        <div class="card card-body cta">
          <div><strong>Find parts that fit your vehicle</strong><p class="text-muted text-sm mb-0">Select your make, model and variant to see guaranteed-fit parts.</p></div>
          <a routerLink="/shop" class="btn btn-primary">Shop parts</a>
        </div>
        @if (p.related?.length) {
          <section class="related">
            <h2>Related articles</h2>
            <div class="rel-grid">
              @for (r of p.related; track r.id) {
                <a class="card rel" [routerLink]="['/blog', r.slug]">
                  @if (r.cover_image) { <img [src]="r.cover_image" [alt]="r.title" loading="lazy" /> }
                  <div class="card-body"><strong class="clamp-2">{{ r.title }}</strong><span class="text-xs text-muted">{{ r.published_at | appDate }} · {{ r.reading_minutes }} min</span></div>
                </a>
              }
            </div>
          </section>
        }
      } @else {
        <app-empty-state icon="file" title="Article not found" message="This article may have been unpublished or moved.">
          <a routerLink="/blog" class="btn btn-primary">Back to blog</a>
        </app-empty-state>
      }
    </div>
  `,
  styles: `
    .narrow { max-width: 820px; padding-bottom: 56px; }
    header h1 { font-size: clamp(28px, 4vw, 40px); line-height: 1.15; margin: 12px 0; }
    .meta { display: flex; flex-wrap: wrap; gap: 16px; align-items: center; color: var(--muted); font-size: 13.5px; margin-bottom: 20px; }
    .meta span { display: inline-flex; gap: 6px; align-items: center; }
    .share { margin-left: auto; }
    .cover { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; border-radius: var(--radius); margin-bottom: 24px; }
    .prose { font-size: 16.5px; line-height: 1.75; }
    .tags { display: flex; flex-wrap: wrap; gap: 8px; margin: 24px 0; }
    .cta { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin: 24px 0; background: var(--line-2); }
    .related h2 { font-size: 20px; }
    .rel-grid { display: grid; gap: 16px; grid-template-columns: 1fr; }
    @media (min-width: 720px) { .rel-grid { grid-template-columns: repeat(3, 1fr); } }
    .rel { overflow: hidden; display: flex; flex-direction: column; }
    .rel img { aspect-ratio: 16 / 9; object-fit: cover; width: 100%; }
    .rel .card-body { display: flex; flex-direction: column; gap: 6px; }
    .rel:hover strong { color: var(--brand); }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BlogPostPage {
  readonly slug = input.required<string>();
  private readonly cms = inject(CmsService);
  private readonly seo = inject(SeoService);
  private readonly toast = inject(ToastService);
  protected readonly post = signal<BlogPost | null>(null);
  protected readonly state = signal<'loading' | 'ready' | 'missing'>('loading');

  constructor() {
    effect((onCleanup) => {
      const slug = this.slug();
      this.state.set('loading');
      const sub = this.cms.getBlogPost(slug).subscribe({
        next: (p) => {
          this.post.set(p);
          this.state.set('ready');
          this.seo.set({ title: p.meta_title || p.title, description: p.meta_description ?? p.excerpt, image: p.cover_image, type: 'article' });
        },
        error: () => {
          this.post.set(null);
          this.state.set('missing');
          this.seo.set({ title: 'Article not found' });
        },
      });
      onCleanup(() => sub.unsubscribe());
    });
  }

  protected async share(p: BlogPost): Promise<void> {
    const url = location.href;
    try {
      if (navigator.share) {
        await navigator.share({ title: p.title, url });
      } else {
        await navigator.clipboard.writeText(url);
        this.toast.success('Link copied to clipboard');
      }
    } catch {
      /* user cancelled the share sheet */
    }
  }
}
