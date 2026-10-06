import { ChangeDetectionStrategy, Component, input, model } from '@angular/core';
import { IconComponent } from './icon.component';

@Component({
  selector: 'app-qty',
  imports: [IconComponent],
  template: `
    <div class="qty" role="group" aria-label="Quantity">
      <button type="button" (click)="change(-1)" [disabled]="disabled() || value() <= min()" aria-label="Decrease quantity"><app-icon name="minus" [size]="14" /></button>
      <span aria-live="polite">{{ value() }}</span>
      <button type="button" (click)="change(1)" [disabled]="disabled() || value() >= max()" aria-label="Increase quantity"><app-icon name="plus" [size]="14" /></button>
    </div>
  `,
  styles: `
    .qty { display: inline-flex; align-items: center; border: 1px solid var(--line); border-radius: var(--radius-sm); height: 38px; background: #fff; }
    button { width: 36px; height: 100%; border: 0; background: none; display: grid; place-items: center; color: var(--ink-2); }
    button:disabled { opacity: .35; cursor: not-allowed; }
    button:hover:not(:disabled) { background: var(--line-2); }
    span { min-width: 32px; text-align: center; font-weight: 700; font-variant-numeric: tabular-nums; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class QuantityStepperComponent {
  readonly value = model(1);
  readonly min = input(1);
  readonly max = input(10);
  readonly disabled = input(false);

  change(delta: number): void {
    this.value.set(Math.min(this.max(), Math.max(this.min(), this.value() + delta)));
  }
}
