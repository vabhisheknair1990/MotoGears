const inr = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2, minimumFractionDigits: 2 });
const inrWhole = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 });
const num = new Intl.NumberFormat('en-IN');

export function formatInr(value: number | null | undefined, whole = false): string {
  const v = Number(value ?? 0);
  return (whole || Number.isInteger(v) ? inrWhole : inr).format(v);
}

export function formatNumber(value: number | null | undefined): string {
  return num.format(Number(value ?? 0));
}

/** "₹1.2L", "₹3.4Cr" style compact rupees for chart axes and KPI tiles. */
export function compactInr(value: number): string {
  const v = Math.abs(value);
  if (v >= 1e7) return `₹${(value / 1e7).toFixed(v >= 1e8 ? 0 : 1)}Cr`;
  if (v >= 1e5) return `₹${(value / 1e5).toFixed(v >= 1e6 ? 0 : 1)}L`;
  if (v >= 1e3) return `₹${(value / 1e3).toFixed(v >= 1e4 ? 0 : 1)}K`;
  return `₹${Math.round(value)}`;
}

export function formatDate(iso: string | null | undefined, withTime = false): string {
  if (!iso) return '—';
  const d = new Date(iso);
  return d.toLocaleDateString('en-IN', withTime
    ? { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }
    : { day: 'numeric', month: 'short', year: 'numeric' });
}

export function timeAgo(iso: string | null | undefined): string {
  if (!iso) return '';
  const s = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
  if (s < 60) return 'just now';
  const units: [number, string][] = [[60, 'minute'], [60, 'hour'], [24, 'day'], [30, 'month'], [12, 'year']];
  let value = s;
  let label = 'second';
  for (const [size, name] of units) {
    if (value < size) break;
    value = Math.floor(value / size);
    label = name;
  }
  return `${value} ${label}${value === 1 ? '' : 's'} ago`;
}

export const STATUS_TONE: Record<string, 'neutral' | 'info' | 'success' | 'warning' | 'danger'> = {
  pending: 'warning', confirmed: 'info', processing: 'info', packed: 'info', shipped: 'info', out_for_delivery: 'info',
  delivered: 'success', cancelled: 'danger', returned: 'warning', refunded: 'neutral',
  paid: 'success', failed: 'danger', success: 'success', initiated: 'neutral',
  approved: 'success', rejected: 'danger', in_stock: 'success', low_stock: 'warning', out_of_stock: 'danger', backorder: 'info',
  active: 'success', inactive: 'neutral', new: 'warning', read: 'neutral', replied: 'success', closed: 'neutral',
  published: 'success', draft: 'neutral', subscribed: 'success', unsubscribed: 'neutral',
};

export function humanize(value: string | null | undefined): string {
  if (!value) return '';
  return value.replace(/[_-]+/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
