import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { IconComponent } from './icon.component';

@Component({
  selector: 'app-empty-state',
  imports: [IconComponent],
  template: `
    <div class="icon-wrap"><app-icon [name]="icon()" [size]="30" [stroke]="1.6" /></div>
    <h3>{{ title() }}</h3>
    @if (message()) { <p class="text-muted">{{ message() }}</p> }
    <ng-content />
  `,
  styles: `
    :host { display: flex; flex-direction: column; align-items: center; text-align: center; padding: 48px 16px; gap: 4px; }
    .icon-wrap { width: 68px; height: 68px; border-radius: 50%; background: var(--line-2); display: grid; place-items: center; color: var(--muted); margin-bottom: 12px; }
    h3 { margin: 0 0 4px; }
    p { max-width: 420px; margin-bottom: 16px; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class EmptyStateComponent {
  readonly icon = input('box');
  readonly title = input.required<string>();
  readonly message = input<string | null>(null);
}
