import { ChangeDetectionStrategy, Component, computed, input, output, signal } from '@angular/core';
import { IconComponent } from '../../../shared/components/icon.component';

const MAX_BYTES = 5 * 1024 * 1024;
const TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

/**
 * Single-image picker with preview. Validates type/size client-side for fast
 * feedback; Laravel validates again (mimes, max size, dimensions).
 */
@Component({
  selector: 'adm-image-input',
  imports: [IconComponent],
  template: `
    <div class="wrap" [class.wide]="wide()">
      <div class="preview" [class.empty]="!preview()">
        @if (preview()) { <img [src]="preview()" alt="" /> } @else { <app-icon name="image" [size]="28" /> }
      </div>
      <div class="ctl">
        <label class="btn btn-sm">
          <app-icon name="upload" [size]="15" /> {{ preview() ? 'Replace' : 'Upload' }}
          <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" (change)="pick($event)" />
        </label>
        @if (preview()) {
          <button type="button" class="btn btn-sm btn-ghost" (click)="clear()"><app-icon name="trash" [size]="15" /> Remove</button>
        }
        <span class="hint">JPG, PNG, WebP or GIF · max 5 MB</span>
        @if (error()) { <span class="error-text">{{ error() }}</span> }
      </div>
    </div>
  `,
  styles: `
    .wrap { display: flex; gap: 14px; align-items: center; }
    .preview { width: 88px; height: 88px; border-radius: var(--radius-sm); border: 1px solid var(--line); overflow: hidden; background: var(--line-2); display: grid; place-items: center; color: var(--muted); flex: none; }
    .wide .preview { width: 180px; height: 90px; }
    .preview img { width: 100%; height: 100%; object-fit: contain; background: #fff; }
    .ctl { display: flex; flex-wrap: wrap; gap: 6px 8px; align-items: center; }
    .ctl .hint, .ctl .error-text { flex-basis: 100%; }
    label.btn { position: relative; overflow: hidden; cursor: pointer; }
    input[type='file'] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ImageInputComponent {
  readonly current = input<string | null>(null);
  readonly wide = input(false);
  readonly fileChange = output<File | null>();
  readonly removed = output<void>();
  private readonly local = signal<string | null>(null);
  private readonly cleared = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly preview = computed(() => this.local() ?? (this.cleared() ? null : this.current()));

  protected pick(event: Event): void {
    const el = event.target as HTMLInputElement;
    const file = el.files?.[0];
    el.value = '';
    if (!file) return;
    if (!TYPES.includes(file.type)) {
      this.error.set('Please choose a JPG, PNG, WebP or GIF image.');
      return;
    }
    if (file.size > MAX_BYTES) {
      this.error.set('Image must be 5 MB or smaller.');
      return;
    }
    this.error.set(null);
    const prev = this.local();
    if (prev) URL.revokeObjectURL(prev);
    this.local.set(URL.createObjectURL(file));
    this.fileChange.emit(file);
  }

  protected clear(): void {
    const prev = this.local();
    if (prev) URL.revokeObjectURL(prev);
    this.local.set(null);
    this.cleared.set(true);
    this.fileChange.emit(null);
    this.removed.emit();
  }
}
