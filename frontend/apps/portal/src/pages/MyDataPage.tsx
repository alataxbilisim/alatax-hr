import React, { useCallback, useEffect, useState } from 'react';
import { useTranslation } from '@shared/i18n';
import { portalApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';

interface MyRequest {
  id: number;
  request_types: string[];
  status: string;
  due_date?: string | null;
  days_remaining?: number;
  identity_verified: boolean;
  created_at?: string | null;
}

function isMyRequest(v: unknown): v is MyRequest {
  return typeof v === 'object' && v !== null && 'id' in v && 'status' in v;
}

/**
 * Portal — Verilerim (D2b kendi KVKK talepleri).
 */
const MyDataPage: React.FC = () => {
  const { t } = useTranslation('common');
  const [loading, setLoading] = useState(true);
  const [rows, setRows] = useState<MyRequest[]>([]);
  const [types, setTypes] = useState<string[]>(['bilgi_talebi']);
  const [description, setDescription] = useState('');

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const res = await portalApi.dataSubjectRequests.list();
      const raw: unknown = res.data.data;
      setRows(Array.isArray(raw) ? raw.filter(isMyRequest) : []);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('portalMyData.loadError')));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => {
    void load();
  }, [load]);

  const create = async () => {
    try {
      await portalApi.dataSubjectRequests.create({
        request_types: types,
        description: description || undefined,
      });
      toast.success(t('portalMyData.created'));
      setDescription('');
      void load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('portalMyData.saveError')));
    }
  };

  const exportPkg = async (id: number) => {
    try {
      const res = await portalApi.dataSubjectRequests.export(id);
      const uuid = res.data.data?.uuid;
      if (typeof uuid !== 'string') {
        toast.error(t('portalMyData.saveError'));
        return;
      }
      const dl = await portalApi.dataSubjectRequests.download(id, uuid);
      const blob = new Blob([dl.data], { type: 'application/zip' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'kvkk-paket.zip';
      a.click();
      URL.revokeObjectURL(url);
      toast.success(t('portalMyData.exportReady'));
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('portalMyData.saveError')));
    }
  };

  const toggleType = (value: string) => {
    setTypes((prev) =>
      prev.includes(value) ? prev.filter((x) => x !== value) : [...prev, value]
    );
  };

  return (
    <div className="page-container animate-fade-in">
      <div className="page-header">
        <h1 className="page-title">{t('portalMyData.title')}</h1>
        <p className="page-subtitle">{t('portalMyData.subtitle')}</p>
      </div>

      <section style={{ marginBottom: 'var(--sp-5)' }}>
        <h2 style={{ fontSize: 'var(--fs-lg)' }}>{t('portalMyData.newRequest')}</h2>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 'var(--sp-2)', margin: 'var(--sp-2) 0' }}>
          {(['bilgi_talebi', 'tasinabilirlik', 'silme', 'duzeltme', 'itiraz'] as const).map((key) => (
            <label key={key} style={{ display: 'flex', alignItems: 'center', gap: 'var(--sp-1)' }}>
              <input
                type="checkbox"
                checked={types.includes(key)}
                onChange={() => toggleType(key)}
              />
              {t(`portalMyData.types.${key}`)}
            </label>
          ))}
        </div>
        <textarea
          className="form-control"
          rows={3}
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          placeholder={t('portalMyData.description')}
        />
        <button type="button" className="btn btn-primary" style={{ marginTop: 'var(--sp-2)' }} onClick={() => void create()}>
          {t('portalMyData.submit')}
        </button>
      </section>

      {loading ? <p>{t('loading')}</p> : null}
      {!loading ? (
        <ul style={{ listStyle: 'none', padding: 0 }}>
          {rows.length === 0 ? <li>{t('portalMyData.empty')}</li> : null}
          {rows.map((r) => (
            <li
              key={r.id}
              style={{
                padding: 'var(--sp-3)',
                borderBottom: '1px solid var(--color-border)',
                display: 'flex',
                justifyContent: 'space-between',
                gap: 'var(--sp-3)',
              }}
            >
              <div>
                <strong>#{r.id}</strong> — {r.status}
                <div className="text-muted">
                  {r.request_types.join(', ')} · {r.due_date}
                </div>
              </div>
              {r.identity_verified ? (
                <button type="button" className="btn btn-sm btn-ghost" onClick={() => void exportPkg(r.id)}>
                  {t('portalMyData.downloadPackage')}
                </button>
              ) : null}
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
};

export default MyDataPage;
