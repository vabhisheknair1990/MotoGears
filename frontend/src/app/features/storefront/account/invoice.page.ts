import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { takeUntilDestroyed, toObservable } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { catchError, of, switchMap } from 'rxjs';
import { Invoice } from '../../../core/models/api.models';
import { ApiService } from '../../../core/api/api.service';
import { IconComponent } from '../../../shared/components/icon.component';
import { InrPipe } from '../../../shared/pipes/inr.pipe';

/** Printable invoice view (browser print → Save as PDF) rendered from the invoice API. */
@Component({
  selector: 'app-invoice-page',
  imports: [RouterLink, IconComponent, InrPipe],
  template: `
    <div class="bar no-print">
      <a [routerLink]="admin() ? ['/admin/orders', id()] : ['/account/orders', id()]" class="btn btn-sm"><app-icon name="arrow-left" [size]="15" /> Back to order</a>
      <div class="row gap-8">
        <button class="btn btn-sm" (click)="download()"><app-icon name="download" [size]="15" /> Download</button>
        <button class="btn btn-sm btn-primary" (click)="print()"><app-icon name="printer" [size]="15" /> Print / Save as PDF</button>
      </div>
    </div>
    @if (error()) {
      <p class="text-center text-muted mt-32">Invoice not available.</p>
    } @else if (inv(); as i) {
      <article class="sheet">
        <header class="top">
          <div>
            <div class="logo">{{ i.seller.name }}<span>.</span></div>
            <p>{{ i.seller.address }}<br />GSTIN: {{ i.seller.gstin }}<br />{{ i.seller.email }} · {{ i.seller.phone }}</p>
          </div>
          <div class="text-right">
            <h1>TAX INVOICE</h1>
            <p><strong>{{ i.invoice_number }}</strong><br />Date: {{ i.invoice_date }}<br />Order: {{ i.order_number }}</p>
          </div>
        </header>
        <section class="parties">
          <div><h4>Billed to</h4><p><strong>{{ i.billing_address.name }}</strong><br />{{ i.billing_address.line1 }}<br />{{ i.billing_address.city }}, {{ i.billing_address.state }} {{ i.billing_address.postal_code }}<br />{{ i.billing_address.phone }}</p></div>
          <div><h4>Shipped to</h4><p><strong>{{ i.shipping_address.name }}</strong><br />{{ i.shipping_address.line1 }}<br />{{ i.shipping_address.city }}, {{ i.shipping_address.state }} {{ i.shipping_address.postal_code }}</p></div>
          <div><h4>Payment</h4><p>{{ i.payment_method }}<br />Status: {{ i.payment_status }}@if (i.transaction_id) {<br />Txn: {{ i.transaction_id }}}</p></div>
        </section>
        <table class="items">
          <thead><tr><th>#</th><th>Item</th><th>HSN</th><th class="r">Qty</th><th class="r">Rate</th><th class="r">Disc.</th><th class="r">Taxable</th><th class="r">GST</th><th class="r">Total</th></tr></thead>
          <tbody>
            @for (it of i.items; track $index) {
              <tr>
                <td>{{ $index + 1 }}</td>
                <td><strong>{{ it.name }}</strong><br /><small>SKU {{ it.sku }}</small></td>
                <td>{{ it.hsn }}</td><td class="r">{{ it.quantity }}</td><td class="r">{{ it.unit_price | inr }}</td><td class="r">{{ it.discount | inr }}</td>
                <td class="r">{{ it.taxable_value | inr }}</td><td class="r">{{ it.tax_amount | inr }}<br /><small>{{ it.tax_rate }}% {{ i.tax_split }}</small></td><td class="r">{{ it.total | inr }}</td>
              </tr>
            }
          </tbody>
        </table>
        <table class="totals">
          <tr><td>Subtotal</td><td class="r">{{ i.totals.subtotal | inr }}</td></tr>
          @if (i.totals.discount > 0) { <tr><td>Discount @if (i.coupon_code) { ({{ i.coupon_code }}) }</td><td class="r">−{{ i.totals.discount | inr }}</td></tr> }
          <tr><td>Shipping</td><td class="r">{{ i.totals.shipping > 0 ? (i.totals.shipping | inr) : 'Free' }}</td></tr>
          <tr><td>GST ({{ i.tax_split }})</td><td class="r">{{ i.totals.tax | inr }}</td></tr>
          <tr class="grand"><td>Grand total</td><td class="r">{{ i.totals.grand_total | inr }}</td></tr>
        </table>
        <footer>This is a computer-generated invoice and does not require a signature.</footer>
      </article>
    } @else {
      <div class="sheet"><div class="skeleton" style="height: 400px"></div></div>
    }
  `,
  styles: `
    :host { display: block; background: var(--bg); min-height: 100vh; padding: 16px; }
    .bar { max-width: 900px; margin: 0 auto 16px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
    .sheet { max-width: 900px; margin: 0 auto; background: #fff; padding: 36px; border-radius: var(--radius); box-shadow: var(--shadow); font-size: 13px; }
    .top { display: flex; justify-content: space-between; gap: 16px; border-bottom: 3px solid var(--brand); padding-bottom: 16px; }
    .logo { font-family: var(--font-display); font-size: 26px; font-weight: 800; } .logo span { color: var(--brand); }
    h1 { font-size: 24px; margin: 0 0 6px; }
    p { color: var(--ink-2); margin: 4px 0; }
    .parties { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin: 20px 0; }
    h4 { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); margin: 0 0 4px; }
    table { width: 100%; border-collapse: collapse; }
    .items th { background: var(--dark); color: #fff; text-align: left; padding: 8px; font-size: 11px; text-transform: uppercase; }
    .items td { padding: 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
    .r { text-align: right; }
    small { color: var(--muted); }
    .totals { width: 320px; margin: 16px 0 0 auto; }
    .totals td { padding: 4px 8px; }
    .grand td { font-weight: 800; font-size: 16px; border-top: 2px solid var(--ink); padding-top: 8px; }
    footer { margin-top: 36px; color: var(--muted); font-size: 11px; border-top: 1px solid var(--line); padding-top: 12px; }
    @media (max-width: 640px) { .sheet { padding: 16px; } .parties { grid-template-columns: 1fr; } .items { font-size: 11px; } }
    @media print { :host { padding: 0; background: #fff; } .sheet { box-shadow: none; padding: 0; } }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class InvoicePage {
  readonly id = input.required<string>();
  /** Route data: true when opened from the admin console (uses the admin invoice endpoints). */
  readonly admin = input(false);
  private readonly api = inject(ApiService);
  private base(): string {
    return this.admin() ? 'admin/orders' : 'orders';
  }
  protected readonly inv = signal<Invoice | null>(null);
  protected readonly error = signal(false);

  constructor() {
    toObservable(this.id).pipe(
      switchMap((id) => this.api.get<Invoice>(`${this.base()}/${Number(id)}/invoice`).pipe(catchError(() => { this.error.set(true); return of(null); }))),
      takeUntilDestroyed(),
    ).subscribe((i) => this.inv.set(i));
  }

  print(): void {
    globalThis.print?.();
  }

  download(): void {
    this.api.blob(`${this.base()}/${Number(this.id())}/invoice/download`).subscribe((blob) => {
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `invoice-${this.inv()?.order_number ?? this.id()}.html`;
      a.click();
      URL.revokeObjectURL(url);
    });
  }
}
