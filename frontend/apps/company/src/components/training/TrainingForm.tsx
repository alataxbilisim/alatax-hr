import React, { useState, useEffect } from 'react';
import { Modal } from '../ui';

interface Training {
  id?: number;
  title: string;
  description?: string;
  category?: string;
  type: 'online' | 'classroom' | 'hybrid';
  duration_hours?: number;
  is_mandatory: boolean;
  is_active: boolean;
}

interface TrainingFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: Omit<Training, 'id'>) => Promise<void>;
  training?: Training | null;
  categories: string[];
}

const typeLabels: Record<string, string> = {
  online: 'Online',
  classroom: 'Sınıf İçi',
  hybrid: 'Hibrit',
};

const TrainingForm: React.FC<TrainingFormProps> = ({
  isOpen,
  onClose,
  onSubmit,
  training,
  categories,
}) => {
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({
    title: '',
    description: '',
    category: '',
    type: 'classroom' as 'online' | 'classroom' | 'hybrid',
    duration_hours: 1,
    is_mandatory: false,
    is_active: true,
  });

  useEffect(() => {
    if (isOpen) {
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
    }
  }, [isOpen, training]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value, type } = e.target;
    const checked = (e.target as HTMLInputElement).checked;
    setFormData(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : type === 'number' ? Number(value) : value,
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      await onSubmit(formData);
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
            <input
              type="text"
              name="category"
              value={formData.category}
              onChange={handleChange}
              className="form-input"
              placeholder="Kategori girin"
              list="category-list"
            />
            <datalist id="category-list">
              {categories.map(cat => (
                <option key={cat} value={cat} />
              ))}
            </datalist>
          </div>
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Eğitim Türü *</label>
            <select
              name="type"
              value={formData.type}
              onChange={handleChange}
              className="form-input"
              required
            >
              {Object.entries(typeLabels).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
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

