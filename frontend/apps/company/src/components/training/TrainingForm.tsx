import React, { useState, useEffect } from 'react';
import { Modal } from '../ui';
import { lookupsApi, type LookupItem } from '@shared/services/api';
import { Select } from '@shared/components';
import toast from 'react-hot-toast';

interface Training {
  id?: number;
  title: string;
  description?: string;
  category?: string;
  type: string;
  duration_hours?: number;
  is_mandatory: boolean;
  is_active: boolean;
}

interface TrainingFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: Omit<Training, 'id'>) => Promise<void>;
  training?: Training | null;
}

const TrainingForm: React.FC<TrainingFormProps> = ({
  isOpen,
  onClose,
  onSubmit,
  training,
}) => {
  const [loading, setLoading] = useState(false);
  const [typeOptions, setTypeOptions] = useState<LookupItem[]>([]);
  const [categoryOptions, setCategoryOptions] = useState<LookupItem[]>([]);
  const [formData, setFormData] = useState({
    title: '',
    description: '',
    category: '',
    type: 'classroom',
    duration_hours: 1,
    is_mandatory: false,
    is_active: true,
  });

  useEffect(() => {
    if (!isOpen) return;

    Promise.all([
      lookupsApi.forType('training_type'),
      lookupsApi.forType('training_category'),
    ])
      .then(([typeRes, categoryRes]) => {
        setTypeOptions(typeRes.data.data ?? []);
        setCategoryOptions(categoryRes.data.data ?? []);
      })
      .catch(() => toast.error('Lookup listeleri yüklenemedi'));

    if (training) {
      setFormData({
        title: training.title,
        description: training.description || '',
        category: training.category || '',
        type: training.type,
        duration_hours: training.duration_hours || 1,
        is_mandatory: training.is_mandatory,
        is_active: training.is_active ?? true,
      });
    } else {
      setFormData({
        title: '',
        description: '',
        category: '',
        type: 'classroom',
        duration_hours: 1,
        is_mandatory: false,
        is_active: true,
      });
    }
  }, [isOpen, training]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value, type } = e.target;
    const checked = (e.target as HTMLInputElement).checked;
    setFormData((prev) => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : type === 'number' ? Number(value) : value,
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      await onSubmit({
        ...formData,
        category: formData.category || undefined,
      });
      onClose();
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={training ? 'Eğitimi Düzenle' : 'Yeni Eğitim'}
      size="md"
    >
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label className="form-label">Eğitim Adı *</label>
          <input
            type="text"
            name="title"
            value={formData.title}
            onChange={handleChange}
            className="form-input"
            placeholder="Örn: İş Sağlığı ve Güvenliği"
            required
          />
        </div>

        <div className="form-group">
          <label className="form-label">Açıklama</label>
          <textarea
            name="description"
            value={formData.description}
            onChange={handleChange}
            className="form-input"
            rows={2}
            placeholder="Eğitim hakkında açıklama"
          />
        </div>

        <div className="form-row">
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Kategori</label>
            <Select
              value={formData.category}
              onChange={(v) => setFormData((prev) => ({ ...prev, category: v }))}
              options={categoryOptions.map((opt) => ({
                value: opt.value,
                label: opt.label,
                color: opt.color,
              }))}
              placeholder="Seçiniz..."
              allowEmpty
              emptyLabel="Seçiniz..."
              clearable
              aria-label="Kategori"
            />
          </div>
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Eğitim Türü *</label>
            <Select
              value={formData.type}
              onChange={(v) => setFormData((prev) => ({ ...prev, type: v }))}
              options={typeOptions.map((opt) => ({
                value: opt.value,
                label: opt.label,
                color: opt.color,
              }))}
              placeholder="Seçiniz..."
              aria-label="Eğitim türü"
            />
          </div>
        </div>

        <div className="form-row">
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Süre (Saat)</label>
            <input
              type="number"
              name="duration_hours"
              value={formData.duration_hours}
              onChange={handleChange}
              className="form-input"
              min={1}
            />
          </div>
          <div className="form-group" style={{ flex: 1, display: 'flex', flexDirection: 'column', justifyContent: 'flex-end', paddingBottom: '0.5rem' }}>
            <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
              <input
                type="checkbox"
                name="is_mandatory"
                checked={formData.is_mandatory}
                onChange={handleChange}
              />
              <span>Zorunlu Eğitim</span>
            </label>
          </div>
        </div>

        <div className="form-group">
          <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
            <input
              type="checkbox"
              name="is_active"
              checked={formData.is_active}
              onChange={handleChange}
            />
            <span>Aktif</span>
          </label>
        </div>

        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '1.5rem', paddingTop: '1rem', borderTop: '1px solid var(--border-color)' }}>
          <button type="button" className="btn btn-ghost" onClick={onClose} disabled={loading}>
            İptal
          </button>
          <button type="submit" className="btn btn-primary" disabled={loading}>
            {loading ? 'Kaydediliyor...' : training ? 'Güncelle' : 'Oluştur'}
          </button>
        </div>
      </form>
    </Modal>
  );
};

export default TrainingForm;
