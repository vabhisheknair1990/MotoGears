import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Category } from '../../core/models/api.models';
import { IconComponent } from './icon.component';

@Component({
  selector: 'app-category-card',
  imports: [RouterLink, IconComponent],
  template: `
    @let c = category();
    <a class="cat" [routerLink]="['/category', c.slug]">
      @if (c.image) { <img [src]="c.image" [alt]="c.name" loading="lazy" /> }
      <div class="overlay">
        <span class="name">{{ c.name }}</span>
        @if (c.products_count !== undefined) { <span class="count">{{ c.products_count }} products</span> }
        <span class="go"><app-icon name="arrow-right" [size]="16" /></span>
      </div>
    </a>
  `,
  styles: `
    .cat { position: relative; display: block; aspect-ratio: 3 / 2; border-radius: var(--radius); overflow: hidden; background: var(--dark-2); color: #fff; }
    .cat img { width: 100%; height: 100%; object-fit: cover; transition: transform .35s; }
    .cat:hover img { transform: scale(1.05); }
    .overlay { position: absolute; inset: auto 0 0 0; padding: 12px 14px; background: linear-gradient(transparent, rgba(0,0,0,.75)); display: flex; flex-direction: column; }
    .name { font-weight: 700; font-size: 15px; }
    .count { font-size: 12px; opacity: .8; }
    .go { position: absolute; right: 12px; bottom: 14px; width: 28px; height: 28px; border-radius: 50%; background: var(--brand); display: grid; place-items: center; opacity: 0; transform: translateX(-6px); transition: .2s; }
    .cat:hover .go { opacity: 1; transform: none; }
    .cat:hover { color: #fff; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CategoryCardComponent {
  readonly category = input.required<Category>();
}
