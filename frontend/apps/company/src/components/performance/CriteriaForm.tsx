import React, { useState, useEffect } from 'react';
import { Modal } from '../ui';

interface CriteriaFormValues {
  id?: number;
  name: string;
  description?: string;
  weight: number;
  max_score: number;
  is_active?: boolean;
}

interface CriteriaFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: Omit<CriteriaFormValues, 'id'>) => Promise<void>;
  criteria?: {
    id?: number;
    name?: string;
    description?: string;
    weight?: number;
    max_score?: number;
    is_active?: boolean;
  } | null;
}

const CriteriaForm: React.FC<CriteriaFormProps> = ({
  isOpen,
  onClose,
  onSubmit,
  criteria,
}) => {
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({
    name: '',
    description: '',
    weight: 20,
    max_score: 5,
    is_active: true,
  });

  useEffect(() => {
    if (isOpen) {
      if (criteria) {
        setFormData({
          name: criteria.name || '',
          description: criteria.description || '',
          weight: criteria.weight ?? 20,
          max_score: criteria.max_score ?? 5,
          is_active: criteria.is_active ?? true,
        });
      } else {
        setFormData({
          name: '',
          description: '',
          weight: 20,
          max_score: 5,
          is_active: true,
        });
      }
    }
  }, [isOpen, criteria]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
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
      title={criteria ? 'Kriteri Düzenle' : 'Yeni Kriter'}
      size="md"
    >
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label className="form-label">Kriter Adı *</label>
          <input
            type="text"
            name="name"
            value={formData.name}
            onChange={handleChange}
            className="form-input"
            placeholder="Örn: İş Kalitesi"
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
            placeholder="Kriterin değerlendirme açıklaması"
          />
        </div>

        <div className="form-row">
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Ağırlık (%) *</label>
            <input
              type="number"
              name="weight"
              value={formData.weight}
              onChange={handleChange}
              className="form-input"
              min={1}
              max={100}
              required
            />
            <small style={{ color: 'var(--text-tertiary)' }}>Toplam ağırlık %100 olmalı</small>
          </div>
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Maksimum Puan *</label>
            <input
              type="number"
              name="max_score"
              value={formData.max_score}
              onChange={handleChange}
              className="form-input"
              min={1}
              max={10}
              required
            />
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
            {loading ? 'Kaydediliyor...' : criteria ? 'Güncelle' : 'Oluştur'}
          </button>
        </div>
      </form>
    </Modal>
  );
};

export default CriteriaForm;

