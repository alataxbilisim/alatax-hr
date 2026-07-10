import React, { useState, useEffect } from 'react';
import { Modal } from '../ui';
import { usersApi } from '@shared/services/api';
import toast from 'react-hot-toast';

interface User {
  id: number;
  name: string;
  email: string;
}

interface AssignmentFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: { user_id: number; notes?: string; assigned_date?: string }) => Promise<void>;
  assetName: string;
}

const AssignmentForm: React.FC<AssignmentFormProps> = ({
  isOpen,
  onClose,
  onSubmit,
  assetName,
}) => {
  const [loading, setLoading] = useState(false);
  const [users, setUsers] = useState<User[]>([]);
  const [formData, setFormData] = useState({
    user_id: '',
    notes: '',
    assigned_date: new Date().toISOString().split('T')[0],
  });

  useEffect(() => {
    if (isOpen) {
      loadUsers();
      setFormData({
        user_id: '',
        notes: '',
        assigned_date: new Date().toISOString().split('T')[0],
      });
    }
  }, [isOpen]);

  const loadUsers = async () => {
    try {
      const response = await usersApi.list({ per_page: 100 });
      setUsers(response.data.data?.data || response.data.data || []);
    } catch {
      toast.error('Kullanıcılar yüklenemedi');
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      await onSubmit({
        user_id: Number(formData.user_id),
        notes: formData.notes || undefined,
        assigned_date: formData.assigned_date || undefined,
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
      title="Zimmet Ata"
      size="sm"
    >
      <form onSubmit={handleSubmit}>
        <p style={{ marginBottom: '1rem', color: 'var(--text-secondary)' }}>
          <strong>{assetName}</strong> varlığını bir kullanıcıya zimmetleyin.
        </p>

        <div className="form-group">
          <label className="form-label">Kullanıcı *</label>
          <select
            name="user_id"
            value={formData.user_id}
            onChange={handleChange}
            className="form-input"
            required
          >
            <option value="">Seçin...</option>
            {users.map(u => (
              <option key={u.id} value={u.id}>{u.name} ({u.email})</option>
            ))}
          </select>
        </div>

        <div className="form-group">
          <label className="form-label">Zimmet Tarihi</label>
          <input
            type="date"
            name="assigned_date"
            value={formData.assigned_date}
            onChange={handleChange}
            className="form-input"
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
            placeholder="Zimmet ile ilgili notlar..."
          />
        </div>

        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '1.5rem', paddingTop: '1rem', borderTop: '1px solid var(--border-color)' }}>
          <button type="button" className="btn btn-ghost" onClick={onClose} disabled={loading}>
            İptal
          </button>
          <button type="submit" className="btn btn-primary" disabled={loading}>
            {loading ? 'Atanıyor...' : 'Zimmetle'}
          </button>
        </div>
      </form>
    </Modal>
  );
};

export default AssignmentForm;

