import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { STATUS_TONE, humanize } from '../../core/utils/format';

/** Status pill; colour always paired with the text label (never colour alone). */
@Component({
  selector: 'app-status-badge',
  template: `<span class="badge" [class]="'badge badge-' + tone()"><span class="dot"></span>{{ label() || text() }}</span>`,
  styles: `.dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }`,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class StatusBadgeComponent {
  readonly status = input.required<string>();
  readonly label = input<string | null>(null);
  protected readonly text = computed(() => humanize(this.status()));
  protected readonly tone = computed(() => {
    const t = STATUS_TONE[this.status()] ?? 'neutral';
    return t === 'neutral' ? 'plain' : t;
  });
}
