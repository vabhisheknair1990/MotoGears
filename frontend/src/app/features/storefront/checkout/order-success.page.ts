import { ChangeDetectionStrategy, Component, computed, inject, input, signal } from '@angular/core';
import { takeUntilDestroyed, toObservable, toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { catchError, of, switchMap } from 'rxjs';
import { OnlinePaymentCode, Order } from '../../../core/models/api.models';
import { CmsService } from '../../../core/services/cms.service';
import { RazorpayService } from '../../../core/services/razorpay.service';
import { OrderService } from '../../../core/services/order.service';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { errorMessage } from '../../../core/utils/http-errors';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { OrderTimelineComponent } from '../../../shared/components/order-timeline.component';
import { AppDatePipe, InrPipe } from '../../../shared/pipes/inr.pipe';

@Component({
  selector: 'app-order-success-page',
  imports: [RouterLink, FormsModule, IconComponent, EmptyStateComponent, OrderTimelineComponent, InrPipe, AppDatePipe],
  template: `
    <div class="container narrow">
      @if (loading()) {
        <div class="skeleton" style="height: 320px; margin-top: 32px"></div>
      } @else if (!order()) {
        <app-empty-state icon="package" title="Order not found" message="We couldn't find that order on your account."><a class="btn btn-primary" routerLink="/account/orders">My orders</a></app-empty-state>
      } @else {
        @let o = order()!;
        @if (needsPayment()) {
          <div class="hero-box failed">
            <div class="ic"><app-icon name="alert" [size]="36" /></div>
            @if (o.payment_status === 'failed') {
              @let reason = o.payment?.failure_reason;
              <h1>Payment failed</h1>
              <p>Your order <strong>{{ o.order_number }}</strong> is reserved, but the payment didn't go through{{ reason ? ': ' + reason : '.' }}</p>
            } @else {
              <h1>Complete your payment</h1>
              <p>Your order <strong>{{ o.order_number }}</strong> is reserved and waiting for payment.</p>
            }
            <p class="text-sm">Pay within 30 minutes to keep your items reserved. If money was already deducted, this page will update automatically once the bank confirms.</p>
          </div>
          <div class="card card-body retry">
            <h2>Pay {{ o.grand_total | inr }}</h2>
            <div class="row gap-8 mb-16 wrap" role="radiogroup" aria-label="Payment method">
              @for (m of methods(); track m.code) {
                <label class="chip" [class.active]="method() === m.code"><input type="radio" class="sr-only" name="m" (change)="chosen.set(m.code)" [checked]="method() === m.code" />{{ m.label }}</label>
              }
            </div>
            @if (method() === 'razorpay') {
              <p class="text-sm text-muted mb-0">Pay by UPI, card, net banking or wallet in Razorpay's secure window.</p>
            } @else if (method() === 'demo_card') {
              <div class="form-grid cols-2">
                <div class="field span-2"><label class="label" for="r-num">Card number</label><input id="r-num" class="input mono" [(ngModel)]="cardNumber" name="n" /></div>
                <div class="field"><label class="label" for="r-exp">Expiry</label><input id="r-exp" class="input mono" [(ngModel)]="expiry" name="e" /></div>
                <div class="field"><label class="label" for="r-cvv">CVV</label><input id="r-cvv" class="input mono" type="password" [(ngModel)]="cvv" name="c" /></div>
              </div>
            } @else if (method() === 'demo_upi') {
              <div class="field"><label class="label" for="r-upi">UPI ID</label><input id="r-upi" class="input" [(ngModel)]="upiId" name="u" /></div>
            }
            @if (retryError()) { <div class="alert alert-danger mt-16">{{ retryError() }}</div> }
            <div class="row end mt-16">
              <a class="btn" [routerLink]="['/account/orders', o.id]">View order</a>
              <button class="btn btn-primary" (click)="retry()" [disabled]="paying() || !method()">@if (paying()) { <span class="spinner"></span> } Pay {{ o.grand_total | inr }}</button>
            </div>
          </div>
        } @else {
          <div class="hero-box">
            <div class="ic ok"><app-icon name="check" [size]="40" [stroke]="3" /></div>
            <h1>Thank you! Your order is confirmed.</h1>
            <p>Order <strong class="mono">{{ o.order_number }}</strong> · placed {{ o.placed_at | appDate: true }}</p>
            <p class="text-sm">{{ o.payment_method === 'cod' ? 'Please keep ' + (o.grand_total | inr) + ' ready for cash on delivery.' : 'Payment received — ' + (o.payment?.transaction_id ?? '') }}</p>
          </div>
          <div class="grid md-grid-2 gap-16">
            <div class="card card-body">
              <h3>Order summary</h3>
              <ul class="list-reset items">
                @for (i of o.items ?? []; track i.id) {
                  <li><img [src]="i.image" class="thumb" alt="" /><span class="grow text-sm">{{ i.product_name }} × {{ i.quantity }}</span><strong class="text-sm">{{ i.line_total | inr }}</strong></li>
                }
              </ul>
              <div class="row between mt-8"><span>Total paid{{ o.payment_method === 'cod' ? ' on delivery' : '' }}</span><strong>{{ o.grand_total | inr }}</strong></div>
            </div>
            <div class="card card-body">
              <h3>Delivering to</h3>
              <p class="text-sm">{{ o.shipping_address.name }}<br />{{ o.shipping_address.line1 }}<br />{{ o.shipping_address.city }}, {{ o.shipping_address.state }} {{ o.shipping_address.postal_code }}</p>
              <h3 class="mt-16">Status</h3>
              <app-order-timeline [status]="o.status" [entries]="o.timeline ?? []" />
            </div>
          </div>
          <div class="row center wrap mt-24">
            <a class="btn btn-primary" [routerLink]="['/account/orders', o.id]">Track order</a>
            <a class="btn" [routerLink]="['/invoice', o.id]">View invoice</a>
            <a class="btn btn-ghost" routerLink="/shop">Continue shopping</a>
          </div>
        }
      }
    </div>
  `,
  styles: `
    .narrow { max-width: 920px; padding-top: 24px; }
    .hero-box { text-align: center; padding: 32px 16px 24px; }
    .hero-box h1 { font-size: clamp(1.6rem, 1.2rem + 1.5vw, 2.3rem); margin: 12px 0 6px; }
    .ic { width: 76px; height: 76px; border-radius: 50%; display: grid; place-items: center; margin: 0 auto; background: var(--warning-50); color: var(--warning); }
    .ic.ok { background: var(--success); color: #fff; box-shadow: 0 0 0 10px var(--success-50); }
    .failed .ic { background: var(--danger-50); color: var(--danger); }
    .retry h2 { font-size: 18px; font-family: var(--font); }
    .chip { cursor: pointer; }
    h3 { font-size: 15px; }
    .items li { display: flex; gap: 10px; align-items: center; padding: 6px 0; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class OrderSuccessPage {
  private readonly orders = inject(OrderService);
  private readonly toast = inject(ToastService);
  readonly orderNumber = input.required<string>();
  readonly paymentParam = input<string | undefined>(undefined, { alias: 'payment' });

  protected readonly order = signal<Order | null>(null);
  protected readonly loading = signal(true);
  protected readonly paying = signal(false);
  protected readonly retryError = signal<string | null>(null);
  private readonly razorpay = inject(RazorpayService);
  private readonly settings = toSignal(inject(CmsService).getSettings().pipe(catchError(() => of(null))), { initialValue: null });
  /** Online methods the store offers right now (Razorpay only when configured on the server). */
  protected readonly methods = computed(() =>
    (this.settings()?.payment_methods ?? []).filter((m): m is { code: OnlinePaymentCode; label: string; description: string } => m.code !== 'cod'),
  );
  private readonly chosen = signal<OnlinePaymentCode | null>(null);
  protected readonly method = computed<OnlinePaymentCode | null>(() => {
    const list = this.methods();
    const pick = this.chosen();
    if (pick && list.some((m) => m.code === pick)) return pick;
    const current = this.order()?.payment_method;
    return list.find((m) => m.code === current)?.code ?? list[0]?.code ?? null;
  });
  protected readonly needsPayment = computed(() => {
    const o = this.order();
    return !!o && o.status === 'pending' && o.payment_status !== 'paid' && o.payment_method !== 'cod';
  });
  protected cardNumber = '4111 1111 1111 1111';
  protected expiry = '12/29';
  protected cvv = '123';
  protected upiId = 'success@upi';

  constructor() {
    inject(SeoService).set({ title: 'Order confirmation' });
    toObservable(this.orderNumber).pipe(
      switchMap((n) => this.orders.getOrder(n).pipe(catchError(() => of(null)))),
      takeUntilDestroyed(),
    ).subscribe((o) => { this.order.set(o); this.loading.set(false); });
  }

  retry(): void {
    const o = this.order();
    const method = this.method();
    if (!o || !method) return;
    this.paying.set(true);
    this.retryError.set(null);
    const details: Record<string, string> | null = method === 'demo_card'
      ? { card_number: this.cardNumber, card_name: o.shipping_address.name, expiry: this.expiry, cvv: this.cvv }
      : method === 'demo_upi' ? { upi_id: this.upiId } : null;
    this.orders.pay(o.id, method, details).subscribe({
      next: (r) => {
        this.order.set(r.result.order);
        if (r.result.razorpay) {
          this.razorpay.checkout(o.id, r.result.razorpay).then(
            (outcome) => {
              this.paying.set(false);
              if (outcome.status === 'paid') {
                this.order.set(outcome.order);
                this.toast.success('Payment successful — order confirmed');
              } else if (outcome.status === 'failed') {
                if (outcome.order) this.order.set(outcome.order);
                this.retryError.set(outcome.message);
              }
            },
            (e: Error) => { this.paying.set(false); this.retryError.set(e.message); },
          );
          return;
        }
        this.paying.set(false);
        if (r.result.payment_successful) this.toast.success('Payment successful — order confirmed');
        else this.retryError.set(r.message);
      },
      error: (e) => { this.paying.set(false); this.retryError.set(errorMessage(e)); },
    });
  }
}
