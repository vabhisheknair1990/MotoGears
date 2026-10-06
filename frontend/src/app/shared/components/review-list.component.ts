import { ChangeDetectionStrategy, Component, OnChanges, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Page, PageMeta, Review, ReviewSummary } from '../../core/models/api.models';
import { ReviewService } from '../../core/services/review.service';
import { ToastService } from '../../core/services/toast.service';
import { AuthStore } from '../../core/state/auth.store';
import { errorMessage, fieldErrors } from '../../core/utils/http-errors';
import { AppDatePipe } from '../pipes/inr.pipe';
import { EmptyStateComponent } from './empty-state.component';
import { IconComponent } from './icon.component';
import { PaginationComponent } from './pagination.component';
import { RatingComponent } from './rating.component';

@Component({
  selector: 'app-review-list',
  imports: [FormsModule, RouterLink, RatingComponent, PaginationComponent, EmptyStateComponent, IconComponent, AppDatePipe],
  templateUrl: './review-list.component.html',
  styleUrl: './review-list.component.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ReviewListComponent implements OnChanges {
  private readonly reviews = inject(ReviewService);
  private readonly toast = inject(ToastService);
  protected readonly auth = inject(AuthStore);

  readonly productId = input.required<number>();
  readonly productSlug = input<string>('');

  protected readonly items = signal<Review[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);
  protected readonly summary = signal<ReviewSummary | null>(null);
  protected readonly loading = signal(true);
  protected readonly sort = signal<string>('');
  protected readonly ratingFilter = signal<number | null>(null);
  protected readonly writing = signal(false);
  protected readonly submitting = signal(false);
  protected readonly formError = signal<string | null>(null);
  protected readonly errors = signal<Record<string, string[]>>({});
  protected form = { rating: 5, title: '', comment: '' };
  protected files: File[] = [];

  ngOnChanges(): void {
    this.load(1);
  }

  load(page: number): void {
    this.loading.set(true);
    this.reviews.getProductReviews(this.productId(), { page, sort: this.sort() || null, rating: this.ratingFilter() }).subscribe({
      next: (p: Page<Review, { summary: ReviewSummary }>) => {
        this.items.set(p.items);
        this.meta.set(p.meta);
        this.summary.set(p.meta.summary);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  pct(star: number): number {
    const s = this.summary();
    if (!s || !s.count) return 0;
    return Math.round(((s.distribution[String(star)] ?? 0) / s.count) * 100);
  }

  filterBy(star: number | null): void {
    this.ratingFilter.set(this.ratingFilter() === star ? null : star);
    this.load(1);
  }

  onFiles(event: Event): void {
    const list = (event.target as HTMLInputElement).files;
    this.files = list ? Array.from(list).slice(0, 4) : [];
  }

  submit(): void {
    this.submitting.set(true);
    this.formError.set(null);
    this.errors.set({});
    this.reviews.submit(this.productId(), { rating: this.form.rating, title: this.form.title || null, comment: this.form.comment }, this.files).subscribe({
      next: ({ message }) => {
        this.submitting.set(false);
        this.writing.set(false);
        this.form = { rating: 5, title: '', comment: '' };
        this.files = [];
        this.toast.success(message);
      },
      error: (e) => {
        this.submitting.set(false);
        this.errors.set(fieldErrors(e));
        this.formError.set(errorMessage(e));
      },
    });
  }
}
