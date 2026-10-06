import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { SeoService } from '../../../core/services/seo.service';
import { AuthStore } from '../../../core/state/auth.store';
import { errorMessage } from '../../../core/utils/http-errors';
import { IconComponent } from '../../../shared/components/icon.component';
import { ADMIN_NAV } from '../layout/admin-nav';

@Component({
  selector: 'adm-login-page',
  imports: [ReactiveFormsModule, RouterLink, IconComponent],
  template: `
    <main class="wrap">
      <div class="panel">
        <div class="brand"><span class="logo"><app-icon name="gauge" [size]="22" /></span><div><strong>MotoGears</strong><small>Admin console</small></div></div>
        <h1>Sign in</h1>
        <p class="sub">Staff access only. Activity in this console is audited.</p>
        @if (denied()) { <div class="alert alert-warning"><app-icon name="lock" [size]="18" /> That account doesn't have admin access. Sign in with a staff account.</div> }
        @if (error()) { <div class="alert alert-danger" role="alert"><app-icon name="alert" [size]="18" /> {{ error() }}</div> }
        <form [formGroup]="form" (ngSubmit)="submit()" novalidate>
          <div class="field">
            <label class="label" for="a-email">Email</label>
            <input id="a-email" class="input" type="email" formControlName="email" autocomplete="username" />
            @if (form.controls.email.invalid && form.controls.email.touched) { <span class="error-text">Enter a valid email address.</span> }
          </div>
          <div class="field">
            <label class="label" for="a-pw">Password</label>
            <div class="pw">
              <input id="a-pw" class="input" [type]="showPw() ? 'text' : 'password'" formControlName="password" autocomplete="current-password" />
              <button type="button" class="icon-btn sm" (click)="showPw.set(!showPw())" [attr.aria-label]="showPw() ? 'Hide password' : 'Show password'"><app-icon name="eye" [size]="16" /></button>
            </div>
            @if (form.controls.password.invalid && form.controls.password.touched) { <span class="error-text">Password is required.</span> }
          </div>
          <button class="btn btn-primary btn-lg btn-block" [disabled]="busy()">@if (busy()) { <span class="spinner"></span> } Sign in</button>
        </form>
        <div class="demo">
          <strong>Demo account</strong>
          <span>admin&#64;example.com / password</span>
          <button type="button" class="link text-sm" (click)="fillDemo()">Fill in</button>
        </div>
        <a routerLink="/" class="back"><app-icon name="arrow-left" [size]="14" /> Back to store</a>
      </div>
    </main>
  `,
  styles: `
    .wrap { min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: radial-gradient(1200px 600px at 10% -10%, rgba(215,38,61,.25), transparent 60%), var(--dark, #0b0f17); }
    .panel { width: 100%; max-width: 420px; background: #fff; border-radius: 16px; padding: 32px 28px; box-shadow: 0 30px 80px rgba(0,0,0,.35); }
    .brand { display: flex; gap: 12px; align-items: center; margin-bottom: 24px; }
    .logo { width: 42px; height: 42px; border-radius: 11px; background: var(--brand); color: #fff; display: grid; place-items: center; }
    .brand strong { display: block; font-family: 'Barlow Condensed', sans-serif; font-size: 24px; line-height: 1; }
    .brand small { color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: 1.2px; }
    h1 { margin: 0 0 4px; font-size: 26px; }
    .sub { color: var(--muted); margin: 0 0 20px; font-size: 14px; }
    .pw { position: relative; }
    .pw .input { padding-right: 44px; }
    .pw .icon-btn { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); }
    form { display: flex; flex-direction: column; gap: 4px; }
    .demo { margin-top: 20px; padding: 12px 14px; border-radius: 10px; background: var(--line-2); display: flex; flex-wrap: wrap; gap: 4px 10px; align-items: center; font-size: 13px; }
    .demo strong { flex-basis: 100%; font-size: 12px; text-transform: uppercase; letter-spacing: .8px; color: var(--muted); }
    .back { display: inline-flex; gap: 4px; align-items: center; margin-top: 18px; font-size: 13px; color: var(--muted); }
    .back:hover { color: var(--brand); }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdminLoginPage {
  readonly returnUrl = input<string | null>(null);
  readonly denied = input<string | null>(null);
  private readonly auth = inject(AuthService);
  private readonly store = inject(AuthStore);
  private readonly router = inject(Router);
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly showPw = signal(false);
  protected readonly form = inject(FormBuilder).nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required]],
  });

  constructor() {
    inject(SeoService).set({ title: 'Admin sign in' });
  }

  protected fillDemo(): void {
    this.form.setValue({ email: 'admin@example.com', password: 'password' });
  }

  protected submit(): void {
    this.form.markAllAsTouched();
    if (this.form.invalid || this.busy()) return;
    this.busy.set(true);
    this.error.set(null);
    const { email, password } = this.form.getRawValue();
    this.auth.adminLogin(email, password).subscribe({
      next: () => {
        this.busy.set(false);
        const target = this.returnUrl();
        void this.router.navigateByUrl(target && target.startsWith('/admin') && !target.startsWith('/admin/login') ? target : firstAllowedAdminUrl(this.store));
      },
      error: (err) => {
        this.busy.set(false);
        this.error.set(errorMessage(err, 'Sign in failed.'));
      },
    });
  }
}

/** Staff without dashboard access land on the first section they can use. */
export function firstAllowedAdminUrl(store: AuthStore): string {
  for (const s of ADMIN_NAV) for (const i of s.items) if (store.hasPermission(i.permission)) return i.link;
  return '/admin/forbidden';
}
