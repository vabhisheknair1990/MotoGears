import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { CartDrawerComponent } from '../../../shared/components/cart-drawer.component';
import { FooterComponent } from '../../../shared/components/footer.component';
import { HeaderComponent } from '../../../shared/components/header.component';

@Component({
  selector: 'app-storefront-layout',
  imports: [RouterOutlet, HeaderComponent, FooterComponent, CartDrawerComponent],
  template: `
    <app-header />
    <main id="main" tabindex="-1"><router-outlet /></main>
    <app-footer />
    <app-cart-drawer />
  `,
  styles: `:host { display: flex; flex-direction: column; min-height: 100vh; } main { flex: 1; outline: none; }`,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class StorefrontLayoutComponent {}
