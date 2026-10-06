import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { environment } from '../../../environments/environment';
import { CART_TOKEN_KEY } from '../services/cart.service';
import { AuthStore } from '../state/auth.store';
import { storage } from '../utils/storage';

/**
 * Attaches the Sanctum bearer token and the guest cart token to API requests only
 * (never to third-party URLs).
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  if (!req.url.startsWith(environment.apiUrl)) {
    return next(req);
  }
  const token = inject(AuthStore).token();
  const cartToken = storage.get(CART_TOKEN_KEY);
  const setHeaders: Record<string, string> = { Accept: 'application/json' };
  if (token) {
    setHeaders['Authorization'] = `Bearer ${token}`;
  }
  if (cartToken) {
    setHeaders['X-Cart-Token'] = cartToken;
  }
  return next(req.clone({ setHeaders }));
};
