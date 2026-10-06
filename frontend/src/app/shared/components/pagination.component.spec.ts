import { TestBed } from '@angular/core/testing';
import { PaginationComponent } from './pagination.component';

describe('PaginationComponent', () => {
  async function render(current: number, last: number) {
    const fixture = TestBed.createComponent(PaginationComponent);
    fixture.componentRef.setInput('meta', { current_page: current, last_page: last, per_page: 20, total: last * 20, from: 1, to: 20 });
    await fixture.whenStable();
    return fixture;
  }

  it('renders a compact page list with gaps', async () => {
    const fixture = await render(5, 10);
    const labels = Array.from((fixture.nativeElement as HTMLElement).querySelectorAll('.pages > *')).map((e) => e.textContent?.trim());
    expect(labels).toEqual(['', '1', '…', '4', '5', '6', '…', '10', '']);
  });

  it('emits the requested page and disables prev on page 1', async () => {
    const fixture = await render(1, 3);
    const emitted: number[] = [];
    fixture.componentInstance.pageChange.subscribe((p) => emitted.push(p));
    const buttons = (fixture.nativeElement as HTMLElement).querySelectorAll<HTMLButtonElement>('.pages button');
    expect(buttons[0].disabled).toBe(true);
    buttons[buttons.length - 1].click();
    expect(emitted).toEqual([2]);
  });

  it('hides page buttons when there is a single page', async () => {
    const fixture = await render(1, 1);
    expect((fixture.nativeElement as HTMLElement).querySelector('.pages')).toBeNull();
  });
});
