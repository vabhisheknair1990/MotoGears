import { ChangeDetectionStrategy, Component, ElementRef, afterNextRender, computed, inject, input, signal } from '@angular/core';

export interface ChartPoint { label: string; value: number; sub?: string }

/**
 * Single-series area chart (SVG). One y-axis, recessive grid, 2px line, a
 * crosshair + tooltip on hover/touch/keyboard, and a visually-hidden data table
 * for screen readers. Width follows the container via ResizeObserver.
 */
@Component({
  selector: 'adm-area-chart',
  template: `
    <div class="chart" [style.height.px]="height()">
      <svg [attr.viewBox]="'0 0 ' + width() + ' ' + height()" [attr.width]="width()" [attr.height]="height()" role="img" [attr.aria-label]="ariaLabel()"
        (pointermove)="move($event)" (pointerleave)="active.set(null)" tabindex="0" (keydown)="key($event)" (blur)="active.set(null)">
        <defs>
          <linearGradient [attr.id]="gid" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" [attr.stop-color]="color()" stop-opacity="0.22" />
            <stop offset="100%" [attr.stop-color]="color()" stop-opacity="0.02" />
          </linearGradient>
        </defs>
        @for (t of ticks(); track t.v) {
          <line class="grid" [attr.x1]="padL" [attr.x2]="width() - padR" [attr.y1]="t.y" [attr.y2]="t.y" />
          <text class="axis" [attr.x]="padL - 8" [attr.y]="t.y + 4" text-anchor="end">{{ t.label }}</text>
        }
        @for (x of xLabels(); track x.i) {
          <text class="axis" [attr.x]="x.x" [attr.y]="height() - 6" text-anchor="middle">{{ x.label }}</text>
        }
        @if (points().length > 1) {
          <path [attr.d]="areaPath()" [attr.fill]="'url(#' + gid + ')'" />
          <path [attr.d]="linePath()" fill="none" [attr.stroke]="color()" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
        }
        @if (activePoint(); as p) {
          <line class="cross" [attr.x1]="p.x" [attr.x2]="p.x" [attr.y1]="padT" [attr.y2]="height() - padB" />
          <circle [attr.cx]="p.x" [attr.cy]="p.y" r="5" [attr.fill]="color()" stroke="#fff" stroke-width="2" />
        }
      </svg>
      @if (activePoint(); as p) {
        <div class="tip" [style.left.px]="p.x" [style.top.px]="p.y" [class.flip]="p.x > width() * 0.7">
          <div class="t-label">{{ p.label }}</div>
          <div class="t-value">{{ format()(p.value) }}</div>
          @if (p.sub) { <div class="t-sub">{{ p.sub }}</div> }
        </div>
      }
      @if (!data().length) { <div class="empty">No data for this period</div> }
    </div>
    <table class="sr-only">
      <caption>{{ ariaLabel() }}</caption>
      <thead><tr><th scope="col">Period</th><th scope="col">Value</th></tr></thead>
      <tbody>@for (d of data(); track $index) { <tr><td>{{ d.label }}</td><td>{{ format()(d.value) }}</td></tr> }</tbody>
    </table>
  `,
  styles: `
    :host { display: block; position: relative; }
    .chart { position: relative; width: 100%; }
    svg { display: block; overflow: visible; touch-action: pan-y; outline: none; }
    svg:focus-visible { outline: 2px solid var(--brand); outline-offset: 4px; border-radius: 4px; }
    .grid { stroke: var(--line); stroke-width: 1; }
    .axis { fill: var(--muted); font-size: 11px; font-family: Inter, sans-serif; }
    .cross { stroke: var(--ink-2, #334155); stroke-width: 1; stroke-dasharray: 3 3; }
    .tip { position: absolute; transform: translate(12px, -50%); background: var(--dark, #0b0f17); color: #fff; padding: 8px 10px; border-radius: 8px; font-size: 12px; pointer-events: none; white-space: nowrap; box-shadow: 0 6px 20px rgba(0,0,0,.18); z-index: 2; }
    .tip.flip { transform: translate(calc(-100% - 12px), -50%); }
    .t-label { color: #cbd5e1; }
    .t-value { font-weight: 700; font-size: 14px; }
    .t-sub { color: #cbd5e1; }
    .empty { position: absolute; inset: 0; display: grid; place-items: center; color: var(--muted); font-size: 13px; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AreaChartComponent {
  readonly data = input.required<ChartPoint[]>();
  readonly height = input(260);
  readonly color = input('#d7263d');
  readonly ariaLabel = input('Chart');
  readonly format = input<(v: number) => string>((v) => v.toLocaleString('en-IN'));
  readonly axisFormat = input<(v: number) => string>((v) => compact(v));

  protected readonly gid = 'g' + Math.random().toString(36).slice(2, 8);
  protected readonly width = signal(600);
  protected readonly active = signal<number | null>(null);
  protected readonly padL = 52;
  protected readonly padR = 12;
  protected readonly padT = 12;
  protected readonly padB = 26;
  private readonly host = inject(ElementRef<HTMLElement>);

  private readonly max = computed(() => niceMax(Math.max(0, ...this.data().map((d) => d.value))));

  protected readonly points = computed(() => {
    const d = this.data();
    const w = this.width() - this.padL - this.padR;
    const h = this.height() - this.padT - this.padB;
    const max = this.max() || 1;
    const step = d.length > 1 ? w / (d.length - 1) : 0;
    return d.map((p, i) => ({ ...p, x: this.padL + i * step, y: this.padT + h - (p.value / max) * h }));
  });

  protected readonly ticks = computed(() => {
    const h = this.height() - this.padT - this.padB;
    const max = this.max();
    return [0, 0.25, 0.5, 0.75, 1].map((f) => ({ v: f, y: this.padT + h - f * h, label: this.axisFormat()(max * f) }));
  });

  protected readonly xLabels = computed(() => {
    const pts = this.points();
    if (!pts.length) return [];
    const every = Math.max(1, Math.ceil(pts.length / Math.max(2, Math.floor(this.width() / 90))));
    return pts.map((p, i) => ({ i, x: p.x, label: p.label })).filter((p) => p.i % every === 0 || p.i === pts.length - 1);
  });

  protected readonly linePath = computed(() => this.points().map((p, i) => `${i ? 'L' : 'M'}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' '));
  protected readonly areaPath = computed(() => {
    const pts = this.points();
    if (pts.length < 2) return '';
    const base = this.height() - this.padB;
    return `${this.linePath()} L${pts[pts.length - 1].x.toFixed(1)},${base} L${pts[0].x.toFixed(1)},${base} Z`;
  });

  protected readonly activePoint = computed(() => {
    const i = this.active();
    return i === null ? null : (this.points()[i] ?? null);
  });

  constructor() {
    afterNextRender(() => {
      const el = this.host.nativeElement as HTMLElement;
      const ro = new ResizeObserver(([entry]) => this.width.set(Math.max(280, Math.floor(entry.contentRect.width))));
      ro.observe(el);
    });
  }

  protected move(event: PointerEvent): void {
    const pts = this.points();
    if (!pts.length) return;
    const rect = (event.currentTarget as SVGElement).getBoundingClientRect();
    const x = ((event.clientX - rect.left) / rect.width) * this.width();
    let best = 0;
    pts.forEach((p, i) => {
      if (Math.abs(p.x - x) < Math.abs(pts[best].x - x)) best = i;
    });
    this.active.set(best);
  }

  protected key(event: KeyboardEvent): void {
    const n = this.points().length;
    if (!n) return;
    const cur = this.active() ?? (event.key === 'ArrowLeft' ? n : -1);
    if (event.key === 'ArrowRight') this.active.set(Math.min(n - 1, cur + 1));
    else if (event.key === 'ArrowLeft') this.active.set(Math.max(0, cur - 1));
    else return;
    event.preventDefault();
  }
}

export function niceMax(v: number): number {
  if (v <= 0) return 1;
  const exp = Math.pow(10, Math.floor(Math.log10(v)));
  const f = v / exp;
  const nice = f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10;
  return nice * exp;
}

export function compact(v: number): string {
  if (v >= 1e7) return (v / 1e7).toFixed(v % 1e7 ? 1 : 0) + 'Cr';
  if (v >= 1e5) return (v / 1e5).toFixed(v % 1e5 ? 1 : 0) + 'L';
  if (v >= 1e3) return (v / 1e3).toFixed(v % 1e3 ? 1 : 0) + 'k';
  return String(Math.round(v * 100) / 100);
}
