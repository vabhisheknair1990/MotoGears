/** localStorage wrapper that never throws (private mode, disabled storage, SSR). */
export const storage = {
  get(key: string): string | null {
    try {
      return globalThis.localStorage?.getItem(key) ?? null;
    } catch {
      return null;
    }
  },
  set(key: string, value: string | null): void {
    try {
      if (value === null) {
        globalThis.localStorage?.removeItem(key);
      } else {
        globalThis.localStorage?.setItem(key, value);
      }
    } catch {
      /* ignore */
    }
  },
  getJson<T>(key: string): T | null {
    const raw = storage.get(key);
    if (!raw) {
      return null;
    }
    try {
      return JSON.parse(raw) as T;
    } catch {
      return null;
    }
  },
  setJson(key: string, value: unknown): void {
    storage.set(key, value === null || value === undefined ? null : JSON.stringify(value));
  },
};
