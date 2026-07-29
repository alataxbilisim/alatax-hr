import React, { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from '@shared/i18n';
import { Select } from '@shared/components';
import { usePermission } from '@shared/hooks';
import {
  reportsApi,
  type ReportDatasetCatalog,
  type ReportMeasurePayload,
} from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import { BsPlus, BsTrash, BsPencil } from 'react-icons/bs';

function isRecord(v: unknown): v is Record<string, unknown> {
  return typeof v === 'object' && v !== null && !Array.isArray(v);
}

function isMeasure(v: unknown): v is ReportMeasurePayload {
  if (!isRecord(v)) return false;
  return typeof v.id === 'number' && typeof v.key === 'string' && typeof v.expression === 'string';
}

function isDataset(v: unknown): v is ReportDatasetCatalog {
  if (!isRecord(v)) return false;
  return typeof v.key === 'string';
}

const MeasuresLibraryPage: React.FC = () => {
  const { t } = useTranslation('common');
  const { canEdit, canView } = usePermission();
  const canEditM = canEdit('reports', 'measures');
  const canViewM = canView('reports', 'measures');

  const [items, setItems] = useState<ReportMeasurePayload[]>([]);
  const [datasets, setDatasets] = useState<ReportDatasetCatalog[]>([]);
  const [datasetKey, setDatasetKey] = useState('');
  const [loading, setLoading] = useState(true);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState({
    key: '',
    label: '',
    expression: '',
    format: 'number',
    decimals: 2,
  });

  const load = useCallback(async () => {
    if (!canViewM) return;
    try {
      setLoading(true);
      const [mRes, dRes] = await Promise.all([
        reportsApi.measures.list({ dataset_key: datasetKey || undefined, per_page: 100 }),
        reportsApi.datasets(),
      ]);
      const raw: unknown = mRes.data.data;
      let list: ReportMeasurePayload[] = [];
      if (Array.isArray(raw)) {
        list = raw.filter(isMeasure);
      } else if (isRecord(raw) && Array.isArray(raw.data)) {
        list = raw.data.filter(isMeasure);
      }
      setItems(list);
      const dsRaw: unknown = dRes.data.data;
      const ds = Array.isArray(dsRaw) ? dsRaw.filter(isDataset) : [];
      setDatasets(ds);
      if (!datasetKey && ds[0]) setDatasetKey(ds[0].key);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('reportEngine.measures.loadError')));
    } finally {
      setLoading(false);
    }
  }, [canViewM, datasetKey, t]);

  useEffect(() => {
    void load();
  }, [load]);

  const resetForm = () => {
    setEditingId(null);
    setForm({ key: '', label: '', expression: '', format: 'number', decimals: 2 });
  };

  const handleSave = async () => {
    if (!canEditM || !datasetKey) return;
    try {
      if (editingId) {
        await reportsApi.measures.update(editingId, {
          dataset_key: datasetKey,
          key: form.key,
          label: form.label,
          expression: form.expression,
          format: form.format === 'money' || form.format === 'percent' ? form.format : 'number',
          decimals: form.decimals,
        });
      } else {
        await reportsApi.measures.create({
          dataset_key: datasetKey,
          key: form.key,
          label: form.label,
          expression: form.expression,
          format: form.format === 'money' || form.format === 'percent' ? form.format : 'number',
          decimals: form.decimals,
        });
      }
      toast.success(t('reportEngine.measures.saveSuccess'));
      resetForm();
      await load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('reportEngine.measures.saveError')));
    }
  };

  const handleValidate = async () => {
    if (!datasetKey) return;
    try {
      await reportsApi.measures.validate({
        dataset: datasetKey,
        expression: form.expression,
      });
      toast.success(t('reportEngine.dsl.valid'));
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('reportEngine.dsl.invalid')));
    }
  };

  const handleDelete = async (id: number) => {
    if (!canEditM) return;
    if (!window.confirm(t('reportEngine.measures.deleteConfirm'))) return;
    try {
      await reportsApi.measures.remove(id);
      toast.success(t('reportEngine.measures.deleteSuccess'));
      await load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('reportEngine.measures.deleteError')));
    }
  };

  if (!canViewM) {
    return <p>{t('reportEngine.errorForbidden')}</p>;
  }

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">{t('reportEngine.measures.title')}</h1>
          <p className="page-subtitle">{t('reportEngine.measures.subtitle')}</p>
        </div>
        <Link to="/reports" className="btn btn-ghost">
          {t('back')}
        </Link>
      </div>

      <div style={{ maxWidth: 320, marginBottom: 'var(--sp-4)' }}>
        <Select
          options={datasets.map((d) => ({ value: d.key, label: d.label || d.key }))}
          value={datasetKey}
          onChange={setDatasetKey}
        />
      </div>

      {canEditM ? (
        <div
          style={{
            display: 'grid',
            gap: 'var(--sp-2)',
            marginBottom: 'var(--sp-4)',
            padding: 'var(--sp-3)',
            border: '1px solid var(--border-primary)',
            borderRadius: 'var(--radius-md)',
          }}
        >
          <h2 style={{ margin: 0, fontSize: 'var(--fs-section)' }}>
            {editingId ? t('reportEngine.measures.edit') : t('reportEngine.measures.create')}
          </h2>
          <input
            className="form-control"
            placeholder={t('reportEngine.measures.key')}
            value={form.key}
            onChange={(e) => setForm((f) => ({ ...f, key: e.target.value }))}
          />
          <input
            className="form-control"
            placeholder={t('reportEngine.measures.label')}
            value={form.label}
            onChange={(e) => setForm((f) => ({ ...f, label: e.target.value }))}
          />
          <textarea
            className="form-control"
            rows={3}
            placeholder={t('reportEngine.dsl.placeholder')}
            value={form.expression}
            onChange={(e) => setForm((f) => ({ ...f, expression: e.target.value }))}
          />
          <div style={{ display: 'flex', gap: 'var(--sp-2)', flexWrap: 'wrap' }}>
            <Select
              options={[
                { value: 'number', label: t('reportEngine.dsl.formatNumber') },
                { value: 'money', label: t('reportEngine.dsl.formatMoney') },
                { value: 'percent', label: t('reportEngine.dsl.formatPercent') },
              ]}
              value={form.format}
              onChange={(v) => setForm((f) => ({ ...f, format: v }))}
            />
            <button type="button" className="btn btn-ghost" onClick={() => void handleValidate()}>
              {t('reportEngine.dsl.validate')}
            </button>
            <button type="button" className="btn btn-primary" onClick={() => void handleSave()}>
              {t('save')}
            </button>
            {editingId ? (
              <button type="button" className="btn btn-ghost" onClick={resetForm}>
                {t('cancel')}
              </button>
            ) : null}
          </div>
          <p style={{ margin: 0, fontSize: 'var(--fs-caption)', color: 'var(--text-tertiary)' }}>
            {t('reportEngine.dsl.examples')}
          </p>
        </div>
      ) : null}

      {loading ? (
        <p>{t('loading')}</p>
      ) : (
        <table className="report-list-table">
          <thead>
            <tr>
              <th>{t('reportEngine.measures.key')}</th>
              <th>{t('reportEngine.measures.label')}</th>
              <th>{t('reportEngine.dsl.expression')}</th>
              <th>{t('reportEngine.colActions')}</th>
            </tr>
          </thead>
          <tbody>
            {items.length === 0 ? (
              <tr>
                <td colSpan={4}>{t('reportEngine.measures.empty')}</td>
              </tr>
            ) : (
              items.map((m) => (
                <tr key={m.id}>
                  <td>{m.key}</td>
                  <td>{m.label}</td>
                  <td>
                    <code style={{ fontSize: 'var(--fs-caption)' }}>{m.expression}</code>
                  </td>
                  <td>
                    {canEditM ? (
                      <div className="report-list-actions">
                        <button
                          type="button"
                          className="btn btn-sm btn-ghost"
                          onClick={() => {
                            setEditingId(m.id);
                            setDatasetKey(m.dataset_key);
                            setForm({
                              key: m.key,
                              label: m.label,
                              expression: m.expression,
                              format: m.format,
                              decimals: m.decimals,
                            });
                          }}
                        >
                          <BsPencil />
                        </button>
                        <button
                          type="button"
                          className="btn btn-sm btn-ghost"
                          onClick={() => void handleDelete(m.id)}
                        >
                          <BsTrash />
                        </button>
                      </div>
                    ) : null}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      )}
      {canEditM ? (
        <p style={{ marginTop: 'var(--sp-3)', color: 'var(--text-tertiary)', fontSize: 'var(--fs-caption)' }}>
          <BsPlus /> {t('reportEngine.dsl.exampleList')}
        </p>
      ) : null}
    </div>
  );
};

export default MeasuresLibraryPage;
