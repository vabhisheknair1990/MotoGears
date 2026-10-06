import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { applyServerErrors, errorMessage } from '../../../core/utils/http-errors';
import { IconComponent } from '../../../shared/components/icon.component';

export function passwordsMatch(group: AbstractControl): ValidationErrors | null {
  const pw = group.get('password')?.value;
  const confirm = group.get('password_confirmation')?.value;
  return pw && confirm && pw !== confirm ? { mismatch: true } : null;
}

@Component({
  selector: 'app-register-page',
  imports: [ReactiveFormsModule, RouterLink, IconComponent],
  template: `
    <div class="auth">
      <div class="box wide card card-body">
        <h1>Create your account</h1>
        <p class="sub">Save your vehicles, track orders and get member-only offers.</p>
        @if (error()) { <div class="alert alert-danger"><app-icon name="alert" [size]="18" /> {{ error() }}</div> }
        <form [formGroup]="form" (ngSubmit)="submit()" novalidate>
          <div class="field">
            <label class="label" for="name">Full name <span class="req">*</span></label>
            <input id="name" class="input" formControlName="name" autocomplete="name" />
            @if (show('name')) { <span class="error-text">{{ msg('name', 'Please enter your name.') }}</span> }
          </div>
          <div class="form-grid cols-2">
            <div class="field">
              <label class="label" for="email">Email <span class="req">*</span></label>
              <input id="email" class="input" type="email" formControlName="email" autocomplete="email" />
              @if (show('email')) { <span class="error-text">{{ msg('email', 'Enter a valid email address.') }}</span> }
            </div>
            <div class="field">
              <label class="label" for="phone">Mobile</label>
              <input id="phone" class="input" formControlName="phone" inputmode="tel" autocomplete="tel" />
              @if (show('phone')) { <span class="error-text">{{ msg('phone', 'Enter a valid mobile number.') }}</span> }
            </div>
            <div class="field">
              <label class="label" for="pw">Password <span class="req">*</span></label>
              <input id="pw" class="input" type="password" formControlName="password" autocomplete="new-password" />
              @if (show('password')) { <span class="error-text">{{ msg('password', 'At least 8 characters with letters and numbers.') }}</span> } @else { <span class="hint">8+ characters, letters and numbers.</span> }
            </div>
            <div class="field">
              <label class="label" for="pw2">Confirm password <span class="req">*</span></label>
              <input id="pw2" class="input" type="password" formControlName="password_confirmation" autocomplete="new-password" />
              @if (form.errors?.['mismatch'] && form.controls.password_confirmation.touched) { <span class="error-text">Passwords do not match.</span> }
            </div>
          </div>
          <label class="check"><input type="checkbox" formControlName="marketing_opt_in" /> Send me offers and maintenance tips</label>
          <button class="btn btn-primary btn-lg btn-block" [disabled]="busy()">@if (busy()) { <span class="spinner"></span> } Create account</button>
          <p class="text-xs text-muted mb-0">By creating an account you agree to our <a routerLink="/page/terms" class="link">terms</a> and <a routerLink="/page/privacy-policy" class="link">privacy policy</a>.</p>
        </form>
        <p class="alt">Already have an account? <a routerLink="/login" [queryParams]="{ returnUrl: returnUrl() }" class="link">Log in</a></p>
      </div>
    </div>
  `,
  styleUrl: './auth.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class RegisterPage {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly toast = inject(ToastService);
  readonly returnUrl = input<string>('/account');
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly form = inject(FormBuilder).nonNullable.group({
    name: ['', [Validators.required, Validators.minLength(2)]],
    email: ['', [Validators.required, Validators.email]],
    phone: ['', Validators.pattern(/^[0-9+\-\s]{8,20}$/)],
    password: ['', [Validators.required, Validators.minLength(8), Validators.pattern(/^(?=.*[A-Za-z])(?=.*\d).+$/)]],
    password_confirmation: ['', Validators.required],
    marketing_opt_in: true,
  }, { validators: passwordsMatch });

  constructor() {
    inject(SeoService).set({ title: 'Create account' });
  }

  show(field: string): boolean {
    const c = this.form.get(field);
    return !!c && c.invalid && c.touched;
  }

  msg(field: string, fallback: string): string {
    return (this.form.get(field)?.errors?.['server'] as string | undefined) ?? fallback;
  }

  submit(): void {
    this.form.markAllAsTouched();
    if (this.form.invalid) return;
    this.busy.set(true);
    this.error.set(null);
    const v = this.form.getRawValue();
    this.auth.register({ ...v, phone: v.phone || null }).subscribe({
      next: (user) => {
        this.toast.success(`Welcome to MotoGears, ${user.name.split(' ')[0]}!`);
        const url = this.returnUrl();
        void this.router.navigateByUrl(url.startsWith('/') && !url.startsWith('//') ? url : '/account');
      },
      error: (e) => {
        this.busy.set(false);
        applyServerErrors(this.form, e);
        this.error.set(errorMessage(e));
      },
    });
  }
}
