/** Shapes returned by the Laravel admin API (/v1/admin). */
import { Address, CustomerVehicle, Order, Product, ProductCard, Review, User } from '../../../core/models/api.models';

export interface Option<T = string | number> { value: T; label: string; group?: string }

export interface Lookups {
  categories: { id: number; name: string; parent_id: number | null; slug: string; is_active: boolean }[];
  brands: { id: number; name: string; slug: string; is_active: boolean }[];
  manufacturers: { id: number; name: string; vehicle_type: string }[];
  attributes: { id: number; name: string; slug: string; values: { id: number; value: string }[] }[];
  order_statuses: Option<string>[];
  payment_methods: Option<string>[];
  tax_rates: number[];
  vehicle_types: string[];
  fuel_types: string[];
  transmissions: string[];
  positions: string[];
}

export interface Named { id: number; name: string }
export interface Money { revenue: number; units?: number; orders?: number }

export interface Dashboard {
  sales: { total: number; today: number; this_month: number; last_month: number; growth_percent: number; average_order_value: number };
  orders: { total: number; today: number; pending: number; processing: number; shipped: number; delivered: number; cancelled: number; by_status: { status: string; label: string; count: number }[] };
  customers: { total: number; new_this_month: number };
  products: { total: number; active: number };
  inventory: { low_stock: number; out_of_stock: number; units_on_hand: number; units_reserved: number };
  top_products: { id: number; name: string; sku: string; units: number; orders: number; revenue: number }[];
  top_categories: { id: number; name: string; units: number; revenue: number }[];
  top_brands: { id: number; name: string; units: number; revenue: number }[];
  recent_orders: { id: number; order_number: string; customer: string; status: string; status_label: string; payment_status: string; grand_total: number; items_count: number; placed_at: string }[];
  low_stock: { id: number; sku: string; product: string; product_id: number; available: number; threshold: number; status: string }[];
  sales_chart: { date: string; label: string; orders: number; revenue: number }[];
  monthly_chart: { month: string; label: string; orders: number; revenue: number }[];
  payment_methods: { method: string; label: string; orders: number; revenue: number }[];
}

export interface AdminProductRow extends ProductCard {
  is_active: boolean; sold_count: number; stock: { quantity: number; reserved: number; available: number } | null; deleted_at: string | null; updated_at: string;
}

export interface AdminProduct extends Omit<Product, 'variants' | 'inventory' | 'faqs'> {
  category_id: number; brand_id: number; cost_price: number | null; is_active: boolean; sold_count: number; view_count: number;
  attribute_value_ids: number[]; updated_at: string; deleted_at: string | null;
  inventory: InventoryRecord | null;
  faqs: { id: number; question: string; answer: string }[];
  variants: { id: number; sku: string; name: string; options: Record<string, string> | null; price_adjustment: number; is_active: boolean }[];
}

export interface CompatibilityRow {
  id?: number;
  manufacturer: { id: number; name: string; slug?: string };
  model: { id: number; name: string } | null;
  variant: { id: number; name: string } | null;
  year_from: number | null; year_to: number | null; notes: string | null; label?: string;
}

export interface CompatibilityInput {
  vehicle_manufacturer_id: number; vehicle_model_id: number | null; vehicle_variant_id: number | null;
  year_from: number | null; year_to: number | null; notes: string | null;
}

export interface AdminCategory {
  id: number; parent_id: number | null; name: string; slug: string; description: string | null; image: string | null; icon: string | null;
  vehicle_type: string | null; seo_title: string | null; seo_description: string | null; is_active: boolean; is_featured: boolean; sort_order: number;
  products_count: number; parent: { id: number; name: string } | null;
}

export interface AdminBrand {
  id: number; name: string; slug: string; logo: string | null; description: string | null; website: string | null; country: string | null;
  seo_title: string | null; seo_description: string | null; is_active: boolean; is_featured: boolean; sort_order: number; products_count: number;
}

export interface AdminManufacturer { id: number; name: string; slug: string; logo: string | null; vehicle_type: string; country: string | null; is_active: boolean; sort_order: number; models_count: number }
export interface AdminModel { id: number; vehicle_manufacturer_id: number; name: string; slug: string; vehicle_type: string; body_type: string | null; is_active: boolean; variants_count: number; manufacturer: { id: number; name: string } }
export interface AdminVariant {
  id: number; vehicle_model_id: number; name: string; year_from: number; year_to: number | null; year_range: string; engine: string | null;
  fuel_type: string | null; transmission: string | null; displacement_cc: number | null; is_active: boolean; full_name: string;
  model: { id: number; name: string; vehicle_type: string; manufacturer: { id: number; name: string } };
}

export type AdminOrder = Order;

export interface OrderListMeta { status_counts: Record<string, number>; statuses: Option<string>[] }

export interface AdminCustomer {
  id: number; name: string; email: string; phone: string | null; is_active: boolean; marketing_opt_in: boolean;
  orders_count: number; total_spent: number; last_order_at: string | null; last_login_at: string | null; created_at: string;
}

export interface AdminCustomerDetail extends AdminCustomer {
  addresses: Address[];
  vehicles: CustomerVehicle[];
  orders: AdminOrder[];
  reviews: AdminReview[];
  average_order_value: number;
}

export interface InventoryRecord {
  id: number; sku: string; product?: { id: number; name: string; slug: string; image: string | null; brand: string; is_active: boolean };
  quantity: number; reserved: number; available: number; low_stock_threshold: number; allow_backorder: boolean; location: string | null; status: string; updated_at: string;
}

export interface InventoryTransaction {
  id: number; type: string; type_label: string; quantity: number; quantity_after: number; reserved_after: number; reference: string | null;
  reference_type: string | null; reference_id: number | null; note: string | null; product: { id: number; name: string; sku: string } | null; user: string | null; created_at: string;
}

export interface InventoryDetail {
  inventory: InventoryRecord;
  history: InventoryTransaction[];
  history_meta: { current_page: number; last_page: number; per_page: number; total: number };
  adjustment_types: Option<string>[];
}

export type AdminReview = Review;

export interface AdminNotification { id: string; type: string; title: string; message: string; url: string | null; read_at: string | null; created_at: string }

export interface SettingEntry { key: string; value: string | number | boolean | null; type: string; group: string; is_public: boolean }

export interface StaffUser extends User { permissions: string[] }
export interface RoleRow { id: number; name: string; label: string; description: string | null; is_staff: boolean; users_count: number; permissions: string[] }
export interface PermissionRow { id: number; name: string; label: string; group: string }

export interface AuditLog {
  id: number; action: string; user: { id: number; name: string } | null; subject_type: string | null; subject_id: number | null;
  old_values: Record<string, unknown> | null; new_values: Record<string, unknown> | null; ip_address: string | null; created_at: string;
}

export type ReportPeriod = 'today' | 'yesterday' | 'last_7_days' | 'last_30_days' | 'this_month' | 'last_month' | 'this_year' | 'custom';
export interface ReportMeta { period: ReportPeriod; from: string; to: string }

export interface SalesReport {
  summary: { revenue: number; orders: number; items_sold: number; gross_sales: number; discounts: number; tax: number; shipping: number; average_order_value: number; cancelled_orders: number; cancelled_value: number; previous_period_revenue: number; growth_percent: number };
  series: { date: string; label: string; orders: number; revenue: number }[];
  by_payment_method: { method: string; label: string; orders: number; revenue: number }[];
  by_status: { status: string; label: string; count: number; value: number }[];
  top_coupons: { code: string; uses: number; discount: number }[];
}
export interface ProductsReport {
  top_products: { id: number; name: string; sku: string; units: number; orders: number; revenue: number }[];
  by_category: { id: number; name: string; units: number; revenue: number }[];
  by_brand: { id: number; name: string; units: number; revenue: number }[];
  most_viewed: { id: number; name: string; sku: string; view_count: number; sold_count: number }[];
  never_sold: number;
}
export interface CustomersReport {
  summary: { new_customers: number; total_customers: number; buyers: number; repeat_buyers: number; repeat_rate: number };
  top_customers: { id: number; name: string; email: string; orders: number; spent: number }[];
  signups: { date: string; label: string; count: number }[];
  top_cities: { city: string; orders: number }[];
}
export interface InventoryReport {
  summary: { skus: number; units_on_hand: number; units_reserved: number; stock_value_cost: number; stock_value_retail: number; low_stock: number; out_of_stock: number };
  movements: { type: string; label: string; entries: number; units: number }[];
  low_stock: { id: number; sku: string; product: string; quantity: number; reserved: number; available: number; threshold: number }[];
  out_of_stock: { id: number; sku: string; product: string; quantity: number; reserved: number; available: number; threshold: number }[];
}

// ── Product import ───────────────────────────────────────────────
export type ImportMode = 'upsert' | 'create' | 'update';
export type ImportStatus = 'pending' | 'validating' | 'validated' | 'queued' | 'importing' | 'completed' | 'completed_with_errors' | 'failed' | 'cancelled';

export interface ImportSummary {
  will_create: number;
  will_update: number;
  will_skip: number;
  invalid: number;
  errors: number;
  warnings: number;
  images: number;
  rows: { products: number; compatibility: number; variants: number; faqs: number };
}

export interface ImportIssue {
  level: 'error' | 'warning';
  sheet: string;
  row: number | null;
  sku: string | null;
  column: string | null;
  message: string;
}

export interface ProductImportJob {
  id: number;
  original_name: string;
  file_size: number;
  mode: ImportMode;
  mode_label: string;
  auto_start: boolean;
  status: ImportStatus;
  status_label: string;
  phase: 'check' | 'import';
  progress: number;
  total_rows: number;
  processed_rows: number;
  created_count: number;
  updated_count: number;
  skipped_count: number;
  failed_count: number;
  warning_count: number;
  error_count: number;
  summary: ImportSummary | null;
  message: string | null;
  is_active: boolean;
  can_start: boolean;
  can_cancel: boolean;
  cancel_requested: boolean;
  waiting_for_worker: boolean;
  has_file: boolean;
  user: { id: number; name: string } | null;
  created_at: string | null;
  validated_at: string | null;
  started_at: string | null;
  finished_at: string | null;
}

export interface ProductImportDetail extends ProductImportJob {
  issues: ImportIssue[];
  issues_total: number;
  issues_truncated: boolean;
}

export interface ImportColumn {
  sheet: string;
  key: string;
  header: string;
  required: boolean;
  help: string | null;
  example: string | null;
  dropdown: boolean;
}
