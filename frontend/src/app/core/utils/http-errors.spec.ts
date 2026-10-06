import { HttpErrorResponse } from '@angular/common/http';
import { FormControl, FormGroup } from '@angular/forms';
import { applyServerErrors, errorMessage, fieldErrors } from './http-errors';

const validation = new HttpErrorResponse({
  status: 422,
  error: { success: false, message: 'Validation failed', errors: { email: ['The email has already been taken.'], 'shipping_address.postal_code': ['Invalid PIN code.'], other: ['Unknown field.'] } },
});

describe('http error helpers', () => {
  it('prefers the API message', () => {
    expect(errorMessage(new HttpErrorResponse({ status: 400, error: { message: 'Coupon expired' } }))).toBe('Coupon expired');
  });

  it('explains network failures and falls back otherwise', () => {
    expect(errorMessage(new HttpErrorResponse({ status: 0 }))).toContain('Cannot reach the server');
    expect(errorMessage(new Error('x'), 'Fallback')).toBe('Fallback');
  });

  it('extracts Laravel field errors', () => {
    expect(fieldErrors(validation)['email']).toEqual(['The email has already been taken.']);
    expect(fieldErrors(new Error('nope'))).toEqual({});
  });

  it('maps 422 errors onto matching controls, including dotted paths', () => {
    const form = new FormGroup({ email: new FormControl(''), postal_code: new FormControl('') });
    const unmatched = applyServerErrors(form, validation);
    expect(form.controls.email.errors?.['server']).toBe('The email has already been taken.');
    expect(form.controls.postal_code.errors?.['server']).toBe('Invalid PIN code.');
    expect(form.controls.email.touched).toBe(true);
    expect(unmatched).toEqual(['Unknown field.']);
  });
});
