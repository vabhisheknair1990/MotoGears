import { TestBed } from '@angular/core/testing';
import { AreaChartComponent, compact, niceMax } from './area-chart.component';
import { BarListComponent } from './bar-list.component';

describe('chart helpers', () => {
  it('rounds axis maxima to nice numbers', () => {
    expect(niceMax(0)).toBe(1);
    expect(niceMax(73)).toBe(100);
    expect(niceMax(180)).toBe(200);
    expect(niceMax(2400)).toBe(2500);
    expect(niceMax(41000)).toBe(50000);
  });

  it('compacts axis labels', () => {
    expect(compact(1500)).toBe('1.5k');
    expect(compact(250000)).toBe('2.5L');
    expect(compact(20000000)).toBe('2Cr');
  });
});

describe('chart components', () => {
  it('area chart renders a line path and an accessible data table', async () => {
    const fixture = TestBed.createComponent(AreaChartComponent);
    fixture.componentRef.setInput('data', [{ label: 'Mon', value: 10 }, { label: 'Tue', value: 30 }, { label: 'Wed', value: 20 }]);
    fixture.componentRef.setInput('ariaLabel', 'Revenue');
    await fixture.whenStable();
    const el: HTMLElement = fixture.nativeElement;
    expect(el.querySelector('path[stroke]')?.getAttribute('d')).toMatch(/^M/);
    expect(el.querySelectorAll('table tbody tr').length).toBe(3);
  });

  it('bar list scales bars relative to the largest value', async () => {
    const fixture = TestBed.createComponent(BarListComponent);
    fixture.componentRef.setInput('items', [{ label: 'Bosch', value: 200 }, { label: 'Hella', value: 50 }]);
    await fixture.whenStable();
    const fills = Array.from((fixture.nativeElement as HTMLElement).querySelectorAll<HTMLElement>('.fill')).map((f) => f.style.width);
    expect(fills).toEqual(['100%', '25%']);
  });
});
