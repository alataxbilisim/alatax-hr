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
import PivotMatrixTable from './PivotMatrixTable';
import './reports.css';

function isDatasetCatalog(value: unknown): value is ReportDatasetCatalog {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) return false;
  if (!('key' in value) || !('fields' in value)) return false;
  return typeof value.key === 'string' && Array.isArray(value.fields);
}

type DateGrain = 'year' | 'quarter' | 'month' | 'week' | 'day';

interface PivotDimState {
  field: string;
  grain: DateGrain | '';
}

interface PivotMeasureState {
  alias: string;
  fn: string;
  field: string;
  expression: string;
  useExpression: boolean;
}

interface PivotResultState {
  row_headers: { field: string; label: string; grain?: string | null }[];
  column_headers: { field: string; label: string; grain?: string | null }[];
  row_keys: string[][];
  column_keys: string[][];
  measures: { alias: string; format: string; decimals: number }[];
  cells: { row: number; col: number; measure: string; value: string | number | null }[];
}

function isPivotResult(value: unknown): value is PivotResultState {
  if (typeof value !== 'object' || value === null) return false;
  if (!('row_keys' in value) || !('column_keys' in value) || !('cells' in value)) return false;
  return Array.isArray(value.row_keys) && Array.isArray(value.column_keys) && Array.isArray(value.cells);
}

function parseGrain(value: string): DateGrain | '' {
  if (
    value === 'year' ||
    value === 'quarter' ||
    value === 'month' ||
    value === 'week' ||
    value === 'day'
  ) {
    return value;
  }
  return '';
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

  const [pivotRows, setPivotRows] = useState<PivotDimState[]>([{ field: '', grain: '' }]);
  const [pivotCols, setPivotCols] = useState<PivotDimState[]>([]);
  const [pivotMeasures, setPivotMeasures] = useState<PivotMeasureState[]>([
    { alias: 'adet', fn: 'count', field: '*', expression: '', useExpression: false },
  ]);
  const [pivotSubtotals, setPivotSubtotals] = useState(false);
  const [pivotGrand, setPivotGrand] = useState(false);
  const [pivotResult, setPivotResult] = useState<PivotResultState | null>(null);
  const [pivotLoading, setPivotLoading] = useState(false);
  const [formulaDraft, setFormulaDraft] = useState('');
  const [detailRows, setDetailRows] = useState<ReportRow[]>([]);

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
      pivot: {
        rows: pivotRows.filter((r) => r.field).map((r) => ({
          field: r.field,
          ...(r.grain ? { grain: r.grain } : {}),
        })),
        columns: pivotCols.filter((r) => r.field).map((r) => ({
          field: r.field,
          ...(r.grain ? { grain: r.grain } : {}),
        })),
        measures: pivotMeasures.map((m) =>
          m.useExpression
            ? { alias: m.alias, expression: m.expression, format: 'number' }
            : { alias: m.alias, fn: m.fn, field: m.field, format: 'number' }
        ),
        subtotals: pivotSubtotals,
        grand_total: pivotGrand,
      },
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
    pivotRows,
    pivotCols,
    pivotMeasures,
    pivotSubtotals,
    pivotGrand,
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
    setViewMode(
      cfg.view_mode === 'chart' ? 'chart' : cfg.view_mode === 'pivot' ? 'pivot' : 'table'
    );
    if (cfg.chart) {
      setChartType(cfg.chart.type);
      setChartCategory(cfg.chart.category_field);
      setChartValue(cfg.chart.value_field);
      setChartSeries(cfg.chart.series_field ?? '');
    }
    if (cfg.pivot) {
      setPivotRows(
        (cfg.pivot.rows ?? []).map((r) => ({
          field: r.field,
          grain: parseGrain(r.grain ?? ''),
        }))
      );
      setPivotCols(
        (cfg.pivot.columns ?? []).map((r) => ({
          field: r.field,
          grain: parseGrain(r.grain ?? ''),
        }))
      );
      setPivotMeasures(
        (cfg.pivot.measures ?? []).map((m, i) => ({
          alias: m.alias ?? `m${i}`,
          fn: m.fn ?? 'count',
          field: m.field ?? '*',
          expression: m.expression ?? '',
          useExpression: Boolean(m.expression),
        }))
      );
      setPivotSubtotals(Boolean(cfg.pivot.subtotals));
      setPivotGrand(Boolean(cfg.pivot.grand_total));
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

  const runPivot = async () => {
    if (!canRun || !datasetKey) return;
    const rows = pivotRows.filter((r) => r.field);
    if (rows.length === 0) {
      toast.error(t('reportEngine.saveValidation'));
      return;
    }
    setPivotLoading(true);
    try {
      const res = await reportsApi.pivot({
        dataset: datasetKey,
        rows: rows.map((r) => ({
          field: r.field,
          ...(r.grain ? { grain: r.grain } : {}),
        })),
        columns: pivotCols
          .filter((c) => c.field)
          .map((c) => ({
            field: c.field,
            ...(c.grain ? { grain: c.grain } : {}),
          })),
        measures: pivotMeasures.map((m): {
          alias: string;
          expression?: string;
          fn?: string;
          field?: string;
          format: 'number';
        } =>
          m.useExpression
            ? { alias: m.alias, expression: m.expression || formulaDraft, format: 'number' }
            : {
                alias: m.alias,
                fn: m.fn,
                field: m.field,
                format: 'number',
              }
        ),
        filters,
        subtotals: pivotSubtotals,
        grand_total: pivotGrand,
      });
      const data: unknown = res.data.data;
      if (isPivotResult(data)) {
        setPivotResult(data);
        setDetailRows([]);
      }
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('reportEngine.errorQuery')));
    } finally {
      setPivotLoading(false);
    }
  };

  const handlePivotCellClick = async (rowIndex: number, colIndex: number) => {
    if (!pivotResult || !datasetKey) return;
    const rowKey = pivotResult.row_keys[rowIndex] ?? [];
    const colKey = pivotResult.column_keys[colIndex] ?? [];
    const cellFilters: ReportFilterPayload[] = [];
    pivotResult.row_headers.forEach((h, i) => {
      cellFilters.push({ field: h.field, op: 'eq', value: rowKey[i] ?? null });
    });
    pivotResult.column_headers.forEach((h, i) => {
      cellFilters.push({ field: h.field, op: 'eq', value: colKey[i] ?? null });
    });
    try {
      const res = await reportsApi.drill({
        dataset: datasetKey,
        mode: 'details',
        filters,
        cell_filters: cellFilters.map((f) => ({
          field: f.field,
          op: f.op,
          value: f.value === '(boş)' ? null : f.value,
        })),
        fields: selectedFields.length > 0 ? selectedFields : catalogFields.map((f) => f.key).slice(0, 8),
        limit: 50,
      });
      const data: unknown = res.data.data;
      if (isReportRunResult(data)) {
        setDetailRows(data.rows);
        toast.success(t('reportEngine.pivot.drillDetails'));
      }
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('reportEngine.errorQuery')));
    }
  };

  const validateFormula = async () => {
    if (!datasetKey || !formulaDraft.trim()) return;
    try {
      await reportsApi.measures.validate({ dataset: datasetKey, expression: formulaDraft });
      toast.success(t('reportEngine.dsl.valid'));
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('reportEngine.dsl.invalid')));
    }
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
          <div style={{ display: 'flex', gap: 'var(--sp-2)', flexWrap: 'wrap' }}>
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
            <button
              type="button"
              className={`btn btn-sm ${viewMode === 'pivot' ? 'btn-primary' : 'btn-ghost'}`}
              onClick={() => setViewMode('pivot')}
            >
              {t('reportEngine.pivot.mode')}
            </button>
          </div>
          {viewMode === 'pivot' ? (
            <div style={{ display: 'grid', gap: 'var(--sp-2)' }}>
              <h3 className="report-builder__panel-title">{t('reportEngine.pivot.rows')}</h3>
              {pivotRows.map((row, idx) => (
                <div key={`pr-${idx}`} className="report-filter-row">
                  <Select
                    options={catalogFields.map((f) => ({ value: f.key, label: f.label }))}
                    value={row.field}
                    onChange={(v) =>
                      setPivotRows((prev) =>
                        prev.map((r, i) => (i === idx ? { ...r, field: v } : r))
                      )
                    }
                    allowEmpty
                  />
                  <Select
                    options={[
                      { value: '', label: t('reportEngine.pivot.grainNone') },
                      { value: 'year', label: 'year' },
                      { value: 'quarter', label: 'quarter' },
                      { value: 'month', label: 'month' },
                      { value: 'week', label: 'week' },
                      { value: 'day', label: 'day' },
                    ]}
                    value={row.grain}
                    onChange={(v) =>
                      setPivotRows((prev) =>
                        prev.map((r, i) => (i === idx ? { ...r, grain: parseGrain(v) } : r))
                      )
                    }
                    allowEmpty
                  />
                  <button
                    type="button"
                    className="btn btn-sm btn-ghost"
                    onClick={() => setPivotRows((prev) => prev.filter((_, i) => i !== idx))}
                  >
                    ×
                  </button>
                  <div />
                </div>
              ))}
              {pivotRows.length < 3 ? (
                <button
                  type="button"
                  className="btn btn-sm btn-ghost"
                  onClick={() => setPivotRows((prev) => [...prev, { field: '', grain: '' }])}
                >
                  + {t('reportEngine.pivot.addRow')}
                </button>
              ) : null}

              <h3 className="report-builder__panel-title">{t('reportEngine.pivot.columns')}</h3>
              {pivotCols.map((col, idx) => (
                <div key={`pc-${idx}`} className="report-filter-row">
                  <Select
                    options={catalogFields.map((f) => ({ value: f.key, label: f.label }))}
                    value={col.field}
                    onChange={(v) =>
                      setPivotCols((prev) =>
                        prev.map((r, i) => (i === idx ? { ...r, field: v } : r))
                      )
                    }
                    allowEmpty
                  />
                  <Select
                    options={[
                      { value: '', label: t('reportEngine.pivot.grainNone') },
                      { value: 'year', label: 'year' },
                      { value: 'month', label: 'month' },
                      { value: 'day', label: 'day' },
                    ]}
                    value={col.grain}
                    onChange={(v) =>
                      setPivotCols((prev) =>
                        prev.map((r, i) => (i === idx ? { ...r, grain: parseGrain(v) } : r))
                      )
                    }
                    allowEmpty
                  />
                  <button
                    type="button"
                    className="btn btn-sm btn-ghost"
                    onClick={() => setPivotCols((prev) => prev.filter((_, i) => i !== idx))}
                  >
                    ×
                  </button>
                  <div />
                </div>
              ))}
              {pivotCols.length < 2 ? (
                <button
                  type="button"
                  className="btn btn-sm btn-ghost"
                  onClick={() => setPivotCols((prev) => [...prev, { field: '', grain: '' }])}
                >
                  + {t('reportEngine.pivot.addCol')}
                </button>
              ) : null}

              <h3 className="report-builder__panel-title">{t('reportEngine.pivot.measures')}</h3>
              {pivotMeasures.map((m, idx) => (
                <div key={`pm-${idx}`} style={{ display: 'grid', gap: 'var(--sp-1)' }}>
                  <input
                    className="form-control"
                    value={m.alias}
                    onChange={(e) =>
                      setPivotMeasures((prev) =>
                        prev.map((x, i) => (i === idx ? { ...x, alias: e.target.value } : x))
                      )
                    }
                    placeholder="alias"
                  />
                  <label style={{ display: 'flex', gap: 'var(--sp-2)', fontSize: 'var(--fs-caption)' }}>
                    <input
                      type="checkbox"
                      checked={m.useExpression}
                      onChange={(e) =>
                        setPivotMeasures((prev) =>
                          prev.map((x, i) =>
                            i === idx ? { ...x, useExpression: e.target.checked } : x
                          )
                        )
                      }
                    />
                    {t('reportEngine.dsl.expression')}
                  </label>
                  {m.useExpression ? (
                    <input
                      className="form-control"
                      value={m.expression}
                      onChange={(e) =>
                        setPivotMeasures((prev) =>
                          prev.map((x, i) =>
                            i === idx ? { ...x, expression: e.target.value } : x
                          )
                        )
                      }
                      placeholder={t('reportEngine.dsl.placeholder')}
                    />
                  ) : (
                    <div className="report-filter-row">
                      <Select
                        options={AGG_FNS.map((fn) => ({ value: fn, label: fn }))}
                        value={m.fn}
                        onChange={(v) =>
                          setPivotMeasures((prev) =>
                            prev.map((x, i) => (i === idx ? { ...x, fn: v } : x))
                          )
                        }
                      />
                      <Select
                        options={[
                          { value: '*', label: '*' },
                          ...catalogFields.map((f) => ({ value: f.key, label: f.label })),
                        ]}
                        value={m.field}
                        onChange={(v) =>
                          setPivotMeasures((prev) =>
                            prev.map((x, i) => (i === idx ? { ...x, field: v } : x))
                          )
                        }
                      />
                      <div />
                      <div />
                    </div>
                  )}
                </div>
              ))}
              <button
                type="button"
                className="btn btn-sm btn-ghost"
                onClick={() =>
                  setPivotMeasures((prev) => [
                    ...prev,
                    {
                      alias: `m${prev.length}`,
                      fn: 'count',
                      field: '*',
                      expression: '',
                      useExpression: false,
                    },
                  ])
                }
              >
                + {t('reportEngine.pivot.addMeasure')}
              </button>
              <label style={{ display: 'flex', gap: 'var(--sp-2)' }}>
                <input
                  type="checkbox"
                  checked={pivotSubtotals}
                  onChange={(e) => setPivotSubtotals(e.target.checked)}
                />
                {t('reportEngine.pivot.subtotals')}
              </label>
              <label style={{ display: 'flex', gap: 'var(--sp-2)' }}>
                <input
                  type="checkbox"
                  checked={pivotGrand}
                  onChange={(e) => setPivotGrand(e.target.checked)}
                />
                {t('reportEngine.pivot.grandTotal')}
              </label>
              <button
                type="button"
                className="btn btn-primary"
                disabled={pivotLoading}
                onClick={() => void runPivot()}
              >
                {t('reportEngine.pivot.run')}
              </button>

              <h3 className="report-builder__panel-title">{t('reportEngine.dsl.expression')}</h3>
              <textarea
                className="form-control"
                rows={2}
                value={formulaDraft}
                onChange={(e) => setFormulaDraft(e.target.value)}
                placeholder={t('reportEngine.dsl.placeholder')}
              />
              <button type="button" className="btn btn-sm btn-ghost" onClick={() => void validateFormula()}>
                {t('reportEngine.dsl.validate')}
              </button>
              <p style={{ margin: 0, fontSize: 'var(--fs-caption)', color: 'var(--text-tertiary)' }}>
                {t('reportEngine.dsl.examples')}
              </p>
              <Link to="/reports/measures" style={{ fontSize: 'var(--fs-caption)' }}>
                {t('reportEngine.measures.title')}
              </Link>
            </div>
          ) : null}
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
          {viewMode === 'pivot' ? (
            <>
              {pivotLoading ? <p>{t('loading')}</p> : null}
              {pivotResult ? (
                <PivotMatrixTable
                  rowHeaders={pivotResult.row_headers}
                  columnHeaders={pivotResult.column_headers}
                  rowKeys={pivotResult.row_keys}
                  columnKeys={pivotResult.column_keys}
                  measures={pivotResult.measures}
                  cells={pivotResult.cells}
                  onCellClick={(ri, ci) => void handlePivotCellClick(ri, ci)}
                />
              ) : (
                <p style={{ color: 'var(--text-tertiary)' }}>{t('reportEngine.previewEmpty')}</p>
              )}
              {detailRows.length > 0 ? (
                <ReportTable
                  columns={
                    Object.keys(detailRows[0] ?? {}).map((key) => ({
                      id: key,
                      accessorKey: key,
                      header: fieldLabel(key),
                    }))
                  }
                  rows={detailRows}
                  emptyLabel={t('reportEngine.previewEmpty')}
                  height={240}
                />
              ) : null}
            </>
          ) : viewMode === 'table' ? (
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
