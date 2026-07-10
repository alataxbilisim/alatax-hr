import React, { useState, useEffect } from 'react';
import { Modal } from '../ui';

interface Period {
  id?: number;
  name: string;
  description?: string;
  start_date: string;
  end_date: string;
  status?: string;
}

interface PeriodFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: Omit<Period, 'id' | 'status'>) => Promise<void>;
  period?: Period | null;
}

const PeriodForm: React.FC<PeriodFormProps> = ({
  isOpen,
  onClose,
  onSubmit,
  period,
}) => {
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({
    name: '',
    description: '',
    start_date: '',
    end_date: '',
  });

  useEffect(() => {
    if (isOpen) {
      if (period) {
        setFormData({
          name: period.name,
          description: period.description || '',
          start_date: period.start_date?.split('T')[0] || '',
          end_date: period.end_date?.split('T')[0] || '',
        });
      } else {
        const today = new Date();
        const threeMonthsLater = new Date(today);
        threeMonthsLater.setMonth(threeMonthsLater.getMonth() + 3);
        
        setFormData({
          name: '',
          description: '',
          start_date: today.toISOString().split('T')[0],
          end_date: threeMonthsLater.toISOString().split('T')[0],
        });
      }
    }
  }, [isOpen, period]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
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
      title={period ? 'Dönemi Düzenle' : 'Yeni Dönem'}
      size="md"
    >
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label className="form-label">Dönem Adı *</label>
          <input
            type="text"
            name="name"
            value={formData.name}
            onChange={handleChange}
            className="form-input"
            placeholder="Örn: 2024 Q1 Değerlendirmesi"
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
            placeholder="Dönem hakkında açıklama"
          />
        </div>

        <div className="form-row">
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Başlangıç Tarihi *</label>
            <input
              type="date"
              name="start_date"
              value={formData.start_date}
              onChange={handleChange}
              className="form-input"
              required
            />
          </div>
          <div className="form-group" style={{ flex: 1 }}>
            <label className="form-label">Bitiş Tarihi *</label>
            <input
              type="date"
              name="end_date"
              value={formData.end_date}
              onChange={handleChange}
              className="form-input"
              required
            />
          </div>
        </div>

        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '1.5rem', paddingTop: '1rem', borderTop: '1px solid var(--border-color)' }}>
          <button type="button" className="btn btn-ghost" onClick={onClose} disabled={loading}>
            İptal
          </button>
          <button type="submit" className="btn btn-primary" disabled={loading}>
            {loading ? 'Kaydediliyor...' : period ? 'Güncelle' : 'Oluştur'}
          </button>
        </div>
      </form>
    </Modal>
  );
};

export default PeriodForm;

