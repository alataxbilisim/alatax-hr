import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from '@shared/i18n';
import {
  settingsRegistryApi,
  type SettingCatalogItem,
} from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';

/**
 * D4a UI-1 — Merkezi davranış ayarları (Lookup/Stüdyo yüzeylerinden ayrı).
 */
const SettingsRegistryPage: React.FC = () => {
  const { t } = useTranslation('common');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [items, setItems] = useState<SettingCatalogItem[]>([]);
  const [draft, setDraft] = useState<Record<string, unknown>>({});
  const [moduleFilter, setModuleFilter] = useState<string>('all');
  const [search, setSearch] = useState('');
  const [showAdvanced, setShowAdvanced] = useState(false);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const res = await settingsRegistryApi.registry({ include_advanced: true });
      const data = res.data.data;
      const list: SettingCatalogItem[] = Array.isArray(data) ? data : [];
      setItems(list);
      const next: Record<string, unknown> = {};
      for (const item of list) {
        next[item.key] = item.value;
      }
      setDraft(next);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('settingsRegistry.loadError')));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => {
    void load();
  }, [load]);

  const modules = useMemo(() => {
    const set = new Set(items.map((i) => i.module_key));
    return Array.from(set).sort();
  }, [items]);

  const filtered = useMemo(() => {
    return items.filter((item) => {
      if (moduleFilter !== 'all' && item.module_key !== moduleFilter) return false;
      if (!showAdvanced && item.tier === 'advanced') return false;
      if (item.tier === 'system') return false;
      if (search.trim()) {
        const q = search.trim().toLowerCase();
        const label = t(item.label_key).toLowerCase();
        return label.includes(q) || item.key.toLowerCase().includes(q);
      }
      return true;
    });
  }, [items, moduleFilter, showAdvanced, search, t]);

  const dirtyKeys = useMemo(() => {
    return filtered
      .filter((item) => item.can_edit && draft[item.key] !== item.value)
      .map((item) => item.key);
  }, [filtered, draft]);

  const save = async () => {
    const values = dirtyKeys.map((key) => ({ key, value: draft[key] }));
    if (values.length === 0) {
      toast.success(t('settingsRegistry.noChanges'));
      return;
    }
    try {
      setSaving(true);
      await settingsRegistryApi.updateValues({ scope_type: 'company', values });
      toast.success(t('settingsRegistry.saveSuccess'));
      await load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('settingsRegistry.saveError')));
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <div className="page-container">{t('loading')}</div>;
  }

  return (
    <div className="page-container animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">{t('settingsRegistry.title')}</h1>
          <p className="page-subtitle">{t('settingsRegistry.subtitle')}</p>
        </div>
        <div className="page-header-actions">
          <button type="button" className="btn btn-primary" disabled={saving || dirtyKeys.length === 0} onClick={() => void save()}>
            {saving ? t('saving') : t('save')}
          </button>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '220px 1fr', gap: 'var(--sp-4)' }}>
        <aside>
          <h3 style={{ fontSize: 'var(--fs-sm)', marginBottom: 'var(--sp-2)' }}>{t('settingsRegistry.modules')}</h3>
          <button
            type="button"
            className={`btn btn-sm ${moduleFilter === 'all' ? 'btn-primary' : 'btn-ghost'}`}
            style={{ display: 'block', width: '100%', marginBottom: 'var(--sp-1)' }}
            onClick={() => setModuleFilter('all')}
          >
            {t('settingsRegistry.modules')}
          </button>
          {modules
            .filter((m) => m !== 'settings')
            .map((mod) => (
              <button
                key={mod}
                type="button"
                className={`btn btn-sm ${moduleFilter === mod ? 'btn-primary' : 'btn-ghost'}`}
                style={{ display: 'block', width: '100%', marginBottom: 'var(--sp-1)' }}
                onClick={() => setModuleFilter(mod)}
              >
                {mod}
              </button>
            ))}
        </aside>

        <section>
          <div style={{ display: 'flex', gap: 'var(--sp-3)', marginBottom: 'var(--sp-3)', flexWrap: 'wrap' }}>
            <input
              type="search"
              placeholder={t('settingsRegistry.search')}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              style={{ flex: 1, minWidth: 180 }}
            />
            <label style={{ display: 'flex', alignItems: 'center', gap: 'var(--sp-2)' }}>
              <input
                type="checkbox"
                checked={showAdvanced}
                onChange={(e) => setShowAdvanced(e.target.checked)}
              />
              {t('settingsRegistry.showAdvanced')}
            </label>
          </div>

          {filtered.map((item) => (
            <div
              key={item.key}
              className="card"
              style={{ marginBottom: 'var(--sp-3)' }}
            >
              <div className="card-body" style={{ display: 'flex', flexDirection: 'column', gap: 'var(--sp-2)' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', gap: 'var(--sp-2)' }}>
                  <strong>{t(item.label_key)}</strong>
                  {!item.is_default && (
                    <span style={{ fontSize: 'var(--fs-xs)', color: 'var(--accent)' }}>
                      {t('settingsRegistry.notDefault')}
                    </span>
                  )}
                </div>
                <p style={{ margin: 0, fontSize: 'var(--fs-sm)', color: 'var(--text-muted)' }}>
                  {t(item.description_key)}
                  {item.affects_key ? ` — ${t(item.affects_key)}` : ''}
                </p>
                {item.type === 'bool' ? (
                  <label style={{ display: 'flex', alignItems: 'center', gap: 'var(--sp-2)' }}>
                    <input
                      type="checkbox"
                      checked={Boolean(draft[item.key])}
                      disabled={!item.can_edit}
                      onChange={(e) =>
                        setDraft((prev) => ({ ...prev, [item.key]: e.target.checked }))
                      }
                    />
                    {t(item.label_key)}
                  </label>
                ) : (
                  <input
                    type={item.type === 'int' || item.type === 'decimal' ? 'number' : 'text'}
                    value={
                      draft[item.key] === undefined || draft[item.key] === null
                        ? ''
                        : String(draft[item.key])
                    }
                    disabled={!item.can_edit}
                    onChange={(e) => {
                      const raw = e.target.value;
                      const next = item.type === 'int' ? (raw === '' ? '' : Number(raw)) : raw;
                      setDraft((prev) => ({ ...prev, [item.key]: next }));
                    }}
                  />
                )}
              </div>
            </div>
          ))}
        </section>
      </div>
    </div>
  );
};

export default SettingsRegistryPage;
