import React, { useState, useEffect } from 'react';
import { useSelector, useDispatch } from 'react-redux';
import { RootState } from '../../store';
import { setUser } from '../../store/slices/authSlice';
import { authApi } from '../../services/api';
import { BsPerson, BsShield, BsBell, BsPalette, BsKey, BsCheckCircle } from 'react-icons/bs';
import toast from 'react-hot-toast';

const ProfilePage: React.FC = () => {
  const dispatch = useDispatch();
  const { user } = useSelector((state: RootState) => state.auth);
  const [activeTab, setActiveTab] = useState<'profile' | 'password' | 'notifications'>('profile');
  const [loading, setLoading] = useState(false);

  const [profileData, setProfileData] = useState({
    name: user?.name || '',
    email: user?.email || '',
    phone: '',
  });

  const [passwordData, setPasswordData] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  useEffect(() => {
    if (user) {
      setProfileData({
        name: user.name || '',
        email: user.email || '',
        phone: '',
      });
    }
  }, [user]);

  const handleProfileSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      const response = await authApi.updateProfile(profileData);
      dispatch(setUser(response.data.data));
      toast.success('Profil bilgileri güncellendi');
    } catch (error) {
      console.error('Profil güncellenemedi:', error);
    } finally {
      setLoading(false);
    }
  };

  const handlePasswordSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (passwordData.password !== passwordData.password_confirmation) {
      toast.error('Şifreler eşleşmiyor');
      return;
    }
    setLoading(true);
    try {
      await authApi.updatePassword(passwordData);
      toast.success('Şifre güncellendi');
      setPasswordData({ current_password: '', password: '', password_confirmation: '' });
    } catch (error) {
      console.error('Şifre güncellenemedi:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Profil Ayarları</h1>
          <p className="page-subtitle">Hesap bilgilerinizi yönetin</p>
        </div>
      </div>

      <div className="grid grid-cols-12 gap-6">
        {/* Sidebar */}
        <div className="col-span-12 md:col-span-3">
          <div className="card">
            <div className="card-body">
              {/* Avatar */}
              <div className="text-center mb-4">
                <div className="w-24 h-24 rounded-full bg-primary/10 flex items-center justify-center mx-auto mb-3" style={{ width: '96px', height: '96px', borderRadius: '50%', backgroundColor: 'var(--primary-soft)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  <span style={{ fontSize: '2.5rem', color: 'var(--primary)' }}>{user?.name?.charAt(0).toUpperCase()}</span>
                </div>
                <h4>{user?.name}</h4>
                <p className="text-secondary">{user?.email}</p>
                <span className="badge badge-primary">{user?.type === 'super_admin' ? 'Super Admin' : user?.type === 'company_admin' ? 'Firma Yöneticisi' : 'Kullanıcı'}</span>
              </div>

              {/* Nav */}
              <nav className="nav flex-column">
                <button
                  className={`nav-link text-left ${activeTab === 'profile' ? 'active' : ''}`}
                  onClick={() => setActiveTab('profile')}
                  style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', padding: '0.75rem', borderRadius: '0.5rem', background: activeTab === 'profile' ? 'var(--primary-soft)' : 'transparent', color: activeTab === 'profile' ? 'var(--primary)' : 'inherit', border: 'none', width: '100%', cursor: 'pointer' }}
                >
                  <BsPerson /> Profil Bilgileri
                </button>
                <button
                  className={`nav-link text-left ${activeTab === 'password' ? 'active' : ''}`}
                  onClick={() => setActiveTab('password')}
                  style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', padding: '0.75rem', borderRadius: '0.5rem', background: activeTab === 'password' ? 'var(--primary-soft)' : 'transparent', color: activeTab === 'password' ? 'var(--primary)' : 'inherit', border: 'none', width: '100%', cursor: 'pointer', marginTop: '0.25rem' }}
                >
                  <BsKey /> Şifre Değiştir
                </button>
                <button
                  className={`nav-link text-left ${activeTab === 'notifications' ? 'active' : ''}`}
                  onClick={() => setActiveTab('notifications')}
                  style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', padding: '0.75rem', borderRadius: '0.5rem', background: activeTab === 'notifications' ? 'var(--primary-soft)' : 'transparent', color: activeTab === 'notifications' ? 'var(--primary)' : 'inherit', border: 'none', width: '100%', cursor: 'pointer', marginTop: '0.25rem' }}
                >
                  <BsBell /> Bildirim Ayarları
                </button>
              </nav>
            </div>
          </div>
        </div>

        {/* Content */}
        <div className="col-span-12 md:col-span-9">
          {activeTab === 'profile' && (
            <div className="card">
              <div className="card-header">
                <h3 className="card-title"><BsPerson /> Profil Bilgileri</h3>
              </div>
              <div className="card-body">
                <form onSubmit={handleProfileSubmit}>
                  <div className="row g-3">
                    <div className="col-md-6">
                      <label className="form-label">Ad Soyad*</label>
                      <input
                        type="text"
                        className="form-control"
                        value={profileData.name}
                        onChange={(e) => setProfileData({ ...profileData, name: e.target.value })}
                        required
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label">E-posta*</label>
                      <input
                        type="email"
                        className="form-control"
                        value={profileData.email}
                        onChange={(e) => setProfileData({ ...profileData, email: e.target.value })}
                        required
                        disabled
                      />
                      <small className="form-text text-muted">E-posta adresi değiştirilemez</small>
                    </div>
                    <div className="col-md-6">
                      <label className="form-label">Telefon</label>
                      <input
                        type="tel"
                        className="form-control"
                        value={profileData.phone}
                        onChange={(e) => setProfileData({ ...profileData, phone: e.target.value })}
                        placeholder="0 (5XX) XXX XX XX"
                      />
                    </div>
                  </div>
                  <div className="mt-4">
                    <button type="submit" className="btn btn-primary" disabled={loading}>
                      {loading ? 'Kaydediliyor...' : 'Kaydet'}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}

          {activeTab === 'password' && (
            <div className="card">
              <div className="card-header">
                <h3 className="card-title"><BsKey /> Şifre Değiştir</h3>
              </div>
              <div className="card-body">
                <form onSubmit={handlePasswordSubmit}>
                  <div className="row g-3">
                    <div className="col-md-12">
                      <label className="form-label">Mevcut Şifre*</label>
                      <input
                        type="password"
                        className="form-control"
                        value={passwordData.current_password}
                        onChange={(e) => setPasswordData({ ...passwordData, current_password: e.target.value })}
                        required
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label">Yeni Şifre*</label>
                      <input
                        type="password"
                        className="form-control"
                        value={passwordData.password}
                        onChange={(e) => setPasswordData({ ...passwordData, password: e.target.value })}
                        required
                        minLength={8}
                      />
                      <small className="form-text text-muted">En az 8 karakter</small>
                    </div>
                    <div className="col-md-6">
                      <label className="form-label">Yeni Şifre (Tekrar)*</label>
                      <input
                        type="password"
                        className="form-control"
                        value={passwordData.password_confirmation}
                        onChange={(e) => setPasswordData({ ...passwordData, password_confirmation: e.target.value })}
                        required
                      />
                    </div>
                  </div>
                  <div className="mt-4">
                    <button type="submit" className="btn btn-primary" disabled={loading}>
                      {loading ? 'Kaydediliyor...' : 'Şifreyi Güncelle'}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}

          {activeTab === 'notifications' && (
            <div className="card">
              <div className="card-header">
                <h3 className="card-title"><BsBell /> Bildirim Ayarları</h3>
              </div>
              <div className="card-body">
                <div className="space-y-4">
                  <div className="form-check form-switch d-flex align-items-center gap-3 p-3 rounded" style={{ backgroundColor: 'var(--bg-secondary)' }}>
                    <input className="form-check-input" type="checkbox" id="emailNotifications" defaultChecked style={{ width: '3rem', height: '1.5rem' }} />
                    <div>
                      <label className="form-check-label fw-bold" htmlFor="emailNotifications">E-posta Bildirimleri</label>
                      <p className="text-secondary mb-0">Önemli güncellemeler e-posta ile gönderilsin</p>
                    </div>
                  </div>
                  <div className="form-check form-switch d-flex align-items-center gap-3 p-3 rounded" style={{ backgroundColor: 'var(--bg-secondary)', marginTop: '1rem' }}>
                    <input className="form-check-input" type="checkbox" id="browserNotifications" style={{ width: '3rem', height: '1.5rem' }} />
                    <div>
                      <label className="form-check-label fw-bold" htmlFor="browserNotifications">Tarayıcı Bildirimleri</label>
                      <p className="text-secondary mb-0">Anlık bildirimler alın</p>
                    </div>
                  </div>
                  <div className="form-check form-switch d-flex align-items-center gap-3 p-3 rounded" style={{ backgroundColor: 'var(--bg-secondary)', marginTop: '1rem' }}>
                    <input className="form-check-input" type="checkbox" id="weeklyReport" defaultChecked style={{ width: '3rem', height: '1.5rem' }} />
                    <div>
                      <label className="form-check-label fw-bold" htmlFor="weeklyReport">Haftalık Özet</label>
                      <p className="text-secondary mb-0">Her hafta aktivite özetini e-posta olarak alın</p>
                    </div>
                  </div>
                </div>
                <div className="mt-4">
                  <button className="btn btn-primary">Kaydet</button>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default ProfilePage;

