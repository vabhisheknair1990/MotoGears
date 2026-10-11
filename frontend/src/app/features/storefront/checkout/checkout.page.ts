import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormBuilder, FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { Router, RouterLink } from '@angular/router';
import { AddressInput, CheckoutSummary, PaymentCode, ShippingMethod } from '../../../core/models/api.models';
import { CartService } from '../../../core/services/cart.service';
import { CheckoutService } from '../../../core/services/checkout.service';
import { CustomerService } from '../../../core/services/customer.service';
import { GuestOrdersService } from '../../../core/services/guest-orders.service';
import { AuthStore } from '../../../core/state/auth.store';
import { RazorpayService } from '../../../core/services/razorpay.service';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { errorMessage, fieldErrors } from '../../../core/utils/http-errors';
import { AddressFormComponent } from '../../../shared/components/address-form.component';
import { CheckoutStepperComponent, Step } from '../../../shared/components/checkout-stepper.component';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { InrPipe } from '../../../shared/pipes/inr.pipe';


@Component({
  selector: 'app-checkout-page',
  imports: [RouterLink, ReactiveFormsModule, CheckoutStepperComponent, AddressFormComponent, EmptyStateComponent, IconComponent, InrPipe],
  templateUrl: './checkout.page.html',
  styleUrl: './checkout.page.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CheckoutPage {
  private readonly checkout = inject(CheckoutService);
  private readonly customer = inject(CustomerService);
  private readonly cartService = inject(CartService);
  private readonly toast = inject(ToastService);
  private readonly router = inject(Router);
  private readonly razorpay = inject(RazorpayService);
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthStore);
  private readonly guestOrders = inject(GuestOrdersService);

  /** Not logged in → guest checkout: contact email + a typed address instead of the address book. */
  protected readonly isGuest = computed(() => !this.auth.isLoggedIn());
  protected readonly guestDisabled = signal(false);
  protected readonly email = new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.email, Validators.maxLength(190)] });
  protected readonly guestAddress = signal<AddressInput | null>(null);
  protected readonly editingGuestAddress = signal(true);
  protected readonly guestAddressErrors = signal<Record<string, string[]>>({});
  protected readonly emailServerError = signal<string | null>(null);

  protected readonly steps = computed<Step[]>(() => [
    { key: 'address', label: this.isGuest() ? 'Details' : 'Address' },
    { key: 'shipping', label: 'Shipping' },
    { key: 'payment', label: 'Payment' },
    { key: 'review', label: 'Review' },
  ]);
  protected readonly step = signal(0);
  protected readonly summary = signal<CheckoutSummary | null>(null);
  protected readonly loading = signal(true);
  protected readonly loadError = signal<string | null>(null);
  protected readonly addressId = signal<number | null>(null);
  protected readonly addingAddress = signal(false);
  protected readonly savingAddress = signal(false);
  protected readonly addressErrors = signal<Record<string, string[]>>({});
  protected readonly shipping = signal<'standard' | 'express'>('standard');
  protected readonly payment = signal<PaymentCode>('cod');
  protected readonly placing = signal(false);
  protected readonly placeError = signal<string | null>(null);
  protected readonly notes = signal('');

  protected readonly cart = computed(() => this.summary()?.cart ?? null);
  protected readonly selectedAddress = computed(() => (this.isGuest() ? this.guestAddress() : this.summary()?.addresses.find((a) => a.id === this.addressId()) ?? null));
  protected readonly selectedShipping = computed<ShippingMethod | null>(() => this.summary()?.shipping_methods.find((s) => s.code === this.shipping()) ?? null);
  protected readonly hasDemoMethods = computed(() => !!this.summary()?.payment_methods.some((p) => p.code.startsWith('demo_')));
  protected readonly paymentOption = computed(() => this.summary()?.payment_methods.find((p) => p.code === this.payment()) ?? null);

  protected readonly card = this.fb.nonNullable.group({
    card_number: ['4111 1111 1111 1111', [Validators.required, Validators.pattern(/^[0-9 ]{13,23}$/)]],
    card_name: ['', [Validators.required, Validators.maxLength(100)]],
    expiry: ['12/29', [Validators.required, Validators.pattern(/^(0[1-9]|1[0-2])\/?[0-9]{2}$/)]],
    cvv: ['123', [Validators.required, Validators.pattern(/^[0-9]{3,4}$/)]],
  });
  /** The first visit picks the first offered method (UPI when available); later reloads keep the choice. */
  private paymentChosen = false;

  constructor() {
    inject(SeoService).set({ title: 'Checkout' });
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.loadError.set(null);
    this.checkout.getSummary(this.shipping(), this.isGuest()).subscribe({
      next: (s) => {
        this.summary.set(s);
        this.loading.set(false);
        this.shipping.set(s.cart.shipping_method);
        if (!this.addressId() && s.addresses.length) {
          this.addressId.set((s.addresses.find((a) => a.is_default) ?? s.addresses[0]).id);
        }
        if (!s.addresses.length && !this.isGuest()) this.addingAddress.set(true);
        const offered = s.payment_methods.filter((m) => m.available);
        if (!this.paymentChosen || !offered.some((m) => m.code === this.payment())) {
          this.paymentChosen = true;
          if (offered[0]) this.payment.set(offered[0].code);
        }
        const user = s.addresses[0]?.name;
        if (user && !this.card.value.card_name) this.card.patchValue({ card_name: user });
      },
      error: (e) => {
        this.loading.set(false);
        if (e instanceof HttpErrorResponse && e.status === 403 && this.isGuest()) {
          this.guestDisabled.set(true);
          return;
        }
        this.loadError.set(errorMessage(e));
      },
    });
  }

  useGuestAddress(input: AddressInput): void {
    this.guestAddress.set(input);
    this.guestAddressErrors.set({});
    this.editingGuestAddress.set(false);
    if (!this.card.value.card_name) this.card.patchValue({ card_name: input.name });
  }

  protected emailError(): string | null {
    if (this.emailServerError()) return this.emailServerError();
    const c = this.email;
    if (!c.invalid || !c.touched) return null;
    return c.errors?.['required'] ? 'Enter your email — your order confirmation and tracking link are sent here.' : 'Enter a valid email address.';
  }

  saveAddress(input: AddressInput): void {
    this.savingAddress.set(true);
    this.addressErrors.set({});
    this.customer.createAddress(input).subscribe({
      next: (a) => {
        this.savingAddress.set(false);
        this.addingAddress.set(false);
        this.summary.update((s) => (s ? { ...s, addresses: [a, ...s.addresses.map((x) => (a.is_default ? { ...x, is_default: false } : x))] } : s));
        this.addressId.set(a.id);
        this.toast.success('Address saved');
      },
      error: (e) => { this.savingAddress.set(false); this.addressErrors.set(fieldErrors(e)); this.toast.error(errorMessage(e)); },
    });
  }

  chooseShipping(code: 'standard' | 'express'): void {
    this.shipping.set(code);
    // The server recalculates shipping & tax; refresh the authoritative totals.
    this.cartService.setShipping(code).subscribe(() => this.load());
  }

  next(): void {
    if (this.step() === 0 && this.isGuest()) {
      this.email.markAsTouched();
      if (this.email.invalid) {
        globalThis.document?.getElementById('g-email')?.focus();
        return;
      }
      if (!this.guestAddress() || this.editingGuestAddress()) {
        this.toast.error('Please enter your delivery address and click "Use this address".');
        return;
      }
    } else if (this.step() === 0 && !this.addressId()) {
      this.toast.error('Please select or add a delivery address.');
      return;
    }
    if (this.step() === 2) {
      const form = this.payment() === 'demo_card' ? this.card : null;
      if (form && form.invalid) {
        form.markAllAsTouched();
        return;
      }
      if (!this.paymentOption()?.available) {
        this.toast.error(this.paymentOption()?.unavailable_reason ?? 'This payment method is unavailable.');
        return;
      }
    }
    this.step.update((s) => Math.min(3, s + 1));
    globalThis.scrollTo?.({ top: 0, behavior: 'smooth' });
  }

  back(): void {
    this.step.update((s) => Math.max(0, s - 1));
  }

  goTo(i: number): void {
    if (i < this.step()) this.step.set(i);
  }

  placeOrder(): void {
    const guest = this.isGuest();
    const address = this.addressId();
    const guestAddress = this.guestAddress();
    if (guest ? !guestAddress || this.email.invalid : !address) return;
    this.placing.set(true);
    this.placeError.set(null);
    this.emailServerError.set(null);
    const details = this.payment() === 'demo_card' ? this.card.getRawValue() : null;
    const common = {
      billing_same_as_shipping: true,
      shipping_method: this.shipping(),
      payment_method: this.payment(),
      payment_details: details,
      notes: this.notes() || null,
    };
    const request$ = guest
      ? this.checkout.placeGuestOrder({ ...common, email: this.email.value.trim(), shipping_address: guestAddress! })
      : this.checkout.placeOrder({ ...common, shipping_address_id: address });
    request$.subscribe({
      next: ({ result }) => {
        const number = result.order.order_number;
        const token = result.guest_token ?? null;
        if (token) {
          this.guestOrders.remember({ number, token, email: this.email.value.trim(), placed_at: result.order.placed_at, total: result.order.grand_total });
        }
        const guestRef = token ? { number, token } : undefined;
        const query = (extra: Record<string, string> = {}) => ({ ...(token ? { token } : {}), ...extra });
        if (result.razorpay) {
          // Order + stock are reserved; now pay in Razorpay's popup. Laravel verifies the result.
          this.razorpay.checkout(result.order.id, result.razorpay, guestRef).then(
            (outcome) => {
              this.placing.set(false);
              if (outcome.status === 'paid') {
                this.toast.success('Payment successful — order confirmed');
                void this.router.navigate(['/order-success', number], { queryParams: query() });
              } else {
                if (outcome.status === 'failed') this.toast.error(outcome.message);
                else this.toast.info('Payment not completed. Your items are reserved for a short while — you can pay from the next page.');
                void this.router.navigate(['/order-success', number], { queryParams: query({ payment: outcome.status }) });
              }
            },
            (e: Error) => {
              this.placing.set(false);
              this.toast.error(e.message);
              void this.router.navigate(['/order-success', number], { queryParams: query({ payment: 'failed' }) });
            },
          );
          return;
        }
        this.placing.set(false);
        if (result.upi) {
          // Order + stock are reserved; the next page shows the QR code / UPI ID request and waits for the payment.
          this.toast.info('Order reserved — complete your UPI payment');
          void this.router.navigate(['/order-success', number], { queryParams: query() });
          return;
        }
        if (result.payment_successful) {
          this.toast.success('Order placed successfully');
        } else {
          this.toast.error(`Payment failed: ${result.payment.failure_reason ?? 'please try again'}`);
        }
        void this.router.navigate(['/order-success', number], { queryParams: query(result.payment_successful ? {} : { payment: 'failed' }) });
      },
      error: (e) => {
        this.placing.set(false);
        this.placeError.set(errorMessage(e));
        if (guest) {
          // Send contact / address problems back to the first step, next to the field.
          const errors = fieldErrors(e);
          const addr: Record<string, string[]> = {};
          for (const [k, v] of Object.entries(errors)) if (k.startsWith('shipping_address.')) addr[k.slice('shipping_address.'.length)] = v;
          if (errors['email']) this.emailServerError.set(errors['email'][0]);
          if (Object.keys(addr).length) {
            this.guestAddressErrors.set(addr);
            this.editingGuestAddress.set(true);
          }
          if (errors['email'] || Object.keys(addr).length) this.step.set(0);
        }
        this.load();
      },
    });
  }

  cardError(field: 'card_number' | 'card_name' | 'expiry' | 'cvv'): boolean {
    const c = this.card.controls[field];
    return c.invalid && c.touched;
  }
}
