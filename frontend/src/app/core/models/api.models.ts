/** Shapes returned by the Laravel REST API (/api/v1). */

export interface ApiEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
  meta?: PageMeta & Record<string, unknown>;
  errors?: Record<string, string[]>;
}

export interface PageMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

export interface Page<T, M = Record<string, unknown>> {
  items: T[];
  meta: PageMeta & M;
}

export type StockStatus = 'in_stock' | 'low_stock' | 'out_of_stock' | 'backorder';
export type VehicleType = 'car' | 'motorcycle' | 'universal';

export interface Ref { id: number; name: string; slug: string }

export interface ProductCard {
  id: number;
  name: string;
  slug: string;
  sku: string;
  part_number: string | null;
  short_description: string | null;
  brand: Ref | null;
  category: Ref | null;
  image: string | null;
  mrp: number;
  price: number;
  discount_percent: number;
  rating: number;
  review_count: number;
  vehicle_type: VehicleType;
  is_universal: boolean;
  is_featured: boolean;
  stock_status: StockStatus;
  available_stock: number;
  fits_vehicle?: boolean;
}

export interface Compatibility {
  id: number;
  manufacturer: Ref | null;
  model: { id: number; name: string } | null;
  variant: { id: number; name: string; fuel_type: string | null; transmission: string | null; engine: string | null } | null;
  year_from: number | null;
  year_to: number | null;
  notes: string | null;
  label: string;
}

export interface ProductImage { id: number; url: string; alt: string; is_primary: boolean; sort_order?: number }

export interface Product extends Omit<ProductCard, 'brand' | 'category'> {
  description: string | null;
  tax_rate: number;
  position: string | null;
  material: string | null;
  dimensions: string | null;
  weight_kg: number | null;
  warranty: string | null;
  installation_info: string | null;
  whats_included: string[];
  specifications: { label: string; value: string }[];
  tags: string[];
  video_url: string | null;
  meta_title: string;
  meta_description: string | null;
  brand: Ref & { logo: string | null; country: string | null };
  category: Ref & { parent: Ref | null };
  images: ProductImage[];
  variants: { id: number; sku: string; name: string; options: Record<string, string> | null; price: number }[];
  attributes: { name: string; values: string[] }[];
  faqs: { question: string; answer: string }[];
  compatibility: Compatibility[];
  inventory: { status: StockStatus; available: number; allow_backorder: boolean };
  breadcrumbs?: { name: string; slug: string | null }[];
  fits_selected_vehicle?: { vehicle: string; fits: boolean } | null;
  created_at: string;
}

export interface Category {
  id: number;
  parent_id: number | null;
  name: string;
  slug: string;
  description: string | null;
  image: string | null;
  icon: string | null;
  vehicle_type: VehicleType;
  seo_title: string;
  seo_description: string | null;
  is_active: boolean;
  is_featured: boolean;
  sort_order: number;
  products_count?: number;
  parent?: Ref | null;
  children?: Category[];
  breadcrumbs?: { name: string; slug: string }[];
}

export interface Brand {
  id: number;
  name: string;
  slug: string;
  logo: string | null;
  description: string | null;
  website: string | null;
  country: string | null;
  seo_title: string;
  seo_description: string | null;
  is_active: boolean;
  is_featured: boolean;
  sort_order: number;
  products_count?: number;
}

export interface Manufacturer {
  id: number; name: string; slug: string; logo: string | null; vehicle_type: 'car' | 'motorcycle' | 'both';
  country: string | null; is_active: boolean; sort_order: number; models_count?: number;
}
export interface VehicleModel {
  id: number; vehicle_manufacturer_id: number; name: string; slug: string; vehicle_type: 'car' | 'motorcycle';
  body_type: string | null; image: string | null; is_active: boolean; variants_count?: number; manufacturer?: Ref;
}
export interface VehicleVariant {
  id: number; vehicle_model_id: number; name: string; year_from: number; year_to: number | null; year_range: string;
  engine: string | null; fuel_type: string | null; transmission: string | null; displacement_cc: number | null; is_active: boolean;
  model?: { id: number; name: string; vehicle_type: string; manufacturer: Ref | null };
  full_name?: string;
}

/** The vehicle a shopper has selected; persisted locally and used to filter the catalogue. */
export interface SelectedVehicle {
  manufacturerId: number;
  manufacturerName: string;
  modelId: number;
  modelName: string;
  year: number | null;
  variantId: number;
  variantName: string;
  vehicleType?: string;
}

export interface Facets {
  brands: (Ref & { count: number })[];
  categories: (Ref & { count: number })[];
  price: { min: number; max: number };
  ratings: number[];
  discounts: number[];
  attributes: { name: string; slug: string; values: { value: string; slug: string; count: number }[] }[];
  sorts: { value: string; label: string }[];
}

export interface CartItem {
  id: number;
  product: ProductCard;
  quantity: number;
  unit_price: number;
  mrp: number;
  line_subtotal: number;
  discount: number;
  tax_rate: number;
  tax_amount: number;
  line_total: number;
  available_stock: number;
  max_quantity: number;
  in_stock: boolean;
}

export interface AppliedCoupon {
  id: number; code: string; name: string; type: 'percentage' | 'fixed' | 'free_shipping';
  description: string | null; discount: number; free_shipping: boolean;
}

export interface Cart {
  id: number | null;
  token: string | null;
  items: CartItem[];
  item_count: number;
  total_quantity: number;
  subtotal: number;
  discount: number;
  shipping: number;
  tax: number;
  grand_total: number;
  mrp_total: number;
  savings: number;
  coupon: AppliedCoupon | null;
  shipping_method: 'standard' | 'express';
  free_shipping_threshold: number;
  amount_to_free_shipping: number;
  warnings: string[];
  is_checkout_ready: boolean;
}

export interface Wishlist {
  items: { id: number; product: ProductCard; added_at: string }[];
  count: number;
  product_ids: number[];
}

export interface Address {
  id: number;
  label: string;
  name: string;
  phone: string;
  line1: string;
  line2: string | null;
  landmark: string | null;
  city: string;
  state: string;
  postal_code: string;
  country: string;
  is_default: boolean;
}
export type AddressInput = Omit<Address, 'id' | 'is_default'> & { is_default?: boolean };
export type AddressSnapshot = Omit<Address, 'id' | 'label' | 'is_default'>;

export interface ShippingMethod { code: 'standard' | 'express'; label: string; description: string; eta_days: [number, number]; cost: number }
export type PaymentCode = 'cod' | 'razorpay' | 'demo_card' | 'demo_upi';
export type OnlinePaymentCode = Exclude<PaymentCode, 'cod'>;
export interface PaymentMethodOption { code: PaymentCode; label: string; description: string; available: boolean; unavailable_reason?: string }

/** Public options for Razorpay checkout.js, produced by Laravel (never contains the key secret). */
export interface RazorpayCheckoutOptions {
  key: string; order_id: string; amount: number; currency: string; name: string; description: string;
  prefill: { name: string; email: string; contact: string }; notes: Record<string, string>; theme: { color: string }; test_mode: boolean;
}

export interface CheckoutSummary {
  cart: Cart;
  addresses: Address[];
  shipping_methods: ShippingMethod[];
  payment_methods: PaymentMethodOption[];
}

export type OrderStatus = 'pending' | 'confirmed' | 'processing' | 'packed' | 'shipped' | 'out_for_delivery' | 'delivered' | 'cancelled' | 'returned' | 'refunded';

export interface OrderItem {
  id: number; product_id: number | null; product_name: string; product_slug: string | null; sku: string; part_number: string | null;
  brand_name: string | null; image: string | null; mrp: number; unit_price: number; quantity: number; discount: number;
  tax_rate: number; tax_amount: number; line_total: number; reviewed?: boolean;
}

export interface Payment {
  id: number; method: string; method_label: string; gateway: string; transaction_id: string | null; amount: number; currency: string;
  status: 'initiated' | 'pending' | 'success' | 'failed' | 'refunded'; failure_reason: string | null;
  details: { brand?: string; last4?: string; vpa?: string; bank?: string; wallet?: string; rzp_method?: string; collect_on_delivery?: boolean; refund_id?: string };
  paid_at: string | null; created_at: string;
}

export interface TimelineEntry { status: OrderStatus; label: string; comment: string | null; by?: string | null; at: string }

export interface Order {
  id: number;
  order_number: string;
  status: OrderStatus;
  status_label: string;
  payment_status: 'pending' | 'paid' | 'failed' | 'refunded';
  payment_method: PaymentCode;
  payment_method_label: string;
  shipping_method: string;
  subtotal: number;
  discount: number;
  shipping: number;
  tax: number;
  grand_total: number;
  currency: string;
  coupon_code: string | null;
  billing_address: AddressSnapshot;
  shipping_address: AddressSnapshot;
  tracking_number: string | null;
  carrier: string | null;
  customer_notes: string | null;
  admin_notes?: string | null;
  items_count?: number;
  total_quantity?: number;
  items?: OrderItem[];
  preview_items?: { name: string; image: string | null; quantity: number }[];
  payment?: Payment | null;
  payments?: Payment[];
  timeline?: TimelineEntry[];
  customer?: { id: number; name: string; email: string; phone: string | null };
  can_cancel: boolean;
  can_pay: boolean;
  can_review: boolean;
  allowed_transitions?: { value: OrderStatus; label: string }[];
  placed_at: string | null;
  shipped_at: string | null;
  delivered_at: string | null;
  cancelled_at: string | null;
  created_at: string;
}

export interface PlaceOrderResult { order: Order; payment: Payment; payment_successful: boolean; razorpay?: RazorpayCheckoutOptions | null }

export interface Invoice {
  invoice_number: string; invoice_date: string; order_number: string; order_id: number; status: string;
  payment_method: string; payment_status: string; transaction_id: string | null;
  seller: { name: string; address: string; gstin: string; email: string; phone: string };
  customer: { name: string; email: string; phone: string | null };
  billing_address: AddressSnapshot; shipping_address: AddressSnapshot;
  items: { name: string; sku: string; part_number: string | null; hsn: string; quantity: number; unit_price: number; discount: number; taxable_value: number; tax_rate: number; tax_amount: number; total: number }[];
  tax_split: string;
  totals: { subtotal: number; discount: number; shipping: number; shipping_tax: number; tax: number; grand_total: number };
  coupon_code: string | null; currency: string;
}

export interface Review {
  id: number; rating: number; title: string | null; comment: string | null; status: 'pending' | 'approved' | 'rejected';
  is_verified_purchase: boolean; is_featured: boolean; helpful_count: number; author: string;
  customer?: { id: number; name: string; email: string } | null;
  images?: { id: number; url: string }[];
  product?: { id: number; name: string; slug: string; image: string | null } | null;
  created_at: string;
}
export interface ReviewSummary { average: number; count: number; distribution: Record<string, number> }

export interface User {
  id: number; name: string; email: string; phone: string | null; avatar: string | null; marketing_opt_in: boolean;
  is_active: boolean; is_staff: boolean; roles: { name: string; label: string }[]; permissions?: string[];
  email_verified_at: string | null; last_login_at: string | null; created_at: string;
  badges?: { pending_reviews: number; new_enquiries: number; open_orders: number; unread_notifications: number };
}

export interface AuthPayload { token: string; token_type: 'Bearer'; expires_at: string; user: User }

export interface CustomerVehicle {
  id: number; year: number | null; nickname: string | null; registration_number: string | null; is_default: boolean;
  variant: VehicleVariant; created_at: string;
}

export interface AppNotification { id: string; type: string; title: string; message: string; url: string | null; read_at: string | null; created_at: string }

export interface AccountDashboard {
  user: User;
  stats: { orders: number; open_orders: number; total_spent: number; wishlist: number; vehicles: number; reviews: number; unread_notifications: number };
  recent_orders: Order[];
  default_vehicle: CustomerVehicle | null;
}

export interface Banner {
  id: number; title: string; subtitle: string | null; eyebrow: string | null; desktop_image: string | null; mobile_image: string | null;
  cta_label: string | null; cta_url: string | null; placement: 'hero' | 'promo' | 'offer'; theme: 'dark' | 'light';
  starts_at: string | null; ends_at: string | null; is_active: boolean; sort_order: number;
}

export interface Coupon {
  id: number; code: string; name: string; description: string | null; type: 'percentage' | 'fixed' | 'free_shipping'; type_label: string;
  value: number; min_order_amount: number; max_discount: number | null; starts_at: string | null; expires_at: string | null;
  usage_limit: number | null; per_user_limit: number | null; used_count: number; is_active: boolean; is_expired: boolean;
  product_ids?: number[]; category_ids?: number[];
  products?: { id: number; name: string; sku: string }[]; categories?: { id: number; name: string }[];
  created_at: string;
}

export interface BlogPost {
  id: number; title: string; slug: string; excerpt: string | null; content?: string; cover_image: string | null; status: 'draft' | 'published';
  published_at: string | null; reading_minutes: number; meta_title: string; meta_description: string | null; author?: string | null;
  category?: Ref | null; tags?: Ref[]; blog_category_id: number | null; related?: BlogPost[]; created_at: string;
}

export interface Testimonial { id: number; name: string; location: string | null; vehicle: string | null; rating: number; content: string }

export interface Homepage {
  hero_banners: Banner[];
  promo_banners: Banner[];
  featured_categories: Category[];
  featured_products: ProductCard[];
  best_sellers: ProductCard[];
  new_arrivals: ProductCard[];
  deals: ProductCard[];
  brands: Brand[];
  offers: Coupon[];
  testimonials: Testimonial[];
  blog_posts: BlogPost[];
  stats: { products: number; brands: number; vehicles: number };
  for_your_vehicle?: { vehicle: { id: number; name: string }; products: ProductCard[] };
}

export interface SearchSuggestions {
  query: string;
  products: ProductCard[];
  categories: { id: number; name: string; slug: string }[];
  brands: { id: number; name: string; slug: string }[];
  vehicles: { id: number; name: string; manufacturer_id: number }[];
  terms: string[];
}

export interface Faq { id: number; category: string; question: string; answer: string }
export interface CmsPage { id: number; title: string; slug: string; content: string; meta_title: string | null; meta_description: string | null }
export interface StoreSettings {
  store_name: string; store_tagline: string; support_phone: string; support_email: string; store_address: string;
  currency: string; free_shipping_threshold: number; standard_shipping_cost?: number; express_shipping_cost?: number; cod_enabled?: boolean;
  payment_methods?: { code: PaymentCode; label: string; description: string }[];
}
