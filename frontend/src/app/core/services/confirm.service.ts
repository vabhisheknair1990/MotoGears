import { Injectable, signal } from '@angular/core';

export interface ConfirmRequest { title: string; message: string; confirmLabel?: string; danger?: boolean; resolve: (ok: boolean) => void }

/** Promise-based confirmation dialog (rendered by <app-confirm-dialog> in the root component). */
@Injectable({ providedIn: 'root' })
export class ConfirmService {
  readonly current = signal<ConfirmRequest | null>(null);

  ask(opts: Omit<ConfirmRequest, 'resolve'>): Promise<boolean> {
    return new Promise((resolve) => this.current.set({ ...opts, resolve }));
  }

  close(ok: boolean): void {
    this.current()?.resolve(ok);
    this.current.set(null);
  }
}
