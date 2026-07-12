import { useCallback, useEffect, useState } from 'react';
import { lookupsApi, type LookupItem } from '../services/api';
import type { SelectOption } from '../components/Select';

/** Lookup forType → SelectOption[] (aktif değerler). */
export function useLookupOptions(lookupType: string): {
  options: SelectOption[];
  items: LookupItem[];
  loading: boolean;
  reload: () => Promise<void>;
} {
  const [items, setItems] = useState<LookupItem[]>([]);
  const [loading, setLoading] = useState(true);

  const reload = useCallback(async () => {
    try {
      setLoading(true);
      const res = await lookupsApi.forType(lookupType);
      setItems(res.data.data ?? []);
    } catch {
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [lookupType]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const options: SelectOption[] = items.map((i) => ({
    value: i.value,
    label: i.label,
    color: i.color,
  }));

  return { options, items, loading, reload };
}
