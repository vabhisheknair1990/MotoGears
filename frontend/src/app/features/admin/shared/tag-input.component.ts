import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { IconComponent } from '../../../shared/components/icon.component';

/** Chips editor for string lists (tags, "what's included", attribute values). Enter or comma adds. */
@Component({
  selector: 'adm-tag-input',
  imports: [IconComponent],
  template: `
    <div class="tags input">
      @for (t of value(); track $index) {
        <span class="chip">{{ t }}<button type="button" (click)="remove($index)" [attr.aria-label]="'Remove ' + t"><app-icon name="x" [size]="12" /></button></span>
      }
      <input [attr.id]="inputId()" [placeholder]="value().length ? '' : placeholder()" (keydown)="key($event)" (blur)="commit($any($event.target))" [attr.aria-label]="placeholder()" />
    </div>
  `,
  styles: `
    .tags { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; height: auto; min-height: 42px; padding: 6px 8px; cursor: text; }
    .chip { padding: 3px 4px 3px 10px; font-size: 12.5px; }
    .chip button { display: inline-grid; place-items: center; border: 0; background: transparent; cursor: pointer; color: inherit; padding: 2px; border-radius: 50%; }
    input { flex: 1; min-width: 120px; border: 0; outline: 0; font: inherit; background: transparent; padding: 4px; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TagInputComponent {
  readonly value = input<string[]>([]);
  readonly placeholder = input('Type and press Enter');
  readonly inputId = input<string | null>(null);
  readonly max = input(50);
  readonly valueChange = output<string[]>();

  protected key(event: KeyboardEvent): void {
    const el = event.target as HTMLInputElement;
    if (event.key === 'Enter' || event.key === ',') {
      event.preventDefault();
      this.commit(el);
    } else if (event.key === 'Backspace' && !el.value && this.value().length) {
      this.remove(this.value().length - 1);
    }
  }

  protected commit(el: HTMLInputElement): void {
    const parts = el.value.split(',').map((s) => s.trim()).filter(Boolean);
    el.value = '';
    if (!parts.length) return;
    const next = [...this.value()];
    for (const p of parts) {
      if (!next.some((t) => t.toLowerCase() === p.toLowerCase()) && next.length < this.max()) next.push(p);
    }
    this.valueChange.emit(next);
  }

  protected remove(i: number): void {
    this.valueChange.emit(this.value().filter((_, idx) => idx !== i));
  }
}
