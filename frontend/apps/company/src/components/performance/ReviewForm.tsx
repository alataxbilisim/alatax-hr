import React, { useState, useEffect } from 'react';
import { Modal } from '../ui';
import { performanceApi, usersApi } from '@shared/services/api';
import toast from 'react-hot-toast';

interface Period {
  id: number;
  name: string;
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
      loadData();
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
      const periodsData = periodsRes.data.data?.data || periodsRes.data.data || [];
      setPeriods(periodsData.filter((p: Period & { status?: string }) => p.status === 'active'));
      setUsers(usersRes.data.data?.data || usersRes.data.data || []);
    } catch {
      toast.error('Veriler yüklenemedi');
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
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
          <select
            name="period_id"
            value={formData.period_id}
            onChange={handleChange}
            className="form-input"
            required
          >
            <option value="">Dönem seçin</option>
            {periods.map(period => (
              <option key={period.id} value={period.id}>{period.name}</option>
            ))}
          </select>
          {periods.length === 0 && (
            <small style={{ color: 'var(--warning)' }}>Aktif dönem bulunmuyor. Önce dönem oluşturun ve aktifleştirin.</small>
          )}
        </div>

        <div className="form-group">
          <label className="form-label">Değerlendirilecek Çalışan *</label>
          <select
            name="employee_id"
            value={formData.employee_id}
            onChange={handleChange}
            className="form-input"
            required
          >
            <option value="">Çalışan seçin</option>
            {users.map(user => (
              <option key={user.id} value={user.id}>{user.name} ({user.email})</option>
            ))}
          </select>
        </div>

        <div className="form-group">
          <label className="form-label">Değerlendiren Kişi *</label>
          <select
            name="reviewer_id"
            value={formData.reviewer_id}
            onChange={handleChange}
            className="form-input"
            required
          >
            <option value="">Değerlendirici seçin</option>
            {users.map(user => (
              <option key={user.id} value={user.id}>{user.name}</option>
            ))}
          </select>
        </div>

        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '1.5rem', paddingTop: '1rem', borderTop: '1px solid var(--border-color)' }}>
          <button type="button" className="btn btn-ghost" onClick={onClose} disabled={loading}>
            İptal
          </button>
          <button type="submit" className="btn btn-primary" disabled={loading || periods.length === 0}>
            {loading ? 'Oluşturuluyor...' : 'Değerlendirme Oluştur'}
          </button>
        </div>
      </form>
    </Modal>
  );
};

export default ReviewForm;

