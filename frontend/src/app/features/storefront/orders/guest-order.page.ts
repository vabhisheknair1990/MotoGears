import { ChangeDetectionStrategy, Component, computed, inject, input, signal } from '@angular/core';
import { takeUntilDestroyed, toObservable } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { catchError, combineLatest, of, switchMap } from 'rxjs';
import { Order } from '../../../core/models/api.models';
import { GuestOrdersService } from '../../../core/services/guest-orders.service';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { errorMessage } from '../../../core/utils/http-errors';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { GuestAccountCardComponent } from '../../../shared/components/guest-account-card.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { ModalComponent } from '../../../shared/components/modal.component';
import { OrderTimelineComponent } from '../../../shared/components/order-timeline.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge.component';
import { AppDatePipe, InrPipe } from '../../../shared/pipes/inr.pipe';

/**
 * A guest order, opened with the secret link from the confirmation email (/order/:number?token=…)
 * or from Track order. Same information and actions as "My orders", without an account.
 */
@Component({
  selector: 'app-guest-order-page',
  imports: [RouterLink, FormsModule, IconComponent, StatusBadgeComponent, OrderTimelineComponent, ModalComponent, EmptyStateComponent, GuestAccountCardComponent, InrPipe, AppDatePipe],
  templateUrl: './guest-order.page.html',
  styleUrls: ['../account/order-detail.page.css', './guest-order.page.css'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class GuestOrderPage {
  private readonly guestOrders = inject(GuestOrdersService);
  private readonly toast = inject(ToastService);
  private readonly seo = inject(SeoService);
  readonly orderNumber = input.required<string>();
  readonly tokenParam = input<string | undefined>(undefined, { alias: 'token' });

  /** Token from the link, or one remembered from an order placed on this device. */
  protected readonly token = computed(() => this.tokenParam() || this.guestOrders.tokenFor(this.orderNumber()) || null);
  protected readonly order = signal<Order | null>(null);
  protected readonly loading = signal(true);
  protected readonly cancelOpen = signal(false);
  protected readonly cancelling = signal(false);
  protected readonly downloading = signal(false);
  protected cancelReason = '';

  constructor() {
    this.seo.set({ title: 'Your order' });
    combineLatest([toObservable(this.orderNumber), toObservable(this.token)]).pipe(
      switchMap(([n, t]) => (t ? this.guestOrders.get(n, t).pipe(catchError(() => of(null))) : of(null))),
      takeUntilDestroyed(),
    ).subscribe((o) => {
      this.order.set(o);
      this.loading.set(false);
      const t = this.token();
      if (o && t && o.is_guest) {
        this.guestOrders.remember({ number: o.order_number, token: t, email: o.customer?.email, placed_at: o.placed_at, total: o.grand_total });
      }
    });
  }

  cancel(): void {
    const o = this.order();
    const t = this.token();
    if (!o || !t) return;
    this.cancelling.set(true);
    this.guestOrders.cancel(o.order_number, t, this.cancelReason || undefined).subscribe({
      next: (updated) => {
        this.order.set(updated);
        this.cancelling.set(false);
        this.cancelOpen.set(false);
        this.toast.success('Order cancelled' + (updated.payment_status === 'refunded' ? ' — refund initiated' : ''));
      },
      error: (e) => { this.cancelling.set(false); this.toast.error(errorMessage(e)); },
    });
  }

  download(): void {
    const o = this.order();
    const t = this.token();
    if (!o || !t) return;
    this.downloading.set(true);
    this.guestOrders.downloadInvoice(o.order_number, t).subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `invoice-${o.order_number}.html`;
        a.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
        this.downloading.set(false);
      },
      error: (e) => { this.downloading.set(false); this.toast.error(errorMessage(e)); },
    });
  }
}
