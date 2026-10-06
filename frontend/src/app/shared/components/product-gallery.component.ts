import { A11yModule } from '@angular/cdk/a11y';
import { ChangeDetectionStrategy, Component, computed, inject, input, signal } from '@angular/core';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { ProductImage } from '../../core/models/api.models';
import { IconComponent } from './icon.component';

/** Image gallery: thumbnails, hover zoom, full-screen viewer (keyboard navigable) and optional video. */
@Component({
  selector: 'app-product-gallery',
  imports: [A11yModule, IconComponent],
  template: `
    <div class="gallery">
      <div class="thumbs" role="tablist" aria-label="Product images">
        @for (img of images(); track img.id; let i = $index) {
          <button type="button" role="tab" [attr.aria-selected]="i === index()" [class.on]="i === index() && !showVideo()" (click)="select(i)" [attr.aria-label]="'Image ' + (i + 1)">
            <img [src]="img.url" [alt]="img.alt" loading="lazy" />
          </button>
        }
        @if (videoUrl()) {
          <button type="button" class="vid" [class.on]="showVideo()" (click)="showVideo.set(true)" aria-label="Play product video"><app-icon name="zap" /></button>
        }
      </div>
      <div class="stage">
        @if (showVideo() && embedUrl()) {
          <iframe [src]="embedUrl()" title="Product video" allow="accelerometer; encrypted-media; picture-in-picture" allowfullscreen></iframe>
        } @else if (current(); as img) {
          <div class="zoom" (mousemove)="move($event)" (mouseleave)="zoomAt.set(null)" (click)="fullscreen.set(true)"
               [style.background-image]="zoomAt() ? 'url(' + img.url + ')' : null" [style.background-position]="zoomAt() ?? 'center'" [class.zooming]="!!zoomAt()">
            <img [src]="img.url" [alt]="img.alt" />
          </div>
          <button type="button" class="fs icon-btn" (click)="fullscreen.set(true)" aria-label="View full screen"><app-icon name="expand" [size]="18" /></button>
          <span class="hint text-xs"><app-icon name="zoom" [size]="13" /> Hover to zoom · click to expand</span>
        } @else {
          <div class="noimg"><app-icon name="image" [size]="48" /></div>
        }
      </div>
    </div>
    @if (fullscreen() && current(); as img) {
      <div class="lightbox" role="dialog" aria-modal="true" aria-label="Image viewer" cdkTrapFocus [cdkTrapFocusAutoCapture]="true"
           (keydown.escape)="fullscreen.set(false)" (keydown.arrowRight)="step(1)" (keydown.arrowLeft)="step(-1)">
        <button type="button" class="icon-btn close" (click)="fullscreen.set(false)" aria-label="Close"><app-icon name="x" /></button>
        @if (images().length > 1) {
          <button type="button" class="icon-btn nav l" (click)="step(-1)" aria-label="Previous image"><app-icon name="chevron-left" [size]="28" /></button>
          <button type="button" class="icon-btn nav r" (click)="step(1)" aria-label="Next image"><app-icon name="chevron-right" [size]="28" /></button>
        }
        <img [src]="img.url" [alt]="img.alt" />
        <span class="counter">{{ index() + 1 }} / {{ images().length }}</span>
      </div>
    }
  `,
  styleUrl: './product-gallery.component.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductGalleryComponent {
  readonly images = input<ProductImage[]>([]);
  readonly videoUrl = input<string | null>(null);
  protected readonly index = signal(0);
  protected readonly showVideo = signal(false);
  protected readonly fullscreen = signal(false);
  protected readonly zoomAt = signal<string | null>(null);
  protected readonly current = computed(() => this.images()[this.index()] ?? null);
  private readonly sanitizer = inject(DomSanitizer);
  /** Only YouTube videos are embedded (validated ID); anything else is ignored for safety. */
  protected readonly embedUrl = computed<SafeResourceUrl | null>(() => {
    const m = /(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([A-Za-z0-9_-]{11})/.exec(this.videoUrl() ?? '');
    return m ? this.sanitizer.bypassSecurityTrustResourceUrl(`https://www.youtube-nocookie.com/embed/${m[1]}`) : null;
  });

  select(i: number): void {
    this.index.set(i);
    this.showVideo.set(false);
  }

  step(delta: number): void {
    const n = this.images().length;
    if (n) this.index.set((this.index() + delta + n) % n);
  }

  move(e: MouseEvent): void {
    const el = e.currentTarget as HTMLElement;
    const r = el.getBoundingClientRect();
    const x = ((e.clientX - r.left) / r.width) * 100;
    const y = ((e.clientY - r.top) / r.height) * 100;
    this.zoomAt.set(`${x.toFixed(1)}% ${y.toFixed(1)}%`);
  }
}
