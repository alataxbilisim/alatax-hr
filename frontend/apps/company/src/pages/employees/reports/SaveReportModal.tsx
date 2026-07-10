import React, { useState } from 'react';
import Modal from '../../../components/ui/Modal';
import { BsSave, BsShare } from 'react-icons/bs';
import type { ReportConfig } from './ReportBuilder';

interface SaveReportModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: (data: { name: string; description: string; is_shared: boolean }) => void;
  config: ReportConfig;
  loading?: boolean;
  editMode?: boolean;
  initialData?: {
    name: string;
    description: string;
    is_shared: boolean;
  };
}

const SaveReportModal: React.FC<SaveReportModalProps> = ({
  isOpen,
  onClose,
  onSave,
  config,
  loading = false,
  editMode = false,
  initialData,
}) => {
  const [name, setName] = useState(initialData?.name || '');
  const [description, setDescription] = useState(initialData?.description || '');
  const [isShared, setIsShared] = useState(initialData?.is_shared || false);
  const [errors, setErrors] = useState<{ name?: string }>({});

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    const newErrors: { name?: string } = {};
    if (!name.trim()) {
      newErrors.name = 'Rapor adı gereklidir';
    } else if (name.length > 255) {
      newErrors.name = 'Rapor adı en fazla 255 karakter olabilir';
    }

    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      return;
    }

    onSave({
      name: name.trim(),
      description: description.trim(),
      is_shared: isShared,
    });
  };

  const getConfigSummary = () => {
    const chartLabels: Record<string, string> = {
      bar: 'Çubuk Grafik',
      horizontal_bar: 'Yatay Çubuk',
      pie: 'Pasta Grafik',
      donut: 'Halka Grafik',
      line: 'Çizgi Grafik',
      area: 'Alan Grafik',
      table: 'Tablo',
    };

    return chartLabels[config.chartType] || config.chartType;
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={editMode ? 'Raporu Düzenle' : 'Raporu Kaydet'}
      size="md"
      footer={
        <div className="modal-footer-actions">
          <button className="btn btn-secondary" onClick={onClose} disabled={loading}>
            İptal
          </button>
          <button className="btn btn-primary" onClick={handleSubmit} disabled={loading}>
            {loading ? (
              <>
                <span className="spinner-border spinner-border-sm" />
                Kaydediliyor...
              </>
            ) : (
              <>
                <BsSave /> {editMode ? 'Güncelle' : 'Kaydet'}
              </>
            )}
          </button>
        </div>
      }
    >
      <form onSubmit={handleSubmit} className="save-report-form">
        <div className="form-group">
          <label className="form-label">Rapor Adı *</label>
          <input
            type="text"
            className={`form-control ${errors.name ? 'is-invalid' : ''}`}
            value={name}
            onChange={(e) => {
              setName(e.target.value);
              if (errors.name) setErrors({});
            }}
            placeholder="Örn: Departman Bazlı Personel Dağılımı"
            autoFocus
          />
          {errors.name && <div className="invalid-feedback">{errors.name}</div>}
        </div>

        <div className="form-group">
          <label className="form-label">Açıklama</label>
          <textarea
            className="form-control"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="Bu rapor hakkında kısa bir açıklama..."
            rows={3}
          />
        </div>

        <div className="form-group">
          <label className="form-check">
            <input
              type="checkbox"
              className="form-check-input"
              checked={isShared}
              onChange={(e) => setIsShared(e.target.checked)}
            />
            <span className="form-check-label">
              <BsShare /> Diğer kullanıcılarla paylaş
            </span>
          </label>
          <small className="form-text text-muted">
            Paylaşılan raporlar tüm firma kullanıcıları tarafından görüntülenebilir.
          </small>
        </div>

        <div className="save-report-summary">
          <h6>Rapor Konfigürasyonu</h6>
          <div className="save-report-config">
            <span className="config-item">
              <strong>Grafik:</strong> {getConfigSummary()}
            </span>
            <span className="config-item">
              <strong>Boyut:</strong> {config.dimension}
            </span>
            <span className="config-item">
              <strong>Metrik:</strong> {config.measure}
            </span>
          </div>
        </div>
      </form>
    </Modal>
  );
};

export default SaveReportModal;

