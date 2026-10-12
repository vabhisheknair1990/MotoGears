import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

/** Logo artwork: full lockup (MOTO GEARS + helmet) or just the helmet mark. */
export const LOGO = {
  /** Dark lettering — for white / light backgrounds. */
  onLight: '/brand/logo.svg',
  /** White lettering — for the dark header, footer and admin sidebar. */
  onDark: '/brand/logo-light.svg',
  mark: '/brand/logo-mark.svg',
} as const;
/** Width ÷ height of the full lockup (viewBox 486 × 213). */
export const LOGO_RATIO = 486 / 213;

@Component({
  selector: 'app-logo',
  template: `<img [src]="src()" [attr.width]="width()" [attr.height]="height()" [alt]="alt()" decoding="async" />`,
  styles: `:host { display: inline-flex; line-height: 0; } img { display: block; height: 100%; width: auto; max-width: none; }`,
  host: { '[style.height.px]': 'height()' },
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LogoComponent {
  /** Background the logo sits on. */
  readonly on = input<'light' | 'dark'>('light');
  readonly variant = input<'full' | 'mark'>('full');
  readonly height = input(40);
  readonly alt = input('Moto Gears');

  protected readonly src = computed(() => (this.variant() === 'mark' ? LOGO.mark : this.on() === 'dark' ? LOGO.onDark : LOGO.onLight));
  protected readonly width = computed(() => Math.round(this.height() * (this.variant() === 'mark' ? 1 : LOGO_RATIO)));
}
