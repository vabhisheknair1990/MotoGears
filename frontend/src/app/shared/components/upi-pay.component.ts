import { ChangeDetectionStrategy, Component, DestroyRef, DOCUMENT, OnInit, computed, inject, input, output, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { DomSanitizer, SafeUrl } from '@angular/platform-browser';
import { EMPTY, Observable, Subscription, catchError, exhaustMap, filter, fromEvent, interval, timer } from 'rxjs';
import { Order, UpiSession } from '../../core/models/api.models';
import { UpiOrderRef, UpiPaymentService } from '../../core/services/upi-payment.service';
import { errorMessage, fieldErrors } from '../../core/utils/http-errors';
import { InrPipe } from '../pipes/inr.pipe';
import { IconComponent } from './icon.component';

export const UPI_ID_PATTERN = /^[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z][a-zA-Z0-9.\-]{1,63}$/;
const POLL_MS = 3000;

/**
 * Pay an order by UPI without leaving the page: scan a QR code with any UPI app. The status is
 * polled every few seconds (and right away when the shopper switches back from their UPI app);
 * `paid` fires with the confirmed order. "UPI ID / app" opens Razorpay's window with only UPI
 * (NPCI retired typing a UPI ID on merchant pages) — except with the demo simulator, which
 * still shows a UPI ID box.
 */
@Component({
  selector: 'app-upi-pay',
  imports: [ReactiveFormsModule, IconComponent, InrPipe],
  template: `
    <div class="upi">
      <div class="seg" role="tablist" aria-label="How do you want to pay?">
        <button type="button" role="tab" [class.on]="tab() === 'qr'" [attr.aria-selected]="tab() === 'qr'" (click)="switchTab('qr')">
          <app-icon name="qr" [size]="17" /> Scan QR code
        </button>
        <button type="button" role="tab" [class.on]="tab() === 'vpa'" [attr.aria-selected]="tab() === 'vpa'" (click)="switchTab('vpa')">
          <app-icon name="smartphone" [size]="17" /> {{ canCollect() ? 'Pay with UPI ID' : 'UPI ID / app' }}
        </button>
      </div>

      @if (phase() === 'paid') {
        <div class="done" role="status">
          <span class="tick"><app-icon name="check" [size]="30" [stroke]="3" /></span>
          <strong>Payment received</strong>
          <span class="text-sm text-muted">Confirming your order…</span>
        </div>
      } @else if (phase() === 'failed') {
        <div class="state-box" role="alert">
          <span class="bad"><app-icon name="alert" [size]="26" /></span>
          <strong>Payment not completed</strong>
          <p class="text-sm text-muted">{{ session()?.message ?? 'The UPI payment did not go through.' }} No money is taken for a failed payment — if it was deducted, your bank returns it automatically.</p>
          <div class="row gap-8 center wrap">
            <button type="button" class="btn btn-primary" (click)="tab() === 'qr' ? showQr() : resetVpa()">Try again</button>
            <button type="button" class="btn" (click)="switchTab(tab() === 'qr' ? 'vpa' : 'qr', true)">{{ tab() === 'qr' ? (canCollect() ? 'Use a UPI ID instead' : 'Use a UPI ID or app instead') : 'Scan a QR code instead' }}</button>
          </div>
        </div>
      } @else if (error(); as err) {
        <div class="state-box" role="alert">
          <span class="bad"><app-icon name="alert" [size]="26" /></span>
          <strong>UPI is not available right now</strong>
          <p class="text-sm text-muted">{{ err }}</p>
          <div class="row gap-8 center wrap">
            <button type="button" class="btn" (click)="tab() === 'qr' ? showQr() : (error.set(null))">Try again</button>
            @if (fallback()) { <button type="button" class="btn btn-primary" (click)="useRazorpay.emit(true)">Pay by UPI with Razorpay</button> }
          </div>
        </div>
      } @else if (tab() === 'qr') {
        <div class="qr-layout">
          <div class="qr-box" [class.expired]="phase() === 'expired'">
            @if (qrImage(); as src) {
              <img [src]="src" width="220" height="220" alt="UPI QR code for {{ amount() | inr }}" />
            } @else {
              <div class="skeleton qr-ph"></div>
            }
            @if (phase() === 'expired') {
              <div class="veil"><strong>QR code expired</strong><button type="button" class="btn btn-sm btn-primary" (click)="showQr()"><app-icon name="refresh" [size]="14" /> Show a new QR</button></div>
            }
          </div>
          <div class="qr-side">
            <div class="amount">{{ amount() | inr }}</div>
            <div class="text-sm text-muted">to {{ session()?.payee ?? 'the store' }}</div>
            @if (appLink(); as link) {
              <a class="btn btn-primary btn-block app-btn" [href]="link"><app-icon name="smartphone" [size]="17" /> Open UPI app to pay</a>
            } @else if (!canCollect() && phase() !== 'paid') {
              <!-- Phones: paying with a UPI app on this device goes through Razorpay's UPI buttons. -->
              <button type="button" class="btn btn-primary btn-block app-btn" (click)="useRazorpay.emit(true)"><app-icon name="smartphone" [size]="17" /> Pay with a UPI app</button>
            }
            <ol class="steps text-sm">
              <li>Open any UPI app — Google Pay, PhonePe, Paytm, BHIM or your bank's app.</li>
              <li>Scan this QR code and check the amount.</li>
              <li>Approve with your UPI PIN. This page updates by itself.</li>
            </ol>
            @if (phase() === 'waiting') {
              <div class="wait" aria-live="polite"><span class="pulse"></span> Waiting for payment… <span class="mono">{{ countdown() }}</span></div>
              <div class="bar"><span [style.width.%]="remainingPct()"></span></div>
            }
          </div>
        </div>
      } @else if (!canCollect()) {
        <div class="via-rzp">
          <span class="ic"><app-icon name="smartphone" [size]="24" /></span>
          <div class="grow">
            <strong>Pay with your UPI ID or UPI app</strong>
            <p class="text-sm text-muted">Razorpay's secure window opens with only UPI. On your phone, tap Google Pay, PhonePe, Paytm or your bank's app; on a computer, scan its QR code or use your UPI ID where it offers one. Then approve <strong>{{ amount() | inr }}</strong> with your UPI PIN.</p>
            <button type="button" class="btn btn-primary" (click)="useRazorpay.emit(true)">Continue to pay {{ amount() | inr }}</button>
          </div>
        </div>
      } @else {
        @if (session()?.flow === 'collect' && phase() !== 'expired') {
          <div class="sent">
            <span class="ic"><app-icon name="smartphone" [size]="24" /></span>
            <div class="grow">
              <strong>Request sent to {{ session()?.vpa }}</strong>@if (session()?.payer_name; as name) { <span class="text-sm text-muted"> · {{ name }}</span> }
              <p class="text-sm mb-0">Open your UPI app, find the request for <strong>{{ amount() | inr }}</strong> from {{ session()?.payee }}, and approve it with your UPI PIN.</p>
              <div class="wait" aria-live="polite"><span class="pulse"></span> Waiting for approval… <span class="mono">{{ countdown() }}</span></div>
              <div class="bar"><span [style.width.%]="remainingPct()"></span></div>
              <button type="button" class="link text-sm" (click)="resetVpa()">Use a different UPI ID</button>
            </div>
          </div>
        } @else {
          @if (phase() === 'expired') { <div class="alert alert-warning mb-16">The payment request expired. Send a new one, or scan a QR code instead.</div> }
          <form class="vpa-form" (submit)="$event.preventDefault(); sendRequest()" novalidate>
            <label class="label" for="upi-vpa">UPI ID</label>
            <div class="row gap-8">
              <input id="upi-vpa" class="input grow" [formControl]="vpa" placeholder="yourname@okhdfcbank" autocomplete="off" autocapitalize="none" spellcheck="false" inputmode="email"
                [class.invalid]="vpaError()" [attr.aria-invalid]="!!vpaError()" aria-describedby="upi-vpa-hint" />
              <button class="btn btn-primary" [disabled]="busy()">@if (busy()) { <span class="spinner"></span> } Send request</button>
            </div>
            @if (vpaError(); as e) { <span class="error-text">{{ e }}</span> }
            @else { <span id="upi-vpa-hint" class="hint">Your UPI ID is in your UPI app's profile, e.g. 98xxxxxx10&#64;ybl or name&#64;oksbi. You'll get a request for {{ amount() | inr }} to approve.</span> }
          </form>
        }
      }

      @if (isDemo() && (phase() === 'waiting' || phase() === 'expired') ) {
        <div class="demo text-sm">
          <app-icon name="info" [size]="16" />
          <span class="grow">Demo mode — no real money moves.
            @if (tab() === 'vpa') { Requests are approved after a few seconds; UPI IDs starting with "fail" are declined. }
          </span>
          <button type="button" class="btn btn-sm" (click)="simulate('success')">Simulate payment</button>
          <button type="button" class="btn btn-sm btn-ghost" (click)="simulate('failure')">Simulate failure</button>
        </div>
      } @else if (isDemo() && tab() === 'vpa' && phase() === 'idle') {
        <p class="demo-hint text-xs text-muted">Demo mode: any UPI ID is approved after a few seconds; one starting with "fail" is declined.</p>
      }
      <p class="secure text-xs text-muted"><app-icon name="lock" [size]="13" /> Your UPI PIN is only ever entered in your UPI app — never on this website.</p>
    </div>
  `,
  styles: `
    .upi { display: grid; gap: 16px; }
    .seg { display: grid; grid-template-columns: 1fr 1fr; background: var(--line-2, #eef1f5); border-radius: 12px; padding: 4px; gap: 4px; }
    .seg button { display: inline-flex; align-items: center; justify-content: center; gap: 7px; border: 0; background: transparent; padding: 10px 8px; border-radius: 9px; font: inherit; font-weight: 600; font-size: 14px; color: var(--muted); cursor: pointer; }
    .seg button.on { background: #fff; color: var(--ink, #111); box-shadow: 0 1px 3px rgb(0 0 0 / .12); }
    .qr-layout { display: grid; grid-template-columns: auto 1fr; gap: 24px; align-items: center; }
    .qr-box { position: relative; width: 244px; height: 244px; padding: 12px; background: #fff; border: 1px solid var(--line); border-radius: 14px; }
    .qr-box img, .qr-ph { width: 220px; height: 220px; display: block; border-radius: 6px; }
    .qr-box.expired img { filter: blur(3px); opacity: .35; }
    .veil { position: absolute; inset: 0; display: grid; place-content: center; gap: 10px; text-align: center; }
    .amount { font-family: var(--font-display, inherit); font-size: 30px; font-weight: 700; line-height: 1.1; }
    .steps { margin: 14px 0 12px; padding-left: 18px; display: grid; gap: 4px; color: var(--muted); }
    .app-btn { display: none; margin-top: 14px; }
    .wait { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; margin-top: 4px; }
    .wait .mono { margin-left: auto; font-weight: 500; color: var(--muted); }
    .pulse { width: 10px; height: 10px; border-radius: 50%; background: var(--success, #16a34a); box-shadow: 0 0 0 0 rgb(22 163 74 / .5); animation: pulse 1.6s infinite; flex: none; }
    @keyframes pulse { 70% { box-shadow: 0 0 0 9px rgb(22 163 74 / 0); } 100% { box-shadow: 0 0 0 0 rgb(22 163 74 / 0); } }
    .bar { height: 4px; border-radius: 4px; background: var(--line-2, #eef1f5); margin-top: 8px; overflow: hidden; }
    .bar span { display: block; height: 100%; background: var(--success, #16a34a); transition: width 1s linear; }
    .sent { display: flex; gap: 14px; padding: 16px; border: 1px solid var(--line); border-radius: 12px; background: var(--success-50, #f0fdf4); }
    .sent .ic { width: 44px; height: 44px; border-radius: 50%; display: grid; place-items: center; background: #fff; color: var(--success, #16a34a); flex: none; }
    .sent p { margin: 6px 0 10px; }
    .via-rzp { display: flex; gap: 14px; padding: 16px; border: 1px solid var(--line); border-radius: 12px; }
    .via-rzp .ic { width: 44px; height: 44px; border-radius: 50%; display: grid; place-items: center; background: var(--line-2, #eef1f5); flex: none; }
    .via-rzp p { margin: 6px 0 12px; }
    .vpa-form { display: grid; gap: 6px; }
    .done, .state-box { display: grid; justify-items: center; text-align: center; gap: 6px; padding: 22px 12px; }
    .tick { width: 56px; height: 56px; border-radius: 50%; display: grid; place-items: center; background: var(--success, #16a34a); color: #fff; }
    .bad { width: 52px; height: 52px; border-radius: 50%; display: grid; place-items: center; background: var(--danger-50); color: var(--danger); }
    .state-box p { max-width: 440px; margin: 0 0 8px; }
    .demo { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 10px; background: var(--info-50); color: var(--info); }
    .demo .grow { min-width: 200px; }
    .demo-hint, .secure { margin: 0; display: flex; align-items: center; gap: 6px; }
    @media (max-width: 640px) {
      .qr-layout { grid-template-columns: 1fr; justify-items: center; text-align: center; gap: 14px; }
      .qr-side { width: 100%; }
      .steps { text-align: left; }
    }
    /* Phones: the UPI app is on this device — open it directly. */
    @media (pointer: coarse), (max-width: 640px) { .app-btn { display: inline-flex; justify-content: center; } }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UpiPayComponent implements OnInit {
  private readonly upi = inject(UpiPaymentService);
  private readonly sanitizer = inject(DomSanitizer);
  private readonly destroyRef = inject(DestroyRef);
  private readonly doc = inject(DOCUMENT);

  readonly order = input.required<Order>();
  /** Guest orders: the secret order token. */
  readonly token = input<string | null>(null);
  readonly start = input<'qr' | 'vpa'>('qr');

  readonly paid = output<Order>();
  /** The order changed (e.g. a failed attempt was recorded). */
  readonly orderChange = output<Order>();
  /** Pay in Razorpay's window instead; `true` = show only UPI there. */
  readonly useRazorpay = output<boolean>();

  protected readonly tab = signal<'qr' | 'vpa'>('qr');
  protected readonly session = signal<UpiSession | null>(null);
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly fallback = signal<string | null>(null);
  protected readonly vpaServerError = signal<string | null>(null);
  protected readonly vpa = new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.maxLength(100), Validators.pattern(UPI_ID_PATTERN)] });
  private readonly qrSession = signal<UpiSession | null>(null);
  private readonly now = signal(Date.now());
  private poller?: Subscription;
  private ticker?: Subscription;

  protected readonly amount = computed(() => this.session()?.amount ?? this.order().grand_total);
  protected readonly isDemo = computed(() => !!this.session()?.demo);
  /** Whether we can take a UPI ID on this page (only the demo simulator; real UPI IDs go via Razorpay). */
  protected readonly canCollect = computed(() => (this.session() ?? this.qrSession())?.collect !== false);
  protected readonly qrImage = computed(() => this.qrSession()?.qr ?? null);
  protected readonly appLink = computed<SafeUrl | null>(() => {
    const link = this.qrSession()?.link;
    // Only ever a upi:// link produced by our server; Angular would otherwise mark it unsafe.
    return link && link.startsWith('upi://') && this.phase() === 'waiting' ? this.sanitizer.bypassSecurityTrustUrl(link) : null;
  });

  /** idle (nothing started on this tab) · waiting · expired · failed · paid */
  protected readonly phase = computed(() => {
    const s = this.session();
    if (!s) return 'idle';
    if (s.status === 'paid' || s.status === 'failed') return s.status;
    const wanted = this.tab() === 'qr' ? 'intent' : 'collect';
    if (s.flow !== wanted) return 'idle';
    if (s.status === 'expired' || this.remaining() === 0) return 'expired';
    return s.status === 'pending' ? 'waiting' : 'idle';
  });

  private readonly expiresAt = computed(() => {
    const s = this.session();
    return s?.expires_at ? Date.parse(s.expires_at) : null;
  });
  private readonly qrTotal = signal(600);
  private readonly collectTotal = signal(300);
  private readonly total = computed(() => (this.session()?.flow === 'collect' ? this.collectTotal() : this.qrTotal()));
  protected readonly remaining = computed(() => {
    const at = this.expiresAt();
    return at === null ? null : Math.max(0, Math.round((at - this.now()) / 1000));
  });
  protected readonly countdown = computed(() => {
    const r = this.remaining();
    if (r === null) return '';
    return `${Math.floor(r / 60)}:${String(r % 60).padStart(2, '0')}`;
  });
  protected readonly remainingPct = computed(() => Math.min(100, ((this.remaining() ?? 0) / this.total()) * 100));

  protected readonly vpaError = this.vpaServerError.asReadonly();

  ngOnInit(): void {
    this.tab.set(this.start());
    // Coming back from the UPI app: check straight away instead of waiting for the next poll.
    fromEvent(this.doc, 'visibilitychange').pipe(
      filter(() => this.doc.visibilityState === 'visible' && this.phase() === 'waiting'),
      exhaustMap(() => this.upi.status(this.ref()).pipe(catchError(() => EMPTY))),
      takeUntilDestroyed(this.destroyRef),
    ).subscribe((s) => this.apply(s));
    this.destroyRef.onDestroy(() => this.stopTimers());

    // Resume an attempt already in progress (e.g. page reloaded), otherwise start on the QR tab.
    this.upi.status(this.ref()).subscribe({
      next: (s) => {
        if (s.status === 'paid') return this.apply(s);
        if (s.status === 'pending' && s.flow === 'collect') {
          this.tab.set('vpa');
          return this.apply(s);
        }
        if (this.tab() === 'qr') this.showQr();
      },
      error: () => { if (this.tab() === 'qr') this.showQr(); },
    });
  }

  protected switchTab(tab: 'qr' | 'vpa', restart = false): void {
    if (this.phase() === 'paid') return;
    this.tab.set(tab);
    this.error.set(null);
    if (tab === 'qr' && (restart || !this.qrSession() || this.session()?.status === 'failed')) {
      this.showQr();
    } else if (tab === 'qr' && this.qrSession()) {
      this.apply(this.qrSession()!, false);
    } else if (restart || this.session()?.status === 'failed') {
      this.resetVpa();
    }
  }

  showQr(): void {
    this.run(this.upi.qr(this.ref()), (s) => {
      this.qrSession.set(s);
      this.qrTotal.set(Math.max(60, s.expires_in ?? 600));
    });
  }

  sendRequest(): void {
    this.vpaServerError.set(null);
    this.vpa.setValue(this.vpa.value.trim());
    if (this.vpa.invalid) {
      this.vpaServerError.set(this.vpa.value ? 'Enter a valid UPI ID, like name@okhdfcbank.' : 'Enter your UPI ID.');
      return;
    }
    this.run(this.upi.collect(this.ref(), this.vpa.value), (s) => this.collectTotal.set(Math.max(60, s.expires_in ?? 300)));
  }

  resetVpa(): void {
    this.session.update((s) => (s && s.status !== 'paid' ? { ...s, status: 'idle', flow: null } : s));
    this.vpaServerError.set(null);
    globalThis.setTimeout?.(() => (this.doc.getElementById('upi-vpa') as HTMLInputElement | null)?.focus(), 0);
  }

  protected simulate(outcome: 'success' | 'failure'): void {
    this.upi.simulate(this.ref(), outcome).subscribe({ next: (s) => this.apply(s), error: (e) => this.error.set(errorMessage(e)) });
  }

  private run(request: Observable<UpiSession>, after: (s: UpiSession) => void): void {
    this.busy.set(true);
    this.error.set(null);
    request.subscribe({
      next: (s) => {
        this.busy.set(false);
        after(s);
        this.apply(s);
      },
      error: (e: unknown) => {
        this.busy.set(false);
        if (e instanceof HttpErrorResponse && e.status === 409) {
          // Already paid (e.g. a late confirmation): pick up the final state.
          this.upi.status(this.ref()).subscribe((s) => this.apply(s));
          return;
        }
        if (e instanceof HttpErrorResponse && e.status === 422 && fieldErrors(e)['vpa']) {
          this.vpaServerError.set(fieldErrors(e)['vpa'][0] === 'UPI ID not found.' ? errorMessage(e) : fieldErrors(e)['vpa'][0]);
          return;
        }
        this.fallback.set(e instanceof HttpErrorResponse ? ((e.error as { fallback?: string } | null)?.fallback ?? null) : null);
        this.error.set(errorMessage(e, 'Could not start the UPI payment.'));
      },
    });
  }

  private apply(s: UpiSession, fromServer = true): void {
    this.session.set(s);
    if (fromServer && s.flow === 'intent' && s.qr) this.qrSession.set(s);
    if (s.order) this.orderChange.emit(s.order);
    if (s.status === 'pending') this.startTimers();
    else this.stopTimers();
    if (s.status === 'paid' && s.order) {
      const order = s.order;
      // A short beat on "Payment received" before the page moves on.
      timer(900).pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => this.paid.emit(order));
    }
  }

  private startTimers(): void {
    this.ticker ??= interval(1000).subscribe(() => this.now.set(Date.now()));
    this.poller ??= interval(POLL_MS).pipe(
      // A failed poll (network blip, rate limit) is skipped; the next one tries again.
      exhaustMap(() => this.upi.status(this.ref()).pipe(catchError(() => EMPTY))),
    ).subscribe((s) => {
      // Keep showing the tab the shopper is on; only terminal states take over.
      if (s.status === 'paid' || s.status === 'failed' || s.flow === this.session()?.flow) this.apply(s);
    });
  }

  private stopTimers(): void {
    this.poller?.unsubscribe();
    this.ticker?.unsubscribe();
    this.poller = this.ticker = undefined;
  }

  private ref(): UpiOrderRef {
    const o = this.order();
    return { id: o.id, number: o.order_number, token: this.token() };
  }
}
