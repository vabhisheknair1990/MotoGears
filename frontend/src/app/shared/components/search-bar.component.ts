import { ChangeDetectionStrategy, Component, DestroyRef, ElementRef, inject, input, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { Subject, catchError, debounceTime, distinctUntilChanged, filter, of, switchMap, tap } from 'rxjs';
import { SearchService } from '../../core/services/search.service';
import { SearchSuggestions } from '../../core/models/api.models';
import { InrPipe } from '../pipes/inr.pipe';
import { IconComponent } from './icon.component';

/**
 * Debounced (300ms) autocomplete — never one request per keystroke. Shows recent and popular
 * searches when empty, and product / category / brand / vehicle suggestions while typing.
 */
@Component({
  selector: 'app-search-bar',
  imports: [IconComponent, RouterLink, InrPipe],
  template: `
    <form class="sb" role="search" (submit)="submit($event)" [class.dark]="dark()">
      <app-icon name="search" [size]="18" class="lead" />
      <input #box type="search" [value]="query()" (input)="onInput(box.value)" (focus)="open.set(true)" (keydown.escape)="open.set(false)"
             placeholder="Search parts, part numbers, brands or vehicles…" aria-label="Search products" autocomplete="off"
             [attr.aria-expanded]="open()" aria-controls="search-panel" />
      @if (loading()) { <span class="spinner"></span> }
      <button type="submit" class="go" aria-label="Search"><app-icon name="arrow-right" [size]="18" /></button>
    </form>
    @if (open()) {
      <div class="panel" id="search-panel" role="listbox">
        @if (query().trim().length < 2) {
          @if (search.recent().length) {
            <div class="grp">
              <div class="row between"><h4>Recent searches</h4><button class="linkish" type="button" (click)="search.clearRecent()">Clear</button></div>
              <div class="chips">@for (t of search.recent(); track t) { <button type="button" class="chip" (click)="go(t)"><app-icon name="history" [size]="14" />{{ t }}</button> }</div>
            </div>
          }
          @if (popular().length) {
            <div class="grp">
              <h4>Popular searches</h4>
              <div class="chips">@for (t of popular(); track t) { <button type="button" class="chip" (click)="go(t)"><app-icon name="trending" [size]="14" />{{ t }}</button> }</div>
            </div>
          }
        } @else if (results(); as r) {
          @if (!r.products.length && !r.categories.length && !r.brands.length && !r.vehicles.length) {
            <p class="none">No matches for “{{ query() }}”. Try a part number or vehicle model.</p>
          }
          @if (r.products.length) {
            <div class="grp">
              <h4>Products</h4>
              @for (p of r.products; track p.id) {
                <a class="prod" [routerLink]="['/products', p.slug]" (click)="close(query())">
                  <img [src]="p.image" alt="" class="thumb" />
                  <span class="grow"><span class="clamp-2">{{ p.name }}</span><span class="text-xs text-muted">{{ p.brand?.name }} · {{ p.sku }}</span></span>
                  <strong>{{ p.price | inr }}</strong>
                </a>
              }
            </div>
          }
          <div class="cols">
            @if (r.categories.length) {
              <div class="grp"><h4>Categories</h4>@for (c of r.categories; track c.id) { <a class="lnk" [routerLink]="['/category', c.slug]" (click)="close()">{{ c.name }}</a> }</div>
            }
            @if (r.brands.length) {
              <div class="grp"><h4>Brands</h4>@for (b of r.brands; track b.id) { <a class="lnk" [routerLink]="['/brand', b.slug]" (click)="close()">{{ b.name }}</a> }</div>
            }
            @if (r.vehicles.length) {
              <div class="grp"><h4>Vehicles</h4>@for (v of r.vehicles; track v.id) { <a class="lnk" routerLink="/search" [queryParams]="{ q: v.name }" (click)="close(v.name)">{{ v.name }} parts</a> }</div>
            }
          </div>
          <button type="button" class="all" (click)="go(query())">See all results for “{{ query() }}” <app-icon name="arrow-right" [size]="14" /></button>
        }
      </div>
    }
  `,
  styleUrl: './search-bar.component.css',
  host: { '(document:click)': 'onDocClick($event)' },
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SearchBarComponent {
  protected readonly search = inject(SearchService);
  private readonly router = inject(Router);
  private readonly host = inject(ElementRef<HTMLElement>);
  private readonly destroyRef = inject(DestroyRef);

  readonly dark = input(false);
  protected readonly query = signal('');
  protected readonly open = signal(false);
  protected readonly loading = signal(false);
  protected readonly results = signal<SearchSuggestions | null>(null);
  protected readonly popular = signal<string[]>([]);
  private readonly input$ = new Subject<string>();

  constructor() {
    this.search.popular().pipe(catchError(() => of([])), takeUntilDestroyed()).subscribe((p) => this.popular.set(p));
    this.input$.pipe(
      debounceTime(300),
      distinctUntilChanged(),
      tap((q) => { if (q.trim().length < 2) this.results.set(null); }),
      filter((q) => q.trim().length >= 2),
      tap(() => this.loading.set(true)),
      switchMap((q) => this.search.suggestions(q.trim()).pipe(catchError(() => of(null)))),
      takeUntilDestroyed(this.destroyRef),
    ).subscribe((r) => {
      this.loading.set(false);
      this.results.set(r);
    });
  }

  onInput(value: string): void {
    this.query.set(value);
    this.open.set(true);
    this.input$.next(value);
  }

  submit(event: Event): void {
    event.preventDefault();
    this.go(this.query());
  }

  go(term: string): void {
    const q = term.trim();
    if (!q) return;
    this.query.set(q);
    this.close(q);
    void this.router.navigate(['/search'], { queryParams: { q } });
  }

  close(remember?: string): void {
    if (remember) this.search.remember(remember);
    this.open.set(false);
  }

  onDocClick(event: MouseEvent): void {
    if (!this.host.nativeElement.contains(event.target as Node)) {
      this.open.set(false);
    }
  }
}
