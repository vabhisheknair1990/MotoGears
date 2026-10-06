import { ChangeDetectionStrategy, Component, computed, input, model } from '@angular/core';
import { IconComponent } from './icon.component';

/** Star rating display (readonly) or input (editable, two-way bound via [(value)]). */
@Component({
  selector: 'app-rating',
  imports: [IconComponent],
  template: `
    @if (editable()) {
      <div class="stars editable" role="radiogroup" aria-label="Rating">
        @for (s of stars; track s) {
          <button type="button" role="radio" [attr.aria-checked]="value() === s" [attr.aria-label]="s + ' star' + (s > 1 ? 's' : '')"
                  (click)="value.set(s)" [class.on]="s <= value()">
            <app-icon [name]="s <= value() ? 'star-filled' : 'star'" [size]="size() + 8" />
          </button>
        }
      </div>
    } @else {
      <span class="stars" [attr.aria-label]="'Rated ' + value() + ' out of 5'" role="img">
        @for (s of stars; track s) {
          <span class="star" [class.on]="s <= rounded()" [class.half]="s === rounded() + 0.5">
            <app-icon [name]="s <= roundedUp() ? 'star-filled' : 'star'" [size]="size()" [stroke]="1.6" />
          </span>
        }
      </span>
      @if (count() !== null) {
        <span class="count">{{ value() ? value().toFixed(1) : '' }} @if (count()) {<span>({{ count() }})</span>}</span>
      }
    }
  `,
  styles: `
    :host { display: inline-flex; align-items: center; gap: 6px; }
    .stars { display: inline-flex; gap: 1px; color: #d0d5dd; }
    .star.on, button.on { color: var(--accent); }
    .editable button { border: 0; background: none; padding: 2px; color: #d0d5dd; border-radius: 4px; }
    .editable button:hover { transform: scale(1.1); }
    .count { font-size: 12.5px; color: var(--muted); font-weight: 600; }
    .count span { font-weight: 400; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class RatingComponent {
  readonly value = model(0);
  readonly count = input<number | null>(null);
  readonly size = input(14);
  readonly editable = input(false);
  protected readonly stars = [1, 2, 3, 4, 5];
  protected readonly rounded = computed(() => Math.floor(this.value()));
  protected readonly roundedUp = computed(() => Math.round(this.value()));
}
