import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { SILENT_ERRORS, SKIP_AUTH_REDIRECT } from '../api/http-context';
import { ToastService } from '../services/toast.service';
import { AuthStore } from '../state/auth.store';

/**
 * Global HTTP error handling:
 *  401 → clear session and redirect to the right login page
 *  403 → "access denied" toast
 *  404 → left to the page (renders its own not-found state)
 *  422 → left to forms (field errors); message toast if the caller isn't silent
 *  429 → rate-limit toast
 *  5xx / network → generic toast (no stack traces are ever shown)
 */
export const errorInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);
  const toast = inject(ToastService);
  const auth = inject(AuthStore);

  return next(req).pipe(
    catchError((err: unknown) => {
      if (!(err instanceof HttpErrorResponse)) {
        return throwError(() => err);
      }
      const silent = req.context.get(SILENT_ERRORS);
      const message = (err.error as { message?: string } | null)?.message;

      switch (err.status) {
        case 401:
          if (!req.context.get(SKIP_AUTH_REDIRECT)) {
            const wasStaff = auth.isStaff();
            auth.clear();
            const onAdmin = router.url.startsWith('/admin') || wasStaff;
            toast.info('Your session has expired. Please log in again.');
            void router.navigate([onAdmin ? '/admin/login' : '/login'], { queryParams: { returnUrl: router.url } });
          }
          break;
        case 403:
          if (!silent) toast.error(message ?? 'Access denied. You do not have permission to do that.');
          break;
        case 404:
          break;
        case 422:
          if (!silent) toast.error(message && message !== 'Validation failed' ? message : 'Please check the highlighted fields.');
          break;
        case 429:
          toast.error('You are doing that too often. Please wait a moment and try again.');
          break;
        case 0:
          if (!silent) toast.error('Cannot reach the server. Please check your connection.');
          break;
        default:
          if (err.status >= 500 && !silent) toast.error('Something went wrong on our side. Please try again.');
          else if (!silent && message && err.status !== 402) toast.error(message);
      }
      return throwError(() => err);
    }),
  );
};
