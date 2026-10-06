import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { SeoService } from '../../../core/services/seo.service';
import { errorMessage } from '../../../core/utils/http-errors';
import { IconComponent } from '../../../shared/components/icon.component';

@Component({
  selector: 'app-forgot-password-page',
  imports: [ReactiveFormsModule, RouterLink, IconComponent],
  template: `
    <div class="auth">
      <div class="box card card-body">
        <h1>Forgot password?</h1>
        @if (sent()) {
          <div class="alert alert-success"><app-icon name="check-circle" [size]="18" /> {{ sent() }}</div>
          <p class="text-sm text-muted mt-16">In this demo, emails are written to the Laravel log (<span class="mono">storage/logs/laravel.log</span>) instead of being sent.</p>
          <a routerLink="/login" class="btn btn-block mt-16">Back to login</a>
        } @else {
          <p class="sub">Enter your account email and we'll send you a reset link.</p>
          @if (error()) { <div class="alert alert-danger">{{ error() }}</div> }
          <form [formGroup]="form" (ngSubmit)="submit()" novalidate>
            <div class="field">
              <label class="label" for="email">Email</label>
              <input id="email" class="input" type="email" formControlName="email" autocomplete="email" />
              @if (form.controls.email.touched && form.controls.email.invalid) { <span class="error-text">Enter a valid email address.</span> }
            </div>
            <button class="btn btn-primary btn-lg btn-block" [disabled]="busy()">@if (busy()) { <span class="spinner"></span> } Send reset link</button>
          </form>
          <p class="alt"><a routerLink="/login" class="link">Back to login</a></p>
        }
      </div>
    </div>
  `,
  styleUrl: './auth.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForgotPasswordPage {
  private readonly auth = inject(AuthService);
  protected readonly busy = signal(false);
  protected readonly sent = signal<string | null>(null);
  protected readonly error = signal<string | null>(null);
  protected readonly form = inject(FormBuilder).nonNullable.group({ email: ['', [Validators.required, Validators.email]] });

  constructor() {
    inject(SeoService).set({ title: 'Forgot password' });
  }

  submit(): void {
    this.form.markAllAsTouched();
    if (this.form.invalid) return;
    this.busy.set(true);
    this.auth.forgotPassword(this.form.getRawValue().email).subscribe({
      next: (msg) => { this.busy.set(false); this.sent.set(msg); },
      error: (e) => { this.busy.set(false); this.error.set(errorMessage(e)); },
    });
  }
}
