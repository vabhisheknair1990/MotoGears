import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { ConfirmService } from '../../core/services/confirm.service';
import { ModalComponent } from './modal.component';

@Component({
  selector: 'app-confirm-dialog',
  imports: [ModalComponent],
  template: `
    @if (confirm.current(); as c) {
      <app-modal [open]="true" [title]="c.title" size="sm" (close)="confirm.close(false)">
        <p class="mt-0 mb-0">{{ c.message }}</p>
        <div modal-footer>
          <button type="button" class="btn" (click)="confirm.close(false)">Cancel</button>
          <button type="button" class="btn" [class.btn-primary]="!c.danger" [class.btn-danger]="c.danger" (click)="confirm.close(true)">{{ c.confirmLabel ?? 'Confirm' }}</button>
        </div>
      </app-modal>
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ConfirmDialogComponent {
  protected readonly confirm = inject(ConfirmService);
}
