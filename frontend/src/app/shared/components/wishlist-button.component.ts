import { ChangeDetectionStrategy, Component, computed, inject, input, signal } from '@angular/core';
import { Router } from '@angular/router';
import { WishlistService } from '../../core/services/wishlist.service';
import { ToastService } from '../../core/services/toast.service';
import { AuthStore } from '../../core/state/auth.store';
import { IconComponent } from './icon.component';

@Component({
  selector: 'app-wishlist-button',
  imports: [IconComponent],
  template: `
    <button type="button" [class]="variant() === 'icon' ? 'icon-btn wl' : 'btn btn-lg wl-btn'" [class.on]="active()"
            [disabled]="busy()" (click)="toggle($event)" [attr.aria-pressed]="active()"
            [attr.aria-label]="active() ? 'Remove from wishlist' : 'Add to wishlist'">
      <app-icon [name]="active() ? 'heart-filled' : 'heart'" [size]="variant() === 'icon' ? 18 : 20" />
      @if (variant() === 'button') { <span>{{ active() ? 'Wishlisted' : 'Wishlist' }}</span> }
    </button>
  `,
  styles: `
    .wl { background: rgba(255,255,255,.92); box-shadow: var(--shadow-sm); width: 36px; height: 36px; color: var(--ink-2); }
    .wl:hover { background: #fff; color: var(--brand); }
    .on { color: var(--brand) !important; }
    .wl-btn.on { border-color: var(--brand); }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class WishlistButtonComponent {
  private readonly wishlist = inject(WishlistService);
  private readonly auth = inject(AuthStore);
  private readonly toast = inject(ToastService);
  private readonly router = inject(Router);

  readonly productId = input.required<number>();
  readonly variant = input<'icon' | 'button'>('icon');
  protected readonly busy = signal(false);
  protected readonly active = computed(() => this.wishlist.ids().has(this.productId()));

  toggle(event: Event): void {
    event.preventDefault();
    event.stopPropagation();
    if (!this.auth.isLoggedIn()) {
      this.toast.info('Log in to save items to your wishlist.');
      void this.router.navigate(['/login'], { queryParams: { returnUrl: this.router.url } });
      return;
    }
    this.busy.set(true);
    const wasActive = this.active();
    const req = wasActive ? this.wishlist.remove(this.productId()) : this.wishlist.add(this.productId());
    req.subscribe({
      next: () => {
        this.busy.set(false);
        this.toast.success(wasActive ? 'Product removed from wishlist' : 'Product added to wishlist', wasActive ? undefined : { label: 'View', url: '/account/wishlist' });
      },
      error: () => this.busy.set(false),
    });
  }
}
