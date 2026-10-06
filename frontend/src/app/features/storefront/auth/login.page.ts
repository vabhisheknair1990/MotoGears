import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { applyServerErrors, errorMessage } from '../../../core/utils/http-errors';
import { IconComponent } from '../../../shared/components/icon.component';

@Component({
  selector: 'app-login-page',
  imports: [ReactiveFormsModule, RouterLink, IconComponent],
  template: `
    <div class="auth">
      <div class="box card card-body">
        <h1>Welcome back</h1>
        <p class="sub">Log in to track orders, save vehicles and check out faster.</p>
        @if (error()) { <div class="alert alert-danger"><app-icon name="alert" [size]="18" /> {{ error() }}</div> }
        <form [formGroup]="form" (ngSubmit)="submit()" novalidate>
          <div class="field">
            <label class="label" for="email">Email</label>
            <input id="email" class="input" type="email" formControlName="email" autocomplete="email" />
            @if (form.controls.email.touched && form.controls.email.invalid) { <span class="error-text">{{ form.controls.email.errors?.['server'] ?? 'Enter a valid email address.' }}</span> }
          </div>
          <div class="field">
            <div class="row between"><label class="label" for="password">Password</label><a routerLink="/forgot-password" class="text-sm link">Forgot password?</a></div>
            <div class="pw">
              <input id="password" class="input" [type]="show() ? 'text' : 'password'" formControlName="password" autocomplete="current-password" />
              <button type="button" class="icon-btn sm" (click)="show.set(!show())" [attr.aria-label]="show() ? 'Hide password' : 'Show password'"><app-icon name="eye" [size]="16" /></button>
            </div>
            @if (form.controls.password.touched && form.controls.password.invalid) { <span class="error-text">Password is required.</span> }
          </div>
          <label class="check"><input type="checkbox" formControlName="remember" /> Keep me logged in for 30 days</label>
          <button class="btn btn-primary btn-lg btn-block" [disabled]="busy()">@if (busy()) { <span class="spinner"></span> } Log in</button>
        </form>
        <p class="alt">New to MotoGears? <a routerLink="/register" [queryParams]="{ returnUrl: returnUrl() }" class="link">Create an account</a></p>
        <div class="demo">Demo customer: <button type="button" (click)="fillDemo()">customer&#64;example.com / password</button></div>
      </div>
    </div>
  `,
  styleUrl: './auth.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LoginPage {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly toast = inject(ToastService);
  readonly returnUrl = input<string>('/account');
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly show = signal(false);
  protected readonly form = inject(FormBuilder).nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', Validators.required],
    remember: false,
  });

  constructor() {
    inject(SeoService).set({ title: 'Log in' });
  }

  fillDemo(): void {
    this.form.patchValue({ email: 'customer@example.com', password: 'password' });
  }

  submit(): void {
    this.form.markAllAsTouched();
    if (this.form.invalid) return;
    this.busy.set(true);
    this.error.set(null);
    const { email, password, remember } = this.form.getRawValue();
    this.auth.login(email, password, remember).subscribe({
      next: (user) => {
        this.toast.success(`Welcome back, ${user.name.split(' ')[0]}!`);
        void this.router.navigateByUrl(this.safeReturn());
      },
      error: (e) => {
        this.busy.set(false);
        applyServerErrors(this.form, e);
        this.error.set(errorMessage(e, 'Login failed. Please try again.'));
      },
    });
  }

  /** Only allow in-app relative redirects (prevents open redirects). */
  private safeReturn(): string {
    const url = this.returnUrl() || '/account';
    return url.startsWith('/') && !url.startsWith('//') ? url : '/account';
  }
}
