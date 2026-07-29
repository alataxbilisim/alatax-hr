import React, { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import toast from 'react-hot-toast';
import {
  settingsRegistryApi,
  type SettingCatalogItem,
} from '../services/api';
import { getErrorMessage } from '../services/apiHelpers';

export interface PageSettingsButtonProps {
  /** Bağlamsal panel — yalnızca bu page_key'e bağlı ayarlar */
  pageKey: string;
  className?: string;
}

/**
 * D4a — Sayfa başlığında ⚙; yan panelde page_key ayarları.
 * Ortak bileşen — sayfa başına kopya yok.
 */
export function PageSettingsButton({ pageKey, className }: PageSettingsButtonProps): React.ReactElement | null {
  const { t } = useTranslation('common');
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [items, setItems] = useState<SettingCatalogItem[]>([]);
  const [draft, setDraft] = useState<Record<string, unknown>>({});

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const res = await settingsRegistryApi.registry({ page_key: pageKey, include_advanced: true });
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
  }, [pageKey, t]);

  useEffect(() => {
    if (open) {
      void load();
    }
  }, [open, load]);

  const save = async () => {
    const values = items
      .filter((item) => item.can_edit && draft[item.key] !== item.value)
      .map((item) => ({ key: item.key, value: draft[item.key] }));
    if (values.length === 0) {
      toast.success(t('settingsRegistry.noChanges'));
      return;
    }
    try {
      setSaving(true);
      await settingsRegistryApi.updateValues({
        scope_type: 'company',
        values,
      });
      toast.success(t('settingsRegistry.saveSuccess'));
      await load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('settingsRegistry.saveError')));
    } finally {
      setSaving(false);
    }
  };

  const resolvedLabel = (from: string): string => {
    const map: Record<string, string> = {
      user: t('settingsRegistry.resolved.user'),
      department: t('settingsRegistry.resolved.department'),
      branch: t('settingsRegistry.resolved.branch'),
      company: t('settingsRegistry.resolved.company'),
      company_legacy: t('settingsRegistry.resolved.companyLegacy'),
      system: t('settingsRegistry.resolved.system'),
      default: t('settingsRegistry.resolved.default'),
    };
    return map[from] ?? from;
  };

  return (
    <>
      <button
        type="button"
        className={className ?? 'btn btn-ghost btn-sm'}
        onClick={() => setOpen(true)}
        title={t('settingsRegistry.pageButton')}
        aria-label={t('settingsRegistry.pageButton')}
      >
        ⚙
      </button>
      {open && (
        <div
          className="settings-contextual-overlay"
          style={{
            position: 'fixed',
            inset: 0,
            zIndex: 1200,
            background: 'var(--overlay-bg, rgba(0,0,0,0.35))',
            display: 'flex',
            justifyContent: 'flex-end',
          }}
          onClick={() => setOpen(false)}
          onKeyDown={(e) => {
            if (e.key === 'Escape') setOpen(false);
          }}
          role="presentation"
        >
          <aside
            className="settings-contextual-panel"
            style={{
              width: 'min(420px, 100%)',
              height: '100%',
              background: 'var(--surface)',
              color: 'var(--text)',
              padding: 'var(--sp-4)',
              overflowY: 'auto',
              boxShadow: 'var(--shadow-lg)',
            }}
            onClick={(e) => e.stopPropagation()}
            role="dialog"
            aria-modal="true"
            aria-label={t('settingsRegistry.contextualTitle')}
          >
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 'var(--sp-3)' }}>
              <h2 style={{ margin: 0, fontSize: 'var(--fs-lg)' }}>{t('settingsRegistry.contextualTitle')}</h2>
              <button type="button" className="btn btn-ghost btn-sm" onClick={() => setOpen(false)}>
                {t('close')}
              </button>
            </div>
            {loading && <p>{t('loading')}</p>}
            {!loading && items.length === 0 && <p>{t('settingsRegistry.emptyForPage')}</p>}
            {!loading &&
              items.map((item) => (
                <div key={item.key} style={{ marginBottom: 'var(--sp-3)' }}>
                  <label style={{ display: 'flex', flexDirection: 'column', gap: 'var(--sp-1)' }}>
                    <span>
                      {t(item.label_key)}
                      {!item.is_default && (
                        <span
                          style={{
                            marginLeft: 'var(--sp-2)',
                            fontSize: 'var(--fs-xs)',
                            color: 'var(--accent)',
                          }}
                        >
                          {t('settingsRegistry.notDefault')}
                        </span>
                      )}
                    </span>
                    <span style={{ fontSize: 'var(--fs-xs)', color: 'var(--text-muted)' }}>
                      {t(item.description_key)} · {resolvedLabel(item.resolved_from)}
                    </span>
                    {item.type === 'bool' ? (
                      <input
                        type="checkbox"
                        checked={Boolean(draft[item.key])}
                        disabled={!item.can_edit}
                        onChange={(e) =>
                          setDraft((prev) => ({ ...prev, [item.key]: e.target.checked }))
                        }
                      />
                    ) : (
                      <input
                        type={item.type === 'int' || item.type === 'decimal' ? 'number' : 'text'}
                        value={draft[item.key] === undefined || draft[item.key] === null ? '' : String(draft[item.key])}
                        disabled={!item.can_edit}
                        onChange={(e) => {
                          const raw = e.target.value;
                          const next =
                            item.type === 'int'
                              ? raw === ''
                                ? ''
                                : Number(raw)
                              : raw;
                          setDraft((prev) => ({ ...prev, [item.key]: next }));
                        }}
                      />
                    )}
                  </label>
                </div>
              ))}
            {items.some((i) => i.can_edit) && (
              <button type="button" className="btn btn-primary" disabled={saving} onClick={() => void save()}>
                {saving ? t('saving') : t('save')}
              </button>
            )}
          </aside>
        </div>
      )}
    </>
  );
}
