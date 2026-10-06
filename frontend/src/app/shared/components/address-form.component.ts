import { ChangeDetectionStrategy, Component, OnInit, inject, input, output, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Address, AddressInput } from '../../core/models/api.models';

export const INDIAN_STATES = [
  'Andaman and Nicobar Islands', 'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chandigarh', 'Chhattisgarh', 'Dadra and Nagar Haveli and Daman and Diu',
  'Delhi', 'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jammu and Kashmir', 'Jharkhand', 'Karnataka', 'Kerala', 'Ladakh', 'Lakshadweep', 'Madhya Pradesh',
  'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Puducherry', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura',
  'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
];

/** Reusable address form (checkout + address book). Server-side 422 errors can be pushed in via `serverErrors`. */
@Component({
  selector: 'app-address-form',
  imports: [ReactiveFormsModule],
  template: `
    <form [formGroup]="form" (ngSubmit)="submit()" class="form-grid cols-2" novalidate>
      <div class="field">
        <label class="label" for="af-name">Full name <span class="req">*</span></label>
        <input id="af-name" class="input" formControlName="name" autocomplete="name" />
        @if (err('name'); as e) { <span class="error-text">{{ e }}</span> }
      </div>
      <div class="field">
        <label class="label" for="af-phone">Mobile number <span class="req">*</span></label>
        <input id="af-phone" class="input" formControlName="phone" inputmode="tel" autocomplete="tel" placeholder="10-digit mobile" />
        @if (err('phone'); as e) { <span class="error-text">{{ e }}</span> }
      </div>
      <div class="field span-2">
        <label class="label" for="af-l1">Address line 1 <span class="req">*</span></label>
        <input id="af-l1" class="input" formControlName="line1" autocomplete="address-line1" placeholder="House / flat no., building, street" />
        @if (err('line1'); as e) { <span class="error-text">{{ e }}</span> }
      </div>
      <div class="field">
        <label class="label" for="af-l2">Address line 2</label>
        <input id="af-l2" class="input" formControlName="line2" autocomplete="address-line2" placeholder="Area, locality" />
      </div>
      <div class="field">
        <label class="label" for="af-lm">Landmark</label>
        <input id="af-lm" class="input" formControlName="landmark" />
      </div>
      <div class="field">
        <label class="label" for="af-city">City <span class="req">*</span></label>
        <input id="af-city" class="input" formControlName="city" autocomplete="address-level2" />
        @if (err('city'); as e) { <span class="error-text">{{ e }}</span> }
      </div>
      <div class="field">
        <label class="label" for="af-state">State <span class="req">*</span></label>
        <select id="af-state" class="select" formControlName="state">
          <option value="">Select state</option>
          @for (s of states; track s) { <option [value]="s">{{ s }}</option> }
        </select>
        @if (err('state'); as e) { <span class="error-text">{{ e }}</span> }
      </div>
      <div class="field">
        <label class="label" for="af-pin">PIN code <span class="req">*</span></label>
        <input id="af-pin" class="input" formControlName="postal_code" inputmode="numeric" maxlength="6" autocomplete="postal-code" />
        @if (err('postal_code'); as e) { <span class="error-text">{{ e }}</span> }
      </div>
      <div class="field">
        <span class="label">Address type</span>
        <div class="row gap-8">
          @for (l of ['Home', 'Work', 'Other']; track l) {
            <label class="chip" [class.active]="form.value.label === l"><input type="radio" formControlName="label" [value]="l" class="sr-only" />{{ l }}</label>
          }
        </div>
      </div>
      @if (showDefault()) {
        <label class="check span-2"><input type="checkbox" formControlName="is_default" /> Make this my default address</label>
      }
      <div class="row end span-2">
        @if (cancellable()) { <button type="button" class="btn" (click)="cancelled.emit()">Cancel</button> }
        <button class="btn btn-primary" [disabled]="busy()">@if (busy()) { <span class="spinner"></span> } {{ submitLabel() }}</button>
      </div>
    </form>
  `,
  styles: `.chip { cursor: pointer; }`,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AddressFormComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  readonly value = input<Address | null>(null);
  readonly busy = input(false);
  readonly submitLabel = input('Save address');
  readonly showDefault = input(true);
  readonly cancellable = input(true);
  readonly serverErrors = input<Record<string, string[]>>({});
  readonly saved = output<AddressInput>();
  readonly cancelled = output<void>();
  protected readonly states = INDIAN_STATES;
  protected readonly submitted = signal(false);

  protected readonly form = this.fb.nonNullable.group({
    label: 'Home',
    name: ['', [Validators.required, Validators.maxLength(100)]],
    phone: ['', [Validators.required, Validators.pattern(/^[0-9+\-\s]{10,20}$/)]],
    line1: ['', [Validators.required, Validators.maxLength(190)]],
    line2: [''],
    landmark: [''],
    city: ['', Validators.required],
    state: ['', Validators.required],
    postal_code: ['', [Validators.required, Validators.pattern(/^[1-9][0-9]{5}$/)]],
    country: 'IN',
    is_default: false,
  });

  ngOnInit(): void {
    const v = this.value();
    if (v) {
      this.form.patchValue({ ...v, line2: v.line2 ?? '', landmark: v.landmark ?? '' });
    }
  }

  err(field: string): string | null {
    const server = this.serverErrors()[field]?.[0];
    if (server) return server;
    const c = this.form.get(field);
    if (!c || !c.invalid || !(c.touched || this.submitted())) return null;
    if (c.errors?.['required']) return 'This field is required.';
    if (field === 'postal_code') return 'Enter a valid 6-digit PIN code.';
    if (field === 'phone') return 'Enter a valid mobile number.';
    return 'Please check this field.';
  }

  submit(): void {
    this.submitted.set(true);
    this.form.markAllAsTouched();
    if (this.form.invalid) return;
    const v = this.form.getRawValue();
    this.saved.emit({ ...v, line2: v.line2 || null, landmark: v.landmark || null });
  }
}
