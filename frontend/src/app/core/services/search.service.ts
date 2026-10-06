import { Injectable, inject, signal } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '../api/api.service';
import { silent } from '../api/http-context';
import { SearchSuggestions } from '../models/api.models';
import { storage } from '../utils/storage';

const RECENT_KEY = 'mg.recent-searches';

@Injectable({ providedIn: 'root' })
export class SearchService {
  private readonly api = inject(ApiService);
  readonly recent = signal<string[]>(storage.getJson<string[]>(RECENT_KEY) ?? []);

  suggestions(q: string): Observable<SearchSuggestions> {
    return this.api.get<SearchSuggestions>('search/suggestions', { q }, silent());
  }

  popular(): Observable<string[]> {
    return this.api.get<string[]>('search/popular', undefined, silent());
  }

  remember(term: string): void {
    const t = term.trim();
    if (!t) return;
    const next = [t, ...this.recent().filter((r) => r.toLowerCase() !== t.toLowerCase())].slice(0, 6);
    this.recent.set(next);
    storage.setJson(RECENT_KEY, next);
  }

  clearRecent(): void {
    this.recent.set([]);
    storage.setJson(RECENT_KEY, null);
  }
}
