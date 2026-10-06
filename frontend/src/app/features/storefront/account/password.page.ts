import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { CustomerService } from '../../../core/services/customer.service';
import { ToastService } from '../../../core/services/toast.service';
import { applyServerErrors, errorMessage } from '../../../core/utils/http-errors';
import { passwordsMatch } from '../auth/register.page';

@Component({
  selector: 'app-password-page',
  imports: [ReactiveFormsModule],
  template: `
    <h1 class="h1">Change password</h1>
    <form class="card card-body stack gap-16" style="max-width: 520px" [formGroup]="form" (ngSubmit)="save()" novalidate>
      <div class="field">
        <label class="label" for="cp">Current password</label>
        <input id="cp" class="input" type="password" formControlName="current_password" autocomplete="current-password" />
        @if (form.controls.current_password.errors?.['server']; as e) { <span class="error-text">{{ e }}</span> }
      </div>
      <div class="field">
        <label class="label" for="np">New password</label>
        <input id="np" class="input" type="password" formControlName="password" autocomplete="new-password" />
        @if (form.controls.password.invalid && form.controls.password.touched) { <span class="error-text">{{ form.controls.password.errors?.['server'] ?? 'At least 8 characters with letters and numbers.' }}</span> }
      </div>
      <div class="field">
        <label class="label" for="np2">Confirm new password</label>
        <input id="np2" class="input" type="password" formControlName="password_confirmation" autocomplete="new-password" />
        @if (form.errors?.['mismatch'] && form.controls.password_confirmation.touched) { <span class="error-text">Passwords do not match.</span> }
      </div>
      <p class="text-xs text-muted mb-0">Changing your password signs you out on all other devices.</p>
      <div class="row end"><button class="btn btn-primary" [disabled]="busy()">Update password</button></div>
    </form>
  `,
  styles: `.h1 { font-size: 28px; margin-bottom: 16px; }`,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PasswordPage {
  private readonly customer = inject(CustomerService);
  private readonly toast = inject(ToastService);
  protected readonly busy = signal(false);
  protected readonly form = inject(FormBuilder).nonNullable.group({
    current_password: ['', Validators.required],
    password: ['', [Validators.required, Validators.minLength(8), Validators.pattern(/^(?=.*[A-Za-z])(?=.*\d).+$/)]],
    password_confirmation: ['', Validators.required],
  }, { validators: passwordsMatch });

  save(): void {
    this.form.markAllAsTouched();
    if (this.form.invalid) return;
    this.busy.set(true);
    this.customer.changePassword(this.form.getRawValue()).subscribe({
      next: (msg) => { this.busy.set(false); this.form.reset(); this.toast.success(msg); },
      error: (e) => { this.busy.set(false); applyServerErrors(this.form, e); this.toast.error(errorMessage(e)); },
    });
  }
}
