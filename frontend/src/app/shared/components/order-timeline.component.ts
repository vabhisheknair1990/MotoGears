import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { OrderStatus, TimelineEntry } from '../../core/models/api.models';
import { AppDatePipe } from '../pipes/inr.pipe';
import { IconComponent } from './icon.component';

const FLOW: { status: OrderStatus; label: string; icon: string }[] = [
  { status: 'pending', label: 'Placed', icon: 'file' },
  { status: 'confirmed', label: 'Confirmed', icon: 'check-circle' },
  { status: 'processing', label: 'Processing', icon: 'settings' },
  { status: 'packed', label: 'Packed', icon: 'package' },
  { status: 'shipped', label: 'Shipped', icon: 'truck' },
  { status: 'out_for_delivery', label: 'Out for delivery', icon: 'pin' },
  { status: 'delivered', label: 'Delivered', icon: 'home' },
];

/** Progress tracker plus the detailed status history returned by the API. */
@Component({
  selector: 'app-order-timeline',
  imports: [AppDatePipe, IconComponent],
  template: `
    @if (!terminal()) {
      <ol class="track" aria-label="Order progress">
        @for (s of flow; track s.status; let i = $index) {
          <li [class.done]="i <= reached()" [class.now]="i === reached()">
            <span class="dot"><app-icon [name]="s.icon" [size]="16" /></span>
            <span class="lbl">{{ s.label }}</span>
          </li>
        }
      </ol>
    } @else {
      <div class="alert" [class.alert-danger]="status() === 'cancelled'" [class.alert-warning]="status() !== 'cancelled'">
        <app-icon name="info" [size]="18" /> This order was {{ status() === 'out_for_delivery' ? 'out for delivery' : status() }}.
      </div>
    }
    <ul class="history list-reset">
      @for (e of reversed(); track $index) {
        <li>
          <span class="bullet" [class]="'bullet ' + e.status"></span>
          <div>
            <strong>{{ e.label }}</strong>
            @if (e.comment) { <p>{{ e.comment }}</p> }
            <span class="text-xs text-muted">{{ e.at | appDate: true }} @if (e.by) { · by {{ e.by }} }</span>
          </div>
        </li>
      }
    </ul>
  `,
  styles: `
    .track { display: flex; list-style: none; padding: 0; margin: 0 0 24px; overflow-x: auto; }
    .track li { flex: 1; min-width: 78px; position: relative; display: flex; flex-direction: column; align-items: center; gap: 6px; font-size: 12px; color: var(--muted); text-align: center; }
    .track li:not(:last-child)::after { content: ''; position: absolute; top: 17px; left: calc(50% + 20px); right: calc(-50% + 20px); height: 3px; background: var(--line); border-radius: 2px; }
    .track li.done:not(:last-child)::after { background: var(--success); }
    .track li.now:not(:last-child)::after { background: linear-gradient(90deg, var(--success), var(--line)); }
    .dot { width: 36px; height: 36px; border-radius: 50%; background: var(--line-2); display: grid; place-items: center; color: var(--muted); }
    .done .dot { background: var(--success); color: #fff; }
    .now .dot { box-shadow: 0 0 0 4px var(--success-50); }
    .done .lbl { color: var(--ink); font-weight: 600; }
    .history li { display: flex; gap: 12px; padding-bottom: 16px; position: relative; }
    .history li:not(:last-child)::before { content: ''; position: absolute; left: 5px; top: 14px; bottom: 0; width: 2px; background: var(--line); }
    .bullet { width: 12px; height: 12px; border-radius: 50%; background: var(--info); margin-top: 4px; flex: none; }
    .bullet.delivered, .bullet.confirmed { background: var(--success); }
    .bullet.cancelled { background: var(--danger); }
    .bullet.returned, .bullet.refunded, .bullet.pending { background: var(--warning); }
    .history p { margin: 2px 0; font-size: 14px; color: var(--ink-2); }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class OrderTimelineComponent {
  readonly status = input.required<OrderStatus>();
  readonly entries = input<TimelineEntry[]>([]);
  protected readonly flow = FLOW;
  protected readonly terminal = computed(() => ['cancelled', 'returned', 'refunded'].includes(this.status()));
  protected readonly reached = computed(() => FLOW.findIndex((f) => f.status === this.status()));
  protected readonly reversed = computed(() => [...this.entries()].reverse());
}
