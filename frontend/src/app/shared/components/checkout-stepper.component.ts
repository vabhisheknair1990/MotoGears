import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { IconComponent } from './icon.component';

export interface Step { key: string; label: string }

@Component({
  selector: 'app-checkout-stepper',
  imports: [IconComponent],
  template: `
    <ol class="stepper" aria-label="Checkout progress">
      @for (s of steps(); track s.key; let i = $index) {
        <li [class.done]="i < current()" [class.current]="i === current()">
          <button type="button" [disabled]="i > current()" (click)="go.emit(i)" [attr.aria-current]="i === current() ? 'step' : null">
            <span class="dot">@if (i < current()) { <app-icon name="check" [size]="14" [stroke]="3" /> } @else { {{ i + 1 }} }</span>
            <span class="lbl">{{ s.label }}</span>
          </button>
        </li>
      }
    </ol>
  `,
  styles: `
    .stepper { display: flex; list-style: none; margin: 0 0 20px; padding: 0; counter-reset: s; }
    li { flex: 1; position: relative; }
    li:not(:last-child)::after { content: ''; position: absolute; top: 16px; left: calc(50% + 20px); right: calc(-50% + 20px); height: 2px; background: var(--line); }
    li.done:not(:last-child)::after { background: var(--success); }
    button { display: flex; flex-direction: column; align-items: center; gap: 6px; width: 100%; border: 0; background: none; padding: 0; color: var(--muted); }
    button:disabled { cursor: default; }
    .dot { width: 32px; height: 32px; border-radius: 50%; border: 2px solid var(--line); background: #fff; display: grid; place-items: center; font-weight: 700; font-size: 13px; }
    .current .dot { border-color: var(--brand); background: var(--brand); color: #fff; }
    .current button { color: var(--ink); font-weight: 700; }
    .done .dot { border-color: var(--success); background: var(--success); color: #fff; }
    .done button { color: var(--ink-2); }
    .lbl { font-size: 13px; }
    @media (max-width: 480px) { .lbl { font-size: 11px; } }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CheckoutStepperComponent {
  readonly steps = input.required<Step[]>();
  readonly current = input(0);
  readonly go = output<number>();
}
