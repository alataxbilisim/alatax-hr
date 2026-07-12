import React, { useState, useEffect } from 'react';
import { Modal } from '../ui';
import { performanceApi, usersApi } from '@shared/services/api';
import { Select } from '@shared/components';
import toast from 'react-hot-toast';

interface Period {
  id: number;
  name: string;
  status?: string;
}

interface User {
  id: number;
  name: string;
  email: string;
}

interface ReviewFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

const ReviewForm: React.FC<ReviewFormProps> = ({
  isOpen,
  onClose,
  onSuccess,
}) => {
  const [loading, setLoading] = useState(false);
  const [periods, setPeriods] = useState<Period[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [formData, setFormData] = useState({
    period_id: '',
    employee_id: '',
    reviewer_id: '',
  });

  useEffect(() => {
    if (isOpen) {
      void loadData();
      setFormData({
        period_id: '',
        employee_id: '',
        reviewer_id: '',
      });
    }
  }, [isOpen]);

  const loadData = async () => {
    try {
      const [periodsRes, usersRes] = await Promise.all([
        performanceApi.periods.list({ per_page: 100 }),
        usersApi.list({ per_page: 100 }),
      ]);
      const periodsData: Period[] = periodsRes.data.data?.data || periodsRes.data.data || [];
      setPeriods(periodsData.filter((p) => p.status === 'active'));
      setUsers(usersRes.data.data?.data || usersRes.data.data || []);
    } catch {
      toast.error('Veriler yüklenemedi');
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      await performanceApi.reviews.create({
        period_id: Number(formData.period_id),
        employee_id: Number(formData.employee_id),
        reviewer_id: Number(formData.reviewer_id),
      });
      toast.success('Değerlendirme oluşturuldu');
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
      title="Yeni Değerlendirme"
      size="md"
    >
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label className="form-label">Dönem *</label>
          <Select
            value={formData.period_id}
            onChange={(v) => setFormData((prev) => ({ ...prev, period_id: v }))}
            options={periods.map((period) => ({
              value: String(period.id),
              label: period.name,
            }))}
            placeholder="Dönem seçin"
            aria-label="Dönem"
          />
          {periods.length === 0 && (
            <small style={{ color: 'var(--warning)' }}>Aktif dönem bulunmuyor. Önce dönem oluşturun ve aktifleştirin.</small>
          )}
        </div>

        <div className="form-group">
          <label className="form-label">Değerlendirilecek Çalışan *</label>
          <Select
            value={formData.employee_id}
            onChange={(v) => setFormData((prev) => ({ ...prev, employee_id: v }))}
            options={users.map((user) => ({
              value: String(user.id),
              label: `${user.name} (${user.email})`,
            }))}
            placeholder="Çalışan seçin"
            aria-label="Çalışan"
          />
        </div>

        <div className="form-group">
          <label className="form-label">Değerlendiren Kişi *</label>
          <Select
            value={formData.reviewer_id}
            onChange={(v) => setFormData((prev) => ({ ...prev, reviewer_id: v }))}
            options={users.map((user) => ({
              value: String(user.id),
              label: user.name,
            }))}
            placeholder="Değerlendirici seçin"
            aria-label="Değerlendiren"
          />
        </div>

        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '1.5rem', paddingTop: '1rem', borderTop: '1px solid var(--border-color)' }}>
          <button type="button" className="btn btn-ghost" onClick={onClose} disabled={loading}>
            İptal
          </button>
          <button
            type="submit"
            className="btn btn-primary"
            disabled={loading || periods.length === 0 || !formData.period_id || !formData.employee_id || !formData.reviewer_id}
          >
            {loading ? 'Oluşturuluyor...' : 'Değerlendirme Oluştur'}
          </button>
        </div>
      </form>
    </Modal>
  );
};

export default ReviewForm;
