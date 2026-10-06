import { Pipe, PipeTransform } from '@angular/core';
import { compactInr, formatDate, formatInr, humanize, timeAgo } from '../../core/utils/format';

@Pipe({ name: 'inr' })
export class InrPipe implements PipeTransform {
  transform(value: number | null | undefined, mode: 'full' | 'whole' | 'compact' = 'full'): string {
    if (mode === 'compact') return compactInr(Number(value ?? 0));
    return formatInr(value, mode === 'whole');
  }
}

@Pipe({ name: 'appDate' })
export class AppDatePipe implements PipeTransform {
  transform(value: string | null | undefined, withTime = false): string {
    return formatDate(value, withTime);
  }
}

@Pipe({ name: 'timeAgo' })
export class TimeAgoPipe implements PipeTransform {
  transform(value: string | null | undefined): string {
    return timeAgo(value);
  }
}

@Pipe({ name: 'humanize' })
export class HumanizePipe implements PipeTransform {
  transform(value: string | null | undefined): string {
    return humanize(value);
  }
}
