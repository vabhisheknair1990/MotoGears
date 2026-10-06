import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { CustomerService } from '../../../core/services/customer.service';
import { ToastService } from '../../../core/services/toast.service';
import { AuthStore } from '../../../core/state/auth.store';
import { applyServerErrors, errorMessage } from '../../../core/utils/http-errors';

@Component({
  selector: 'app-profile-page',
  imports: [ReactiveFormsModule],
  template: `
    <h1 class="h1">Profile</h1>
    <form class="card card-body form-grid cols-2" [formGroup]="form" (ngSubmit)="save()" novalidate>
      <div class="field">
        <label class="label" for="p-name">Full name</label>
        <input id="p-name" class="input" formControlName="name" autocomplete="name" />
        @if (form.controls.name.invalid && form.controls.name.touched) { <span class="error-text">{{ form.controls.name.errors?.['server'] ?? 'Name is required.' }}</span> }
      </div>
      <div class="field">
        <label class="label" for="p-email">Email</label>
        <input id="p-email" class="input" type="email" formControlName="email" autocomplete="email" />
        @if (form.controls.email.invalid && form.controls.email.touched) { <span class="error-text">{{ form.controls.email.errors?.['server'] ?? 'Enter a valid email.' }}</span> }
      </div>
      <div class="field">
        <label class="label" for="p-phone">Mobile</label>
        <input id="p-phone" class="input" formControlName="phone" autocomplete="tel" />
        @if (form.controls.phone.invalid && form.controls.phone.touched) { <span class="error-text">{{ form.controls.phone.errors?.['server'] ?? 'Enter a valid mobile number.' }}</span> }
      </div>
      <div class="field"><span class="label">Communication</span><label class="check"><input type="checkbox" formControlName="marketing_opt_in" /> Email me offers & maintenance tips</label></div>
      <div class="row end span-2"><button class="btn btn-primary" [disabled]="busy() || form.pristine">@if (busy()) { <span class="spinner"></span> } Save changes</button></div>
    </form>
  `,
  styles: `.h1 { font-size: 28px; margin-bottom: 16px; }`,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProfilePage {
  private readonly customer = inject(CustomerService);
  private readonly toast = inject(ToastService);
  private readonly auth = inject(AuthStore);
  protected readonly busy = signal(false);
  protected readonly form = inject(FormBuilder).nonNullable.group({
    name: [this.auth.user()?.name ?? '', [Validators.required, Validators.minLength(2)]],
    email: [this.auth.user()?.email ?? '', [Validators.required, Validators.email]],
    phone: [this.auth.user()?.phone ?? '', Validators.pattern(/^[0-9+\-\s]{8,20}$/)],
    marketing_opt_in: this.auth.user()?.marketing_opt_in ?? false,
  });

  save(): void {
    this.form.markAllAsTouched();
    if (this.form.invalid) return;
    this.busy.set(true);
    const v = this.form.getRawValue();
    this.customer.updateProfile({ ...v, phone: v.phone || null }).subscribe({
      next: () => { this.busy.set(false); this.form.markAsPristine(); this.toast.success('Profile updated'); },
      error: (e) => { this.busy.set(false); applyServerErrors(this.form, e); this.toast.error(errorMessage(e)); },
    });
  }
}
