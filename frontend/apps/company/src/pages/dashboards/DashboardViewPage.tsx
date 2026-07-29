import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import GridLayout, { type Layout, type LayoutItem } from 'react-grid-layout/legacy';
import 'react-grid-layout/css/styles.css';
import { useTranslation } from '@shared/i18n';
import {
  ReportChart,
  ReportTable,
  type ReportChartMapping,
  type ReportChartType,
  type ReportRow,
  type ReportTableColumn,
} from '@shared/components';
import { usePermission } from '@shared/hooks';
import {
  dashboardsApi,
  reportsApi,
  type DashboardPayload,
  type DashboardRunResult,
  type DashboardWidgetDef,
  type SavedReportPayload,
} from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import PivotMatrixTable from '../reports/PivotMatrixTable';
import {
  datePresetRange,
  formatRanAt,
  isDashboardPayload,
  isDashboardRunResult,
  isKpiData,
  isReportRows,
  isTextData,
  minPollIntervalMs,
  parseWidgetType,
  resultByWidgetId,
  valuesToRunPayload,
  widgetsFromDashboard,
  type GlobalFilterValues,
} from './dashboardHelpers';
import './dashboards.css';

interface PivotWidgetData {
  row_headers?: { field: string; label: string }[];
  column_headers?: { field: string; label: string }[];
  row_keys: string[][];
  column_keys?: string[][];
  measures?: { alias: string; format: string; decimals: number }[];
  cells: { row: number; col: number; measure: string; value: string | number | null }[];
}

function isPivotWidgetData(data: unknown): data is PivotWidgetData {
  if (typeof data !== 'object' || data === null) return false;
  if (!('row_keys' in data) || !('cells' in data)) return false;
  return Array.isArray(data.row_keys) && Array.isArray(data.cells);
}

function normalizePivot(data: PivotWidgetData): {
  row_headers: { field: string; label: string }[];
  column_headers: { field: string; label: string }[];
  row_keys: string[][];
  column_keys: string[][];
  measures: { alias: string; format: string; decimals: number }[];
  cells: { row: number; col: number; measure: string; value: string | number | null }[];
} {
  return {
    row_headers: Array.isArray(data.row_headers) ? data.row_headers : [],
    column_headers: Array.isArray(data.column_headers) ? data.column_headers : [],
    row_keys: data.row_keys,
    column_keys: Array.isArray(data.column_keys) ? data.column_keys : [],
    measures: Array.isArray(data.measures) ? data.measures : [],
    cells: data.cells,
  };
}

function toReportRows(rows: Record<string, unknown>[]): ReportRow[] {
  return rows.map((r, i) => {
    const row: ReportRow = { __i: i };
    for (const [k, v] of Object.entries(r)) {
      if (v === null || v === undefined) row[k] = null;
      else if (typeof v === 'string' || typeof v === 'number' || typeof v === 'boolean') row[k] = v;
      else row[k] = JSON.stringify(v);
    }
    return row;
  });
}

function isSavedReport(value: unknown): value is SavedReportPayload {
  if (typeof value !== 'object' || value === null) return false;
  return 'id' in value && typeof value.id === 'number' && 'name' in value;
}

function parseChartType(value: string | undefined): ReportChartType {
  if (value === 'line' || value === 'pie' || value === 'area' || value === 'stacked_bar') return value;
  return 'bar';
}

const DashboardViewPage: React.FC = () => {
  const { t, i18n } = useTranslation('common');
  const { id: idParam } = useParams();
  const [searchParams, setSearchParams] = useSearchParams();
  const { canEdit, canCreate } = usePermission();
  const canEditD = canEdit('reports', 'dashboards');
  const canCreateW = canCreate('reports', 'dashboards');

  const dashboardId = Number(idParam);
  const editMode = searchParams.get('edit') === '1' && canEditD;

  const [dashboard, setDashboard] = useState<DashboardPayload | null>(null);
  const [widgets, setWidgets] = useState<DashboardWidgetDef[]>([]);
  const [run, setRun] = useState<DashboardRunResult | null>(null);
  const [loading, setLoading] = useState(true);
  const [running, setRunning] = useState(false);
  const [reports, setReports] = useState<SavedReportPayload[]>([]);
  const [showAdd, setShowAdd] = useState(false);
  const [newTitle, setNewTitle] = useState('');
  const [newType, setNewType] = useState('kpi');
  const [newReportId, setNewReportId] = useState('');
  const [cross, setCross] = useState<Record<string, string>>({});
  const [gridWidth, setGridWidth] = useState(1200);
  const wrapRef = useRef<HTMLDivElement | null>(null);
  const visibleRef = useRef(true);

  const filterValues: GlobalFilterValues = useMemo(() => {
    const v: GlobalFilterValues = {};
    searchParams.forEach((val, key) => {
      if (key === 'edit') return;
      v[key] = val;
    });
    return v;
  }, [searchParams]);

  const setFilter = (key: string, value: string) => {
    const next = new URLSearchParams(searchParams);
    if (value === '') next.delete(key);
    else next.set(key, value);
    setSearchParams(next, { replace: true });
  };

  const applyPreset = (preset: string) => {
    const next = new URLSearchParams(searchParams);
    next.set('date_preset', preset);
    if (preset === 'custom') {
      setSearchParams(next, { replace: true });
      return;
    }
    const range = datePresetRange(preset);
    if (range) {
      next.set('hire_date_from', range.from);
      next.set('hire_date_to', range.to);
    }
    setSearchParams(next, { replace: true });
  };

  const loadDashboard = useCallback(async () => {
    if (!Number.isFinite(dashboardId) || dashboardId <= 0) return;
    try {
      setLoading(true);
      const res = await dashboardsApi.get(dashboardId);
      const data = res.data.data;
      if (!isDashboardPayload(data)) {
        toast.error(t('dashboards.loadError'));
        return;
      }
      setDashboard(data);
      setWidgets(widgetsFromDashboard(data));
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('dashboards.loadError')));
    } finally {
      setLoading(false);
    }
  }, [dashboardId, t]);

  const runBatch = useCallback(async () => {
    if (!Number.isFinite(dashboardId) || dashboardId <= 0) return;
    try {
      setRunning(true);
      const res = await dashboardsApi.run(dashboardId, {
        values: valuesToRunPayload(filterValues),
        cross: Object.keys(cross).length > 0 ? cross : undefined,
      });
      const data = res.data.data;
      if (isDashboardRunResult(data)) {
        setRun(data);
        if (data.meta.partial && data.meta.warnings?.length) {
          toast(data.meta.warnings[0]);
        }
      }
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('dashboards.runError')));
    } finally {
      setRunning(false);
    }
  }, [dashboardId, filterValues, cross, t]);

  useEffect(() => {
    void loadDashboard();
  }, [loadDashboard]);

  useEffect(() => {
    if (!dashboard) return;
    void runBatch();
  }, [dashboard, filterValues, cross]); // eslint-disable-line react-hooks/exhaustive-deps -- run on filter/cross change

  useEffect(() => {
    const el = wrapRef.current;
    if (!el) return;
    const ro = new ResizeObserver((entries) => {
      const w = entries[0]?.contentRect.width;
      if (w && w > 0) setGridWidth(Math.floor(w));
    });
    ro.observe(el);
    return () => ro.disconnect();
  }, [dashboard]);

  useEffect(() => {
    const onVis = () => {
      visibleRef.current = document.visibilityState === 'visible';
    };
    document.addEventListener('visibilitychange', onVis);
    return () => document.removeEventListener('visibilitychange', onVis);
  }, []);

  useEffect(() => {
    const ms = minPollIntervalMs(widgets);
    if (ms === null) return;
    const timer = window.setInterval(() => {
      if (!visibleRef.current) return;
      void runBatch();
    }, ms);
    return () => window.clearInterval(timer);
  }, [widgets, runBatch]);

  useEffect(() => {
    if (!editMode) return;
    void (async () => {
      try {
        const res = await reportsApi.list({ per_page: 100 });
        const raw: unknown = res.data.data;
        let list: SavedReportPayload[] = [];
        if (Array.isArray(raw)) list = raw.filter(isSavedReport);
        else if (typeof raw === 'object' && raw !== null && 'data' in raw && Array.isArray(raw.data)) {
          list = raw.data.filter(isSavedReport);
        }
        setReports(list);
      } catch {
        /* liste opsiyonel */
      }
    })();
  }, [editMode]);

  const byId = useMemo(() => resultByWidgetId(run), [run]);

  const layout: Layout = useMemo(
    () =>
      widgets.map((w): LayoutItem => {
        const L = w.layout ?? { x: 0, y: 0, w: 6, h: 4 };
        const cols = gridWidth < 768 ? 1 : 12;
        if (cols === 1) {
          return {
            i: w.id,
            x: 0,
            y: L.y,
            w: 1,
            h: Math.max(L.h, 3),
            static: !editMode,
            isResizable: editMode,
          };
        }
        return {
          i: w.id,
          x: L.x,
          y: L.y,
          w: L.w,
          h: L.h,
          static: !editMode,
          isResizable: editMode,
        };
      }),
    [widgets, editMode, gridWidth]
  );

  const cols = gridWidth < 768 ? 1 : 12;

  const handleLayoutChange = (newLayout: Layout) => {
    if (!editMode) return;
    setWidgets((prev) =>
      prev.map((w) => {
        const item = newLayout.find((l) => l.i === w.id);
        if (!item) return w;
        return {
          ...w,
          layout: { x: item.x, y: item.y, w: cols === 1 ? (w.layout?.w ?? 6) : item.w, h: item.h },
        };
      })
    );
  };

  const saveLayout = async () => {
    if (!dashboard || !canEditD) return;
    try {
      const res = await dashboardsApi.update(dashboard.id, {
        layout: { widgets },
      });
      const data = res.data.data;
      if (isDashboardPayload(data)) {
        setDashboard(data);
        setWidgets(widgetsFromDashboard(data));
        toast.success(t('dashboards.saveSuccess'));
      }
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('dashboards.saveError')));
    }
  };

  const addWidget = async () => {
    if (!dashboard || !canCreateW) return;
    const type = parseWidgetType(newType);
    const reportId = newReportId ? Number(newReportId) : null;
    if (type !== 'text' && (!reportId || !Number.isFinite(reportId))) {
      toast.error(t('dashboards.reportRequired'));
      return;
    }
    const id = `w_${Date.now()}`;
    const next: DashboardWidgetDef[] = [
      ...widgets,
      {
        id,
        type,
        title: newTitle.trim() || t('dashboards.widgetUntitled'),
        report_id: type === 'text' ? null : reportId,
        content: type === 'text' ? t('dashboards.textPlaceholder') : null,
        layout: { x: 0, y: Infinity, w: type === 'kpi' ? 3 : 6, h: type === 'kpi' ? 2 : 5 },
        refresh_interval: 0,
        ignore_cross_filter: false,
        row_limit: 25,
        visual: type === 'chart' ? { chart_type: 'bar' } : null,
      },
    ];
    if (next.length > 20) {
      toast.error(t('dashboards.widgetLimit'));
      return;
    }
    try {
      const res = await dashboardsApi.update(dashboard.id, { layout: { widgets: next } });
      const data = res.data.data;
      if (isDashboardPayload(data)) {
        setDashboard(data);
        setWidgets(widgetsFromDashboard(data));
        setShowAdd(false);
        setNewTitle('');
        setNewReportId('');
        toast.success(t('dashboards.widgetAdded'));
        void runBatch();
      }
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('dashboards.saveError')));
    }
  };

  const removeWidget = async (widgetId: string) => {
    if (!dashboard || !canEditD) return;
    const next = widgets.filter((w) => w.id !== widgetId);
    try {
      const res = await dashboardsApi.update(dashboard.id, { layout: { widgets: next } });
      const data = res.data.data;
      if (isDashboardPayload(data)) {
        setDashboard(data);
        setWidgets(widgetsFromDashboard(data));
        void runBatch();
      }
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('dashboards.saveError')));
    }
  };

  const toggleIgnoreCross = async (widgetId: string) => {
    if (!dashboard || !canEditD) return;
    const next = widgets.map((w) =>
      w.id === widgetId ? { ...w, ignore_cross_filter: !w.ignore_cross_filter } : w
    );
    setWidgets(next);
    try {
      await dashboardsApi.update(dashboard.id, { layout: { widgets: next } });
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('dashboards.saveError')));
    }
  };

  const applyCross = (field: string, value: string) => {
    setCross((prev) => ({ ...prev, [field]: value }));
  };

  const clearCross = () => setCross({});

  const renderWidgetBody = (w: DashboardWidgetDef) => {
    const result = byId.get(w.id);
    if (!result) {
      return <div className="dash-widget__meta">{running ? t('dashboards.running') : '—'}</div>;
    }
    if (!result.success) {
      return <div className="dash-widget__error">{result.error ?? t('dashboards.widgetError')}</div>;
    }
    const data = result.data;

    if (w.type === 'text' && isTextData(data)) {
      return <pre className="dash-text">{data.content}</pre>;
    }

    if (w.type === 'kpi' && isKpiData(data)) {
      const dir = data.direction;
      return (
        <div className="dash-kpi">
          <div className="dash-kpi__value">{String(data.value ?? '—')}</div>
          {dir === 'up' ? <span className="dash-kpi__dir-up">▲</span> : null}
          {dir === 'down' ? <span className="dash-kpi__dir-down">▼</span> : null}
        </div>
      );
    }

    if ((w.type === 'table' || w.type === 'chart') && isReportRows(data)) {
      const rawRows = data.rows;
      const keys =
        rawRows.length > 0 && typeof rawRows[0] === 'object' && rawRows[0] !== null
          ? Object.keys(rawRows[0])
          : [];
      const reportRows = toReportRows(rawRows);
      if (w.type === 'table') {
        const columns: ReportTableColumn[] = keys.map((k) => ({ id: k, header: k, accessorKey: k }));
        return (
          <div>
            <ReportTable columns={columns} rows={reportRows} height={220} emptyLabel={t('dashboards.noData')} />
            {w.report_id ? (
              <Link to={`/reports/${w.report_id}`} className="btn btn-sm btn-ghost">
                {t('dashboards.openReport')}
              </Link>
            ) : null}
            {keys[0] ? (
              <div className="dash-widget__meta">
                {rawRows.slice(0, 5).map((r, i) => {
                  const val = r[keys[0]];
                  if (val === null || val === undefined) return null;
                  return (
                    <button
                      key={i}
                      type="button"
                      className="btn btn-sm btn-ghost"
                      onClick={() => applyCross(keys[0], String(val))}
                    >
                      {String(val)}
                    </button>
                  );
                })}
              </div>
            ) : null}
          </div>
        );
      }
      const visual = w.visual ?? {};
      const chartType = parseChartType(visual.chart_type);
      const categoryField = visual.category ?? keys[0] ?? '';
      const valueField = visual.value ?? keys[1] ?? keys[0] ?? '';
      const mapping: ReportChartMapping = {
        type: chartType,
        categoryField,
        valueField,
        seriesField: visual.series,
      };
      return (
        <div
          role="presentation"
          onClick={() => {
            if (rawRows[0] && categoryField) {
              const v = rawRows[0][categoryField];
              if (v !== null && v !== undefined) applyCross(categoryField, String(v));
            }
          }}
        >
          <ReportChart
            type={chartType}
            rows={reportRows}
            mapping={mapping}
            height={200}
            emptyLabel={t('dashboards.noData')}
          />
        </div>
      );
    }

    if (w.type === 'pivot' && isPivotWidgetData(data)) {
      const pivot = normalizePivot(data);
      const rowField = pivot.row_headers[0]?.field ?? 'row';
      return (
        <PivotMatrixTable
          rowHeaders={pivot.row_headers}
          columnHeaders={pivot.column_headers}
          rowKeys={pivot.row_keys}
          columnKeys={pivot.column_keys}
          measures={pivot.measures}
          cells={pivot.cells}
          onCellClick={(ri) => {
            const key = pivot.row_keys[ri];
            if (key && key[0] !== undefined) applyCross(rowField, key[0]);
          }}
        />
      );
    }

    return <div className="dash-widget__meta">{t('dashboards.noData')}</div>;
  };

  if (loading || !dashboard) {
    return <div className="dash-empty page-container">{t('dashboards.loading')}</div>;
  }

  const crossEntries = Object.entries(cross);

  return (
    <div className="dash-page page-container">
      <div className="dash-toolbar">
        <div>
          <Link to="/dashboards" className="btn btn-ghost btn-sm">
            ← {t('dashboards.back')}
          </Link>
          <h1 className="page-title">{dashboard.name}</h1>
          {dashboard.description ? <p className="page-subtitle">{dashboard.description}</p> : null}
        </div>
        <div style={{ display: 'flex', gap: 'var(--sp-2)', flexWrap: 'wrap' }}>
          <button type="button" className="btn btn-secondary" onClick={() => void runBatch()} disabled={running}>
            {t('dashboards.refresh')}
          </button>
          {canEditD ? (
            <>
              <button
                type="button"
                className={`btn ${editMode ? 'btn-primary' : 'btn-ghost'}`}
                onClick={() => {
                  const next = new URLSearchParams(searchParams);
                  if (editMode) next.delete('edit');
                  else next.set('edit', '1');
                  setSearchParams(next);
                }}
              >
                {editMode ? t('dashboards.viewMode') : t('dashboards.editMode')}
              </button>
              {editMode ? (
                <>
                  <button type="button" className="btn btn-primary" onClick={() => void saveLayout()}>
                    {t('dashboards.save')}
                  </button>
                  <button type="button" className="btn btn-secondary" onClick={() => setShowAdd(true)}>
                    {t('dashboards.addWidget')}
                  </button>
                </>
              ) : null}
            </>
          ) : null}
        </div>
      </div>

      <div className="dash-filters">
        <label>
          {t('dashboards.filterDate')}
          <select
            value={filterValues.date_preset ?? ''}
            onChange={(e) => applyPreset(e.target.value)}
          >
            <option value="">{t('dashboards.presetNone')}</option>
            <option value="this_month">{t('dashboards.presetThisMonth')}</option>
            <option value="last_month">{t('dashboards.presetLastMonth')}</option>
            <option value="this_year">{t('dashboards.presetThisYear')}</option>
            <option value="custom">{t('dashboards.presetCustom')}</option>
          </select>
        </label>
        {(filterValues.date_preset === 'custom' || filterValues.hire_date_from) && (
          <>
            <label>
              {t('dashboards.from')}
              <input
                type="date"
                value={filterValues.hire_date_from ?? ''}
                onChange={(e) => setFilter('hire_date_from', e.target.value)}
              />
            </label>
            <label>
              {t('dashboards.to')}
              <input
                type="date"
                value={filterValues.hire_date_to ?? ''}
                onChange={(e) => setFilter('hire_date_to', e.target.value)}
              />
            </label>
          </>
        )}
        <label>
          {t('dashboards.filterDepartment')}
          <input
            type="number"
            value={filterValues.department_id ?? ''}
            onChange={(e) => setFilter('department_id', e.target.value)}
            placeholder="ID"
          />
        </label>
        <label>
          {t('dashboards.filterBranch')}
          <input
            type="number"
            value={filterValues.branch_id ?? ''}
            onChange={(e) => setFilter('branch_id', e.target.value)}
            placeholder="ID"
          />
        </label>
      </div>

      {crossEntries.length > 0 ? (
        <div className="dash-cross-bar">
          <strong>{t('dashboards.crossFilter')}:</strong>
          {crossEntries.map(([k, v]) => (
            <span key={k}>
              {k}={v}
            </span>
          ))}
          <button type="button" className="btn btn-sm btn-ghost" onClick={clearCross}>
            {t('dashboards.clearCross')}
          </button>
        </div>
      ) : null}

      {showAdd ? (
        <div className="dash-widget-form">
          <h3>{t('dashboards.addWidget')}</h3>
          <label>
            {t('dashboards.widgetTitle')}
            <input value={newTitle} onChange={(e) => setNewTitle(e.target.value)} />
          </label>
          <label>
            {t('dashboards.widgetType')}
            <select value={newType} onChange={(e) => setNewType(e.target.value)}>
              <option value="kpi">KPI</option>
              <option value="chart">{t('dashboards.typeChart')}</option>
              <option value="table">{t('dashboards.typeTable')}</option>
              <option value="pivot">{t('dashboards.typePivot')}</option>
              <option value="text">{t('dashboards.typeText')}</option>
            </select>
          </label>
          {newType !== 'text' ? (
            <label>
              {t('dashboards.sourceReport')}
              <select value={newReportId} onChange={(e) => setNewReportId(e.target.value)}>
                <option value="">{t('dashboards.selectReport')}</option>
                {reports.map((r) => (
                  <option key={r.id} value={r.id}>
                    {r.name}
                  </option>
                ))}
              </select>
            </label>
          ) : null}
          <div style={{ display: 'flex', gap: 'var(--sp-2)' }}>
            <button type="button" className="btn btn-primary" onClick={() => void addWidget()}>
              {t('dashboards.add')}
            </button>
            <button type="button" className="btn btn-ghost" onClick={() => setShowAdd(false)}>
              {t('dashboards.cancel')}
            </button>
          </div>
        </div>
      ) : null}

      {widgets.length === 0 ? (
        <div className="dash-empty">{t('dashboards.noWidgets')}</div>
      ) : (
        <div className="dash-grid-wrap" ref={wrapRef}>
          <GridLayout
            className="layout"
            layout={layout}
            cols={cols}
            rowHeight={48}
            width={gridWidth}
            onLayoutChange={handleLayoutChange}
            isDraggable={editMode}
            isResizable={editMode}
            compactType="vertical"
            margin={[12, 12]}
          >
            {widgets.map((w) => {
              const result = byId.get(w.id);
              const skipped = result?.meta.filter_skipped_keys?.length;
              const filterOk = result?.meta.filter_applied;
              return (
                <div key={w.id}>
                  <div className="dash-widget">
                    <div className="dash-widget__head">
                      <span>
                        {w.title || w.type}
                        {skipped && !filterOk ? (
                          <span className="dash-widget__badge" title={t('dashboards.filterSkipped')}>
                            ({t('dashboards.filterSkippedShort')})
                          </span>
                        ) : null}
                        {skipped && filterOk && skipped > 0 ? (
                          <span className="dash-widget__badge" title={(result?.meta.filter_skipped_keys ?? []).join(', ')}>
                            ({t('dashboards.filterPartial')})
                          </span>
                        ) : null}
                      </span>
                      <span className="dash-widget__meta">
                        {formatRanAt(result?.meta.ran_at, i18n.language)}
                        {editMode ? (
                          <>
                            {' '}
                            <button
                              type="button"
                              className="btn btn-sm btn-ghost"
                              onClick={() => void toggleIgnoreCross(w.id)}
                              title={t('dashboards.ignoreCross')}
                            >
                              {w.ignore_cross_filter ? '⌀' : '⊕'}
                            </button>
                            <button
                              type="button"
                              className="btn btn-sm btn-ghost"
                              onClick={() => void removeWidget(w.id)}
                            >
                              ×
                            </button>
                          </>
                        ) : null}
                      </span>
                    </div>
                    <div className="dash-widget__body">{renderWidgetBody(w)}</div>
                  </div>
                </div>
              );
            })}
          </GridLayout>
        </div>
      )}
    </div>
  );
};

export default DashboardViewPage;
