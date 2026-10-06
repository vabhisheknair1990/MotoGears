import { ChangeDetectionStrategy, Component, computed, effect, inject, input, numberAttribute, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { BlogPost, PageMeta } from '../../../core/models/api.models';
import { CmsService } from '../../../core/services/cms.service';
import { SeoService } from '../../../core/services/seo.service';
import { AppDatePipe } from '../../../shared/pipes/inr.pipe';
import { BreadcrumbComponent } from '../../../shared/components/breadcrumb.component';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { PaginationComponent } from '../../../shared/components/pagination.component';
import { SkeletonComponent } from '../../../shared/components/skeleton.component';

interface BlogCategoryCount { id: number; name: string; slug: string; posts_count: number }

@Component({
  selector: 'app-blog-list-page',
  imports: [RouterLink, AppDatePipe, BreadcrumbComponent, EmptyStateComponent, IconComponent, PaginationComponent, SkeletonComponent],
  template: `
    <div class="container">
      <div class="page-title">
        <app-breadcrumb [items]="[{ label: 'Home', link: '/' }, { label: 'Blog' }]" />
        <h1>Garage notes</h1>
        <p class="text-muted mb-0">Maintenance guides, buying advice and fitment tips from our technicians.</p>
      </div>

      <div class="toolbar">
        <div class="chips" role="list">
          <a role="listitem" class="chip" [class.active]="!category()" [routerLink]="[]" [queryParams]="{ category: null, page: null }" queryParamsHandling="merge">All</a>
          @for (c of categories(); track c.id) {
            <a role="listitem" class="chip" [class.active]="category() === c.slug" [routerLink]="[]" [queryParams]="{ category: c.slug, page: null }" queryParamsHandling="merge">{{ c.name }} <span class="text-subtle">{{ c.posts_count }}</span></a>
          }
        </div>
        <form class="input-group" (submit)="$event.preventDefault(); applySearch(q.value)">
          <input #q class="input" type="search" placeholder="Search articles…" aria-label="Search articles" [value]="search() ?? ''" />
          <button class="btn btn-dark" type="submit" aria-label="Search"><app-icon name="search" [size]="18" /></button>
        </form>
      </div>
      @if (tag()) {
        <p class="text-sm">Tagged <span class="badge badge-dark">#{{ tag() }}</span> <a class="link" [routerLink]="[]" [queryParams]="{ tag: null, page: null }" queryParamsHandling="merge">clear</a></p>
      }

      @if (loading()) {
        <div class="posts"><app-skeleton variant="card" [count]="6" /></div>
      } @else {
        <div class="posts">
          @for (p of posts(); track p.id; let first = $first) {
            <article class="card post" [class.lead]="first && pageNo() === 1 && !search() && !category()">
              <a [routerLink]="['/blog', p.slug]" class="cover">
                @if (p.cover_image) { <img [src]="p.cover_image" [alt]="p.title" loading="lazy" /> } @else { <div class="ph"><app-icon name="file" [size]="36" /></div> }
              </a>
              <div class="body">
                <div class="meta">
                  @if (p.category) { <span class="badge badge-brand">{{ p.category.name }}</span> }
                  <span>{{ p.published_at | appDate }}</span><span>·</span><span>{{ p.reading_minutes }} min read</span>
                </div>
                <h2><a [routerLink]="['/blog', p.slug]">{{ p.title }}</a></h2>
                @if (p.excerpt) { <p class="text-muted clamp-2 mb-0">{{ p.excerpt }}</p> }
              </div>
            </article>
          } @empty {
            <app-empty-state icon="file" title="No articles found" message="Try another category or search term.">
              <a routerLink="/blog" class="btn btn-ghost">View all articles</a>
            </app-empty-state>
          }
        </div>
        <app-pagination [meta]="meta()" (pageChange)="goPage($event)" />
      }
    </div>
  `,
  styles: `
    .toolbar { display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .input-group { max-width: 320px; width: 100%; }
    .posts { display: grid; gap: 20px; grid-template-columns: 1fr; margin-bottom: 8px; }
    @media (min-width: 720px) { .posts { grid-template-columns: repeat(2, 1fr); } }
    @media (min-width: 1080px) { .posts { grid-template-columns: repeat(3, 1fr); } .post.lead { grid-column: span 2; } }
    .post { overflow: hidden; display: flex; flex-direction: column; }
    .cover { display: block; aspect-ratio: 16 / 9; background: var(--line-2); overflow: hidden; }
    .cover img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s; }
    .post:hover .cover img { transform: scale(1.03); }
    .ph { height: 100%; display: grid; place-items: center; color: var(--muted); }
    .body { padding: 16px 18px 20px; }
    .meta { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; color: var(--muted); font-size: 12.5px; margin-bottom: 8px; }
    h2 { font-size: 19px; margin: 0 0 8px; line-height: 1.3; }
    .lead h2 { font-size: 24px; }
    h2 a:hover { color: var(--brand); }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BlogListPage {
  // Query params bound via withComponentInputBinding.
  readonly category = input<string | null>(null);
  readonly tag = input<string | null>(null);
  readonly search = input<string | null>(null);
  readonly page = input(1, { transform: (v: unknown) => numberAttribute(v, 1) });

  private readonly cms = inject(CmsService);
  private readonly router = inject(Router);
  protected readonly posts = signal<BlogPost[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);
  protected readonly categories = signal<BlogCategoryCount[]>([]);
  protected readonly loading = signal(true);
  protected readonly pageNo = computed(() => this.page());

  constructor() {
    inject(SeoService).set({ title: 'Blog — maintenance guides & buying advice', description: 'Car and bike maintenance guides, product comparisons and fitment tips.' });
    effect((onCleanup) => {
      const query = { page: this.page(), category: this.category(), tag: this.tag(), search: this.search() };
      this.loading.set(true);
      const sub = this.cms.getBlog(query).subscribe({
        next: (res) => {
          this.posts.set(res.items);
          this.meta.set(res.meta);
          this.categories.set(res.meta.categories ?? this.categories());
          this.loading.set(false);
        },
        error: () => {
          this.posts.set([]);
          this.meta.set(null);
          this.loading.set(false);
        },
      });
      onCleanup(() => sub.unsubscribe());
    });
  }

  protected applySearch(value: string): void {
    this.router.navigate([], { queryParams: { search: value.trim() || null, page: null }, queryParamsHandling: 'merge' });
  }

  protected goPage(page: number): void {
    this.router.navigate([], { queryParams: { page: page > 1 ? page : null }, queryParamsHandling: 'merge' });
  }
}
