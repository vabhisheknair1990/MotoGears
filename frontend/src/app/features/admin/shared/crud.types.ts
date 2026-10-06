import { Observable } from 'rxjs';
import { Query } from '../../../core/api/api.service';
import { AdminApiService } from '../data/admin-api.service';
import { Lookups, Option } from '../data/admin.models';

/* eslint-disable @typescript-eslint/no-explicit-any */
export type Row = Record<string, any>;
export type FormValue = Record<string, unknown>;

export interface CrudCtx { lookups: Lookups | null; async: Record<string, Option[]> }

export type FieldType =
  | 'text' | 'email' | 'url' | 'password' | 'textarea' | 'html' | 'number' | 'select' | 'checkbox'
  | 'date' | 'datetime' | 'image' | 'tags' | 'multiselect' | 'products' | 'rating' | 'readonly';

export interface CrudField {
  key: string;
  label: string;
  type: FieldType;
  required?: boolean;
  /** Required only when creating (e.g. password). */
  requiredOnCreate?: boolean;
  options?: Option[] | ((ctx: CrudCtx, value: FormValue) => Option[]);
  placeholder?: string;
  hint?: string;
  /** Span both columns of the form grid. */
  full?: boolean;
  min?: number;
  max?: number;
  maxLength?: number;
  step?: number;
  rows?: number;
  default?: unknown;
  pattern?: RegExp;
  patternMessage?: string;
  uppercase?: boolean;
  /** For images: row key holding the current URL, and the flag sent to remove it. */
  imageKey?: string;
  removeKey?: string;
  wide?: boolean;
  /** Clear these fields when this one changes (dependent dropdowns). */
  resets?: string[];
  visible?: (value: FormValue, row: Row | null) => boolean;
  createOnly?: boolean;
  /** For `readonly`: how to render the value. */
  render?: (row: Row) => string;
}

export type ColumnType = 'text' | 'strong' | 'bool' | 'status' | 'image' | 'date' | 'datetime' | 'money' | 'number' | 'rating' | 'code' | 'toggle';

export interface CrudColumn {
  key: string;
  label: string;
  type?: ColumnType;
  value?: (row: Row) => unknown;
  sub?: (row: Row) => string | null | undefined;
  align?: 'right' | 'center';
  hideMobile?: boolean;
}

export interface CrudFilter { key: string; label: string; options: Option[] | ((ctx: CrudCtx) => Option[]) }

export interface CrudLink { label: string; icon?: string; path: (row: Row) => unknown[]; query?: (row: Row) => Record<string, unknown> }

export interface CrudConfig {
  key: string;
  title: string;
  subtitle?: string;
  singular: string;
  resource: string;
  columns: CrudColumn[];
  fields: CrudField[];
  rowTitle: (row: Row) => string;
  filters?: CrudFilter[];
  search?: string | false;
  multipart?: boolean;
  create?: boolean;
  edit?: boolean;
  remove?: boolean;
  /** Boolean column toggled inline with PATCH { [toggle]: value }. */
  toggle?: string;
  /** false when the endpoint returns a plain array (no pagination meta). */
  paginated?: boolean;
  perPage?: number;
  initialQuery?: Query;
  modalSize?: 'md' | 'lg' | 'xl';
  fromRow?: (row: Row) => FormValue;
  toPayload?: (value: FormValue, row: Row | null) => FormValue;
  asyncOptions?: Record<string, (api: AdminApiService) => Observable<Option[]>>;
  links?: CrudLink[];
  exportFile?: { path: string; filename: string };
  deleteMessage?: (row: Row) => string;
  emptyText?: string;
  /** HTTP verb for JSON updates (default PATCH). */
  updateMethod?: 'put' | 'patch';
  /** Dialog only shows details (no save), e.g. audit log entries. */
  readOnly?: boolean;
  /** Lookups become stale after writes to these resources. */
  refreshesLookups?: boolean;
}
