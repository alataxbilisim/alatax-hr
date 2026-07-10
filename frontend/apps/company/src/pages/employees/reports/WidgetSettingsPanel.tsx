import React, { useState } from 'react';
import { FiX, FiSave, FiTag, FiMessageSquare } from 'react-icons/fi';
import type { DashboardWidget, ChartType, ReportMetadata } from './types';

interface WidgetSettingsPanelProps {
  widget: DashboardWidget | null;
  isOpen: boolean;
  onClose: () => void;
  onSave: (widget: DashboardWidget) => void;
  metadata: ReportMetadata | null;
}

const CHART_TYPES: { type: ChartType; label: string }[] = [
  { type: 'bar', label: 'Çubuk Grafik' },
  { type: 'pie', label: 'Pasta Grafik' },
  { type: 'line', label: 'Çizgi Grafik' },
  { type: 'heatmap', label: 'Isı Haritası' },
];

const KPI_TYPES = [
  { value: 'count', label: 'Toplam Sayı' },
  { value: 'sum', label: 'Toplam Değer' },
  { value: 'avg', label: 'Ortalama' },
  { value: 'min', label: 'Minimum' },
  { value: 'max', label: 'Maksimum' },
];

const WidgetSettingsPanel: React.FC<WidgetSettingsPanelProps> = ({
  widget,
  isOpen,
  onClose,
  onSave,
  metadata,
}) => {
  // Parent key={widget?.id ?? 'closed'} ile remount — effect ile prop sync yok
  const [title, setTitle] = useState(widget?.title ?? '');
  const [notes, setNotes] = useState(widget?.notes ?? '');
  const [labels, setLabels] = useState<string[]>(widget?.labels ?? []);
  const [labelInput, setLabelInput] = useState('');
  const [config, setConfig] = useState<DashboardWidget['config']>(
    widget?.config ? { ...widget.config } : {}
  );

  if (!isOpen || !widget) return null;

  const handleAddLabel = () => {
    if (labelInput.trim() && !labels.includes(labelInput.trim())) {
      setLabels([...labels, labelInput.trim()]);
      setLabelInput('');
    }
  };

  const handleRemoveLabel = (label: string) => {
    setLabels(labels.filter((l) => l !== label));
  };

  const handleSave = () => {
    onSave({
      ...widget,
      title,
      notes: notes || undefined,
      labels: labels.length > 0 ? labels : undefined,
      config,
    });
    onClose();
  };

  const updateConfig = (key: string, value: unknown) => {
    setConfig((prev) => ({ ...prev, [key]: value }));
  };

  const renderConfigFields = () => {
    switch (widget.type) {
      case 'text':
        return (
          <div className="form-group">
            <label>İçerik</label>
            <textarea
              value={config.content || ''}
              onChange={(e) => updateConfig('content', e.target.value)}
              placeholder="Metin içeriği..."
              rows={5}
            />
          </div>
        );

      case 'kpi':
        return (
          <>
            <div className="form-group">
              <label>Metrik</label>
              <select
                value={config.measure || 'count'}
                onChange={(e) => updateConfig('measure', e.target.value)}
              >
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
                value={config.kpiType || 'count'}
                onChange={(e) => updateConfig('kpiType', e.target.value)}
              >
                {KPI_TYPES.map((k) => (
                  <option key={k.value} value={k.value}>
                    {k.label}
                  </option>
                ))}
              </select>
            </div>
          </>
        );

      case 'chart':
        return (
          <>
            <div className="form-group">
              <label>Grafik Türü</label>
              <select
                value={config.chartType || 'bar'}
                onChange={(e) => updateConfig('chartType', e.target.value)}
              >
                {CHART_TYPES.map((ct) => (
                  <option key={ct.type} value={ct.type}>
                    {ct.label}
                  </option>
                ))}
              </select>
            </div>
            <div className="form-group">
              <label>Boyut</label>
              <select
                value={config.dimension || 'department'}
                onChange={(e) => updateConfig('dimension', e.target.value)}
              >
                {metadata?.dimensions.map((d) => (
                  <option key={d.value} value={d.value}>
                    {d.label}
                  </option>
                ))}
              </select>
            </div>
            <div className="form-group">
              <label>Metrik</label>
              <select
                value={config.measure || 'count'}
                onChange={(e) => updateConfig('measure', e.target.value)}
              >
                {metadata?.measures.map((m) => (
                  <option key={m.value} value={m.value}>
                    {m.label}
                  </option>
                ))}
              </select>
            </div>
          </>
        );

      case 'table':
      case 'treemap':
        return (
          <>
            <div className="form-group">
              <label>Boyut</label>
              <select
                value={config.dimension || 'department'}
                onChange={(e) => updateConfig('dimension', e.target.value)}
              >
                {metadata?.dimensions.map((d) => (
                  <option key={d.value} value={d.value}>
                    {d.label}
                  </option>
                ))}
              </select>
            </div>
            <div className="form-group">
              <label>Metrik</label>
              <select
                value={config.measure || 'count'}
                onChange={(e) => updateConfig('measure', e.target.value)}
              >
                {metadata?.measures.map((m) => (
                  <option key={m.value} value={m.value}>
                    {m.label}
                  </option>
                ))}
              </select>
            </div>
          </>
        );

      default:
        return null;
    }
  };

  return (
    <div className="settings-panel-overlay" onClick={onClose}>
      <div className="settings-panel" onClick={(e) => e.stopPropagation()}>
        <div className="settings-panel-header">
          <h3>Widget Ayarları</h3>
          <button className="panel-close" onClick={onClose}>
            <FiX />
          </button>
        </div>

        <div className="settings-panel-body">
          {/* Başlık */}
          <div className="form-group">
            <label>Başlık</label>
            <input
              type="text"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              placeholder="Widget başlığı"
            />
          </div>

          {/* Widget Config */}
          <div className="config-section">
            <h4>Yapılandırma</h4>
            {renderConfigFields()}
          </div>

          {/* Etiketler */}
          <div className="form-group">
            <label>
              <FiTag /> Etiketler
            </label>
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

          {/* Notlar */}
          <div className="form-group">
            <label>
              <FiMessageSquare /> Notlar
            </label>
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="Widget hakkında not ekleyin..."
              rows={3}
            />
          </div>
        </div>

        <div className="settings-panel-footer">
          <button className="btn btn-secondary" onClick={onClose}>
            İptal
          </button>
          <button className="btn btn-primary" onClick={handleSave}>
            <FiSave /> Kaydet
          </button>
        </div>
      </div>
    </div>
  );
};

export default WidgetSettingsPanel;

