import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, input, OnInit, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { GuestOrdersService } from '../../../core/services/guest-orders.service';
import { SeoService } from '../../../core/services/seo.service';
import { AuthStore } from '../../../core/state/auth.store';
import { errorMessage } from '../../../core/utils/http-errors';
import { IconComponent } from '../../../shared/components/icon.component';
import { AppDatePipe, InrPipe } from '../../../shared/pipes/inr.pipe';

/** Find an order with its number + the email used at checkout (no account needed). */
@Component({
  selector: 'app-track-order-page',
  imports: [ReactiveFormsModule, RouterLink, IconComponent, InrPipe, AppDatePipe],
  template: `
    <div class="container track">
      <div class="card card-body box">
        <span class="ic"><app-icon name="package" [size]="26" /></span>
        <h1>Track your order</h1>
        <p class="text-muted">Enter your order number (e.g. MG261011-48213) and the email you used at checkout.</p>
        <form [formGroup]="form" (ngSubmit)="submit()" class="stack gap-16" novalidate>
          <div class="field">
            <label class="label" for="t-num">Order number</label>
            <input id="t-num" class="input mono" formControlName="order_number" placeholder="MG261011-48213" autocomplete="off" autocapitalize="characters"
                   [class.invalid]="form.controls.order_number.invalid && form.controls.order_number.touched" />
          </div>
          <div class="field">
            <label class="label" for="t-email">Email address</label>
            <input id="t-email" class="input" type="email" formControlName="email" autocomplete="email" placeholder="you@example.com"
                   [class.invalid]="form.controls.email.invalid && form.controls.email.touched" />
          </div>
          @if (error()) {
            <div class="alert" [class.alert-danger]="!needsLogin()" [class.alert-info]="needsLogin()" role="alert">
              <app-icon [name]="needsLogin() ? 'info' : 'alert'" [size]="18" />
              <span>{{ error() }} @if (needsLogin()) { <a class="link" routerLink="/login" [queryParams]="{ returnUrl: '/account/orders' }">Log in</a> }</span>
            </div>
          }
          <button class="btn btn-primary btn-lg btn-block" [disabled]="busy()">@if (busy()) { <span class="spinner"></span> } Find my order</button>
        </form>
        @if (auth.isLoggedIn()) {
          <p class="text-sm text-muted mt-16 mb-0">Orders placed while logged in are in <a class="link" routerLink="/account/orders">My orders</a>.</p>
        } @else {
          <p class="text-sm text-muted mt-16 mb-0">Have an account? <a class="link" routerLink="/login" [queryParams]="{ returnUrl: '/account/orders' }">Log in</a> to see all your orders.</p>
        }
      </div>

      @if (guestOrders.remembered().length) {
        <section class="card recent">
          <div class="card-head"><h2>Orders placed on this device</h2></div>
          <ul class="list-reset">
            @for (r of guestOrders.remembered(); track r.number) {
              <li>
                <a class="row between gap-12" [routerLink]="['/order', r.number]" [queryParams]="{ token: r.token }">
                  <span><span class="mono fw-600">{{ r.number }}</span>@if (r.placed_at) { <span class="text-sm text-muted"> · {{ r.placed_at | appDate }}</span> }</span>
                  <span class="row gap-8">@if (r.total) { <strong>{{ r.total | inr }}</strong> }<app-icon name="chevron-right" [size]="16" /></span>
                </a>
              </li>
            }
          </ul>
        </section>
      }
    </div>
  `,
  styles: `
    .track { max-width: 520px; padding-top: 32px; padding-bottom: 48px; display: grid; gap: 20px; }
    .box { text-align: left; }
    .box h1 { font-size: clamp(1.5rem, 1.2rem + 1vw, 2rem); margin: 10px 0 4px; }
    .ic { width: 52px; height: 52px; border-radius: 50%; display: grid; place-items: center; background: var(--brand-50); color: var(--brand); }
    .recent h2 { font-size: 16px; margin: 0; }
    .recent li a { padding: 12px 20px; border-top: 1px solid var(--line); color: var(--ink); }
    .recent li:first-child a { border-top: 0; }
    .recent li a:hover { background: var(--surface-2, #f7f7f8); }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TrackOrderPage implements OnInit {
  protected readonly guestOrders = inject(GuestOrdersService);
  protected readonly auth = inject(AuthStore);
  private readonly router = inject(Router);
  readonly orderParam = input<string | undefined>(undefined, { alias: 'order' });

  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly needsLogin = signal(false);
  protected readonly form = inject(FormBuilder).nonNullable.group({
    order_number: ['', [Validators.required, Validators.maxLength(30)]],
    email: ['', [Validators.required, Validators.email]],
  });

  constructor() {
    inject(SeoService).set({ title: 'Track order', description: 'Track your MotoGears order with your order number and email.' });
  }

  ngOnInit(): void {
    if (this.orderParam()) this.form.patchValue({ order_number: this.orderParam()! });
  }

  submit(): void {
    this.form.markAllAsTouched();
    if (this.form.invalid) return;
    this.busy.set(true);
    this.error.set(null);
    this.needsLogin.set(false);
    const { order_number, email } = this.form.getRawValue();
    this.guestOrders.track(order_number.toUpperCase(), email).subscribe({
      next: (r) => void this.router.navigate(['/order', r.order_number], { queryParams: { token: r.token } }),
      error: (e) => {
        this.busy.set(false);
        this.needsLogin.set(e instanceof HttpErrorResponse && e.status === 409);
        this.error.set(e instanceof HttpErrorResponse && e.status === 429 ? 'Too many attempts. Please wait a minute and try again.' : errorMessage(e));
      },
    });
  }
}
