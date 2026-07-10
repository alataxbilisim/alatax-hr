import React, { useState, useMemo } from 'react';
import {
  BsBarChartLine,
  BsPieChart,
  BsGraphUp,
  BsTable,
  BsFilter,
  BsXCircle,
} from 'react-icons/bs';
import type { ColorScheme } from './charts';

interface ReportMetadata {
  dimensions: Array<{ value: string; label: string }>;
  measures: Array<{ value: string; label: string }>;
  chart_types: Array<{ value: string; label: string }>;
  filters: {
    departments: Array<{ id: number; name: string }>;
    positions: string[];
    cities: string[];
    statuses: Array<{ value: string; label: string }>;
    contract_types: Array<{ value: string; label: string }>;
    work_types: Array<{ value: string; label: string }>;
    genders: Array<{ value: string; label: string }>;
  };
  color_schemes: Array<{ value: string; label: string }>;
}

export interface ReportConfig {
  dimension: string;
  measure: string;
  chartType: 'bar' | 'horizontal_bar' | 'pie' | 'donut' | 'line' | 'area' | 'heatmap' | 'table';
  filters: {
    status?: string[];
    department_id?: number[];
    position?: string[];
    city?: string[];
    contract_type?: string[];
    work_type?: string[];
    gender?: string[];
    date_range?: {
      start: string;
      end: string;
    };
  };
  options: {
    showLegend: boolean;
    showDataLabels: boolean;
    colorScheme: ColorScheme;
  };
}

interface ReportBuilderProps {
  config: ReportConfig;
  onChange: (config: ReportConfig) => void;
  metadata: ReportMetadata | null;
  loading?: boolean;
}

const chartTypeIcons: Record<string, React.ReactNode> = {
  bar: <BsBarChartLine />,
  horizontal_bar: <BsBarChartLine style={{ transform: 'rotate(90deg)' }} />,
  pie: <BsPieChart />,
  donut: <BsPieChart />,
  line: <BsGraphUp />,
  area: <BsGraphUp />,
  heatmap: <BsTable />,
  table: <BsTable />,
};

function getActiveFilterKeys(filters: ReportConfig['filters']): string[] {
  const active: string[] = [];
  if (filters.status?.length) active.push('status');
  if (filters.department_id?.length) active.push('department_id');
  if (filters.position?.length) active.push('position');
  if (filters.city?.length) active.push('city');
  if (filters.contract_type?.length) active.push('contract_type');
  if (filters.work_type?.length) active.push('work_type');
  if (filters.gender?.length) active.push('gender');
  if (filters.date_range?.start || filters.date_range?.end) active.push('date_range');
  return active;
}

const ReportBuilder: React.FC<ReportBuilderProps> = ({
  config,
  onChange,
  metadata,
  loading = false,
}) => {
  const [showFilters, setShowFilters] = useState(false);
  const activeFilters = useMemo(() => getActiveFilterKeys(config.filters), [config.filters]);

  const updateConfig = (updates: Partial<ReportConfig>) => {
    onChange({ ...config, ...updates });
  };

  const updateFilters = (filterUpdates: Partial<ReportConfig['filters']>) => {
    onChange({
      ...config,
      filters: { ...config.filters, ...filterUpdates },
    });
  };

  const updateOptions = (optionUpdates: Partial<ReportConfig['options']>) => {
    onChange({
      ...config,
      options: { ...config.options, ...optionUpdates },
    });
  };

  const clearFilter = (filterKey: keyof ReportConfig['filters']) => {
    const newFilters = { ...config.filters };
    delete newFilters[filterKey];
    onChange({ ...config, filters: newFilters });
  };

  const clearAllFilters = () => {
    onChange({ ...config, filters: {} });
  };

  if (!metadata) {
    return (
      <div className="report-builder">
        <div className="report-builder-loading">
          <div className="spinner-border spinner-border-sm" role="status" />
          <span>Yükleniyor...</span>
        </div>
      </div>
    );
  }

  return (
    <div className="report-builder">
      <div className="report-builder-section">
        <label className="report-builder-label">Boyut (Gruplama)</label>
        <select
          className="form-control"
          value={config.dimension}
          onChange={(e) => updateConfig({ dimension: e.target.value })}
          disabled={loading}
        >
          {metadata.dimensions.map((dim) => (
            <option key={dim.value} value={dim.value}>
              {dim.label}
            </option>
          ))}
        </select>
      </div>

      <div className="report-builder-section">
        <label className="report-builder-label">Metrik (Ölçüm)</label>
        <select
          className="form-control"
          value={config.measure}
          onChange={(e) => updateConfig({ measure: e.target.value })}
          disabled={loading}
        >
          {metadata.measures.map((m) => (
            <option key={m.value} value={m.value}>
              {m.label}
            </option>
          ))}
        </select>
      </div>

      <div className="report-builder-section">
        <label className="report-builder-label">Grafik Türü</label>
        <div className="report-chart-type-grid">
          {metadata.chart_types.map((ct) => (
            <button
              key={ct.value}
              className={`report-chart-type-btn ${config.chartType === ct.value ? 'active' : ''}`}
              onClick={() => updateConfig({ chartType: ct.value as ReportConfig['chartType'] })}
              disabled={loading}
              title={ct.label}
            >
              {chartTypeIcons[ct.value] || <BsBarChartLine />}
              <span>{ct.label}</span>
            </button>
          ))}
        </div>
      </div>

      <div className="report-builder-section">
        <div className="report-builder-section-header">
          <label className="report-builder-label">
            <BsFilter /> Filtreler
            {activeFilters.length > 0 && (
              <span className="report-filter-badge">{activeFilters.length}</span>
            )}
          </label>
          <button
            className="btn btn-ghost btn-xs"
            onClick={() => setShowFilters(!showFilters)}
          >
            {showFilters ? 'Gizle' : 'Göster'}
          </button>
        </div>

        {showFilters && (
          <div className="report-filters">
            {activeFilters.length > 0 && (
              <button
                className="btn btn-ghost btn-xs text-danger mb-2"
                onClick={clearAllFilters}
              >
                <BsXCircle /> Tümünü Temizle
              </button>
            )}

            {/* Durum Filtresi */}
            <div className="report-filter-group">
              <div className="report-filter-header">
                <span>Durum</span>
                {config.filters.status?.length ? (
                  <button className="btn-icon-xs" onClick={() => clearFilter('status')}>
                    <BsXCircle />
                  </button>
                ) : null}
              </div>
              <div className="report-filter-checkboxes">
                {metadata.filters.statuses.map((s) => (
                  <label key={s.value} className="report-filter-checkbox">
                    <input
                      type="checkbox"
                      checked={config.filters.status?.includes(s.value) || false}
                      onChange={(e) => {
                        const current = config.filters.status || [];
                        if (e.target.checked) {
                          updateFilters({ status: [...current, s.value] });
                        } else {
                          updateFilters({ status: current.filter((v) => v !== s.value) });
                        }
                      }}
                    />
                    <span>{s.label}</span>
                  </label>
                ))}
              </div>
            </div>

            {/* Departman Filtresi */}
            <div className="report-filter-group">
              <div className="report-filter-header">
                <span>Departman</span>
                {config.filters.department_id?.length ? (
                  <button className="btn-icon-xs" onClick={() => clearFilter('department_id')}>
                    <BsXCircle />
                  </button>
                ) : null}
              </div>
              <select
                className="form-control form-control-sm"
                multiple
                value={(config.filters.department_id || []).map(String)}
                onChange={(e) => {
                  const selected = Array.from(e.target.selectedOptions, (opt) => Number(opt.value));
                  updateFilters({ department_id: selected.length > 0 ? selected : undefined });
                }}
              >
                {metadata.filters.departments.map((d) => (
                  <option key={d.id} value={d.id}>
                    {d.name}
                  </option>
                ))}
              </select>
            </div>

            {/* Cinsiyet Filtresi */}
            <div className="report-filter-group">
              <div className="report-filter-header">
                <span>Cinsiyet</span>
                {config.filters.gender?.length ? (
                  <button className="btn-icon-xs" onClick={() => clearFilter('gender')}>
                    <BsXCircle />
                  </button>
                ) : null}
              </div>
              <div className="report-filter-checkboxes">
                {metadata.filters.genders.map((g) => (
                  <label key={g.value} className="report-filter-checkbox">
                    <input
                      type="checkbox"
                      checked={config.filters.gender?.includes(g.value) || false}
                      onChange={(e) => {
                        const current = config.filters.gender || [];
                        if (e.target.checked) {
                          updateFilters({ gender: [...current, g.value] });
                        } else {
                          updateFilters({ gender: current.filter((v) => v !== g.value) });
                        }
                      }}
                    />
                    <span>{g.label}</span>
                  </label>
                ))}
              </div>
            </div>

            {/* Sözleşme Tipi Filtresi */}
            <div className="report-filter-group">
              <div className="report-filter-header">
                <span>Sözleşme Tipi</span>
                {config.filters.contract_type?.length ? (
                  <button className="btn-icon-xs" onClick={() => clearFilter('contract_type')}>
                    <BsXCircle />
                  </button>
                ) : null}
              </div>
              <div className="report-filter-checkboxes">
                {metadata.filters.contract_types.map((ct) => (
                  <label key={ct.value} className="report-filter-checkbox">
                    <input
                      type="checkbox"
                      checked={config.filters.contract_type?.includes(ct.value) || false}
                      onChange={(e) => {
                        const current = config.filters.contract_type || [];
                        if (e.target.checked) {
                          updateFilters({ contract_type: [...current, ct.value] });
                        } else {
                          updateFilters({ contract_type: current.filter((v) => v !== ct.value) });
                        }
                      }}
                    />
                    <span>{ct.label}</span>
                  </label>
                ))}
              </div>
            </div>

            {/* Tarih Aralığı Filtresi */}
            <div className="report-filter-group">
              <div className="report-filter-header">
                <span>İşe Alım Tarihi</span>
                {(config.filters.date_range?.start || config.filters.date_range?.end) ? (
                  <button className="btn-icon-xs" onClick={() => clearFilter('date_range')}>
                    <BsXCircle />
                  </button>
                ) : null}
              </div>
              <div className="report-filter-date-range">
                <input
                  type="date"
                  className="form-control form-control-sm"
                  placeholder="Başlangıç"
                  value={config.filters.date_range?.start || ''}
                  onChange={(e) =>
                    updateFilters({
                      date_range: {
                        ...config.filters.date_range,
                        start: e.target.value,
                        end: config.filters.date_range?.end || '',
                      },
                    })
                  }
                />
                <span>-</span>
                <input
                  type="date"
                  className="form-control form-control-sm"
                  placeholder="Bitiş"
                  value={config.filters.date_range?.end || ''}
                  onChange={(e) =>
                    updateFilters({
                      date_range: {
                        ...config.filters.date_range,
                        start: config.filters.date_range?.start || '',
                        end: e.target.value,
                      },
                    })
                  }
                />
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Görünüm Seçenekleri */}
      <div className="report-builder-section">
        <label className="report-builder-label">Görünüm Ayarları</label>
        
        <div className="report-options">
          <label className="report-option-checkbox">
            <input
              type="checkbox"
              checked={config.options.showLegend}
              onChange={(e) => updateOptions({ showLegend: e.target.checked })}
            />
            <span>Legend Göster</span>
          </label>

          <label className="report-option-checkbox">
            <input
              type="checkbox"
              checked={config.options.showDataLabels}
              onChange={(e) => updateOptions({ showDataLabels: e.target.checked })}
            />
            <span>Veri Etiketleri</span>
          </label>

          <div className="report-option-row">
            <span>Renk Şeması</span>
            <select
              className="form-control form-control-sm"
              value={config.options.colorScheme}
              onChange={(e) => updateOptions({ colorScheme: e.target.value as ColorScheme })}
            >
              {metadata.color_schemes.map((cs) => (
                <option key={cs.value} value={cs.value}>
                  {cs.label}
                </option>
              ))}
            </select>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ReportBuilder;

