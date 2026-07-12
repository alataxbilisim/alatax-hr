import React, { useState, useEffect } from 'react';
import { Modal } from '../ui';
import { onboardingApi, usersApi } from '@shared/services/api';
import { Select } from '@shared/components';
import toast from 'react-hot-toast';

interface Template {
  id: number;
  name: string;
  estimated_days: number;
}

interface User {
  id: number;
  name: string;
  email: string;
}

interface ProcessFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

const ProcessForm: React.FC<ProcessFormProps> = ({
  isOpen,
  onClose,
  onSuccess,
}) => {
  const [loading, setLoading] = useState(false);
  const [templates, setTemplates] = useState<Template[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [formData, setFormData] = useState({
    user_id: '',
    template_id: '',
    title: '',
    start_date: new Date().toISOString().split('T')[0],
    target_end_date: '',
    assigned_to: '',
    notes: '',
  });

  useEffect(() => {
    if (isOpen) {
      void loadData();
      setFormData({
        user_id: '',
        template_id: '',
        title: '',
        start_date: new Date().toISOString().split('T')[0],
        target_end_date: '',
        assigned_to: '',
        notes: '',
      });
    }
  }, [isOpen]);

  const loadData = async () => {
    try {
      const [templatesRes, usersRes] = await Promise.all([
        onboardingApi.templates.list({ per_page: 100 }),
        usersApi.list({ per_page: 100 }),
      ]);
      setTemplates(templatesRes.data.data?.data || templatesRes.data.data || []);
      setUsers(usersRes.data.data?.data || usersRes.data.data || []);
    } catch {
      toast.error('Veriler yüklenemedi');
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handleTemplateChange = (value: string) => {
    const template = templates.find((t) => t.id === Number(value));
    if (template) {
      const startDate = new Date(formData.start_date);
      startDate.setDate(startDate.getDate() + template.estimated_days);
      setFormData((prev) => ({
        ...prev,
        template_id: value,
        target_end_date: startDate.toISOString().split('T')[0],
        title: prev.title || `${template.name} Süreci`,
      }));
      return;
    }
    setFormData((prev) => ({ ...prev, template_id: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      const data = {
        ...formData,
        user_id: Number(formData.user_id),
        template_id: formData.template_id ? Number(formData.template_id) : null,
        assigned_to: formData.assigned_to ? Number(formData.assigned_to) : null,
      };
      await onboardingApi.processes.create(data);
      toast.success('Onboarding süreci başlatıldı');
      onSuccess();
      onClose();
    } catch {
      // Error handled by interceptor
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Yeni Onboarding Süreci"
      size="md"
    >
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label className="form-label">Çalışan *</label>
          <Select
            value={formData.user_id}
            onChange={(v) => setFormData((prev) => ({ ...prev, user_id: v }))}
            options={users.map((user) => ({
              value: String(user.id),
              label: `${user.name} (${user.email})`,
            }))}
            placeholder="Çalışan seçin"
            aria-label="Çalışan"
          />
        </div>

        <div className="form-group">
          <label className="form-label">Şablon</label>
          <Select
            value={formData.template_id}
            onChange={handleTemplateChange}
            options={templates.map((template) => ({
              value: String(template.id),
              label: `${template.name} (${template.estimated_days} gün)`,
            }))}
            allowEmpty
            emptyLabel="Şablon seçin (opsiyonel)"
            placeholder="Şablon seçin (opsiyonel)"
            aria-label="Şablon"
          />
        </div>

        <div className="form-group">
          <label className="form-label">Süreç Başlığı *</label>
          <input
            type="text"
            name="title"
            value={formData.title}
            onChange={handleChange}
            className="form-input"
            placeholder="Örn: Ali Veli - Yazılım Geliştirici Onboarding"
            required
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
            <label className="form-label">Hedef Bitiş Tarihi</label>
            <input
              type="date"
              name="target_end_date"
              value={formData.target_end_date}
              onChange={handleChange}
              className="form-input"
            />
          </div>
        </div>

        <div className="form-group">
          <label className="form-label">Sorumlu Kişi</label>
          <Select
            value={formData.assigned_to}
            onChange={(v) => setFormData((prev) => ({ ...prev, assigned_to: v }))}
            options={users.map((user) => ({
              value: String(user.id),
              label: user.name,
            }))}
            allowEmpty
            emptyLabel="Sorumlu seçin (opsiyonel)"
            placeholder="Sorumlu seçin (opsiyonel)"
            aria-label="Sorumlu kişi"
          />
        </div>

        <div className="form-group">
          <label className="form-label">Notlar</label>
          <textarea
            name="notes"
            value={formData.notes}
            onChange={handleChange}
            className="form-input"
            rows={2}
            placeholder="Ek notlar..."
          />
        </div>

        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '1.5rem', paddingTop: '1rem', borderTop: '1px solid var(--border-color)' }}>
          <button type="button" className="btn btn-ghost" onClick={onClose} disabled={loading}>
            İptal
          </button>
          <button type="submit" className="btn btn-primary" disabled={loading || !formData.user_id}>
            {loading ? 'Başlatılıyor...' : 'Süreci Başlat'}
          </button>
        </div>
      </form>
    </Modal>
  );
};

export default ProcessForm;
