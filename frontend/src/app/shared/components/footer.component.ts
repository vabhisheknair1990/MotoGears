import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { catchError, of } from 'rxjs';
import { CmsService } from '../../core/services/cms.service';
import { ToastService } from '../../core/services/toast.service';
import { errorMessage } from '../../core/utils/http-errors';
import { IconComponent } from './icon.component';

@Component({
  selector: 'app-footer',
  imports: [RouterLink, FormsModule, IconComponent],
  template: `
    <section class="trust">
      <div class="container trust-grid">
        <div><app-icon name="shield" [size]="26" /><span><strong>Genuine parts</strong>Direct from brands & distributors</span></div>
        <div><app-icon name="check-circle" [size]="26" /><span><strong>Fitment guarantee</strong>Free return if it doesn't fit</span></div>
        <div><app-icon name="truck" [size]="26" /><span><strong>Fast delivery</strong>Across 19,000+ PIN codes</span></div>
        <div><app-icon name="rotate" [size]="26" /><span><strong>Easy returns</strong>7-day hassle-free returns</span></div>
      </div>
    </section>
    <footer class="foot">
      <div class="container cols">
        <div class="about">
          <a routerLink="/" class="logo"><span class="mark">M</span><span class="word">Moto<b>Gears</b></span></a>
          <p>{{ settings()?.store_tagline ?? 'Genuine parts. Perfect fit.' }} Car & bike parts and accessories from the brands mechanics trust.</p>
          <p class="contact"><app-icon name="phone" [size]="15" /> {{ settings()?.support_phone }}<br /><app-icon name="mail" [size]="15" /> {{ settings()?.support_email }}</p>
        </div>
        <div>
          <h4>Shop</h4>
          <a routerLink="/category/car-parts">Car parts</a>
          <a routerLink="/category/motorcycle-parts">Motorcycle parts</a>
          <a routerLink="/category/accessories">Accessories</a>
          <a routerLink="/category/engine-oil">Engine oils</a>
          <a routerLink="/brands">All brands</a>
          <a routerLink="/vehicles">Shop by vehicle</a>
        </div>
        <div>
          <h4>Help</h4>
          <a routerLink="/account/orders">Track order</a>
          <a routerLink="/faq">FAQs</a>
          <a routerLink="/page/shipping-policy">Shipping policy</a>
          <a routerLink="/page/returns-policy">Returns & refunds</a>
          <a routerLink="/contact">Contact us</a>
        </div>
        <div>
          <h4>Company</h4>
          <a routerLink="/about">About us</a>
          <a routerLink="/blog">Blog</a>
          <a routerLink="/page/privacy-policy">Privacy policy</a>
          <a routerLink="/page/terms">Terms & conditions</a>
        </div>
        <div class="news">
          <h4>Offers & maintenance tips</h4>
          <p>One email a fortnight. No spam.</p>
          <form (ngSubmit)="subscribe()" class="input-group">
            <input class="input" type="email" name="email" [(ngModel)]="email" placeholder="Your email" aria-label="Email address" required />
            <button class="btn btn-primary" [disabled]="busy()">Subscribe</button>
          </form>
          <div class="pay">
            <span>COD</span><span>Visa</span><span>Mastercard</span><span>RuPay</span><span>UPI</span>
          </div>
        </div>
      </div>
      <div class="container legal">
        <span>© {{ year }} {{ settings()?.store_name ?? 'MotoGears' }}. Demo store — no real payments are processed.</span>
        <span>GST invoices on every order</span>
      </div>
    </footer>
  `,
  styleUrl: './footer.component.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FooterComponent {
  private readonly cms = inject(CmsService);
  private readonly toast = inject(ToastService);
  protected readonly settings = toSignal(this.cms.getSettings().pipe(catchError(() => of(null))), { initialValue: null });
  protected readonly year = new Date().getFullYear();
  protected email = '';
  protected readonly busy = signal(false);

  subscribe(): void {
    if (!this.email) return;
    this.busy.set(true);
    this.cms.subscribe(this.email).subscribe({
      next: (msg) => { this.toast.success(msg); this.email = ''; this.busy.set(false); },
      error: (e) => { this.toast.error(errorMessage(e)); this.busy.set(false); },
    });
  }
}
