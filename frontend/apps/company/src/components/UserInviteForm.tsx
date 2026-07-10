import React, { useState, useEffect } from 'react';
import { Modal } from './ui';
import { usersApi, rolesApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsEnvelope, BsPerson } from 'react-icons/bs';

interface Role {
  id: number;
  name: string;
}

interface UserInviteFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

const UserInviteForm: React.FC<UserInviteFormProps> = ({
  isOpen,
  onClose,
  onSuccess,
}) => {
  const [loading, setLoading] = useState(false);
  const [roles, setRoles] = useState<Role[]>([]);
  const [formData, setFormData] = useState({
    email: '',
    name: '',
    roles: [] as number[],
  });
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (isOpen) {
      loadRoles();
      setFormData({ email: '', name: '', roles: [] });
      setErrors({});
    }
  }, [isOpen]);

  const loadRoles = async () => {
    try {
      const response = await rolesApi.list();
      const data = response.data.data;
      setRoles(data.data || data || []);
    } catch {
      toast.error('Roller yüklenemedi');
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }));
    }
  };

  const handleRoleChange = (roleId: number) => {
    setFormData((prev) => ({
      ...prev,
      roles: prev.roles.includes(roleId)
        ? prev.roles.filter((id) => id !== roleId)
        : [...prev.roles, roleId],
    }));
  };

  const validate = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.name.trim()) {
      newErrors.name = 'Ad soyad gerekli';
    }

    if (!formData.email.trim()) {
      newErrors.email = 'E-posta gerekli';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      newErrors.email = 'Geçerli e-posta girin';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validate()) return;

    setLoading(true);
    try {
      await usersApi.invite({
        email: formData.email,
        name: formData.name,
        roles: formData.roles,
      });
      toast.success('Davet e-postası gönderildi');
      onSuccess();
      onClose();
    } catch (error: unknown) {
      const err = error as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } };
      if (err.response?.data?.errors) {
        const backendErrors: Record<string, string> = {};
        Object.entries(err.response.data.errors).forEach(([key, msgs]) => {
          backendErrors[key] = msgs[0];
        });
        setErrors(backendErrors);
      } else {
        toast.error(err.response?.data?.message || 'Davet gönderilemedi');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Kullanıcı Davet Et"
      size="md"
      footer={
        <>
          <button className="btn btn-secondary" onClick={onClose} disabled={loading}>
            İptal
          </button>
          <button className="btn btn-primary" onClick={handleSubmit} disabled={loading}>
            {loading ? 'Gönderiliyor...' : 'Davet Gönder'}
          </button>
        </>
      }
    >
      <form onSubmit={handleSubmit}>
        {/* Name */}
        <div className="form-group">
          <label className="form-label">Ad Soyad *</label>
          <div style={{ position: 'relative' }}>
            <BsPerson
              style={{
                position: 'absolute',
                left: '12px',
                top: '50%',
                transform: 'translateY(-50%)',
                color: 'var(--text-tertiary)',
              }}
            />
            <input
              type="text"
              name="name"
              className={`form-control ${errors.name ? 'is-invalid' : ''}`}
              value={formData.name}
              onChange={handleChange}
              placeholder="Örn: Ahmet Yılmaz"
              style={{ paddingLeft: '40px' }}
            />
          </div>
          {errors.name && <div className="form-error">{errors.name}</div>}
        </div>

        {/* Email */}
        <div className="form-group">
          <label className="form-label">E-posta *</label>
          <div style={{ position: 'relative' }}>
            <BsEnvelope
              style={{
                position: 'absolute',
                left: '12px',
                top: '50%',
                transform: 'translateY(-50%)',
                color: 'var(--text-tertiary)',
              }}
            />
            <input
              type="email"
              name="email"
              className={`form-control ${errors.email ? 'is-invalid' : ''}`}
              value={formData.email}
              onChange={handleChange}
              placeholder="ornek@sirket.com"
              style={{ paddingLeft: '40px' }}
            />
          </div>
          {errors.email && <div className="form-error">{errors.email}</div>}
        </div>

        {/* Roles */}
        <div className="form-group">
          <label className="form-label">Roller (Opsiyonel)</label>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem', maxHeight: '200px', overflowY: 'auto' }}>
            {roles.length === 0 ? (
              <p style={{ color: 'var(--text-tertiary)', fontSize: '0.875rem' }}>Rol bulunamadı</p>
            ) : (
              roles.map((role) => (
                <label key={role.id} style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
                  <input
                    type="checkbox"
                    checked={formData.roles.includes(role.id)}
                    onChange={() => handleRoleChange(role.id)}
                  />
                  <span>{role.name}</span>
                </label>
              ))
            )}
          </div>
        </div>

        <div className="alert alert-info" style={{ marginTop: '1rem', fontSize: '0.875rem' }}>
          <strong>Not:</strong> Davet edilen kullanıcıya e-posta gönderilecek. Kullanıcı e-postadaki linke tıklayarak hesabını oluşturabilir.
        </div>
      </form>
    </Modal>
  );
};

export default UserInviteForm;

