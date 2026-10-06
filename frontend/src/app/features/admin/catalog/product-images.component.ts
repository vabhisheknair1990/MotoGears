import { CdkDrag, CdkDragDrop, CdkDragHandle, CdkDropList, moveItemInArray } from '@angular/cdk/drag-drop';
import { ChangeDetectionStrategy, Component, OnDestroy, inject, input, output, signal } from '@angular/core';
import { ToastService } from '../../../core/services/toast.service';
import { errorMessage } from '../../../core/utils/http-errors';
import { IconComponent } from '../../../shared/components/icon.component';
import { AdminApiService } from '../data/admin-api.service';

export interface ManagedImage { id: number; url: string; alt: string; is_primary: boolean }

const MAX_BYTES = 5 * 1024 * 1024;
const TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
const MAX_IMAGES = 15;

/**
 * Product gallery manager. With a productId, changes go straight to the API
 * (upload, drag-to-reorder, primary, alt text, delete). Without one (new
 * product), files are queued and uploaded by the parent after creation.
 */
@Component({
  selector: 'adm-product-images',
  imports: [CdkDropList, CdkDrag, CdkDragHandle, IconComponent],
  template: `
    <div class="drop" [class.over]="dragOver()" (dragover)="$event.preventDefault(); dragOver.set(true)" (dragleave)="dragOver.set(false)" (drop)="onDrop($event)">
      <app-icon name="upload" [size]="24" />
      <div><strong>Drop images here</strong> or <label class="link pick">browse<input type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif" (change)="onPick($event)" /></label></div>
      <span class="hint">JPG, PNG, WebP or GIF · max 5 MB each · up to {{ max }} images. Drag to reorder; the first image is used when no primary is set.</span>
      @if (uploading()) { <span class="text-sm"><span class="spinner"></span> Uploading…</span> }
    </div>

    @if (productId()) {
      <div class="grid" cdkDropList cdkDropListOrientation="mixed" (cdkDropListDropped)="reorder($event)">
        @for (img of images(); track img.id) {
          <figure class="tile" cdkDrag [class.primary]="img.is_primary">
            <button type="button" class="grip" cdkDragHandle aria-label="Drag to reorder"><app-icon name="grip" [size]="16" /></button>
            <img [src]="img.url" [alt]="img.alt" />
            @if (img.is_primary) { <span class="badge badge-brand tag">Primary</span> }
            <figcaption>
              <input class="input input-sm" [value]="img.alt" (change)="saveAlt(img, $any($event.target).value)" aria-label="Alt text" placeholder="Alt text" />
              <div class="acts">
                @if (!img.is_primary) { <button type="button" class="btn btn-sm btn-ghost" (click)="makePrimary(img)" [disabled]="busy()">Set primary</button> }
                <button type="button" class="icon-btn sm danger" (click)="remove(img)" [disabled]="busy()" aria-label="Delete image"><app-icon name="trash" [size]="15" /></button>
              </div>
            </figcaption>
          </figure>
        }
      </div>
    } @else if (queued().length) {
      <div class="grid">
        @for (q of queued(); track q.url; let i = $index) {
          <figure class="tile" [class.primary]="i === 0">
            <img [src]="q.url" [alt]="q.file.name" />
            @if (i === 0) { <span class="badge badge-brand tag">Primary</span> }
            <figcaption><span class="text-xs truncate">{{ q.file.name }}</span><button type="button" class="icon-btn sm danger" (click)="unqueue(i)" aria-label="Remove image"><app-icon name="x" [size]="15" /></button></figcaption>
          </figure>
        }
      </div>
      <p class="hint">Images upload when you create the product.</p>
    }
  `,
  styles: `
    .drop { border: 2px dashed var(--line); border-radius: var(--radius); padding: 20px; display: flex; flex-direction: column; align-items: center; gap: 6px; text-align: center; color: var(--muted); transition: border-color .15s, background .15s; }
    .drop.over { border-color: var(--brand); background: #fff5f6; }
    .drop strong { color: var(--ink); }
    .pick { position: relative; cursor: pointer; }
    .pick input { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; }
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; margin-top: 14px; }
    .tile { position: relative; margin: 0; border: 1px solid var(--line); border-radius: var(--radius-sm); overflow: hidden; background: #fff; }
    .tile.primary { border-color: var(--brand); box-shadow: 0 0 0 1px var(--brand); }
    .tile img { width: 100%; aspect-ratio: 1; object-fit: contain; background: #f8fafc; display: block; }
    .tag { position: absolute; top: 6px; left: 6px; }
    .grip { position: absolute; top: 6px; right: 6px; border: 0; background: rgba(255,255,255,.9); border-radius: 6px; padding: 4px; cursor: grab; display: grid; place-items: center; color: var(--ink-2); }
    figcaption { display: flex; flex-direction: column; gap: 6px; padding: 8px; }
    figcaption .acts { display: flex; justify-content: space-between; align-items: center; }
    .icon-btn.danger:hover { color: #dc2626; background: #fee2e2; }
    .cdk-drag-preview { box-shadow: 0 10px 30px rgba(0,0,0,.2); border-radius: 8px; }
    .cdk-drag-placeholder { opacity: .3; }
    .cdk-drag-animating, .cdk-drop-list-dragging .tile:not(.cdk-drag-placeholder) { transition: transform 200ms cubic-bezier(0, 0, .2, 1); }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductImagesComponent implements OnDestroy {
  readonly productId = input<number | null>(null);
  readonly images = input<ManagedImage[]>([]);
  readonly imagesChange = output<ManagedImage[]>();
  readonly queuedChange = output<File[]>();
  private readonly api = inject(AdminApiService);
  private readonly toast = inject(ToastService);
  protected readonly queued = signal<{ file: File; url: string }[]>([]);
  protected readonly uploading = signal(false);
  protected readonly busy = signal(false);
  protected readonly dragOver = signal(false);
  protected readonly max = MAX_IMAGES;

  ngOnDestroy(): void {
    this.queued().forEach((q) => URL.revokeObjectURL(q.url));
  }

  protected onPick(event: Event): void {
    const el = event.target as HTMLInputElement;
    this.accept(Array.from(el.files ?? []));
    el.value = '';
  }

  protected onDrop(event: DragEvent): void {
    event.preventDefault();
    this.dragOver.set(false);
    this.accept(Array.from(event.dataTransfer?.files ?? []));
  }

  private accept(files: File[]): void {
    const ok = files.filter((f) => TYPES.includes(f.type) && f.size <= MAX_BYTES);
    if (ok.length < files.length) this.toast.error('Some files were skipped — only JPG, PNG, WebP or GIF up to 5 MB are allowed.');
    const room = MAX_IMAGES - (this.productId() ? this.images().length : this.queued().length);
    const take = ok.slice(0, Math.max(0, room));
    if (take.length < ok.length) this.toast.error(`A product can have at most ${MAX_IMAGES} images.`);
    if (!take.length) return;
    if (this.productId()) {
      this.upload(take);
    } else {
      this.queued.update((q) => [...q, ...take.map((file) => ({ file, url: URL.createObjectURL(file) }))]);
      this.queuedChange.emit(this.queued().map((q) => q.file));
    }
  }

  protected unqueue(i: number): void {
    const item = this.queued()[i];
    if (item) URL.revokeObjectURL(item.url);
    this.queued.update((q) => q.filter((_, idx) => idx !== i));
    this.queuedChange.emit(this.queued().map((q) => q.file));
  }

  private upload(files: File[]): void {
    const id = this.productId();
    if (!id) return;
    const form = new FormData();
    files.forEach((f) => form.append('images[]', f));
    this.uploading.set(true);
    this.api.uploadForm<ManagedImage[]>(`products/${id}/images`, form).subscribe({
      next: (res) => {
        this.uploading.set(false);
        this.imagesChange.emit(res.data);
        this.toast.success(res.message || 'Images uploaded');
      },
      error: (err) => {
        this.uploading.set(false);
        this.toast.error(errorMessage(err, 'Upload failed.'));
      },
    });
  }

  protected reorder(event: CdkDragDrop<unknown>): void {
    if (event.previousIndex === event.currentIndex) return;
    const list = [...this.images()];
    moveItemInArray(list, event.previousIndex, event.currentIndex);
    this.imagesChange.emit(list);
    this.api.put<ManagedImage[]>(`products/${this.productId()}/images/reorder`, { order: list.map((i) => i.id) }).subscribe({
      error: (err) => this.toast.error(errorMessage(err, 'Could not save the new order.')),
    });
  }

  protected makePrimary(img: ManagedImage): void {
    this.patch(img, { is_primary: true }, 'Primary image updated');
  }

  protected saveAlt(img: ManagedImage, alt: string): void {
    if (alt.trim() === img.alt) return;
    this.patch(img, { alt: alt.trim() || null }, 'Alt text saved');
  }

  private patch(img: ManagedImage, body: Record<string, unknown>, msg: string): void {
    this.busy.set(true);
    this.api.patch<ManagedImage[]>(`products/${this.productId()}/images/${img.id}`, body).subscribe({
      next: (res) => {
        this.busy.set(false);
        this.imagesChange.emit(res.data);
        this.toast.success(msg);
      },
      error: (err) => {
        this.busy.set(false);
        this.toast.error(errorMessage(err));
      },
    });
  }

  protected remove(img: ManagedImage): void {
    this.busy.set(true);
    this.api.delete<ManagedImage[]>(`products/${this.productId()}/images/${img.id}`).subscribe({
      next: (res) => {
        this.busy.set(false);
        this.imagesChange.emit(res.data);
        this.toast.success('Image deleted');
      },
      error: (err) => {
        this.busy.set(false);
        this.toast.error(errorMessage(err));
      },
    });
  }
}
