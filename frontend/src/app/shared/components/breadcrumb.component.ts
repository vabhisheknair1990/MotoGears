import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IconComponent } from './icon.component';

export interface Crumb { label: string; link?: string | unknown[] | null }

@Component({
  selector: 'app-breadcrumb',
  imports: [RouterLink, IconComponent],
  template: `
    <nav aria-label="Breadcrumb">
      <ol>
        @for (c of items(); track $index; let last = $last) {
          <li>
            @if (c.link && !last) {
              <a [routerLink]="c.link">{{ c.label }}</a>
              <app-icon name="chevron-right" [size]="14" />
            } @else {
              <span [attr.aria-current]="last ? 'page' : null">{{ c.label }}</span>
            }
          </li>
        }
      </ol>
    </nav>
  `,
  styles: `
    ol { display: flex; flex-wrap: wrap; gap: 4px; list-style: none; margin: 0; padding: 0; font-size: 13px; color: var(--muted); }
    li { display: inline-flex; align-items: center; gap: 4px; }
    a:hover { color: var(--brand); }
    span[aria-current] { color: var(--ink-2); font-weight: 500; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BreadcrumbComponent {
  readonly items = input.required<Crumb[]>();
}
