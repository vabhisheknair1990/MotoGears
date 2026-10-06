import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { PageMeta, Review } from '../../../core/models/api.models';
import { ConfirmService } from '../../../core/services/confirm.service';
import { ReviewService } from '../../../core/services/review.service';
import { ToastService } from '../../../core/services/toast.service';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { PaginationComponent } from '../../../shared/components/pagination.component';
import { RatingComponent } from '../../../shared/components/rating.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge.component';
import { AppDatePipe } from '../../../shared/pipes/inr.pipe';

@Component({
  selector: 'app-my-reviews-page',
  imports: [RouterLink, RatingComponent, StatusBadgeComponent, PaginationComponent, EmptyStateComponent, AppDatePipe],
  template: `
    <h1 class="h1">My reviews</h1>
    <div class="card">
      @for (r of items(); track r.id) {
        <div class="rv">
          @if (r.product) { <a [routerLink]="['/products', r.product.slug]"><img [src]="r.product.image" class="img" alt="" /></a> }
          <div class="grow">
            @if (r.product) { <a [routerLink]="['/products', r.product.slug]" class="fw-600">{{ r.product.name }}</a> }
            <div class="row gap-8"><app-rating [value]="r.rating" /><app-status-badge [status]="r.status" /></div>
            @if (r.title) { <strong class="text-sm">{{ r.title }}</strong> }
            <p class="text-sm">{{ r.comment }}</p>
            <span class="text-xs text-muted">{{ r.created_at | appDate }}</span>
          </div>
          <button class="btn btn-sm btn-ghost" (click)="remove(r)">Delete</button>
        </div>
      } @empty {
        <app-empty-state icon="star" title="No reviews yet" message="Review products from your delivered orders to help other riders and drivers."><a class="btn btn-primary" routerLink="/account/orders">Go to my orders</a></app-empty-state>
      }
    </div>
    <app-pagination [meta]="meta()" (pageChange)="load($event)" />
  `,
  styles: `
    .h1 { font-size: 28px; margin-bottom: 16px; }
    .rv { display: flex; gap: 14px; padding: 16px 18px; border-bottom: 1px solid var(--line-2); align-items: flex-start; }
    .rv .grow { display: flex; flex-direction: column; gap: 4px; }
    .rv p { margin: 0; color: var(--ink-2); }
    .img { width: 64px; height: 64px; border-radius: var(--radius-sm); object-fit: cover; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MyReviewsPage {
  private readonly reviews = inject(ReviewService);
  private readonly toast = inject(ToastService);
  private readonly confirm = inject(ConfirmService);
  protected readonly items = signal<Review[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);

  constructor() {
    this.load(1);
  }

  load(page: number): void {
    this.reviews.getMine(page).subscribe((p) => { this.items.set(p.items); this.meta.set(p.meta); });
  }

  async remove(r: Review): Promise<void> {
    if (!(await this.confirm.ask({ title: 'Delete review?', message: 'This cannot be undone.', confirmLabel: 'Delete', danger: true }))) return;
    this.reviews.deleteMine(r.id).subscribe(() => { this.toast.info('Review deleted'); this.load(this.meta()?.current_page ?? 1); });
  }
}
