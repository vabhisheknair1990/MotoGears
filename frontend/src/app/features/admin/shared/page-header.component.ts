import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IconComponent } from '../../../shared/components/icon.component';

@Component({
  selector: 'adm-page-header',
  imports: [RouterLink, IconComponent],
  template: `
    <div class="ph">
      <div class="min0">
        @if (back()) {
          <a class="back" [routerLink]="back()"><app-icon name="arrow-left" [size]="16" /> {{ backLabel() }}</a>
        }
        <h1>{{ title() }}</h1>
        @if (subtitle()) { <p class="text-muted mb-0">{{ subtitle() }}</p> }
      </div>
      <div class="actions"><ng-content /></div>
    </div>
  `,
  styles: `
    .ph { display: flex; flex-wrap: wrap; gap: 12px 16px; align-items: flex-end; justify-content: space-between; margin-bottom: 20px; }
    .min0 { min-width: 0; }
    h1 { font-size: 26px; margin: 0 0 4px; }
    .back { display: inline-flex; align-items: center; gap: 4px; font-size: 13px; color: var(--muted); margin-bottom: 6px; }
    .back:hover { color: var(--brand); }
    .actions { display: flex; flex-wrap: wrap; gap: 8px; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PageHeaderComponent {
  readonly title = input.required<string>();
  readonly subtitle = input<string | null>(null);
  readonly back = input<string | null>(null);
  readonly backLabel = input('Back');
}
