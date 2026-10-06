import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { takeUntilDestroyed, toObservable } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { catchError, of, switchMap } from 'rxjs';
import { Order, OrderItem } from '../../../core/models/api.models';
import { OrderService } from '../../../core/services/order.service';
import { ReviewService } from '../../../core/services/review.service';
import { ToastService } from '../../../core/services/toast.service';
import { errorMessage } from '../../../core/utils/http-errors';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { ModalComponent } from '../../../shared/components/modal.component';
import { OrderTimelineComponent } from '../../../shared/components/order-timeline.component';
import { RatingComponent } from '../../../shared/components/rating.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge.component';
import { AppDatePipe, InrPipe } from '../../../shared/pipes/inr.pipe';

@Component({
  selector: 'app-order-detail-page',
  imports: [RouterLink, FormsModule, IconComponent, StatusBadgeComponent, OrderTimelineComponent, ModalComponent, RatingComponent, EmptyStateComponent, InrPipe, AppDatePipe],
  templateUrl: './order-detail.page.html',
  styleUrl: './order-detail.page.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class OrderDetailPage {
  private readonly orders = inject(OrderService);
  private readonly reviews = inject(ReviewService);
  private readonly toast = inject(ToastService);
  readonly id = input.required<string>();

  protected readonly order = signal<Order | null>(null);
  protected readonly loading = signal(true);
  protected readonly cancelOpen = signal(false);
  protected readonly cancelling = signal(false);
  protected readonly downloading = signal(false);
  protected cancelReason = '';
  protected readonly reviewItem = signal<OrderItem | null>(null);
  protected readonly reviewBusy = signal(false);
  protected readonly reviewError = signal<string | null>(null);
  protected review = { rating: 5, title: '', comment: '' };

  constructor() {
    toObservable(this.id).pipe(
      switchMap((id) => this.orders.getOrder(id).pipe(catchError(() => of(null)))),
      takeUntilDestroyed(),
    ).subscribe((o) => { this.order.set(o); this.loading.set(false); });
  }

  cancel(): void {
    const o = this.order();
    if (!o) return;
    this.cancelling.set(true);
    this.orders.cancel(o.id, this.cancelReason || undefined).subscribe({
      next: (updated) => {
        this.order.set(updated);
        this.cancelling.set(false);
        this.cancelOpen.set(false);
        this.toast.success('Order cancelled' + (updated.payment_status === 'refunded' ? ' — refund initiated' : ''));
      },
      error: () => this.cancelling.set(false),
    });
  }

  download(): void {
    const o = this.order();
    if (!o) return;
    this.downloading.set(true);
    this.orders.downloadInvoice(o.id).subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `invoice-${o.order_number}.html`;
        a.click();
        URL.revokeObjectURL(url);
        this.downloading.set(false);
      },
      error: () => this.downloading.set(false),
    });
  }

  openReview(item: OrderItem): void {
    this.review = { rating: 5, title: '', comment: '' };
    this.reviewError.set(null);
    this.reviewItem.set(item);
  }

  submitReview(): void {
    const item = this.reviewItem();
    if (!item?.product_id) return;
    this.reviewBusy.set(true);
    this.reviewError.set(null);
    this.reviews.submit(item.product_id, { rating: this.review.rating, title: this.review.title || null, comment: this.review.comment }).subscribe({
      next: ({ message }) => {
        this.reviewBusy.set(false);
        this.reviewItem.set(null);
        this.toast.success(message);
        this.order.update((o) => (o ? { ...o, items: o.items?.map((i) => (i.id === item.id ? { ...i, reviewed: true } : i)) } : o));
      },
      error: (e) => { this.reviewBusy.set(false); this.reviewError.set(errorMessage(e)); },
    });
  }
}
