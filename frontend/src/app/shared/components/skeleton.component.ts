import { ChangeDetectionStrategy, Component, input } from '@angular/core';

/** Loading placeholders. `variant="card"` mirrors a product card; `lines` renders text bars. */
@Component({
  selector: 'app-skeleton',
  template: `
    @switch (variant()) {
      @case ('card') {
        @for (i of range(); track i) {
          <div class="sk-card" aria-hidden="true">
            <div class="skeleton img"></div>
            <div class="skeleton line w40"></div>
            <div class="skeleton line"></div>
            <div class="skeleton line w70"></div>
            <div class="skeleton line w50 tall"></div>
          </div>
        }
      }
      @case ('row') {
        @for (i of range(); track i) {
          <div class="sk-row" aria-hidden="true"><div class="skeleton sq"></div><div class="grow"><div class="skeleton line w70"></div><div class="skeleton line w40"></div></div></div>
        }
      }
      @default {
        @for (i of range(); track i) {
          <div class="skeleton line" [style.width.%]="i % 3 === 2 ? 60 : 100" aria-hidden="true"></div>
        }
      }
    }
    <span class="sr-only">Loading…</span>
  `,
  styles: `
    :host { display: contents; }
    .sk-card { background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); padding: 12px; display: flex; flex-direction: column; gap: 10px; }
    .img { aspect-ratio: 1; width: 100%; border-radius: var(--radius-sm); }
    .line { height: 12px; margin-bottom: 8px; }
    .line.tall { height: 20px; }
    .w40 { width: 40%; } .w50 { width: 50%; } .w70 { width: 70%; }
    .sk-row { display: flex; gap: 12px; align-items: center; padding: 10px 0; }
    .sq { width: 48px; height: 48px; flex: none; }
    .grow { flex: 1; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SkeletonComponent {
  readonly variant = input<'card' | 'row' | 'lines'>('lines');
  readonly count = input(3);
  protected range(): number[] {
    return Array.from({ length: this.count() }, (_, i) => i);
  }
}
