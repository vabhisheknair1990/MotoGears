import { TestBed } from '@angular/core/testing';
import { PriceComponent } from './price.component';

describe('PriceComponent', () => {
  it('shows MRP and discount only when discounted', async () => {
    const fixture = TestBed.createComponent(PriceComponent);
    fixture.componentRef.setInput('price', 1600);
    fixture.componentRef.setInput('mrp', 2000);
    await fixture.whenStable();
    const el: HTMLElement = fixture.nativeElement;
    expect(el.querySelector('.price-now')?.textContent).toContain('1,600');
    expect(el.querySelector('.price-mrp')?.textContent).toContain('2,000');
    expect(el.querySelector('.price-off')?.textContent).toContain('20% off');

    fixture.componentRef.setInput('mrp', 1600);
    await fixture.whenStable();
    expect(el.querySelector('.price-mrp')).toBeNull();
  });
});
