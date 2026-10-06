import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ToastService } from '../../core/services/toast.service';
import { IconComponent } from './icon.component';

@Component({
  selector: 'app-toast-container',
  imports: [IconComponent, RouterLink],
  template: `
    <div class="stack-t" aria-live="polite" aria-atomic="false">
      @for (t of toast.toasts(); track t.id) {
        <div class="toast" [class]="'toast ' + t.tone" role="status">
          <app-icon [name]="t.tone === 'success' ? 'check-circle' : t.tone === 'error' ? 'alert' : 'info'" [size]="18" />
          <span class="msg">{{ t.message }}</span>
          @if (t.action) { <a [routerLink]="t.action.url" (click)="toast.dismiss(t.id)">{{ t.action.label }}</a> }
          <button type="button" (click)="toast.dismiss(t.id)" aria-label="Dismiss"><app-icon name="x" [size]="14" /></button>
        </div>
      }
    </div>
  `,
  styles: `
    .stack-t { position: fixed; z-index: 200; bottom: 16px; left: 50%; transform: translateX(-50%); display: flex; flex-direction: column; gap: 8px; width: min(440px, calc(100% - 32px)); }
    @media (min-width: 768px) { .stack-t { left: auto; right: 24px; transform: none; bottom: 24px; } }
    .toast { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: var(--radius); background: var(--dark); color: #fff; box-shadow: var(--shadow-lg); animation: pop .2s ease; font-size: 14px; }
    .toast.success app-icon { color: #4ade80; } .toast.error app-icon { color: #f87171; } .toast.info app-icon, .toast.warning app-icon { color: var(--accent); }
    .msg { flex: 1; }
    a { color: var(--accent); font-weight: 700; white-space: nowrap; }
    button { border: 0; background: none; color: #98a2b3; padding: 2px; display: grid; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ToastContainerComponent {
  protected readonly toast = inject(ToastService);
}
