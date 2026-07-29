import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useParams, useLocation } from 'react-router-dom';
import { useTranslation } from '@shared/i18n';
import { Select } from '@shared/components';
import {
  ReportChart,
  ReportTable,
  type ReportChartHandle,
  type ReportChartMapping,
  type ReportChartType,
  type ReportRow,
  type ReportTableColumn,
  type ReportViewMode,
} from '@shared/components';
import { usePermission } from '@shared/hooks';
import {
  reportsApi,
  rolesApi,
  usersApi,
  type ReportCatalogField,
  type ReportConfigPayload,
  type ReportDatasetCatalog,
  type ReportFilterPayload,
  type ReportAggregationPayload,
  type ReportSortPayload,
  type SavedReportPayload,
} from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import {
  buildQueryFromConfig,
  isReportCatalogField,
  isReportRunResult,
  isSavedReportPayload,
  operatorsForFieldType,
} from './reportHelpers';
import { exportReportExcel, exportReportPdf } from './exportReport';
import './reports.css';

function isDatasetCatalog(value: unknown): value is ReportDatasetCatalog {
  if (typeof value !== 'object' || value === null) return false;
  const v = value as Record<string, unknown>;
  return typeof v.key === 'string' && Array.isArray(v.fields);
}

const AGG_FNS = ['count', 'sum', 'avg', 'min', 'max'] as const;
const CHART_TYPES: ReportChartType[] = ['bar', 'line', 'pie', 'area', 'stacked_bar'];

function parseChartType(value: string): ReportChartType {
  if (
    value === 'bar' ||
    value === 'line' ||
    value === 'pie' ||
    value === 'area' ||
    value === 'stacked_bar'
  ) {
    return value;
  }
  return 'bar';
}

const ReportBuilderPage: React.FC = () => {
  const { t } = useTranslation('common');
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const location = useLocation();
  const reportId = id && id !== 'new' ? Number(id) : null;
  const isEdit = reportId !== null && !Number.isNaN(reportId);
  const isRunOnly = Boolean(isEdit && !location.pathname.includes('/edit'));

  const { canCreate, canEdit, hasPermission } = usePermission();
  const canSave = isEdit ? canEdit('reports', 'definitions') : canCreate('reports', 'definitions');
  const canRun = hasPermission('reports', 'definitions', 'run');

  const [datasets, setDatasets] = useState<ReportDatasetCatalog[]>([]);
  const [datasetKey, setDatasetKey] = useState('');
  const [fieldSearch, setFieldSearch] = useState('');
  const [selectedFields, setSelectedFields] = useState<string[]>([]);
  const [groupBy, setGroupBy] = useState<string[]>([]);
  const [aggregations, setAggregations] = useState<ReportAggregationPayload[]>([]);
  const [filters, setFilters] = useState<ReportFilterPayload[]>([]);
  const [sorts, setSorts] = useState<ReportSortPayload[]>([]);
  const [limit, setLimit] = useState(50);
  const [offset, setOffset] = useState(0);
  const [viewMode, setViewMode] = useState<ReportViewMode>('table');
  const [chartType, setChartType] = useState<ReportChartType>('bar');
  const [chartCategory, setChartCategory] = useState('');
  const [chartValue, setChartValue] = useState('');
  const [chartSeries, setChartSeries] = useState('');

  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [isShared, setIsShared] = useState(false);
  const [shareUserIds, setShareUserIds] = useState<number[]>([]);
  const [shareRoleIds, setShareRoleIds] = useState<number[]>([]);
  const [roleOptions, setRoleOptions] = useState<{ value: string; label: string }[]>([]);
  const [userOptions, setUserOptions] = useState<{ value: string; label: string }[]>([]);

  const [previewRows, setPreviewRows] = useState<ReportRow[]>([]);
  const [previewFields, setPreviewFields] = useState<string[]>([]);
  const [previewCount, setPreviewCount] = useState(0);
  const [previewError, setPreviewError] = useState<string | null>(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [loaded, setLoaded] = useState(!isEdit);

  const chartRef = useRef<ReportChartHandle>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const activeDataset = useMemo(
    () => datasets.find((d) => d.key === datasetKey) ?? null,
    [datasets, datasetKey]
  );

  const catalogFields = useMemo(() => {
    const fields = activeDataset?.fields ?? [];
    return fields.filter(isReportCatalogField);
  }, [activeDataset]);

  const fieldMap = useMemo(() => {
    const m = new Map<string, ReportCatalogField>();
    catalogFields.forEach((f) => m.set(f.key, f));
    return m;
  }, [catalogFields]);

  const filteredCatalog = useMemo(() => {
    const q = fieldSearch.trim().toLowerCase();
    if (!q) return catalogFields;
    return catalogFields.filter(
      (f) =>
        f.key.toLowerCase().includes(q) ||
        f.label.toLowerCase().includes(q) ||
        f.label_key.toLowerCase().includes(q)
    );
  }, [catalogFields, fieldSearch]);

  const dimensions = filteredCatalog.filter((f) => f.role === 'dimension' && !f.is_custom);
  const measures = filteredCatalog.filter((f) => f.role === 'measure' && !f.is_custom);
  const customs = filteredCatalog.filter((f) => f.is_custom);

  const configPayload = useMemo((): ReportConfigPayload => {
    const chart: ReportConfigPayload['chart'] =
      chartCategory && chartValue
        ? {
            type: chartType,
            category_field: chartCategory,
            value_field: chartValue,
            ...(chartSeries ? { series_field: chartSeries } : {}),
          }
        : undefined;
    return {
      dataset: datasetKey,
      fields: selectedFields,
      filters,
      group_by: groupBy,
      aggregations,
      sorts,
      view_mode: viewMode,
      chart,
    };
  }, [
    datasetKey,
    selectedFields,
    filters,
    groupBy,
    aggregations,
    sorts,
    viewMode,
    chartType,
    chartCategory,
    chartValue,
    chartSeries,
  ]);

  const applyReport = useCallback((report: SavedReportPayload) => {
    setName(report.name);
    setDescription(report.description ?? '');
    setDatasetKey(report.dataset_key ?? '');
    setIsShared(report.is_shared);
    setShareUserIds(report.share_user_ids ?? []);
    setShareRoleIds(report.share_role_ids ?? []);
    const cfg = report.config ?? {};
    setSelectedFields(cfg.fields ?? []);
    setFilters(cfg.filters ?? []);
    setGroupBy(cfg.group_by ?? []);
    setAggregations(cfg.aggregations ?? []);
    setSorts(cfg.sorts ?? []);
    setViewMode(cfg.view_mode === 'chart' ? 'chart' : 'table');
    if (cfg.chart) {
      setChartType(cfg.chart.type);
      setChartCategory(cfg.chart.category_field);
      setChartValue(cfg.chart.value_field);
      setChartSeries(cfg.chart.series_field ?? '');
    }
  }, []);

  useEffect(() => {
    void (async () => {
      try {
        const [dsRes, rolesRes, usersRes] = await Promise.all([
          reportsApi.datasets(),
          rolesApi.list().catch(() => null),
          usersApi.list({ per_page: 100 }).catch(() => null),
        ]);
        const rawDs: unknown = dsRes.data.data;
        const list = Array.isArray(rawDs) ? rawDs.filter(isDatasetCatalog) : [];
        setDatasets(list);
        if (!datasetKey && list[0]) setDatasetKey(list[0].key);

        if (rolesRes) {
          const rd: unknown = rolesRes.data.data;
          const arr = Array.isArray(rd)
            ? rd
            : Array.isArray((rd as { data?: unknown })?.data)
              ? (rd as { data: unknown[] }).data
              : [];
          setRoleOptions(
            arr
              .filter((r): r is { id: number; name: string } => {
                if (typeof r !== 'object' || r === null) return false;
                const o = r as Record<string, unknown>;
                return typeof o.id === 'number' && typeof o.name === 'string';
              })
              .map((r) => ({ value: String(r.id), label: r.name }))
          );
        }
        if (usersRes) {
          const ud: unknown = usersRes.data.data;
          const arr = Array.isArray(ud)
            ? ud
            : Array.isArray((ud as { data?: unknown })?.data)
              ? (ud as { data: unknown[] }).data
              : [];
          setUserOptions(
            arr
              .filter((u): u is { id: number; name: string } => {
                if (typeof u !== 'object' || u === null) return false;
                const o = u as Record<string, unknown>;
                return typeof o.id === 'number' && typeof o.name === 'string';
              })
              .map((u) => ({ value: String(u.id), label: u.name }))
          );
        }
      } catch (error: unknown) {
        toast.error(getErrorMessage(error, t('reportEngine.loadError')));
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps -- mount once
  }, []);

  useEffect(() => {
    if (!isEdit || !reportId) return;
    void (async () => {
      try {
        const res = await reportsApi.get(reportId);
        const data: unknown = res.data.data;
        if (isSavedReportPayload(data)) {
          applyReport(data);
        }
      } catch (error: unknown) {
        toast.error(getErrorMessage(error, t('reportEngine.loadError')));
      } finally {
        setLoaded(true);
      }
    })();
  }, [isEdit, reportId, applyReport, t]);

  /** Dataset değişince alan listesi katalogdan gelir; seçimler temizlenir (yükleme hariç) */
  const prevDatasetRef = useRef<string | null>(null);
  useEffect(() => {
    if (!loaded) return;
    if (prevDatasetRef.current === null) {
      prevDatasetRef.current = datasetKey;
      return;
    }
    if (prevDatasetRef.current !== datasetKey) {
      prevDatasetRef.current = datasetKey;
      setSelectedFields([]);
      setGroupBy([]);
      setAggregations([]);
      setFilters([]);
      setSorts([]);
      setChartCategory('');
      setChartValue('');
      setChartSeries('');
      setOffset(0);
    }
  }, [datasetKey, loaded]);

  const runPreview = useCallback(async () => {
    if (!canRun || !datasetKey || selectedFields.length === 0) {
      setPreviewRows([]);
      setPreviewFields([]);
      setPreviewCount(0);
      return;
    }
    setPreviewLoading(true);
    setPreviewError(null);
    try {
      const payload = buildQueryFromConfig(datasetKey, configPayload, { limit, offset });
      const res = await reportsApi.preview(payload);
      const data: unknown = res.data.data;
      if (!isReportRunResult(data)) {
        setPreviewError(t('reportEngine.previewInvalid'));
        return;
      }
      setPreviewRows(data.rows);
      setPreviewFields(data.meta.fields);
      setPreviewCount(data.meta.count);
    } catch (error: unknown) {
      const status =
        typeof error === 'object' &&
        error !== null &&
        'response' in error &&
        typeof (error as { response?: { status?: number } }).response?.status === 'number'
          ? (error as { response: { status: number } }).response.status
          : 0;
      if (status === 403) {
        setPreviewError(t('reportEngine.errorForbidden'));
      } else if (status === 422) {
        setPreviewError(getErrorMessage(error, t('reportEngine.errorQuery')));
      } else {
        setPreviewError(getErrorMessage(error, t('reportEngine.errorGeneric')));
      }
      setPreviewRows([]);
    } finally {
      setPreviewLoading(false);
    }
  }, [canRun, datasetKey, selectedFields.length, configPayload, limit, offset, t]);

  useEffect(() => {
    if (!loaded || !canRun) return;
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      void runPreview();
    }, 500);
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, [runPreview, loaded, canRun]);

  const toggleField = (key: string) => {
    setSelectedFields((prev) =>
      prev.includes(key) ? prev.filter((k) => k !== key) : [...prev, key]
    );
  };

  const moveField = (key: string, dir: -1 | 1) => {
    setSelectedFields((prev) => {
      const idx = prev.indexOf(key);
      if (idx < 0) return prev;
      const next = [...prev];
      const j = idx + dir;
      if (j < 0 || j >= next.length) return prev;
      const tmp = next[idx];
      const swap = next[j];
      if (tmp === undefined || swap === undefined) return prev;
      next[idx] = swap;
      next[j] = tmp;
      return next;
    });
  };

  const handleSave = async () => {
    if (!canSave || !datasetKey || !name.trim()) {
      toast.error(t('reportEngine.saveValidation'));
      return;
    }
    setSaving(true);
    try {
      const body = {
        name: name.trim(),
        description: description.trim() || null,
        dataset_key: datasetKey,
        config: configPayload,
        fields: selectedFields,
        filters,
        group_by: groupBy,
        aggregations,
        sorts,
        is_shared: isShared,
        share_user_ids: shareUserIds,
        share_role_ids: shareRoleIds,
      };
      if (isEdit && reportId) {
        await reportsApi.update(reportId, body);
        toast.success(t('reportEngine.saveSuccess'));
      } else {
        const res = await reportsApi.create(body);
        const created: unknown = res.data.data;
        toast.success(t('reportEngine.saveSuccess'));
        if (isSavedReportPayload(created)) {
          navigate(`/reports/${created.id}/edit`);
          return;
        }
      }
      navigate('/reports');
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('reportEngine.saveError')));
    } finally {
      setSaving(false);
    }
  };

  const handleExport = async (kind: 'excel' | 'pdf') => {
    if (!datasetKey || selectedFields.length === 0) return;
    try {
      const payload = buildQueryFromConfig(datasetKey, configPayload, {
        limit: 50000,
        offset: 0,
      });
      const outcome =
        kind === 'excel'
          ? await exportReportExcel(payload, name || 'rapor')
          : await exportReportPdf(payload, name || 'rapor', name || t('reportEngine.listTitle'));
      if (outcome.truncated) {
        toast.error(
          t('reportEngine.exportTruncated', {
            max: outcome.exportMax,
            count: outcome.rowCount,
          })
        );
      } else {
        toast.success(t('reportEngine.exportSuccess', { count: outcome.rowCount }));
      }
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('reportEngine.exportError')));
    }
  };

  const handlePng = () => {
    const url = chartRef.current?.getPngDataUrl();
    if (!url) return;
    const a = document.createElement('a');
    a.href = url;
    a.download = `${name || 'rapor'}-chart.png`;
    a.click();
  };

  const tableColumns: ReportTableColumn[] = previewFields.map((key) => ({
    id: key,
    accessorKey: key,
    header: fieldMap.get(key)?.label ?? key,
  }));

  const chartMapping: ReportChartMapping = {
    type: chartType,
    categoryField: chartCategory || previewFields[0] || '',
    valueField: chartValue || previewFields[1] || previewFields[0] || '',
    seriesField: chartSeries || undefined,
  };

  const fieldLabel = (key: string) => fieldMap.get(key)?.label ?? key;

  if (!loaded) {
    return <p style={{ color: 'var(--text-secondary)' }}>{t('loading')}</p>;
  }

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">
            {isEdit ? t('reportEngine.editTitle') : t('reportEngine.newTitle')}
          </h1>
          <p className="page-subtitle">{t('reportEngine.builderSubtitle')}</p>
        </div>
        <div className="page-header-actions" style={{ display: 'flex', gap: 'var(--sp-2)' }}>
          <Link to="/reports" className="btn btn-ghost">
            {t('back')}
          </Link>
          {canRun ? (
            <>
              <button type="button" className="btn btn-ghost" onClick={() => void handleExport('excel')}>
                {t('reportEngine.exportExcel')}
              </button>
              <button type="button" className="btn btn-ghost" onClick={() => void handleExport('pdf')}>
                {t('reportEngine.exportPdf')}
              </button>
            </>
          ) : null}
          {canSave && !isRunOnly ? (
            <button
              type="button"
              className="btn btn-primary"
              disabled={saving}
              onClick={() => void handleSave()}
            >
              {t('save')}
            </button>
          ) : null}
        </div>
      </div>

      {!isRunOnly ? (
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: '1fr 1fr 1fr',
            gap: 'var(--sp-3)',
            marginBottom: 'var(--sp-3)',
          }}
        >
          <div>
            <label className="form-label">{t('reportEngine.name')}</label>
            <input
              className="form-control"
              value={name}
              onChange={(e) => setName(e.target.value)}
            />
          </div>
          <div>
            <label className="form-label">{t('reportEngine.description')}</label>
            <input
              className="form-control"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
            />
          </div>
          <div>
            <label className="form-label">{t('reportEngine.share')}</label>
            <label style={{ display: 'flex', gap: 'var(--sp-2)', alignItems: 'center' }}>
              <input
                type="checkbox"
                checked={isShared}
                onChange={(e) => setIsShared(e.target.checked)}
              />
              {t('reportEngine.sharePublic')}
            </label>
          </div>
        </div>
      ) : null}

      {!isRunOnly && (roleOptions.length > 0 || userOptions.length > 0) ? (
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: '1fr 1fr',
            gap: 'var(--sp-3)',
            marginBottom: 'var(--sp-3)',
          }}
        >
          <div>
            <label className="form-label">{t('reportEngine.shareRoles')}</label>
            <Select
              options={roleOptions}
              value={shareRoleIds[0] ? String(shareRoleIds[0]) : ''}
              onChange={(v) => setShareRoleIds(v ? [Number(v)] : [])}
              placeholder={t('reportEngine.shareRoles')}
            />
          </div>
          <div>
            <label className="form-label">{t('reportEngine.shareUsers')}</label>
            <Select
              options={userOptions}
              value={shareUserIds[0] ? String(shareUserIds[0]) : ''}
              onChange={(v) => setShareUserIds(v ? [Number(v)] : [])}
              placeholder={t('reportEngine.shareUsers')}
            />
          </div>
        </div>
      ) : null}

      <div className="report-builder">
        {/* Sol: dataset + alanlar */}
        <aside className="report-builder__panel">
          <h2 className="report-builder__panel-title">{t('reportEngine.dataset')}</h2>
          <Select
            options={datasets.map((d) => ({
              value: d.key,
              label: d.label || t(d.label_key, { defaultValue: d.key }),
            }))}
            value={datasetKey}
            onChange={(v) => setDatasetKey(v)}
          />
          <input
            className="form-control"
            placeholder={t('reportEngine.fieldSearch')}
            value={fieldSearch}
            onChange={(e) => setFieldSearch(e.target.value)}
          />
          <div className="report-builder__scroll">
            {dimensions.length > 0 ? (
              <>
                <div className="report-field-group">{t('reportEngine.dimensions')}</div>
                {dimensions.map((f) => (
                  <div
                    key={f.key}
                    className="report-field-item"
                    onClick={() => toggleField(f.key)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') toggleField(f.key);
                    }}
                    role="button"
                    tabIndex={0}
                  >
                    <span>{f.label}</span>
                    <span>{selectedFields.includes(f.key) ? '✓' : '+'}</span>
                  </div>
                ))}
              </>
            ) : null}
            {measures.length > 0 ? (
              <>
                <div className="report-field-group">{t('reportEngine.measures')}</div>
                {measures.map((f) => (
                  <div
                    key={f.key}
                    className="report-field-item"
                    onClick={() => toggleField(f.key)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') toggleField(f.key);
                    }}
                    role="button"
                    tabIndex={0}
                  >
                    <span>{f.label}</span>
                    <span>{selectedFields.includes(f.key) ? '✓' : '+'}</span>
                  </div>
                ))}
              </>
            ) : null}
            {customs.length > 0 ? (
              <>
                <div className="report-field-group">{t('reportEngine.customFields')}</div>
                {customs.map((f) => (
                  <div
                    key={f.key}
                    className="report-field-item"
                    onClick={() => toggleField(f.key)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') toggleField(f.key);
                    }}
                    role="button"
                    tabIndex={0}
                  >
                    <span>{f.label}</span>
                    <span>{selectedFields.includes(f.key) ? '✓' : '+'}</span>
                  </div>
                ))}
              </>
            ) : null}
          </div>
        </aside>

        {/* Orta: kolon / grup / agg / filtre */}
        <section className="report-builder__panel">
          <h2 className="report-builder__panel-title">{t('reportEngine.columns')}</h2>
          <div className="report-chip-row">
            {selectedFields.map((key) => (
              <span key={key} className="report-chip">
                {fieldLabel(key)}
                <button type="button" onClick={() => moveField(key, -1)} aria-label="up">
                  ↑
                </button>
                <button type="button" onClick={() => moveField(key, 1)} aria-label="down">
                  ↓
                </button>
                <button type="button" onClick={() => toggleField(key)}>
                  ×
                </button>
              </span>
            ))}
          </div>

          <h2 className="report-builder__panel-title">{t('reportEngine.groupBy')}</h2>
          <Select
            options={selectedFields.map((k) => ({ value: k, label: fieldLabel(k) }))}
            value={groupBy[0] ?? ''}
            onChange={(v) => setGroupBy(v ? [v] : [])}
            placeholder={t('reportEngine.groupBy')}
            allowEmpty
            clearable
          />

          <h2 className="report-builder__panel-title">{t('reportEngine.aggregations')}</h2>
          {aggregations.map((agg, idx) => (
            <div key={`${agg.field}-${idx}`} className="report-filter-row">
              <Select
                options={selectedFields.map((k) => ({ value: k, label: fieldLabel(k) }))}
                value={agg.field}
                onChange={(v) => {
                  setAggregations((prev) =>
                    prev.map((a, i) => (i === idx ? { ...a, field: v } : a))
                  );
                }}
              />
              <Select
                options={AGG_FNS.map((fn) => ({ value: fn, label: fn }))}
                value={agg.fn}
                onChange={(v) => {
                  setAggregations((prev) =>
                    prev.map((a, i) => (i === idx ? { ...a, fn: v } : a))
                  );
                }}
              />
              <input
                className="form-control"
                placeholder="alias"
                value={agg.alias ?? ''}
                onChange={(e) => {
                  const alias = e.target.value;
                  setAggregations((prev) =>
                    prev.map((a, i) => (i === idx ? { ...a, alias } : a))
                  );
                }}
              />
              <button
                type="button"
                className="btn btn-sm btn-ghost"
                onClick={() => setAggregations((prev) => prev.filter((_, i) => i !== idx))}
              >
                ×
              </button>
            </div>
          ))}
          <button
            type="button"
            className="btn btn-sm btn-ghost"
            onClick={() =>
              setAggregations((prev) => [
                ...prev,
                { field: selectedFields[0] ?? '', fn: 'count', alias: 'cnt' },
              ])
            }
          >
            + {t('reportEngine.addAggregation')}
          </button>

          <h2 className="report-builder__panel-title">{t('reportEngine.filters')}</h2>
          {filters.map((flt, idx) => {
            const fType = fieldMap.get(flt.field)?.type ?? 'string';
            const ops = operatorsForFieldType(fType);
            return (
              <div key={`${flt.field}-${idx}`} className="report-filter-row">
                <Select
                  options={catalogFields.map((f) => ({ value: f.key, label: f.label }))}
                  value={flt.field}
                  onChange={(v) => {
                    setFilters((prev) =>
                      prev.map((a, i) => (i === idx ? { ...a, field: v } : a))
                    );
                  }}
                />
                <Select
                  options={ops.map((op) => ({ value: op, label: op }))}
                  value={flt.op}
                  onChange={(v) => {
                    setFilters((prev) =>
                      prev.map((a, i) => (i === idx ? { ...a, op: v } : a))
                    );
                  }}
                />
                <input
                  className="form-control"
                  value={flt.value === undefined || flt.value === null ? '' : String(flt.value)}
                  onChange={(e) => {
                    const value = e.target.value;
                    setFilters((prev) =>
                      prev.map((a, i) => (i === idx ? { ...a, value } : a))
                    );
                  }}
                />
                <button
                  type="button"
                  className="btn btn-sm btn-ghost"
                  onClick={() => setFilters((prev) => prev.filter((_, i) => i !== idx))}
                >
                  ×
                </button>
              </div>
            );
          })}
          <button
            type="button"
            className="btn btn-sm btn-ghost"
            onClick={() =>
              setFilters((prev) => [
                ...prev,
                { field: catalogFields[0]?.key ?? '', op: 'eq', value: '' },
              ])
            }
          >
            + {t('reportEngine.addFilter')}
          </button>

          <h2 className="report-builder__panel-title">{t('reportEngine.sorts')}</h2>
          <div className="report-filter-row">
            <Select
              options={selectedFields.map((k) => ({ value: k, label: fieldLabel(k) }))}
              value={sorts[0]?.field ?? ''}
              onChange={(v) =>
                setSorts(v ? [{ field: v, dir: sorts[0]?.dir ?? 'asc' }] : [])
              }
            />
            <Select
              options={[
                { value: 'asc', label: 'asc' },
                { value: 'desc', label: 'desc' },
              ]}
              value={sorts[0]?.dir ?? 'asc'}
              onChange={(v) => {
                const field = sorts[0]?.field;
                if (!field) return;
                setSorts([{ field, dir: v }]);
              }}
            />
            <div />
            <div />
          </div>

          <div style={{ display: 'flex', gap: 'var(--sp-2)', alignItems: 'center' }}>
            <label className="form-label">{t('reportEngine.limit')}</label>
            <input
              type="number"
              className="form-control"
              style={{ width: 100 }}
              min={1}
              max={1000}
              value={limit}
              onChange={(e) => setLimit(Math.min(1000, Math.max(1, Number(e.target.value) || 50)))}
            />
          </div>

          <h2 className="report-builder__panel-title">{t('reportEngine.viewMode')}</h2>
          <div style={{ display: 'flex', gap: 'var(--sp-2)' }}>
            <button
              type="button"
              className={`btn btn-sm ${viewMode === 'table' ? 'btn-primary' : 'btn-ghost'}`}
              onClick={() => setViewMode('table')}
            >
              {t('reportEngine.viewTable')}
            </button>
            <button
              type="button"
              className={`btn btn-sm ${viewMode === 'chart' ? 'btn-primary' : 'btn-ghost'}`}
              onClick={() => setViewMode('chart')}
            >
              {t('reportEngine.viewChart')}
            </button>
          </div>
          {viewMode === 'chart' ? (
            <div style={{ display: 'grid', gap: 'var(--sp-2)' }}>
              <Select
                options={CHART_TYPES.map((ct) => ({
                  value: ct,
                  label: t(`reportEngine.chartType.${ct}`),
                }))}
                value={chartType}
                onChange={(v) => setChartType(parseChartType(v))}
              />
              <Select
                options={previewFields.map((k) => ({ value: k, label: fieldLabel(k) }))}
                value={chartCategory}
                onChange={setChartCategory}
                placeholder={t('reportEngine.chartCategory')}
                allowEmpty
              />
              <Select
                options={previewFields.map((k) => ({ value: k, label: fieldLabel(k) }))}
                value={chartValue}
                onChange={setChartValue}
                placeholder={t('reportEngine.chartValue')}
                allowEmpty
              />
              <Select
                options={previewFields.map((k) => ({ value: k, label: fieldLabel(k) }))}
                value={chartSeries}
                onChange={setChartSeries}
                placeholder={t('reportEngine.chartSeries')}
                allowEmpty
                clearable
              />
            </div>
          ) : null}
        </section>

        {/* Sağ: önizleme */}
        <aside className="report-builder__panel">
          <h2 className="report-builder__panel-title">
            {t('reportEngine.preview')}
            {previewLoading ? ` · ${t('loading')}` : ''}
          </h2>
          <p style={{ margin: 0, fontSize: 'var(--fs-caption)', color: 'var(--text-tertiary)' }}>
            {t('reportEngine.previewMeta', { count: previewCount, limit, offset })}
          </p>
          {previewError ? (
            <div
              style={{
                padding: 'var(--sp-3)',
                background: 'var(--danger-soft)',
                color: 'var(--danger-text)',
                borderRadius: 'var(--radius-sm)',
                fontSize: 'var(--fs-body)',
              }}
            >
              {previewError}
            </div>
          ) : null}
          {viewMode === 'table' ? (
            <ReportTable
              columns={tableColumns}
              rows={previewRows}
              emptyLabel={t('reportEngine.previewEmpty')}
              pageInfo={{ offset, limit, count: previewCount }}
              onPageChange={setOffset}
              onSortChange={(s) => {
                setSorts(s);
                setOffset(0);
              }}
            />
          ) : (
            <>
              <ReportChart
                ref={chartRef}
                type={chartType}
                rows={previewRows}
                mapping={chartMapping}
                emptyLabel={t('reportEngine.previewEmpty')}
              />
              <button type="button" className="btn btn-sm btn-ghost" onClick={handlePng}>
                {t('reportEngine.exportPng')}
              </button>
            </>
          )}
        </aside>
      </div>
    </div>
  );
};

export default ReportBuilderPage;
