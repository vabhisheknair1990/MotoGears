import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

export interface BarItem { label: string; value: number; display?: string; sub?: string }

/**
 * Ranked horizontal bars (HTML, not SVG) — one hue, thin marks with rounded
 * data-ends, values in text ink, full-row hover with a native tooltip.
 */
@Component({
  selector: 'adm-bar-list',
  template: `
    <ol class="bars" [attr.aria-label]="ariaLabel()">
      @for (b of rows(); track $index) {
        <li [attr.title]="b.label + ': ' + (b.display ?? b.value)">
          <div class="row-t"><span class="name truncate">{{ b.label }}</span><span class="val">{{ b.display ?? b.value }}</span></div>
          <div class="track"><div class="fill" [style.width.%]="b.pct" [style.background]="color()"></div></div>
          @if (b.sub) { <div class="sub">{{ b.sub }}</div> }
        </li>
      } @empty {
        <li class="empty">{{ emptyText() }}</li>
      }
    </ol>
  `,
  styles: `
    .bars { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 12px; }
    li { padding: 2px 4px; border-radius: 6px; }
    li:hover { background: var(--line-2); }
    .row-t { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; margin-bottom: 5px; }
    .name { color: var(--ink); min-width: 0; }
    .val { font-weight: 600; color: var(--ink); white-space: nowrap; font-variant-numeric: tabular-nums; }
    .track { height: 8px; background: transparent; border-radius: 4px; }
    .fill { height: 100%; border-radius: 0 4px 4px 0; min-width: 2px; }
    .sub { font-size: 11.5px; color: var(--muted); margin-top: 3px; }
    .empty { color: var(--muted); font-size: 13px; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BarListComponent {
  readonly items = input.required<BarItem[]>();
  readonly color = input('#d7263d');
  readonly ariaLabel = input('Ranking');
  readonly emptyText = input('No data for this period');
  protected readonly rows = computed(() => {
    const max = Math.max(0, ...this.items().map((i) => i.value)) || 1;
    return this.items().map((i) => ({ ...i, pct: (i.value / max) * 100 }));
  });
}
