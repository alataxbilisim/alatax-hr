import React, { useEffect, useState } from 'react';
import { useSelector } from 'react-redux';
import { RootState } from '../store';
import { portalApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsEnvelope, BsPhone, BsGeoAlt, BsCalendar, BsBuilding, BsCheck } from 'react-icons/bs';

interface ProfileData {
  user: {
    name: string;
    email: string;
    phone: string | null;
  };
  employee: {
    employee_code: string;
    position: string;
    department: string;
    hire_date: string;
    birth_date: string | null;
    personal_email: string | null;
    personal_phone: string | null;
    address: string | null;
    city: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    emergency_contact_relation: string | null;
  };
}

const ProfilePage: React.FC = () => {
  const { user } = useSelector((state: RootState) => state.auth);
  const [profile, setProfile] = useState<ProfileData | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [savingPassword, setSavingPassword] = useState(false);
  
  // Editable fields
  const [editData, setEditData] = useState({
    personal_email: '',
    personal_phone: '',
    address: '',
    city: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    emergency_contact_relation: '',
  });
  
  // Password change
  const [passwordData, setPasswordData] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  useEffect(() => {
    loadProfile();
  }, []);

  const loadProfile = async () => {
    try {
      const response = await portalApi.profile.get();
      const data = response.data.data;
      setProfile(data);
      setEditData({
        personal_email: data.employee?.personal_email || '',
        personal_phone: data.employee?.personal_phone || '',
        address: data.employee?.address || '',
        city: data.employee?.city || '',
        emergency_contact_name: data.employee?.emergency_contact_name || '',
        emergency_contact_phone: data.employee?.emergency_contact_phone || '',
        emergency_contact_relation: data.employee?.emergency_contact_relation || '',
      });
    } catch {
      toast.error('Profil yüklenemedi');
    } finally {
      setLoading(false);
    }
  };

  const handleSaveProfile = async () => {
    setSaving(true);
    try {
      await portalApi.profile.update(editData);
      toast.success('Profil güncellendi');
      loadProfile();
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } };
      toast.error(err.response?.data?.message || 'Profil güncellenemedi');
    } finally {
      setSaving(false);
    }
  };

  const handleChangePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (passwordData.password !== passwordData.password_confirmation) {
      toast.error('Şifreler eşleşmiyor');
      return;
    }
    if (passwordData.password.length < 8) {
      toast.error('Şifre en az 8 karakter olmalıdır');
      return;
    }

    setSavingPassword(true);
    try {
      await portalApi.profile.updatePassword(passwordData);
      toast.success('Şifre güncellendi');
      setPasswordData({ current_password: '', password: '', password_confirmation: '' });
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } };
      toast.error(err.response?.data?.message || 'Şifre güncellenemedi');
    } finally {
      setSavingPassword(false);
    }
  };

  if (loading) {
    return (
      <div className="page-loading">
        <div className="loading-spinner"></div>
      </div>
    );
  }

  return (
    <div className="animate-fade-in">
      {/* Profile Header */}
      <div className="profile-header">
        <div className="profile-avatar">
          {user?.name?.charAt(0).toUpperCase()}
        </div>
        <div className="profile-info">
          <h2>{profile?.user?.name}</h2>
          <p>{profile?.employee?.position} - {profile?.employee?.department}</p>
          <div className="profile-meta">
            <span><BsCalendar /> İşe Başlama: {profile?.employee?.hire_date ? new Date(profile.employee.hire_date).toLocaleDateString('tr-TR') : '-'}</span>
            <span><BsBuilding /> Sicil No: {profile?.employee?.employee_code}</span>
          </div>
        </div>
      </div>

      <div className="row">
        {/* Contact Information */}
        <div className="col-lg-6 mb-4">
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">İletişim Bilgileri</h3>
            </div>
            <div className="card-body">
              <div className="mb-3">
                <label className="form-label">
                  <BsEnvelope className="me-2" /> Kurumsal E-posta
                </label>
                <input
                  type="email"
                  className="form-control"
                  value={profile?.user?.email || ''}
                  readOnly
                />
              </div>
              <div className="mb-3">
                <label className="form-label">
                  <BsPhone className="me-2" /> Kurumsal Telefon
                </label>
                <input
                  type="text"
                  className="form-control"
                  value={profile?.user?.phone || '-'}
                  readOnly
                />
              </div>
              <div className="mb-3">
                <label className="form-label">Kişisel E-posta</label>
                <input
                  type="email"
                  className="form-control"
                  value={editData.personal_email}
                  onChange={(e) => setEditData({ ...editData, personal_email: e.target.value })}
                />
              </div>
              <div className="mb-3">
                <label className="form-label">Kişisel Telefon</label>
                <input
                  type="text"
                  className="form-control"
                  value={editData.personal_phone}
                  onChange={(e) => setEditData({ ...editData, personal_phone: e.target.value })}
                />
              </div>
            </div>
          </div>
        </div>

        {/* Address */}
        <div className="col-lg-6 mb-4">
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">
                <BsGeoAlt className="me-2" /> Adres Bilgileri
              </h3>
            </div>
            <div className="card-body">
              <div className="mb-3">
                <label className="form-label">Adres</label>
                <textarea
                  className="form-control"
                  rows={3}
                  value={editData.address}
                  onChange={(e) => setEditData({ ...editData, address: e.target.value })}
                ></textarea>
              </div>
              <div className="mb-3">
                <label className="form-label">Şehir</label>
                <input
                  type="text"
                  className="form-control"
                  value={editData.city}
                  onChange={(e) => setEditData({ ...editData, city: e.target.value })}
                />
              </div>
            </div>
          </div>
        </div>

        {/* Emergency Contact */}
        <div className="col-lg-6 mb-4">
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Acil Durum İletişim</h3>
            </div>
            <div className="card-body">
              <div className="mb-3">
                <label className="form-label">Ad Soyad</label>
                <input
                  type="text"
                  className="form-control"
                  value={editData.emergency_contact_name}
                  onChange={(e) => setEditData({ ...editData, emergency_contact_name: e.target.value })}
                />
              </div>
              <div className="mb-3">
                <label className="form-label">Telefon</label>
                <input
                  type="text"
                  className="form-control"
                  value={editData.emergency_contact_phone}
                  onChange={(e) => setEditData({ ...editData, emergency_contact_phone: e.target.value })}
                />
              </div>
              <div className="mb-3">
                <label className="form-label">Yakınlık</label>
                <input
                  type="text"
                  className="form-control"
                  value={editData.emergency_contact_relation}
                  onChange={(e) => setEditData({ ...editData, emergency_contact_relation: e.target.value })}
                />
              </div>
              <button
                className="btn btn-primary"
                onClick={handleSaveProfile}
                disabled={saving}
              >
                {saving ? 'Kaydediliyor...' : <><BsCheck size={18} /> Bilgileri Kaydet</>}
              </button>
            </div>
          </div>
        </div>

        {/* Password Change */}
        <div className="col-lg-6 mb-4">
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Şifre Değiştir</h3>
            </div>
            <div className="card-body">
              <form onSubmit={handleChangePassword}>
                <div className="mb-3">
                  <label className="form-label">Mevcut Şifre</label>
                  <input
                    type="password"
                    className="form-control"
                    value={passwordData.current_password}
                    onChange={(e) => setPasswordData({ ...passwordData, current_password: e.target.value })}
                    required
                  />
                </div>
                <div className="mb-3">
                  <label className="form-label">Yeni Şifre</label>
                  <input
                    type="password"
                    className="form-control"
                    value={passwordData.password}
                    onChange={(e) => setPasswordData({ ...passwordData, password: e.target.value })}
                    required
                    minLength={8}
                  />
                </div>
                <div className="mb-3">
                  <label className="form-label">Yeni Şifre (Tekrar)</label>
                  <input
                    type="password"
                    className="form-control"
                    value={passwordData.password_confirmation}
                    onChange={(e) => setPasswordData({ ...passwordData, password_confirmation: e.target.value })}
                    required
                  />
                </div>
                <button
                  type="submit"
                  className="btn btn-primary"
                  disabled={savingPassword}
                >
                  {savingPassword ? 'Güncelleniyor...' : 'Şifreyi Güncelle'}
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ProfilePage;
