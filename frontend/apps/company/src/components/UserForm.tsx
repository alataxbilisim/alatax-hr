import React, { useState, useEffect } from 'react';
import { Modal } from './ui';
import { usersApi, rolesApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import AvatarUpload from './ui/AvatarUpload';

interface Role {
  id: number;
  name: string;
}

interface User {
  id?: number;
  name: string;
  email: string;
  phone?: string;
  password?: string;
  password_confirmation?: string;
  roles: number[];
  is_active?: boolean;
  avatar?: string;
}

interface UserFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  user?: {
    id?: number;
    name?: string;
    email?: string;
    phone?: string | null;
    is_active?: boolean;
    avatar?: string | null;
    roles?: Array<{ id: number; name: string }> | number[];
  };
}

const UserForm: React.FC<UserFormProps> = ({
  isOpen,
  onClose,
  onSuccess,
  user,
}) => {
  const isEditing = !!user?.id;
  const [loading, setLoading] = useState(false);
  const [roles, setRoles] = useState<Role[]>([]);
  const [avatarUrl, setAvatarUrl] = useState<string | null>(user?.avatar || null);
  const [formData, setFormData] = useState<User>({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    roles: [],
    is_active: true,
  });
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (isOpen) {
      loadRoles();
      if (user) {
        setFormData({
          name: user.name || '',
          email: user.email || '',
          phone: user.phone || '',
          password: '',
          password_confirmation: '',
          roles: Array.isArray(user.roles)
            ? user.roles.map((r) => (typeof r === 'number' ? r : r.id))
            : [],
          is_active: user.is_active ?? true,
        });
      } else {
        setFormData({
          name: '',
          email: '',
          phone: '',
          password: '',
          password_confirmation: '',
          roles: [],
          is_active: true,
        });
      }
      setErrors({});
    }
  }, [isOpen, user]);

  const loadRoles = async () => {
    try {
      const response = await rolesApi.list();
      setRoles(response.data.data || []);
    } catch {
      // Roles might not be accessible
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
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

    if (!isEditing) {
      if (!formData.password) {
        newErrors.password = 'Şifre gerekli';
      } else if (formData.password.length < 8) {
        newErrors.password = 'Şifre en az 8 karakter olmalı';
      }

      if (formData.password !== formData.password_confirmation) {
        newErrors.password_confirmation = 'Şifreler eşleşmiyor';
      }
    } else if (formData.password) {
      if (formData.password.length < 8) {
        newErrors.password = 'Şifre en az 8 karakter olmalı';
      }
      if (formData.password !== formData.password_confirmation) {
        newErrors.password_confirmation = 'Şifreler eşleşmiyor';
      }
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validate()) return;

    setLoading(true);
    try {
      const payload: Record<string, unknown> = {
        name: formData.name,
        email: formData.email,
        phone: formData.phone || null,
        roles: formData.roles,
        is_active: formData.is_active,
      };

      if (formData.password) {
        payload.password = formData.password;
        payload.password_confirmation = formData.password_confirmation;
      }

      if (isEditing && user?.id) {
        await usersApi.update(user.id, payload);
        toast.success('Kullanıcı güncellendi');
      } else {
        await usersApi.create(payload);
        toast.success('Kullanıcı oluşturuldu');
      }

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
        
        // İlk hatayı toast olarak göster
        const firstError = Object.values(backendErrors)[0];
        if (firstError) {
          toast.error(firstError);
        }
      } else if (err.response?.data?.message) {
        toast.error(err.response.data.message);
      } else {
        toast.error('Kullanıcı kaydedilirken bir hata oluştu');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? 'Kullanıcı Düzenle' : 'Yeni Kullanıcı'}
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
        {/* Avatar Upload - Sadece düzenleme modunda */}
        {isEditing && user?.id && (
          <div className="form-group">
            <label className="form-label">Profil Fotoğrafı</label>
            <AvatarUpload
              currentAvatarUrl={avatarUrl}
              onUpload={async (file) => {
                const response = await usersApi.uploadAvatar(user.id!, file);
                setAvatarUrl(response.data.data.avatar_url);
                onSuccess(); // Form'u yeniden yükle
              }}
              onDelete={async () => {
                await usersApi.deleteAvatar(user.id!);
                setAvatarUrl(null);
                onSuccess(); // Form'u yeniden yükle
              }}
              userId={user.id!}
              userName={formData.name || user.name || ''}
            />
          </div>
        )}

        {/* Name */}
        <div className="form-group">
          <label className="form-label">Ad Soyad *</label>
          <input
            type="text"
            name="name"
            className={`form-control ${errors.name ? 'is-invalid' : ''}`}
            value={formData.name}
            onChange={handleChange}
            placeholder="Örn: Ahmet Yılmaz"
          />
          {errors.name && <div className="form-error">{errors.name}</div>}
        </div>

        {/* Email */}
        <div className="form-group">
          <label className="form-label">E-posta *</label>
          <input
            type="email"
            name="email"
            className={`form-control ${errors.email ? 'is-invalid' : ''}`}
            value={formData.email}
            onChange={handleChange}
            placeholder="ornek@sirket.com"
          />
          {errors.email && <div className="form-error">{errors.email}</div>}
        </div>

        {/* Phone */}
        <div className="form-group">
          <label className="form-label">Telefon</label>
          <input
            type="tel"
            name="phone"
            className="form-control"
            value={formData.phone}
            onChange={(e) => {
              // Telefon formatı: (5xx) xxx xx xx
              let value = e.target.value.replace(/\D/g, '');
              if (value.startsWith('0')) value = value.substring(1);
              if (value.length > 10) value = value.substring(0, 10);
              
              let formatted = '';
              if (value.length > 0) {
                formatted = '(' + value.substring(0, 3);
                if (value.length > 3) {
                  formatted += ') ' + value.substring(3, 6);
                  if (value.length > 6) {
                    formatted += ' ' + value.substring(6, 8);
                    if (value.length > 8) {
                      formatted += ' ' + value.substring(8, 10);
                    }
                  }
                }
              }
              
              setFormData(prev => ({ ...prev, phone: formatted }));
            }}
            placeholder="(5xx) xxx xx xx"
          />
        </div>

        {/* Password */}
        <div className="form-group">
          <label className="form-label">
            Şifre {!isEditing && '*'}
            {isEditing && <span style={{ fontWeight: 400, color: 'var(--text-tertiary)', fontSize: '0.75rem' }}> (Boş bırakırsanız değişmez)</span>}
          </label>
          <input
            type="password"
            name="password"
            className={`form-control ${errors.password ? 'is-invalid' : ''}`}
            value={formData.password}
            onChange={handleChange}
            placeholder="••••••••"
          />
          {errors.password && <div className="form-error">{errors.password}</div>}
        </div>

        {/* Password Confirmation */}
        <div className="form-group">
          <label className="form-label">Şifre Tekrar {!isEditing && '*'}</label>
          <input
            type="password"
            name="password_confirmation"
            className={`form-control ${errors.password_confirmation ? 'is-invalid' : ''}`}
            value={formData.password_confirmation}
            onChange={handleChange}
            placeholder="••••••••"
          />
          {errors.password_confirmation && <div className="form-error">{errors.password_confirmation}</div>}
        </div>

        {/* Roles */}
        {roles.length > 0 && (
          <div className="form-group">
            <label className="form-label">Roller</label>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem' }}>
              {roles.map((role) => (
                <label
                  key={role.id}
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '0.375rem',
                    padding: '0.375rem 0.75rem',
                    background: formData.roles.includes(role.id) ? 'var(--primary-soft)' : 'var(--surface-glass)',
                    border: `1px solid ${formData.roles.includes(role.id) ? 'var(--primary)' : 'var(--border-primary)'}`,
                    borderRadius: 'var(--radius-md)',
                    cursor: 'pointer',
                    fontSize: '0.8125rem',
                    transition: 'all 0.15s ease',
                  }}
                >
                  <input
                    type="checkbox"
                    checked={formData.roles.includes(role.id)}
                    onChange={() => handleRoleChange(role.id)}
                    style={{ display: 'none' }}
                  />
                  <span style={{ color: formData.roles.includes(role.id) ? 'var(--primary)' : 'var(--text-secondary)' }}>
                    {role.name}
                  </span>
                </label>
              ))}
            </div>
          </div>
        )}

        {/* Active Status */}
        <div className="form-group">
          <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
            <input
              type="checkbox"
              name="is_active"
              checked={formData.is_active}
              onChange={handleChange}
              style={{ width: 16, height: 16, accentColor: 'var(--primary)' }}
            />
            <span style={{ fontSize: '0.8125rem', color: 'var(--text-primary)' }}>Aktif Kullanıcı</span>
          </label>
        </div>
      </form>
    </Modal>
  );
};

export default UserForm;

