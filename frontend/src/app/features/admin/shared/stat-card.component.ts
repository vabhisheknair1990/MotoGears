import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IconComponent } from '../../../shared/components/icon.component';

/** KPI tile: label, headline value, optional delta vs. previous period. */
@Component({
  selector: 'adm-stat',
  imports: [RouterLink, IconComponent],
  template: `
    <a class="card stat" [routerLink]="link()" [class.nolink]="!link()">
      <div class="top"><span class="label-t">{{ label() }}</span><span class="ic"><app-icon [name]="icon()" [size]="18" /></span></div>
      <div class="value">{{ value() }}</div>
      <div class="foot">
        @if (delta() !== null) {
          <span class="delta" [class.up]="up()" [class.down]="!up()">
            <app-icon [name]="up() ? 'trending' : 'arrow-right'" [size]="14" [style.transform]="up() ? null : 'rotate(45deg)'" />
            {{ up() ? '+' : '' }}{{ delta() }}%
          </span>
        }
        @if (hint()) { <span class="text-muted">{{ hint() }}</span> }
      </div>
    </a>
  `,
  styles: `
    .stat { display: flex; flex-direction: column; gap: 6px; padding: 16px 18px; transition: border-color .15s, box-shadow .15s; min-width: 0; }
    .stat:not(.nolink):hover { border-color: var(--ink-3, #94a3b8); box-shadow: var(--shadow-sm); }
    .nolink { pointer-events: none; }
    .top { display: flex; justify-content: space-between; align-items: center; }
    .label-t { font-size: 13px; color: var(--muted); font-weight: 500; }
    .ic { width: 32px; height: 32px; border-radius: 8px; background: var(--line-2); color: var(--ink-2); display: grid; place-items: center; }
    .value { font-family: 'Barlow Condensed', sans-serif; font-weight: 700; font-size: 30px; line-height: 1.1; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .foot { display: flex; gap: 8px; align-items: center; font-size: 12.5px; flex-wrap: wrap; }
    .delta { display: inline-flex; align-items: center; gap: 2px; font-weight: 600; padding: 1px 6px; border-radius: 999px; }
    .up { color: #15803d; background: #dcfce7; }
    .down { color: #b91c1c; background: #fee2e2; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class StatCardComponent {
  readonly label = input.required<string>();
  readonly value = input.required<string | number>();
  readonly icon = input('chart');
  readonly hint = input<string | null>(null);
  readonly delta = input<number | null>(null);
  readonly link = input<string | null>(null);
  protected readonly up = computed(() => (this.delta() ?? 0) >= 0);
}
