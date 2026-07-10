import React from 'react';
import {
  BarChartWidget,
  PieChartWidget,
  LineChartWidget,
  HeatmapWidget,
  DataTableWidget,
  ChartDataItem,
} from './charts';
import type { ReportConfig } from './ReportBuilder';
import { BsArrowClockwise } from 'react-icons/bs';

interface ReportPreviewProps {
  config: ReportConfig;
  data: ChartDataItem[];
  loading: boolean;
  error: string | null;
  dimensionLabel?: string;
  measureLabel?: string;
  total?: number;
  onRefresh?: () => void;
}

const ReportPreview: React.FC<ReportPreviewProps> = ({
  config,
  data,
  loading,
  error,
  dimensionLabel = 'Kategori',
  measureLabel = 'Değer',
  total,
  onRefresh,
}) => {
  const renderChart = () => {
    if (loading) {
      return (
        <div className="report-preview-loading">
          <div className="spinner-border" role="status">
            <span className="visually-hidden">Yükleniyor...</span>
          </div>
          <p>Rapor oluşturuluyor...</p>
        </div>
      );
    }

    if (error) {
      return (
        <div className="report-preview-error">
          <p>{error}</p>
          {onRefresh && (
            <button className="btn btn-secondary btn-sm" onClick={onRefresh}>
              <BsArrowClockwise /> Tekrar Dene
            </button>
          )}
        </div>
      );
    }

    if (data.length === 0) {
      return (
        <div className="report-preview-empty">
          <div className="report-preview-empty-icon">📊</div>
          <h4>Veri Bulunamadı</h4>
          <p>Seçili kriterlere uygun veri bulunamadı. Filtreleri değiştirmeyi deneyin.</p>
        </div>
      );
    }

    const { chartType, options } = config;

    switch (chartType) {
      case 'bar':
        return (
          <BarChartWidget
            data={data}
            layout="vertical"
            colorScheme={options.colorScheme}
            showLegend={options.showLegend}
            showDataLabels={options.showDataLabels}
            measureLabel={measureLabel}
          />
        );
      case 'horizontal_bar':
        return (
          <BarChartWidget
            data={data}
            layout="horizontal"
            colorScheme={options.colorScheme}
            showLegend={options.showLegend}
            showDataLabels={options.showDataLabels}
            measureLabel={measureLabel}
          />
        );
      case 'pie':
        return (
          <PieChartWidget
            data={data}
            innerRadius={0}
            colorScheme={options.colorScheme}
            showLegend={options.showLegend}
            showDataLabels={options.showDataLabels}
          />
        );
      case 'donut':
        return (
          <PieChartWidget
            data={data}
            innerRadius={0.5}
            colorScheme={options.colorScheme}
            showLegend={options.showLegend}
            showDataLabels={options.showDataLabels}
          />
        );
      case 'line':
        return (
          <LineChartWidget
            data={data}
            colorScheme={options.colorScheme}
            showLegend={options.showLegend}
            showPoints={true}
            enableArea={false}
            measureLabel={measureLabel}
          />
        );
      case 'area':
        return (
          <LineChartWidget
            data={data}
            colorScheme={options.colorScheme}
            showLegend={options.showLegend}
            showPoints={true}
            enableArea={true}
            measureLabel={measureLabel}
          />
        );
      case 'heatmap':
        return (
          <HeatmapWidget
            data={data}
            colorScheme={options.colorScheme}
            showLegend={options.showLegend}
            measureLabel={measureLabel}
          />
        );
      case 'table':
        return (
          <DataTableWidget
            data={data}
            dimensionLabel={dimensionLabel}
            measureLabel={measureLabel}
          />
        );
      default:
        return (
          <BarChartWidget
            data={data}
            colorScheme={options.colorScheme}
            showLegend={options.showLegend}
            showDataLabels={options.showDataLabels}
            measureLabel={measureLabel}
          />
        );
    }
  };

  return (
    <div className="report-preview">
      <div className="report-preview-header">
        <div className="report-preview-info">
          <span className="report-preview-dimension">{dimensionLabel}</span>
          <span className="report-preview-separator">→</span>
          <span className="report-preview-measure">{measureLabel}</span>
        </div>
        {total !== undefined && !loading && data.length > 0 && (
          <div className="report-preview-total">
            Toplam: <strong>{total.toLocaleString('tr-TR')}</strong>
          </div>
        )}
      </div>

      <div className="report-preview-chart">
        {renderChart()}
      </div>

      {/* Grafik altında mini tablo */}
      {!loading && data.length > 0 && config.chartType !== 'table' && (
        <div className="report-preview-summary">
          <div className="report-summary-grid">
            {data.slice(0, 5).map((item, index) => (
              <div key={item.id} className="report-summary-item">
                <span className="report-summary-rank">#{index + 1}</span>
                <span className="report-summary-label">{item.label}</span>
                <span className="report-summary-value">{item.value.toLocaleString('tr-TR')}</span>
              </div>
            ))}
            {data.length > 5 && (
              <div className="report-summary-more">
                +{data.length - 5} daha fazla
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default ReportPreview;

