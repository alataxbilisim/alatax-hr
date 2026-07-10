import React from 'react';
import { FiTrendingUp, FiTrendingDown, FiUsers, FiDollarSign } from 'react-icons/fi';
import type { KPIData, WidgetConfig } from '../types';

interface KPICardWidgetProps {
  data: KPIData;
  config?: WidgetConfig;
}

const KPICardWidget: React.FC<KPICardWidgetProps> = ({ data, config }) => {
  const kpiType = config?.kpiType || 'count';
  const measure = config?.measure || 'count';

  // KPI tipine göre gösterilecek değer
  const getValue = (): number | string => {
    switch (kpiType) {
      case 'sum':
        return data.sum ?? data.total;
      case 'avg':
        return data.average ?? 0;
      case 'min':
        return data.min ?? 0;
      case 'max':
        return data.max ?? 0;
      case 'count':
      default:
        return data.total;
    }
  };

  // Değeri formatla
  const formatValue = (val: number | string): string => {
    const numVal = typeof val === 'string' ? parseFloat(val) : val;
    
    if (measure === 'salary') {
      return new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency: 'TRY',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(numVal);
    }
    
    if (numVal >= 1000000) {
      return (numVal / 1000000).toFixed(1) + 'M';
    }
    if (numVal >= 1000) {
      return (numVal / 1000).toFixed(1) + 'K';
    }
    return numVal.toLocaleString('tr-TR');
  };

  // KPI tipine göre etiket
  const getLabel = (): string => {
    const measureLabels: Record<string, string> = {
      count: 'Personel Sayısı',
      salary: 'Maaş',
      age: 'Yaş',
      tenure: 'Kıdem (Yıl)',
    };

    const kpiLabels: Record<string, string> = {
      count: 'Toplam',
      sum: 'Toplam',
      avg: 'Ortalama',
      min: 'Minimum',
      max: 'Maksimum',
    };

    const measureLabel = measureLabels[measure] || measure;
    const kpiLabel = kpiLabels[kpiType] || '';

    return `${kpiLabel} ${measureLabel}`;
  };

  // İkon seç
  const getIcon = () => {
    if (measure === 'salary') return <FiDollarSign />;
    return <FiUsers />;
  };

  const value = getValue();
  const formattedValue = formatValue(value);

  return (
    <div className="kpi-card-widget">
      <div className="kpi-icon">{getIcon()}</div>
      <div className="kpi-content">
        <div className="kpi-value">{formattedValue}</div>
        <div className="kpi-label">{data.label || getLabel()}</div>
      </div>
      {data.average !== undefined && kpiType === 'count' && (
        <div className="kpi-secondary">
          <div className="kpi-mini-stat">
            <span className="stat-value">{formatValue(data.average)}</span>
            <span className="stat-label">Ort.</span>
          </div>
          {data.min !== undefined && (
            <div className="kpi-mini-stat">
              <FiTrendingDown className="stat-icon down" />
              <span className="stat-value">{formatValue(data.min)}</span>
            </div>
          )}
          {data.max !== undefined && (
            <div className="kpi-mini-stat">
              <FiTrendingUp className="stat-icon up" />
              <span className="stat-value">{formatValue(data.max)}</span>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default KPICardWidget;

