import { ChangeDetectionStrategy, Component, OnInit, inject, input, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { applyServerErrors, errorMessage } from '../../../core/utils/http-errors';
import { passwordsMatch } from './register.page';

@Component({
  selector: 'app-reset-password-page',
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <div class="auth">
      <div class="box card card-body">
        <h1>Set a new password</h1>
        <p class="sub">Choose a strong password you haven't used before.</p>
        @if (!token()) { <div class="alert alert-warning">This reset link is incomplete. Please request a new one.</div> }
        @if (error()) { <div class="alert alert-danger">{{ error() }}</div> }
        <form [formGroup]="form" (ngSubmit)="submit()" novalidate>
          <div class="field"><label class="label" for="email">Email</label><input id="email" class="input" type="email" formControlName="email" /></div>
          <div class="field">
            <label class="label" for="pw">New password</label><input id="pw" class="input" type="password" formControlName="password" autocomplete="new-password" />
            @if (form.controls.password.touched && form.controls.password.invalid) { <span class="error-text">At least 8 characters with letters and numbers.</span> }
          </div>
          <div class="field">
            <label class="label" for="pw2">Confirm password</label><input id="pw2" class="input" type="password" formControlName="password_confirmation" autocomplete="new-password" />
            @if (form.errors?.['mismatch'] && form.controls.password_confirmation.touched) { <span class="error-text">Passwords do not match.</span> }
          </div>
          <button class="btn btn-primary btn-lg btn-block" [disabled]="busy() || !token()">Reset password</button>
        </form>
        <p class="alt"><a routerLink="/forgot-password" class="link">Request a new link</a></p>
      </div>
    </div>
  `,
  styleUrl: './auth.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ResetPasswordPage implements OnInit {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly toast = inject(ToastService);
  readonly token = input<string>('');
  readonly email = input<string>('');
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly form = inject(FormBuilder).nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(8), Validators.pattern(/^(?=.*[A-Za-z])(?=.*\d).+$/)]],
    password_confirmation: ['', Validators.required],
  }, { validators: passwordsMatch });

  constructor() {
    inject(SeoService).set({ title: 'Reset password' });
  }

  ngOnInit(): void {
    this.form.patchValue({ email: this.email() });
  }

  submit(): void {
    this.form.markAllAsTouched();
    if (this.form.invalid) return;
    this.busy.set(true);
    this.auth.resetPassword({ token: this.token(), ...this.form.getRawValue() }).subscribe({
      next: (msg) => { this.toast.success(msg); void this.router.navigate(['/login']); },
      error: (e) => { this.busy.set(false); applyServerErrors(this.form, e); this.error.set(errorMessage(e)); },
    });
  }
}
