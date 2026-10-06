import { A11yModule } from '@angular/cdk/a11y';
import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { IconComponent } from './icon.component';

/** Accessible dialog: focus is trapped inside (CDK), Escape and backdrop close it. */
@Component({
  selector: 'app-modal',
  imports: [A11yModule, IconComponent],
  template: `
    @if (open()) {
      <div class="backdrop" (click)="close.emit()"></div>
      <div class="dialog" [class]="'dialog ' + size()" role="dialog" aria-modal="true" [attr.aria-label]="title()"
           cdkTrapFocus [cdkTrapFocusAutoCapture]="true" (keydown.escape)="close.emit()">
        <header>
          <h2>{{ title() }}</h2>
          <button type="button" class="icon-btn sm" (click)="close.emit()" aria-label="Close"><app-icon name="x" [size]="18" /></button>
        </header>
        <div class="content"><ng-content /></div>
        <ng-content select="[modal-footer]" />
      </div>
    }
  `,
  styles: `
    .dialog { position: fixed; z-index: 100; left: 50%; top: 50%; transform: translate(-50%, -50%); width: calc(100% - 32px); max-height: calc(100vh - 48px);
      display: flex; flex-direction: column; background: var(--surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); animation: pop .18s ease; }
    .dialog.sm { max-width: 420px; } .dialog.md { max-width: 560px; } .dialog.lg { max-width: 820px; } .dialog.xl { max-width: 1100px; }
    header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--line); }
    h2 { margin: 0; font-size: 18px; font-family: var(--font); }
    .content { padding: 20px; overflow-y: auto; }
    :host ::ng-deep [modal-footer] { padding: 14px 20px; border-top: 1px solid var(--line); display: flex; justify-content: flex-end; gap: 10px; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModalComponent {
  readonly open = input(false);
  readonly title = input('');
  readonly size = input<'sm' | 'md' | 'lg' | 'xl'>('md');
  readonly close = output<void>();
}
