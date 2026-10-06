import { Injectable, signal } from '@angular/core';

export type ToastTone = 'success' | 'error' | 'info' | 'warning';
export interface Toast { id: number; tone: ToastTone; message: string; action?: { label: string; url: string } }

/** Global notification system (rendered by <app-toast-container>). */
@Injectable({ providedIn: 'root' })
export class ToastService {
  private seq = 0;
  readonly toasts = signal<Toast[]>([]);

  show(message: string, tone: ToastTone = 'info', action?: Toast['action'], ms = 4000): void {
    const toast: Toast = { id: ++this.seq, tone, message, action };
    this.toasts.update((list) => [...list.slice(-3), toast]);
    setTimeout(() => this.dismiss(toast.id), ms);
  }

  success(message: string, action?: Toast['action']): void {
    this.show(message, 'success', action);
  }

  error(message: string): void {
    this.show(message, 'error', undefined, 6000);
  }

  info(message: string): void {
    this.show(message, 'info');
  }

  dismiss(id: number): void {
    this.toasts.update((list) => list.filter((t) => t.id !== id));
  }
}
