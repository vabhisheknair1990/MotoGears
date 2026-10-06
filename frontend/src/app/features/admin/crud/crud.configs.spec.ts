import { CRUD_CONFIGS, categoryOptions } from './crud.configs';

describe('admin CRUD configs', () => {
  it('each config has unique field keys and columns', () => {
    for (const cfg of Object.values(CRUD_CONFIGS)) {
      const keys = cfg.fields.map((f) => f.key);
      expect(new Set(keys).size, cfg.key).toBe(keys.length);
      expect(cfg.columns.length, cfg.key).toBeGreaterThan(0);
      expect(cfg.resource, cfg.key).not.toMatch(/^\//);
    }
  });

  it('image fields only appear on multipart resources', () => {
    for (const cfg of Object.values(CRUD_CONFIGS)) {
      if (cfg.fields.some((f) => f.type === 'image')) expect(cfg.multipart, cfg.key).toBe(true);
    }
  });

  it('coupon payload zeroes the value for free-shipping coupons and drops max discount for fixed ones', () => {
    const toPayload = CRUD_CONFIGS['coupons'].toPayload!;
    expect(toPayload({ type: 'free_shipping', value: 50, max_discount: 10 }, null)).toMatchObject({ value: 0, max_discount: null });
    expect(toPayload({ type: 'fixed', value: 500, max_discount: 100 }, null)).toMatchObject({ value: 500, max_discount: null });
  });

  it('labels sub-categories with their parent', () => {
    const opts = categoryOptions({ categories: [{ id: 1, name: 'Car Parts', parent_id: null, slug: 'car-parts', is_active: true }, { id: 2, name: 'Brakes', parent_id: 1, slug: 'brakes', is_active: true }] } as never);
    expect(opts.map((o) => o.label)).toEqual(['Car Parts', 'Car Parts › Brakes']);
  });
});
