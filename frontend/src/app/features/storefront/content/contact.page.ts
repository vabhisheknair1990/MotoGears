import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { catchError, of } from 'rxjs';
import { AuthStore } from '../../../core/state/auth.store';
import { CmsService } from '../../../core/services/cms.service';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { applyServerErrors, errorMessage } from '../../../core/utils/http-errors';
import { BreadcrumbComponent } from '../../../shared/components/breadcrumb.component';
import { IconComponent } from '../../../shared/components/icon.component';

@Component({
  selector: 'app-contact-page',
  imports: [ReactiveFormsModule, BreadcrumbComponent, IconComponent],
  template: `
    <div class="container">
      <div class="page-title">
        <app-breadcrumb [items]="[{ label: 'Home', link: '/' }, { label: 'Contact us' }]" />
        <h1>Contact us</h1>
        <p class="text-muted mb-0">Not sure a part fits? Our fitment experts reply within one business day.</p>
      </div>
      <div class="layout">
        <aside class="stack">
          <div class="card card-body info">
            <app-icon name="phone" [size]="22" />
            <div><strong>Call us</strong><a class="link" [href]="'tel:' + (settings()?.support_phone ?? '')">{{ settings()?.support_phone ?? '—' }}</a><span class="text-xs text-muted">Mon–Sat, 9:00–19:00 IST</span></div>
          </div>
          <div class="card card-body info">
            <app-icon name="mail" [size]="22" />
            <div><strong>Email</strong><a class="link" [href]="'mailto:' + (settings()?.support_email ?? '')">{{ settings()?.support_email ?? '—' }}</a><span class="text-xs text-muted">Include your order number if you have one</span></div>
          </div>
          <div class="card card-body info">
            <app-icon name="pin" [size]="22" />
            <div><strong>Warehouse &amp; office</strong><span class="text-sm">{{ settings()?.store_address ?? '—' }}</span></div>
          </div>
        </aside>
        <section class="card card-body">
          @if (sent()) {
            <div class="done">
              <app-icon name="check-circle" [size]="44" />
              <h2>Thanks, we've got your message</h2>
              <p class="text-muted">{{ sent() }}</p>
              <button class="btn btn-ghost" type="button" (click)="reset()">Send another message</button>
            </div>
          } @else {
            <h2 class="mt-0">Send us a message</h2>
            @if (error()) { <div class="alert alert-danger"><app-icon name="alert" [size]="18" /> {{ error() }}</div> }
            <form [formGroup]="form" (ngSubmit)="submit()" novalidate>
              <div class="form-grid cols-2">
                <div class="field">
                  <label class="label" for="c-name">Name <span class="req">*</span></label>
                  <input id="c-name" class="input" formControlName="name" autocomplete="name" />
                  @if (show('name')) { <span class="error-text">{{ msg('name', 'Please enter your name.') }}</span> }
                </div>
                <div class="field">
                  <label class="label" for="c-email">Email <span class="req">*</span></label>
                  <input id="c-email" class="input" type="email" formControlName="email" autocomplete="email" />
                  @if (show('email')) { <span class="error-text">{{ msg('email', 'Enter a valid email address.') }}</span> }
                </div>
                <div class="field">
                  <label class="label" for="c-phone">Phone</label>
                  <input id="c-phone" class="input" formControlName="phone" inputmode="tel" autocomplete="tel" />
                  @if (show('phone')) { <span class="error-text">{{ msg('phone', 'Enter a valid phone number.') }}</span> }
                </div>
                <div class="field">
                  <label class="label" for="c-subject">Subject <span class="req">*</span></label>
                  <select id="c-subject" class="select" formControlName="subject">
                    <option value="">Choose a topic</option>
                    @for (s of subjects; track s) { <option [value]="s">{{ s }}</option> }
                  </select>
                  @if (show('subject')) { <span class="error-text">{{ msg('subject', 'Please choose a subject.') }}</span> }
                </div>
              </div>
              <div class="field">
                <label class="label" for="c-msg">Message <span class="req">*</span></label>
                <textarea id="c-msg" class="textarea" rows="6" formControlName="message" placeholder="Tell us your vehicle (make, model, year, variant) and what you need help with."></textarea>
                @if (show('message')) { <span class="error-text">{{ msg('message', 'Please write at least 10 characters.') }}</span> }
                <span class="hint">{{ form.controls.message.value.length }}/3000</span>
              </div>
              <!-- Honeypot: hidden from people, bots fill it and the API rejects the request. -->
              <input class="hp" tabindex="-1" autocomplete="off" aria-hidden="true" formControlName="website" />
              <button class="btn btn-primary btn-lg" [disabled]="busy()">@if (busy()) { <span class="spinner"></span> } Send message</button>
            </form>
          }
        </section>
      </div>
    </div>
  `,
  styles: `
    .layout { display: grid; gap: 24px; margin-bottom: 48px; }
    @media (min-width: 900px) { .layout { grid-template-columns: 320px 1fr; align-items: start; } }
    .info { display: flex; gap: 14px; align-items: flex-start; color: var(--brand); }
    .info div { display: flex; flex-direction: column; gap: 2px; color: var(--ink); }
    .hp { position: absolute; left: -9999px; width: 1px; height: 1px; opacity: 0; }
    .done { text-align: center; padding: 32px 8px; color: var(--success, #16a34a); }
    .done h2, .done p { color: var(--ink); }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ContactPage {
  private readonly cms = inject(CmsService);
  private readonly toast = inject(ToastService);
  private readonly auth = inject(AuthStore);
  protected readonly settings = toSignal(this.cms.getSettings().pipe(catchError(() => of(null))));
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly sent = signal<string | null>(null);
  protected readonly subjects = ['Fitment question', 'Order status', 'Returns & refunds', 'Payment issue', 'Bulk / workshop order', 'Other'];

  protected readonly form = inject(FormBuilder).nonNullable.group({
    name: [this.auth.user()?.name ?? '', [Validators.required, Validators.maxLength(100)]],
    email: [this.auth.user()?.email ?? '', [Validators.required, Validators.email, Validators.maxLength(190)]],
    phone: ['', [Validators.pattern(/^[0-9+\-\s]{8,20}$/)]],
    subject: ['', [Validators.required, Validators.maxLength(150)]],
    message: ['', [Validators.required, Validators.minLength(10), Validators.maxLength(3000)]],
    website: [''],
  });

  constructor() {
    inject(SeoService).set({ title: 'Contact us', description: 'Get in touch with our fitment experts for help choosing the right part for your car or bike.' });
  }

  protected show(name: keyof typeof this.form.controls): boolean {
    const c = this.form.controls[name];
    return c.invalid && (c.touched || c.dirty);
  }

  protected msg(name: keyof typeof this.form.controls, fallback: string): string {
    return (this.form.controls[name].errors?.['server'] as string | undefined) ?? fallback;
  }

  protected submit(): void {
    this.form.markAllAsTouched();
    if (this.form.invalid || this.busy()) return;
    this.busy.set(true);
    this.error.set(null);
    const v = this.form.getRawValue();
    this.cms.submitContact({ name: v.name, email: v.email, phone: v.phone || null, subject: v.subject, message: v.message }).subscribe({
      next: (message) => {
        this.busy.set(false);
        this.sent.set(message);
        this.toast.success('Message sent');
      },
      error: (err) => {
        this.busy.set(false);
        applyServerErrors(this.form, err);
        this.error.set(errorMessage(err));
      },
    });
  }

  protected reset(): void {
    this.sent.set(null);
    this.form.controls.subject.reset('');
    this.form.controls.message.reset('');
  }
}
