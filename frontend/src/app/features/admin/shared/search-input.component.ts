import { ChangeDetectionStrategy, Component, DestroyRef, inject, input, output } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { IconComponent } from '../../../shared/components/icon.component';

/** Debounced (300 ms) search box used by admin list toolbars. */
@Component({
  selector: 'adm-search',
  imports: [IconComponent],
  template: `
    <label class="s">
      <app-icon name="search" [size]="16" />
      <input class="input input-sm" type="search" [placeholder]="placeholder()" [attr.aria-label]="placeholder()" [value]="value() ?? ''" (input)="input$.next($any($event.target).value)" />
    </label>
  `,
  styles: `
    .s { position: relative; display: block; min-width: 220px; flex: 1 1 240px; max-width: 360px; }
    app-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }
    .input { padding-left: 32px; width: 100%; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SearchInputComponent {
  readonly placeholder = input('Search…');
  readonly value = input<string | null>('');
  readonly search = output<string>();
  protected readonly input$ = new Subject<string>();

  constructor() {
    this.input$.pipe(debounceTime(300), distinctUntilChanged(), takeUntilDestroyed(inject(DestroyRef))).subscribe((v) => this.search.emit(v.trim()));
  }
}
