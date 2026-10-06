import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { InrPipe } from '../pipes/inr.pipe';

@Component({
  selector: 'app-price',
  imports: [InrPipe],
  template: `
    <span class="price-now" [class.lg]="size() === 'lg'">{{ price() | inr }}</span>
    @if (discount() > 0) {
      <span class="price-mrp">{{ mrp() | inr }}</span>
      <span class="price-off">{{ discount() }}% off</span>
    }
  `,
  styles: `
    :host { display: inline-flex; align-items: baseline; flex-wrap: wrap; gap: 6px 8px; }
    .price-now { font-size: 17px; }
    .price-now.lg { font-size: 30px; font-family: var(--font-display); letter-spacing: .01em; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PriceComponent {
  readonly price = input.required<number>();
  readonly mrp = input<number>(0);
  readonly size = input<'md' | 'lg'>('md');
  protected readonly discount = computed(() => (this.mrp() > this.price() ? Math.round(((this.mrp() - this.price()) / this.mrp()) * 100) : 0));
}
