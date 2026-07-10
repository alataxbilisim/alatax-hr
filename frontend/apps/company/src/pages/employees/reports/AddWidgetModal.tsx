import React, { useState } from 'react';
import { FiX, FiBarChart2, FiPieChart, FiTrendingUp, FiGrid, FiFileText, FiHash } from 'react-icons/fi';
import type { DashboardWidget, WidgetType, ChartType, ReportMetadata, WidgetConfig } from './types';

interface AddWidgetModalProps {
  isOpen: boolean;
  onClose: () => void;
  onAdd: (widget: Omit<DashboardWidget, 'id'>) => void;
  metadata: ReportMetadata | null;
}

const WIDGET_TYPES: { type: WidgetType; label: string; icon: React.ReactNode; description: string }[] = [
  { type: 'kpi', label: 'KPI Kartı', icon: <FiHash />, description: 'Toplam, ortalama gibi özet değerler' },
  { type: 'chart', label: 'Grafik', icon: <FiBarChart2 />, description: 'Bar, pasta, çizgi grafikleri' },
  { type: 'table', label: 'Veri Tablosu', icon: <FiGrid />, description: 'Detaylı veri görünümü' },
  { type: 'treemap', label: 'Treemap', icon: <FiPieChart />, description: 'Hiyerarşik alan görünümü' },
  { type: 'text', label: 'Metin/Not', icon: <FiFileText />, description: 'Açıklama veya not ekleyin' },
];

const CHART_TYPES: { type: ChartType; label: string; icon: React.ReactNode }[] = [
  { type: 'bar', label: 'Çubuk Grafik', icon: <FiBarChart2 /> },
  { type: 'pie', label: 'Pasta Grafik', icon: <FiPieChart /> },
  { type: 'line', label: 'Çizgi Grafik', icon: <FiTrendingUp /> },
  { type: 'heatmap', label: 'Isı Haritası', icon: <FiGrid /> },
];

type KpiType = NonNullable<WidgetConfig['kpiType']>;

const KPI_TYPES: { value: KpiType; label: string }[] = [
  { value: 'count', label: 'Toplam Sayı' },
  { value: 'sum', label: 'Toplam Değer' },
  { value: 'avg', label: 'Ortalama' },
  { value: 'min', label: 'Minimum' },
  { value: 'max', label: 'Maksimum' },
];

const AddWidgetModal: React.FC<AddWidgetModalProps> = ({
  isOpen,
  onClose,
  onAdd,
  metadata,
}) => {
  const [step, setStep] = useState(1);
  const [widgetType, setWidgetType] = useState<WidgetType>('chart');
  const [title, setTitle] = useState('');
  const [chartType, setChartType] = useState<ChartType>('bar');
  const [dimension, setDimension] = useState('department');
  const [measure, setMeasure] = useState('count');
  const [kpiType, setKpiType] = useState<KpiType>('count');
  const [textContent, setTextContent] = useState('');
  const [labels, setLabels] = useState<string[]>([]);
  const [labelInput, setLabelInput] = useState('');
  const [notes, setNotes] = useState('');

  if (!isOpen) return null;

  const handleAddLabel = () => {
    if (labelInput.trim() && !labels.includes(labelInput.trim())) {
      setLabels([...labels, labelInput.trim()]);
      setLabelInput('');
    }
  };

  const handleRemoveLabel = (label: string) => {
    setLabels(labels.filter((l) => l !== label));
  };

  const handleSubmit = () => {
    const defaultLayouts: Record<WidgetType, { w: number; h: number; minW: number; minH: number }> = {
      chart: { w: 6, h: 4, minW: 3, minH: 3 },
      kpi: { w: 3, h: 2, minW: 2, minH: 2 },
      table: { w: 6, h: 4, minW: 4, minH: 3 },
      treemap: { w: 6, h: 4, minW: 4, minH: 3 },
      text: { w: 3, h: 2, minW: 2, minH: 1 },
    };

    const widget: Omit<DashboardWidget, 'id'> = {
      type: widgetType,
      title: title || getDefaultTitle(),
      notes: notes || undefined,
      labels: labels.length > 0 ? labels : undefined,
      config: buildConfig(),
      layout: {
        x: 0,
        y: Infinity, // react-grid-layout will place at bottom
        ...defaultLayouts[widgetType],
      },
    };

    onAdd(widget);
    resetForm();
    onClose();
  };

  const getDefaultTitle = (): string => {
    const typeLabels: Record<WidgetType, string> = {
      chart: 'Yeni Grafik',
      kpi: 'KPI Kartı',
      table: 'Veri Tablosu',
      treemap: 'Treemap',
      text: 'Not',
    };
    return typeLabels[widgetType];
  };

  const buildConfig = (): WidgetConfig => {
    switch (widgetType) {
      case 'text':
        return { content: textContent };
      case 'kpi':
        return { measure, kpiType };
      case 'chart':
        return { dimension, measure, chartType };
      case 'table':
      case 'treemap':
        return { dimension, measure };
      default:
        return {};
    }
  };

  const resetForm = () => {
    setStep(1);
    setWidgetType('chart');
    setTitle('');
    setChartType('bar');
    setDimension('department');
    setMeasure('count');
    setKpiType('count');
    setTextContent('');
    setLabels([]);
    setNotes('');
  };

  const renderStep1 = () => (
    <div className="widget-type-grid">
      {WIDGET_TYPES.map((wt) => (
        <button
          key={wt.type}
          className={`widget-type-card ${widgetType === wt.type ? 'selected' : ''}`}
          onClick={() => setWidgetType(wt.type)}
        >
          <div className="widget-type-icon">{wt.icon}</div>
          <div className="widget-type-info">
            <span className="widget-type-label">{wt.label}</span>
            <span className="widget-type-desc">{wt.description}</span>
          </div>
        </button>
      ))}
    </div>
  );

  const renderStep2 = () => {
    switch (widgetType) {
      case 'text':
        return (
          <div className="widget-config-form">
            <div className="form-group">
              <label>Başlık</label>
              <input
                type="text"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                placeholder="Widget başlığı"
              />
            </div>
            <div className="form-group">
              <label>İçerik</label>
              <textarea
                value={textContent}
                onChange={(e) => setTextContent(e.target.value)}
                placeholder="Metin içeriğini yazın..."
                rows={5}
              />
            </div>
          </div>
        );

      case 'kpi':
        return (
          <div className="widget-config-form">
            <div className="form-group">
              <label>Başlık</label>
              <input
                type="text"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                placeholder="KPI başlığı"
              />
            </div>
            <div className="form-group">
              <label>Metrik</label>
              <select value={measure} onChange={(e) => setMeasure(e.target.value)}>
                {metadata?.measures.map((m) => (
                  <option key={m.value} value={m.value}>
                    {m.label}
                  </option>
                ))}
              </select>
            </div>
            <div className="form-group">
              <label>KPI Türü</label>
              <select
                value={kpiType}
                onChange={(e) => {
                  const next = KPI_TYPES.find((k) => k.value === e.target.value);
                  if (next) setKpiType(next.value);
                }}
              >
                {KPI_TYPES.map((k) => (
                  <option key={k.value} value={k.value}>
                    {k.label}
                  </option>
                ))}
              </select>
            </div>
          </div>
        );

      case 'chart':
        return (
          <div className="widget-config-form">
            <div className="form-group">
              <label>Başlık</label>
              <input
                type="text"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                placeholder="Grafik başlığı"
              />
            </div>
            <div className="form-group">
              <label>Grafik Türü</label>
              <div className="chart-type-grid">
                {CHART_TYPES.map((ct) => (
                  <button
                    key={ct.type}
                    className={`chart-type-btn ${chartType === ct.type ? 'selected' : ''}`}
                    onClick={() => setChartType(ct.type)}
                  >
                    {ct.icon}
                    <span>{ct.label}</span>
                  </button>
                ))}
              </div>
            </div>
            <div className="form-group">
              <label>Boyut (X Ekseni)</label>
              <select value={dimension} onChange={(e) => setDimension(e.target.value)}>
                {metadata?.dimensions.map((d) => (
                  <option key={d.value} value={d.value}>
                    {d.label}
                  </option>
                ))}
              </select>
            </div>
            <div className="form-group">
              <label>Metrik (Y Ekseni)</label>
              <select value={measure} onChange={(e) => setMeasure(e.target.value)}>
                {metadata?.measures.map((m) => (
                  <option key={m.value} value={m.value}>
                    {m.label}
                  </option>
                ))}
              </select>
            </div>
          </div>
        );

      case 'table':
      case 'treemap':
        return (
          <div className="widget-config-form">
            <div className="form-group">
              <label>Başlık</label>
              <input
                type="text"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                placeholder="Widget başlığı"
              />
            </div>
            <div className="form-group">
              <label>Boyut</label>
              <select value={dimension} onChange={(e) => setDimension(e.target.value)}>
                {metadata?.dimensions.map((d) => (
                  <option key={d.value} value={d.value}>
                    {d.label}
                  </option>
                ))}
              </select>
            </div>
            <div className="form-group">
              <label>Metrik</label>
              <select value={measure} onChange={(e) => setMeasure(e.target.value)}>
                {metadata?.measures.map((m) => (
                  <option key={m.value} value={m.value}>
                    {m.label}
                  </option>
                ))}
              </select>
            </div>
          </div>
        );

      default:
        return null;
    }
  };

  const renderStep3 = () => (
    <div className="widget-config-form">
      <div className="form-group">
        <label>Etiketler</label>
        <div className="labels-input">
          <input
            type="text"
            value={labelInput}
            onChange={(e) => setLabelInput(e.target.value)}
            placeholder="Etiket ekle..."
            onKeyPress={(e) => e.key === 'Enter' && handleAddLabel()}
          />
          <button type="button" onClick={handleAddLabel}>
            Ekle
          </button>
        </div>
        {labels.length > 0 && (
          <div className="labels-list">
            {labels.map((label) => (
              <span key={label} className="label-tag">
                {label}
                <button type="button" onClick={() => handleRemoveLabel(label)}>
                  ×
                </button>
              </span>
            ))}
          </div>
        )}
      </div>
      <div className="form-group">
        <label>Notlar</label>
        <textarea
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          placeholder="Widget hakkında not ekleyin..."
          rows={3}
        />
      </div>
    </div>
  );

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content add-widget-modal" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h3>Widget Ekle</h3>
          <button className="modal-close" onClick={onClose}>
            <FiX />
          </button>
        </div>

        <div className="modal-steps">
          <div className={`step ${step >= 1 ? 'active' : ''}`}>1. Tür Seç</div>
          <div className={`step ${step >= 2 ? 'active' : ''}`}>2. Yapılandır</div>
          <div className={`step ${step >= 3 ? 'active' : ''}`}>3. Detaylar</div>
        </div>

        <div className="modal-body">
          {step === 1 && renderStep1()}
          {step === 2 && renderStep2()}
          {step === 3 && renderStep3()}
        </div>

        <div className="modal-footer">
          {step > 1 && (
            <button className="btn btn-secondary" onClick={() => setStep(step - 1)}>
              Geri
            </button>
          )}
          {step < 3 ? (
            <button className="btn btn-primary" onClick={() => setStep(step + 1)}>
              İleri
            </button>
          ) : (
            <button className="btn btn-primary" onClick={handleSubmit}>
              Widget Ekle
            </button>
          )}
        </div>
      </div>
    </div>
  );
};

export default AddWidgetModal;

