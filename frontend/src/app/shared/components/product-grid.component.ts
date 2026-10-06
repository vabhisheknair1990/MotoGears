import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { ProductCard } from '../../core/models/api.models';
import { ProductCardComponent } from './product-card.component';
import { SkeletonComponent } from './skeleton.component';

@Component({
  selector: 'app-product-grid',
  imports: [ProductCardComponent, SkeletonComponent],
  template: `
    <div class="pgrid" [class.list]="layout() === 'list'" [class.compact]="columns() === 5" [attr.aria-busy]="loading()">
      @if (loading()) {
        <app-skeleton variant="card" [count]="skeletons()" />
      } @else {
        @for (p of products(); track p.id) {
          <app-product-card [product]="p" [layout]="layout()" [fitsSelected]="fitsSelected()" />
        }
      }
    </div>
  `,
  styles: `
    .pgrid { display: grid; gap: 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    @media (min-width: 768px) { .pgrid { gap: 16px; grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (min-width: 1100px) { .pgrid { grid-template-columns: repeat(4, minmax(0, 1fr)); } .pgrid.compact { grid-template-columns: repeat(5, minmax(0, 1fr)); } }
    .pgrid.list { grid-template-columns: 1fr; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductGridComponent {
  readonly products = input<ProductCard[]>([]);
  readonly loading = input(false);
  readonly layout = input<'grid' | 'list'>('grid');
  readonly columns = input<4 | 5>(4);
  readonly skeletons = input(8);
  readonly fitsSelected = input<boolean | null>(null);
}
