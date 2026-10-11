import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Order } from '../../core/models/api.models';
import { AuthService } from '../../core/services/auth.service';
import { GuestOrdersService } from '../../core/services/guest-orders.service';
import { ToastService } from '../../core/services/toast.service';
import { AuthStore } from '../../core/state/auth.store';
import { errorMessage, fieldErrors } from '../../core/utils/http-errors';
import { IconComponent } from './icon.component';

/**
 * Shown on guest order pages: create an account with just a password (the order moves into it),
 * or — when already logged in with the same email — add the order to "My orders".
 */
@Component({
  selector: 'app-guest-account-card',
  imports: [FormsModule, RouterLink, IconComponent],
  template: `
    <section class="card card-body gac">
      @if (auth.isLoggedIn()) {
        <div class="row gap-12 top">
          <span class="ic"><app-icon name="user" [size]="20" /></span>
          <div class="grow">
            <h3>Add this order to your account</h3>
            @if (sameEmail()) {
              <p class="text-sm text-muted">See it in My orders with your other orders, get notifications and write reviews.</p>
              <button type="button" class="btn btn-primary btn-sm" (click)="attach()" [disabled]="busy()">@if (busy()) { <span class="spinner"></span> } Add to My orders</button>
            } @else {
              <p class="text-sm text-muted mb-0">This order was placed with <strong>{{ order().customer?.email }}</strong>. Log in with that email to add it to an account.</p>
            }
          </div>
        </div>
      } @else {
        <div class="row gap-12 top">
          <span class="ic"><app-icon name="user" [size]="20" /></span>
          <div class="grow">
            <h3>Save your details for next time</h3>
            <p class="text-sm text-muted">Create a password and this order moves into your new account for <strong>{{ order().customer?.email }}</strong> — track orders, save addresses and check out faster.</p>
            <form class="form-grid cols-2" (ngSubmit)="create()" novalidate>
              <div class="field">
                <label class="label" for="gac-pw">Password</label>
                <input id="gac-pw" class="input" type="password" name="pw" [(ngModel)]="password" autocomplete="new-password" minlength="8" [class.invalid]="!!errors()['password']" />
                <span class="hint">At least 8 characters, with letters and numbers.</span>
              </div>
              <div class="field">
                <label class="label" for="gac-pw2">Confirm password</label>
                <input id="gac-pw2" class="input" type="password" name="pw2" [(ngModel)]="confirm" autocomplete="new-password" />
              </div>
              <label class="check span-2"><input type="checkbox" name="mk" [(ngModel)]="marketing" /> Email me offers and new arrivals</label>
              @if (error()) {
                <div class="alert alert-danger span-2" role="alert"><app-icon name="alert" [size]="16" />
                  <span>{{ error() }} @if (accountExists()) { <a class="link" routerLink="/login" [queryParams]="{ returnUrl: returnUrl() }">Log in</a> }</span>
                </div>
              }
              <div class="span-2"><button class="btn btn-primary" [disabled]="busy() || !password || !confirm">@if (busy()) { <span class="spinner"></span> } Create account</button></div>
            </form>
          </div>
        </div>
      }
    </section>
  `,
  styles: `
    .gac h3 { font-size: 16px; margin: 0 0 4px; }
    .top { align-items: flex-start; }
    .ic { width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center; background: var(--brand-50); color: var(--brand); flex: none; }
    form { margin-top: 12px; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class GuestAccountCardComponent {
  protected readonly auth = inject(AuthStore);
  private readonly authService = inject(AuthService);
  private readonly guestOrders = inject(GuestOrdersService);
  private readonly toast = inject(ToastService);
  private readonly router = inject(Router);

  readonly order = input.required<Order>();
  readonly token = input.required<string>();
  readonly attached = output<Order>();

  protected password = '';
  protected confirm = '';
  protected marketing = false;
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly errors = signal<Record<string, string[]>>({});
  protected readonly accountExists = signal(false);
  protected readonly sameEmail = computed(() => (this.auth.user()?.email ?? '').toLowerCase() === (this.order().customer?.email ?? '').toLowerCase());
  protected readonly returnUrl = computed(() => `/order/${this.order().order_number}?token=${this.token()}`);

  create(): void {
    this.error.set(null);
    this.accountExists.set(false);
    if (this.password !== this.confirm) {
      this.error.set('The passwords do not match.');
      return;
    }
    this.busy.set(true);
    this.guestOrders.createAccount(this.order().order_number, this.token(), { password: this.password, password_confirmation: this.confirm, marketing_opt_in: this.marketing }).subscribe({
      next: (payload) => {
        this.authService.applySession(payload);
        this.guestOrders.forget(this.order().order_number);
        this.toast.success('Account created — your order is in My orders');
        void this.router.navigate(['/account/orders', payload.order_id]);
      },
      error: (e) => {
        this.busy.set(false);
        this.errors.set(fieldErrors(e));
        this.accountExists.set(e instanceof HttpErrorResponse && e.status === 409);
        this.error.set(errorMessage(e));
      },
    });
  }

  attach(): void {
    this.busy.set(true);
    this.guestOrders.attach(this.order().order_number, this.token()).subscribe({
      next: (o) => {
        this.guestOrders.forget(o.order_number);
        this.toast.success('Order added to My orders');
        this.attached.emit(o);
        void this.router.navigate(['/account/orders', o.id]);
      },
      error: (e) => { this.busy.set(false); this.toast.error(errorMessage(e)); },
    });
  }
}
