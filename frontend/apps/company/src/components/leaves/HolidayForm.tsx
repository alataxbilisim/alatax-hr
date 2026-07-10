import React, { useState, useEffect } from 'react';
import { Modal } from '../ui';
import { leavesApi } from '@shared/services/api';
import toast from 'react-hot-toast';

interface Holiday {
  id?: number;
  name: string;
  date: string;
  end_date?: string;
  type: 'national' | 'company' | 'regional';
  is_recurring: boolean;
  is_half_day: boolean;
  description?: string;
}

interface HolidayFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  holiday?: Holiday;
}

const HolidayForm: React.FC<HolidayFormProps> = ({
  isOpen,
  onClose,
  onSuccess,
  holiday,
}) => {
  const isEditing = !!holiday?.id;
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState<Holiday>({
    name: '',
    date: '',
    end_date: '',
    type: 'company',
    is_recurring: false,
    is_half_day: false,
    description: '',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (isOpen) {
      if (holiday) {
        setFormData({
          name: holiday.name || '',
          date: holiday.date || '',
          end_date: holiday.end_date || '',
          type: holiday.type || 'company',
          is_recurring: holiday.is_recurring || false,
          is_half_day: holiday.is_half_day || false,
          description: holiday.description || '',
        });
      } else {
        setFormData({
          name: '',
          date: '',
          end_date: '',
          type: 'company',
          is_recurring: false,
          is_half_day: false,
          description: '',
        });
      }
      setErrors({});
    }
  }, [isOpen, holiday]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const { name, value, type } = e.target;
    const checked = (e.target as HTMLInputElement).checked;

    setFormData((prev) => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value,
    }));

    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }));
    }
  };

  const validate = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.name.trim()) {
      newErrors.name = 'Tatil adı gerekli';
    }

    if (!formData.date) {
      newErrors.date = 'Tarih gerekli';
    }

    if (formData.end_date && formData.date && new Date(formData.end_date) < new Date(formData.date)) {
      newErrors.end_date = 'Bitiş tarihi başlangıçtan önce olamaz';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validate()) return;

    setLoading(true);
    try {
      const payload = {
        ...formData,
        end_date: formData.end_date || null,
      };

      if (isEditing && holiday?.id) {
        await leavesApi.holidays.update(holiday.id, payload);
        toast.success('Tatil güncellendi');
      } else {
        await leavesApi.holidays.create(payload);
        toast.success('Tatil oluşturuldu');
      }

      onSuccess();
      onClose();
    } catch (error: unknown) {
      const err = error as { response?: { data?: { errors?: Record<string, string[]> } } };
      if (err.response?.data?.errors) {
        const backendErrors: Record<string, string> = {};
        Object.entries(err.response.data.errors).forEach(([key, msgs]) => {
          backendErrors[key] = msgs[0];
        });
        setErrors(backendErrors);
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? 'Tatil Düzenle' : 'Yeni Tatil'}
      size="md"
      footer={
        <>
          <button className="btn btn-secondary" onClick={onClose} disabled={loading}>
            İptal
          </button>
          <button className="btn btn-primary" onClick={handleSubmit} disabled={loading}>
            {loading ? 'Kaydediliyor...' : isEditing ? 'Güncelle' : 'Oluştur'}
          </button>
        </>
      }
    >
      <form onSubmit={handleSubmit}>
        {/* Name */}
        <div className="form-group">
          <label className="form-label">Tatil Adı *</label>
          <input
            type="text"
            name="name"
            className={`form-control ${errors.name ? 'is-invalid' : ''}`}
            value={formData.name}
            onChange={handleChange}
            placeholder="Örn: Kurban Bayramı"
          />
          {errors.name && <div className="form-error">{errors.name}</div>}
        </div>

        {/* Dates */}
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
          <div className="form-group">
            <label className="form-label">Başlangıç Tarihi *</label>
            <input
              type="date"
              name="date"
              className={`form-control ${errors.date ? 'is-invalid' : ''}`}
              value={formData.date}
              onChange={handleChange}
            />
            {errors.date && <div className="form-error">{errors.date}</div>}
          </div>

          <div className="form-group">
            <label className="form-label">Bitiş Tarihi</label>
            <input
              type="date"
              name="end_date"
              className={`form-control ${errors.end_date ? 'is-invalid' : ''}`}
              value={formData.end_date}
              onChange={handleChange}
            />
            {errors.end_date && <div className="form-error">{errors.end_date}</div>}
            <small className="text-muted">Tek günlük tatiller için boş bırakın</small>
          </div>
        </div>

        {/* Type */}
        <div className="form-group">
          <label className="form-label">Tatil Tipi *</label>
          <select
            name="type"
            className="form-select"
            value={formData.type}
            onChange={handleChange}
          >
            <option value="company">Şirket Tatili</option>
            <option value="regional">Bölgesel Tatil</option>
          </select>
        </div>

        {/* Options */}
        <div className="form-group">
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
            <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
              <input
                type="checkbox"
                name="is_recurring"
                checked={formData.is_recurring}
                onChange={handleChange}
                style={{ width: 16, height: 16, accentColor: 'var(--primary)' }}
              />
              <span style={{ fontSize: '0.8125rem', color: 'var(--text-primary)' }}>
                Her yıl tekrarla
              </span>
            </label>

            <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
              <input
                type="checkbox"
                name="is_half_day"
                checked={formData.is_half_day}
                onChange={handleChange}
                style={{ width: 16, height: 16, accentColor: 'var(--primary)' }}
              />
              <span style={{ fontSize: '0.8125rem', color: 'var(--text-primary)' }}>
                Yarım gün tatil
              </span>
            </label>
          </div>
        </div>

        {/* Description */}
        <div className="form-group">
          <label className="form-label">Açıklama</label>
          <textarea
            name="description"
            className="form-control"
            value={formData.description}
            onChange={handleChange}
            rows={2}
            placeholder="Tatil hakkında ek bilgi (isteğe bağlı)"
          />
        </div>
      </form>
    </Modal>
  );
};

export default HolidayForm;

