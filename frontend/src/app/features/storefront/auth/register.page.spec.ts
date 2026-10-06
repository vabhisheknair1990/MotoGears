import { FormControl, FormGroup } from '@angular/forms';
import { passwordsMatch } from './register.page';

describe('passwordsMatch validator', () => {
  const group = (a: string, b: string) => new FormGroup({ password: new FormControl(a), password_confirmation: new FormControl(b) });

  it('flags mismatched confirmation', () => {
    expect(passwordsMatch(group('secret123', 'secret124'))).toEqual({ mismatch: true });
  });

  it('passes when equal or not yet filled', () => {
    expect(passwordsMatch(group('secret123', 'secret123'))).toBeNull();
    expect(passwordsMatch(group('secret123', ''))).toBeNull();
  });
});
