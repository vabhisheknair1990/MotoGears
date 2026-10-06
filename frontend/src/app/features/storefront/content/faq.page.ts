import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { catchError, of } from 'rxjs';
import { CmsService } from '../../../core/services/cms.service';
import { SeoService } from '../../../core/services/seo.service';
import { BreadcrumbComponent } from '../../../shared/components/breadcrumb.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { SkeletonComponent } from '../../../shared/components/skeleton.component';

@Component({
  selector: 'app-faq-page',
  imports: [RouterLink, BreadcrumbComponent, IconComponent, SkeletonComponent],
  template: `
    <div class="container narrow">
      <div class="page-title">
        <app-breadcrumb [items]="[{ label: 'Home', link: '/' }, { label: 'FAQ' }]" />
        <h1>Frequently asked questions</h1>
        <p class="text-muted mb-0">Fitment, shipping, payments, returns and warranty — answered.</p>
      </div>
      <input class="input mb-16" type="search" placeholder="Search questions…" aria-label="Search FAQs" (input)="query.set($any($event.target).value)" />
      @if (!groups()) {
        <app-skeleton variant="row" [count]="6" />
      } @else {
        @for (g of filtered(); track g.category) {
          <section class="mb-16">
            <h2 class="cat">{{ g.category }}</h2>
            <div class="card">
              @for (f of g.items; track f.id) {
                <details [open]="query().length > 1">
                  <summary>{{ f.question }}<app-icon name="chevron-down" [size]="18" /></summary>
                  <div class="answer" [innerHTML]="f.answer"></div>
                </details>
              }
            </div>
          </section>
        } @empty {
          <p class="text-muted">No questions match “{{ query() }}”. <a routerLink="/contact" class="link">Ask us directly</a>.</p>
        }
      }
      <div class="card card-body cta">
        <div><strong>Still need help?</strong><p class="text-muted text-sm mb-0">Our team can confirm fitment using your vehicle's details.</p></div>
        <a routerLink="/contact" class="btn btn-dark">Contact support</a>
      </div>
    </div>
  `,
  styles: `
    .narrow { max-width: 860px; padding-bottom: 48px; }
    .cat { font-size: 18px; margin: 0 0 8px; }
    details { border-bottom: 1px solid var(--line); }
    details:last-child { border-bottom: 0; }
    summary { list-style: none; cursor: pointer; padding: 16px 18px; font-weight: 600; display: flex; justify-content: space-between; gap: 12px; align-items: center; }
    summary::-webkit-details-marker { display: none; }
    details[open] summary app-icon { transform: rotate(180deg); }
    summary:focus-visible { outline: 2px solid var(--brand); outline-offset: -2px; }
    .answer { padding: 0 18px 16px; color: var(--ink-2); line-height: 1.65; }
    .cta { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FaqPage {
  protected readonly groups = toSignal(inject(CmsService).getFaqs().pipe(catchError(() => of([]))));
  protected readonly query = signal('');
  protected readonly filtered = computed(() => {
    const q = this.query().trim().toLowerCase();
    const groups = this.groups() ?? [];
    if (!q) return groups;
    return groups
      .map((g) => ({ ...g, items: g.items.filter((f) => (f.question + ' ' + f.answer).toLowerCase().includes(q)) }))
      .filter((g) => g.items.length > 0);
  });

  constructor() {
    inject(SeoService).set({ title: 'FAQ', description: 'Answers to common questions about fitment, delivery, payments, returns and warranty.' });
  }
}
