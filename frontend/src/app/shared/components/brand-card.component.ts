import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Brand } from '../../core/models/api.models';

@Component({
  selector: 'app-brand-card',
  imports: [RouterLink],
  template: `
    @let b = brand();
    <a class="brand" [routerLink]="['/brand', b.slug]" [attr.aria-label]="b.name">
      @if (b.logo) { <img [src]="b.logo" [alt]="b.name + ' logo'" loading="lazy" /> } @else { <span>{{ b.name }}</span> }
    </a>
  `,
  styles: `
    .brand { display: grid; place-items: center; height: 88px; padding: 10px 16px; background: #fff; border: 1px solid var(--line); border-radius: var(--radius); transition: .2s; }
    .brand:hover { border-color: var(--ink); box-shadow: var(--shadow-sm); }
    img { max-height: 64px; width: auto; filter: grayscale(.2); }
    .brand:hover img { filter: none; }
    span { font-weight: 800; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BrandCardComponent {
  readonly brand = input.required<Brand>();
}
