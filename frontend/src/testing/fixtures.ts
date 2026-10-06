import { Cart, CartItem, ProductCard, User } from '../app/core/models/api.models';

/** Small factories for unit tests. Shapes mirror the Laravel API resources. */
export function productCard(overrides: Partial<ProductCard> = {}): ProductCard {
  return {
    id: 1, name: 'Bosch Premium Brake Pad', slug: 'bosch-premium-brake-pad', sku: 'BOS-BP-001', part_number: '0986AB1234',
    short_description: null, brand: { id: 1, name: 'Bosch', slug: 'bosch' }, category: { id: 2, name: 'Brake System', slug: 'brake-system' },
    image: null, mrp: 2000, price: 1600, discount_percent: 20, rating: 4.5, review_count: 12, vehicle_type: 'car',
    is_universal: false, is_featured: false, stock_status: 'in_stock', available_stock: 20, ...overrides,
  };
}

export function cartItem(overrides: Partial<CartItem> = {}): CartItem {
  return {
    id: 10, product: productCard(), quantity: 2, unit_price: 1600, mrp: 2000, line_subtotal: 3200, discount: 0, tax_rate: 18,
    tax_amount: 576, line_total: 3776, available_stock: 20, max_quantity: 10, in_stock: true, ...overrides,
  };
}

export function cart(overrides: Partial<Cart> = {}): Cart {
  return {
    id: 5, token: 'guest-token-abc', items: [cartItem()], item_count: 1, total_quantity: 2, subtotal: 3200, discount: 0, shipping: 0,
    tax: 576, grand_total: 3776, mrp_total: 4000, savings: 800, coupon: null, shipping_method: 'standard', free_shipping_threshold: 2999,
    amount_to_free_shipping: 0, warnings: [], is_checkout_ready: true, ...overrides,
  };
}

export function user(overrides: Partial<User> = {}): User {
  return {
    id: 7, name: 'Aarav Menon', email: 'customer@example.com', phone: null, avatar: null, marketing_opt_in: false, is_active: true,
    is_staff: false, roles: [{ name: 'customer', label: 'Customer' }], permissions: [], email_verified_at: null, last_login_at: null,
    created_at: '2026-01-01T00:00:00+05:30', ...overrides,
  };
}

export function envelope<T>(data: T, message = 'OK', meta?: Record<string, unknown>) {
  return { success: true, message, data, ...(meta ? { meta } : {}) };
}
