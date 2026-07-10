import React, { useEffect, useState } from 'react';
import { FiEdit2, FiTrash2, FiMove, FiTag, FiMessageSquare } from 'react-icons/fi';
import type { DashboardWidget, ChartDataItem, KPIData } from './types';
import { employeesApi } from '@alatax/shared';
import BarChartWidget from './charts/BarChartWidget';
import PieChartWidget from './charts/PieChartWidget';
import LineChartWidget from './charts/LineChartWidget';
import HeatmapWidget from './charts/HeatmapWidget';
import DataTableWidget from './charts/DataTableWidget';
import KPICardWidget from './widgets/KPICardWidget';
import TreemapWidget from './widgets/TreemapWidget';
import TextNoteWidget from './widgets/TextNoteWidget';

interface WidgetFactoryProps {
  widget: DashboardWidget;
  isEditing?: boolean;
  onEdit?: () => void;
  onDelete?: () => void;
}

/** Backend aggregated satır: { name, value } */
interface ApiWidgetDataRow {
  name: string;
  value: number;
}

function isKPIData(value: unknown): value is KPIData {
  return typeof value === 'object' && value !== null && !Array.isArray(value) && 'total' in value;
}

function isApiWidgetDataRow(value: unknown): value is ApiWidgetDataRow {
  if (typeof value !== 'object' || value === null) return false;
  if (!('name' in value) || !('value' in value)) return false;
  return typeof value.name === 'string' && typeof value.value === 'number';
}

/** API name → kanonik ChartDataItem (label/id) */
function mapApiRowsToChartData(raw: unknown): ChartDataItem[] {
  if (!Array.isArray(raw)) return [];
  return raw.filter(isApiWidgetDataRow).map((item, index) => ({
    id: item.name || index,
    label: item.name,
    value: item.value,
  }));
}

const WidgetFactory: React.FC<WidgetFactoryProps> = ({
  widget,
  isEditing = false,
  onEdit,
  onDelete,
}) => {
  const [data, setData] = useState<ChartDataItem[] | KPIData | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Text widget'ı için veri çekme gerekmiyor
  const needsData = widget.type !== 'text';

  useEffect(() => {
    if (!needsData) return;

    const fetchData = async () => {
      setLoading(true);
      setError(null);
      try {
        const response = await employeesApi.dashboards.getWidgetData({
          type: widget.type,
          config: widget.config,
        });
        const payload: unknown = response.data.data;

        if (widget.type === 'kpi') {
          if (isKPIData(payload)) {
            setData(payload);
          } else {
            setError('Veri yüklenemedi');
            setData(null);
          }
        } else {
          setData(mapApiRowsToChartData(payload));
        }
      } catch (err) {
        setError('Veri yüklenemedi');
        console.error('Widget data error:', err);
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [widget.type, widget.config, needsData]);

  const renderContent = () => {
    // Text widget
    if (widget.type === 'text') {
      return <TextNoteWidget content={widget.config.content || ''} />;
    }

    // Loading state
    if (loading) {
      return (
        <div className="widget-loading">
          <div className="loading-spinner"></div>
          <span>Yükleniyor...</span>
        </div>
      );
    }

    // Error state
    if (error) {
      return (
        <div className="widget-error">
          <span>⚠️ {error}</span>
        </div>
      );
    }

    // No data
    if (!data) {
      return (
        <div className="widget-no-data">
          <span>Veri bulunamadı</span>
        </div>
      );
    }

    // KPI Widget
    if (widget.type === 'kpi') {
      if (!isKPIData(data)) {
        return (
          <div className="widget-no-data">
            <span>Veri bulunamadı</span>
          </div>
        );
      }
      return <KPICardWidget data={data} config={widget.config} />;
    }

    // Chart / table / treemap — dizi bekler
    if (!Array.isArray(data)) {
      return (
        <div className="widget-no-data">
          <span>Veri bulunamadı</span>
        </div>
      );
    }

    const chartData: ChartDataItem[] = data;

    // Treemap Widget
    if (widget.type === 'treemap') {
      return <TreemapWidget data={chartData} />;
    }

    // Table Widget
    if (widget.type === 'table') {
      return <DataTableWidget data={chartData} />;
    }

    // Chart Widgets
    if (widget.type === 'chart') {
      const chartType = widget.config.chartType || 'bar';

      switch (chartType) {
        case 'pie':
          return <PieChartWidget data={chartData} />;
        case 'line':
          return <LineChartWidget data={chartData} />;
        case 'heatmap':
          return <HeatmapWidget data={chartData} />;
        case 'bar':
        default:
          return <BarChartWidget data={chartData} />;
      }
    }

    return null;
  };

  return (
    <div className={`dashboard-widget ${isEditing ? 'editing' : ''}`}>
      {/* Widget Header */}
      <div className="widget-header">
        {isEditing && (
          <div className="widget-drag-handle">
            <FiMove />
          </div>
        )}
        <div className="widget-title-section">
          <h4 className="widget-title">{widget.title || 'Widget'}</h4>
          {widget.labels && widget.labels.length > 0 && (
            <div className="widget-labels">
              {widget.labels.map((label, idx) => (
                <span key={idx} className="widget-label">
                  <FiTag size={10} /> {label}
                </span>
              ))}
            </div>
          )}
        </div>
        {isEditing && (
          <div className="widget-actions">
            <button className="widget-action-btn" onClick={onEdit} title="Düzenle">
              <FiEdit2 />
            </button>
            <button className="widget-action-btn delete" onClick={onDelete} title="Sil">
              <FiTrash2 />
            </button>
          </div>
        )}
      </div>

      {/* Widget Content */}
      <div className="widget-content">{renderContent()}</div>

      {/* Widget Notes */}
      {widget.notes && (
        <div className="widget-notes">
          <FiMessageSquare size={12} />
          <span>{widget.notes}</span>
        </div>
      )}
    </div>
  );
};

export default WidgetFactory;
