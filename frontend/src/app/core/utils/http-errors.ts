import { HttpErrorResponse } from '@angular/common/http';
import { AbstractControl, FormGroup } from '@angular/forms';

/** Extracts a readable message from any API error. */
export function errorMessage(err: unknown, fallback = 'Something went wrong. Please try again.'): string {
  if (err instanceof HttpErrorResponse) {
    const body = err.error as { message?: string } | null;
    if (body && typeof body === 'object' && body.message) {
      return body.message;
    }
    if (err.status === 0) {
      return 'Cannot reach the server. Check your connection and try again.';
    }
  }
  return fallback;
}

export function fieldErrors(err: unknown): Record<string, string[]> {
  if (err instanceof HttpErrorResponse && err.status === 422) {
    const body = err.error as { errors?: Record<string, string[]> } | null;
    return body?.errors ?? {};
  }
  return {};
}

/**
 * Maps Laravel 422 errors onto matching reactive-form controls (supports dotted
 * paths such as "shipping_address.postal_code"). Returns messages that matched no control.
 */
export function applyServerErrors(form: FormGroup, err: unknown): string[] {
  const unmatched: string[] = [];
  for (const [field, messages] of Object.entries(fieldErrors(err))) {
    const control: AbstractControl | null = form.get(field) ?? form.get(field.split('.').pop() ?? field);
    if (control) {
      control.setErrors({ ...(control.errors ?? {}), server: messages[0] });
      control.markAsTouched();
    } else {
      unmatched.push(...messages);
    }
  }
  return unmatched;
}
